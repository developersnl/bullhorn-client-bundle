<?php

namespace Developersnl\BullhornClientBundle\Client;

use Exception;
use Developersnl\BullhornClientBundle\Client\BullhornRestClient as Client;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use stdClass;

class BullhornClient
{
    /** @var Client */
    protected $client;

    /** @var CacheInterface */
    protected $cache;

    /** @var int */
    protected $ttl;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /**
     * BullhornClient constructor.
     *
     * @param Client $client
     * @param CacheInterface $cache
     * @param int $ttl
     * @param string $username
     * @param string $password
     */
    public function __construct(Client $client, CacheInterface $cache, string $username, string $password, $ttl = 300)
    {
        $this->client = $client;
        $this->cache = $cache;
        $this->ttl = $ttl;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Gets url from bullhorn and caches it.
     *
     * Takes api url as parameter and creates a unique key value using sha1.
     * It then checks if the key does not exist in cache.
     * value becomes a decoded json object and gets put into cache if it isn't already.
     *
     * @param string $url
     * @param bool $useCache
     * @return array
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function get(string $url, bool $useCache = true): array
    {
        $key = sha1($url);

        if (!$useCache || ($value = $this->cache->get($key)) === null) {
            // mishit: generate value;
            $value = json_encode($this->request('GET', $url), JSON_THROW_ON_ERROR, 512);

            if ($useCache) {
                $this->cache->set($key, $value, $this->ttl);
            }
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $options
     * @param array $headers
     * @return stdClass
     * @throws Exception
     */
    public function request(string $method, string $url, $options = [], $headers = []): stdClass
    {
        $this->client->initiateSession($this->username, $this->password);
        return $this->client->request($method, $url, $options, $headers) ?? new stdClass();
    }
}
