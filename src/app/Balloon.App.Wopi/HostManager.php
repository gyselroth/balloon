<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Wopi;

use GuzzleHttp\ClientInterface as GuzzleHttpClientInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class HostManager
{
    /**
     * Hosts.
     *
     * @var array
     */
    protected $hosts = [];

    /**
     * Client url.
     *
     * @var string
     */
    protected $client;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Cache.
     *
     * @var CacheInterface
     */
    protected $cache;

    /**
     * Hosts.
     */
    public function __construct(GuzzleHttpClientInterface $client, CacheInterface $cache, LoggerInterface $logger, array $hosts = [])
    {
        $this->client = $client;
        $this->hosts = $hosts;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Set options.
     */
    public function setOptions(array $config = []): SessionManager
    {
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'hosts':
                    $this->hosts = (array) $value;

                    break;
                case 'client':
                    $this->client = (string) $value;

                break;
                default:
                    throw new InvalidArgumentexception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * Get client url.
     */
    public function getClientUrl(): ?string
    {
        return $this->client;
    }

    /**
     * Get session by id.
     */
    public function getHosts(): array
    {
        $result = [];

        foreach ($this->hosts as $url) {
            if (!isset($url['url']) || !isset($url['name'])) {
                $this->logger->error('skip wopi host entry, either name or url not set', [
                    'category' => get_class($this),
                ]);

                continue;
            }

            try {
                $result[] = [
                    'url' => $url['url'],
                    'name' => $url['name'],
                    'discovery' => $this->fetchDiscovery($url['url']),
                ];
            } catch (\Exception $e) {
                $this->logger->error('failed to fetch wopi discovery document [{url}]', [
                    'category' => get_class($this),
                    'url' => $url,
                    'exception' => $e,
                ]);
            }
        }

        return $result;
    }

    /**
     * Verify wopi proof key.
     */
    public function verifyWopiProof(string $key): bool
    {
    }

    /**
     * Fetch discovery.
     */
    protected function fetchDiscovery(string $url): object
    {
        if ($result = $this->cache->get($url)) {
            return $result;
        }

        $this->logger->debug('fetch wopi discovery [{url}]', [
            'category' => get_class($this),
            'url' => $url,
        ]);

        $response = $this->client->request(
            'GET',
            $url
         );

        $body = $response->getBody()->getContents();

        return simplexml_load_string($body);
    }
}
