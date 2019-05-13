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

class HostManager
{
    /**
     * Hosts.
     *
     * @var array
     */
    protected $hosts = [];

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Cache.
     *
     * @var array
     */
    protected $cache;

    /**
     * Hosts.
     */
    public function __construct(GuzzleHttpClientInterface $client, LoggerInterface $logger, array $hosts = [])
    {
        $this->client = $client;
        $this->hosts = $hosts;
        $this->logger = $logger;
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
     * Fetch discovery.
     */
    protected function fetchDiscovery(string $url): object
    {
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
