<?php

namespace Developersnl\BullhornClientBundle\Client;

use Exception;
use GuzzleHttp\Client as HttpClient;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider as OAuth2Provider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use stdClass;
use Symfony\Contracts\Cache\CacheInterface;

class AuthenticationClient
{
    /** @var string */
    protected $clientId;

    /** @var OAuth2Provider */
    protected $authProvider;

    /** @var string */
    protected $authUrl;

    /** @var string */
    protected $tokenUrl;

    /** @var string */
    protected $loginUrl;

    /** @var CacheInterface */
    protected $cache;

    /** @var string */
    private $lastResponseBody;

    /** @var string[][] */
    private $lastResponseHeaders;

    /**
     * AuthenticationClient constructor.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $authUrl
     * @param string $tokenUrl
     * @param string $loginUrl
     * @param CacheInterface $cache
     */
    public function __construct(string $clientId, string $clientSecret, string $authUrl, string $tokenUrl, string $loginUrl, CacheInterface $cache)
    {
        $this->clientId = $clientId;
        $this->authUrl = $authUrl;
        $this->tokenUrl = $tokenUrl;
        $this->loginUrl = $loginUrl;
        $this->cache = $cache;

        $this->authProvider = new OAuth2Provider([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'urlAuthorize' => $authUrl,
            'urlAccessToken' => $tokenUrl,
            'urlResourceOwnerDetails' => ''
        ]);
    }

    /**
     * @return string|null
     */
    public function getRestToken(): ?string
    {
        return $this->cache->get($this->getRestTokenKey(), function () { return null; });
    }

    /**
     * @return string|null
     */
    public function getRestUrl(): ?string
    {
        return $this->cache->get($this->getRestUrlKey(), function () { return null; });
    }

    /**
     * @return string|null
     */
    public function getRefreshToken(): ?string
    {
        return $this->cache->get($this->getRefreshTokenKey(), function () { return null; });
    }

    /**
     * @return string
     */
    protected function getRestTokenKey(): string
    {
        return $this->clientId.'-restToken';
    }

    /**
     * @return string
     */
    protected function getRestUrlKey(): string
    {
        return $this->clientId.'-restUrl';
    }

    /**
     * @return string
     */
    protected function getRefreshTokenKey(): string
    {
        return $this->clientId.'-refreshToken';
    }

    /**
     * @param string $name
     * @param $value
     */
    protected function storeData(string $name, $value): void
    {
        $this->cache->delete($name);
        $this->cache->get($name, function () use ($value) {
            return $value;
        });
    }

    /**
     * @param string $username
     * @param string $password
     * @param array $options
     * @throws Exception
     * @return void
     */
    public function initiateSession(string $username, string $password, array $options = []): void
    {
        $authCode = $this->getAuthorizationCode(
            $username,
            $password
        );

        $accessToken = $this->createAccessToken($authCode);
        $session = $this->createSession(
            $accessToken,
            $options
        );

        $this->storeSession($session);
    }

    /**
     * @param AccessTokenInterface $accessToken
     * @param array $options
     * @return stdClass
     * @throws Exception
     */
    protected function createSession(AccessTokenInterface $accessToken, array $options): stdClass
    {
        $response = $this->doRestLogin($accessToken->getToken(), $options);
        if ($response->getStatusCode() == 200) {
            $this->storeData(
                $this->getRefreshTokenKey(),
                $accessToken->getRefreshToken()
            );
            return json_decode($response->getBody());
        }

        throw new \Exception(
            'Login returned: ' .
            $response->getBody() .
            ' With headers: ' .
            json_encode($response->getHeaders())
        );
    }

    /**
     * @param $session
     * @return void
     */
    protected function storeSession($session): void
    {
        $this->storeData(
            $this->getRestTokenKey(),
            $session->BhRestToken
        );

        $this->storeData(
            $this->getRestUrlKey(),
            $session->restUrl
        );
    }

    /**
     * @param AccessTokenInterface|string $accessToken
     * @param array $options
     * @return ResponseInterface
     */
    protected function doRestLogin($accessToken, array $options): ResponseInterface
    {
        $options = array_merge(
            $this->getDefaultSessionOptions(),
            $options
        );

        $options['access_token'] = $accessToken;

        $fullUrl = $this->loginUrl . '?' . http_build_query($options);

        $loginRequest = $this->authProvider->getAuthenticatedRequest(
            'GET',
            $fullUrl,
            $accessToken
        );

        return $this->authProvider->getResponse($loginRequest);
    }

    /**
     * @return array
     */
    protected function getDefaultSessionOptions(): array
    {
        return [
            'version' => '*',
            'ttl' => 60
        ];
    }

    /**
     * @param string $authCode
     * @return AccessTokenInterface
     * @throws Exception
     */
    protected function createAccessToken(string $authCode): AccessTokenInterface
    {
        try {
            return $this->authProvider->getAccessToken(
                'authorization_code',
                ['code' => $authCode]
            );
        } catch (IdentityProviderException $e) {
            error_log('failed to create access token with auth code: ' . $authCode);
            error_log($e);
            error_log($this->lastResponseBody);
            error_log(implode(' | ', $this->lastResponseHeaders));
            throw new Exception('Identity provider exception');
        }
    }

    /**
     * @param $username
     * @param $password
     * @return string
     * @throws Exception
     */
    protected function getAuthorizationCode(string $username, string $password): string
    {
        $authRequest = $this->authProvider->getAuthorizationUrl([
            'response_type' => 'code',
            'action'=> 'Login',
            'username' => $username,
            'password' => $password
        ]);

        $locationHeader =  '';
        $response = $this->makeHttpRequest(
            $authRequest,
            [
                'allow_redirects' => true,
                'on_stats' => function ($stats) use (&$locationHeader) {
                    $locationHeader = (string) $stats->getEffectiveUri();
                }
            ]
        );

        $responseBody = $response->getBody()->getContents();
        $this->checkAuthorizationErrors($responseBody);

        $this->lastResponseBody = $responseBody;
        $this->lastResponseHeaders = $response->getHeaders();

        try {
            return $this->parseAuthorizationCodeFromUrl($locationHeader);
        } catch (Exception $e) {
            // fallback to plain cURL request
            try {
                $ch = curl_init($authRequest);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_NOBODY, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                $response = curl_exec($ch);

                return $this->parseAuthorizationCodeFromUrl($response);
            } catch (Exception $e) {
                throw new Exception($e . ' From request: ' . $authRequest);
            }
        }
    }

    /**
     * @param string $responseBody
     * @throws Exception
     */
    protected function checkAuthorizationErrors(string $responseBody): void
    {
        if (false !== strpos($responseBody, 'Invalid Client Id')) {
            throw new Exception("Invalid client ID");
        } elseif (false !== strpos($responseBody, '<p class="error">')) {
            throw new Exception("Invalid account credentials");
        }
    }

    /**
     * @param string $url
     * @return string
     * @throws Exception
     */
    protected function parseAuthorizationCodeFromUrl(string $url): string
    {
        $temp = preg_split("/code=/", $url);
        if (count($temp) > 1) {
            $temp = preg_split("/&/", $temp[1]);
            return urldecode($temp[0]);
        } else {
            throw new Exception('Cannot parse auth code from url: ' . $url);
        }
    }

    /**
     * @param array $options
     * @throws Exception
     * @return void
     */
    public function refreshSession(array $options = []): void
    {
        $refreshToken = $this->getRefreshToken();
        if (!isset($refreshToken)) {
            throw new Exception('attempted session refresh with invalid refresh token');
        }

        $accessToken = $this->refreshAccessToken($refreshToken);
        $session = $this->createSession(
            $accessToken,
            $options
        );
        $this->storeSession($session);
    }

    /**
     * @param $refreshToken
     * @return AccessTokenInterface
     * @throws Exception
     */
    protected function refreshAccessToken(string $refreshToken): AccessTokenInterface
    {
        try {
            return $this->authProvider->getAccessToken(
                'refresh_token',
                ['refresh_token' => $refreshToken]
            );
        } catch (IdentityProviderException $e) {
            throw new Exception('attempted session refresh with invalid refresh token');
        }
    }

    /**
     * @param string|UriInterface $request
     * @param array $options
     * @return ResponseInterface
     */
    protected function makeHttpRequest($request, $options = []): ResponseInterface
    {
        $client = new HttpClient();
        return $client->get($request, $options);
    }
}
