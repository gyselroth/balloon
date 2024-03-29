<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Wopi;

use GuzzleHttp\ClientInterface as GuzzleHttpClientInterface;
use InvalidArgumentException;
use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class HostManager
{
    /**
     * Discovery path.
     */
    public const DISCOVERY_PATH = '/hosting/discovery';

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
    protected $client_url;

    /**
     * HTTP client.
     *
     * @var GuzzleHttpClientInterface
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
     * Cache ttl.
     *
     * @var int
     */
    protected $cache_ttl = 84600;

    /**
     * Validate proof.
     *
     * @var bool
     */
    protected $validate_proof = true;

    /**
     * Hosts.
     */
    public function __construct(GuzzleHttpClientInterface $client, CacheInterface $cache, LoggerInterface $logger, array $config = [])
    {
        $this->client = $client;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Set options.
     */
    public function setOptions(array $config = []): HostManager
    {
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'hosts':
                    $this->hosts = (array) $value;

                    break;
                case 'cache_ttl':
                    $this->cache_ttl = (int) $value;

                break;
                case 'validate_proof':
                    $this->validate_proof = (bool) $value;

                break;
                default:
                    throw new InvalidArgumentexception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * Get session by id.
     */
    public function getHosts(): array
    {
        $result = [];

        foreach ($this->hosts as $url) {
            if (!isset($url['url']) || !isset($url['name']) || !isset($url['wopi_url'])) {
                $this->logger->error('skip wopi host entry, either name, wopi_url or url not set', [
                    'category' => static::class,
                ]);

                continue;
            }

            try {
                $discovery = $this->fetchDiscovery($url['url']);

                if (isset($url['replace']['to'], $url['replace']['from'])) {
                    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
                        $url['replace']['to'] = str_replace('{protocol}', $_SERVER['HTTP_X_FORWARDED_PROTO'], $url['replace']['to']);
                    }

                    if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
                        $url['replace']['to'] = str_replace('{host}', $_SERVER['HTTP_X_FORWARDED_HOST'], $url['replace']['to']);
                    }

                    $discovery = preg_replace($url['replace']['from'], $url['replace']['to'], $discovery);
                }

                $discovery = json_decode($discovery, true, 512, JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
                $result[] = [
                    'url' => $url['url'],
                    'name' => $url['name'],
                    'wopi_url' => $url['wopi_url'],
                    'discovery' => $discovery,
                ];
            } catch (\Exception $e) {
                $this->logger->error('failed to fetch wopi discovery document [{url}]', [
                    'category' => static::class,
                    'url' => $url,
                    'exception' => $e,
                ]);
            }
        }

        return $result;
    }

    /**
     * Verify wopi proof key.
     *
     * @see https://wopi.readthedocs.io/en/latest/scenarios/proofkeys.html
     * @see https://github.com/microsoft/Office-Online-Test-Tools-and-Documentation/blob/master/samples/python/proof_keys/__init__.py
     */
    public function verifyWopiProof(array $data): bool
    {
        if ($this->validate_proof === false) {
            $this->logger->debug('skip wopi proof validation, validate_proof is disabled', [
                'category' => static::class,
            ]);

            return true;
        }

        foreach ($this->getHosts() as $host) {
            if (!isset($host['discovery']['proof-key']['@attributes']['modulus'])) {
                $this->logger->debug('skip wopi proof validation, no public keys for wopi host [{host}] provided', [
                    'category' => static::class,
                    'host' => $host['url'],
                ]);

                continue;
            }

            $this->logger->debug('start wopi proof validation for host [{host}]', [
                'category' => static::class,
                'host' => $host['url'],
                'data' => $data,
            ]);

            $keys = $host['discovery']['proof-key']['@attributes'];
            $pub_key_old = new RSA();
            $pub_key_old->setSignatureMode(RSA::SIGNATURE_PKCS1);
            $pub_key_old->setHash('sha256');
            $key_old = [
                'n' => new BigInteger(base64_decode($keys['oldmodulus']), 256),
                'e' => new BigInteger(base64_decode($keys['oldexponent']), 256),
            ];
            $pub_key_old->loadKey($key_old);

            $pub_key = new RSA();
            $pub_key->setSignatureMode(RSA::SIGNATURE_PKCS1);
            $pub_key->setHash('sha256');
            $key = [
                'n' => new BigInteger(base64_decode($keys['modulus']), 256),
                'e' => new BigInteger(base64_decode($keys['exponent']), 256),
            ];

            $pub_key->loadKey($key);

            //php string is already a byte array
            $token_bytes = $data['access-token'];
            //pack number of token bytes into 32 bit, big endian byte order => 4bytes
            $token_length_bytes = pack('N*', strlen($token_bytes));
            //php string is already a byte array, specs require url all upper case
            $url_bytes = strtoupper($data['host-url']);
            //pack number of url bytes into 32 bit, big endian byte order => 4bytes
            $url_length_bytes = pack('N*', strlen($url_bytes));
            //pack timestamp into 64 bit, big endian byte order => 8bytes
            $timestamp_bytes = pack('J*', (int) $data['timestamp']);
            //pack number of url bytes into 32 bit, big endian byte order => 4bytes
            $timestamp_length_bytes = pack('N*', strlen($timestamp_bytes));

            $expected = implode('', [
                $token_length_bytes,
                $token_bytes,
                $url_length_bytes,
                $url_bytes,
                $timestamp_length_bytes,
                $timestamp_bytes,
            ]);

            if ($pub_key->verify($expected, base64_decode($data['proof'])) ||
              $pub_key->verify($expected, base64_decode($data['proof-old'])) ||
              $pub_key_old->verify($expected, base64_decode($data['proof']))) {
                $this->logger->debug('wopi proof signature matches', [
                    'category' => static::class,
                ]);

                return true;
            }
        }

        throw new Exception\WopiProofValidationFailed('wopi signature validation failed');
    }

    /**
     * Fetch discovery.
     */
    protected function fetchDiscovery(string $url): string
    {
        $discovery = $url.self::DISCOVERY_PATH;

        $key = md5($discovery);

        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $this->logger->debug('wopi discovery not found in cache, fetch wopi discovery [{url}]', [
            'category' => static::class,
            'url' => $url,
        ]);

        $response = $this->client->request(
            'GET',
            $discovery
        );

        $body = $response->getBody()->getContents();
        $body = json_encode(simplexml_load_string($body), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $result = $this->cache->set($key, $body, $this->cache_ttl);

        $this->logger->debug('stored wopi discovery [{url}] in cache for [{ttl}]s', [
            'category' => static::class,
            'url' => $discovery,
            'ttl' => $this->cache_ttl,
            'result' => $result,
        ]);

        return $body;
    }
}
