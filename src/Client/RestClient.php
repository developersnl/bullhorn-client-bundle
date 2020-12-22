<?php

namespace Developersnl\BullhornClientBundle\Client;

use Exception;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use stdClass;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RestClient
{
    /** @var AuthenticationClient */
    protected $authClient;

    /** @var HttpClient */
    protected $httpClient;

    /** @var CacheInterface */
    protected $cache;

    /** @var int */
    protected $ttl;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /** @var array */
    protected $options;

    /**
     * RestClient constructor.
     *
     * @param AuthenticationClient $authClient
     * @param CacheInterface $cache
     * @param string $username
     * @param string $password
     * @param int $ttl
     * @param array $options
     */
    public function __construct(AuthenticationClient $authClient, CacheInterface $cache, string $username, string $password, int $ttl = 300, array $options = [])
    {
        $this->authClient = $authClient;
        $this->cache = $cache;
        $this->username = $username;
        $this->password = $password;
        $this->ttl = $ttl;
        $this->options = array_merge(
            [
                'autoRefresh' => true,
                'maxSessionRetry' => 5
            ],
            $options
        );
    }

    /**
     * @param string $username
     * @param string $password
     * @param array $options
     * @return void
     * @throws Exception
     */
    public function initiateSession(string $username, string $password, array $options = []): void
    {
        $session = false;
        $tries = 0;
        do {
            try {
                $this->authClient->initiateSession(
                    $username,
                    $password,
                    $options
                );
                $session = true;
            } catch (Exception $e) {
                ++$tries;
                if ($tries >= $this->options['maxSessionRetry']) {
                    throw $e;
                }
                usleep(1500000);
            }
        } while (!$session);

        $this->httpClient = new HttpClient([
            'base_uri' => $this->authClient->getRestUrl()
        ]);
    }

    /**
     * @param array $options
     *
     * @return void
     */
    public function refreshSession(array $options = []): void
    {
        $this->authClient->refreshSession($options);
        $this->httpClient = new HttpClient([
            'base_uri' => $this->authClient->getRestUrl()
        ]);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array  $options
     * @param array  $headers
     *
     * @return stdClass
     * @throws \Exception
     */
    public function request(string $method, string $url, array $options = [], array $headers = []): ?stdClass
    {
        $this->initiateSession($this->username, $this->password);

        $options['headers'] = $this->appendDefaultHeadersTo($headers);
        try {
            $response = $this->httpClient->request(
                $method,
                $url,
                $options
            );
            $responseBody = $response->getBody()->getContents();
            return json_decode($responseBody);
        } catch (ClientException $e) {
            if ($this->options['autoRefresh']) {
                $request = [
                    'method' => $method,
                    'url' => $url,
                    'options' => $options,
                    'headers' => $headers
                ];
                return $this->handleRequestException($request, $e);
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param array $request
     * @param ClientException $exception
     * @return stdClass
     * @throws Exception
     */
    protected function handleRequestException(array $request, ClientException $exception): stdClass
    {
        if ($exception->getResponse()->getStatusCode() == 401) {
            return $this->handleExpiredSessionOnRequest($request);
        } else {
            throw $exception;
        }
    }

    /**
     * @param $request
     * @return stdClass
     * @throws Exception
     */
    protected function handleExpiredSessionOnRequest(array $request): stdClass
    {
        $this->refreshSession();

        return $this->request(
            $request['method'],
            $request['url'],
            $request['options'],
            $request['headers']
        );
    }

    /**
     * @param array $headers
     * @return array
     */
    protected function appendDefaultHeadersTo(array $headers): array
    {
        return array_merge(
            $headers,
            [
                'BhRestToken' => $this->authClient->getRestToken()
            ]
        );
    }

    /**
     * @param string $url
     * @param bool $cache
     *
     * @return array
     */
    public function get(string $url, bool $cache = true): array
    {
        return $cache
            ? $this->cache->get(sha1($url), function (ItemInterface $item) use ($url) {
                return json_encode($this->request('GET', $url), JSON_THROW_ON_ERROR, 512);
            })
            : json_decode(json_encode($this->request('GET', $url), JSON_THROW_ON_ERROR, 512), true, 512, JSON_THROW_ON_ERROR);
    }
}
