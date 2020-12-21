<?php

namespace Developersnl\BullhornClientBundle\Client;

use Exception;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use stdClass;

class BullhornRestClient
{
    /** @var BullhornAuthClient */
    protected $authClient;

    /** @var HttpClient */
    protected $httpClient;

    /** @var array */
    protected $options;

    /**
     * BullhornRestClient constructor.
     *
     * @param array $options
     * @param BullhornAuthClient $authClient
     */
    public function __construct(BullhornAuthClient $authClient, array $options = [])
    {
        $this->authClient = $authClient;

        $defaultOptions = [
            'autoRefresh' => true,
            'maxSessionRetry' => 5
        ];

        $this->options = array_merge(
            $defaultOptions,
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
    public function initiateSession(
        string $username,
        string $password,
        array $options = []
    ): void {
        $gotSession = false;
        $tries = 0;
        do {
            try {
                $this->authClient->initiateSession(
                    $username,
                    $password,
                    $options
                );
                $gotSession = true;
            } catch (Exception $e) {
                ++$tries;
                if ($tries >= $this->options['maxSessionRetry']) {
                    throw $e;
                }
                usleep(1500000);
            }
        } while (!$gotSession);

        $this->httpClient = new HttpClient([
            'base_uri' => $this->authClient->getRestUrl()
        ]);
    }

    /**
     * @param array $options
     * @throws \Exception
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
     * @param array $options
     * @param array $headers
     * @return stdClass
     * @throws \Exception
     */
    public function request(
        string $method,
        string $url,
        array $options = [],
        array $headers = []
    ): ?stdClass {
        $fullHeaders = $this->appendDefaultHeadersTo($headers);
        $options['headers'] = $fullHeaders;
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
        $defaultHeaders = [
            'BhRestToken' => $this->authClient->getRestToken()
        ];
        return array_merge(
            $headers,
            $defaultHeaders
        );
    }
}
