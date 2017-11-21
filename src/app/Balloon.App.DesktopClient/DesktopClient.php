<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\DesktopClient;

use Psr\Log\LoggerInterface;

class DesktopClient
{
    /**
     * Github request url.
     *
     * @var string
     */
    protected $github_request_url = 'https://api.github.com/repos/gyselroth/balloon-client-desktop/releases/latest';

    /**
     * Github request timeout.
     *
     * @var int
     */
    protected $github_request_timeout = 10;

    /**
     * Github asset mapping.
     *
     * @var array
     */
    protected $github_asset_mapping = [
        'deb' => '#.*\.deb$#',
        'rpm' => '#.*\.rpm$#',
        'exe' => '#.*\.exe$#',
        'linux_zip' => '#.*\.zip$#',
        'dmg' => '#.*\.dmg$#',
    ];

    /**
     * Github request useragent.
     *
     * @var string
     */
    protected $github_request_useragent = 'balloon server';

    /**
     * Formats.
     *
     * @var array
     */
    protected $formats = [];

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger
     * @param iterable        $config
     */
    public function __construct(LoggerInterface $logger, ?Iterable $config = null)
    {
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return DesktopClient
     */
    public function setOptions(?Iterable $config = null): self
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'github_request_url':
                case 'github_request_timeout':
                    $this->{$option} = (string) $value;

                break;
                case 'github_request_timeout':
                    $this->github_request_timeout = (int) $value;

                break;
                case 'formats':
                case 'github_asset_mapping':
                    $this->{$option} = $value;

                break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }
    }

    /**
     * Get url.
     *
     * @param string $format
     *
     * @return string
     */
    public function getUrl(string $format): string
    {
        if (isset($this->formats[$format])) {
            return $this->formats[$format];
        }

        return $this->getGithubUrl($format);
    }

    /**
     * Get github url.
     *
     * @param string $format
     *
     * @return string
     */
    protected function getGithubUrl(string $format): string
    {
        if (!isset($this->github_asset_mapping[$format])) {
            throw new Exception('unknown format '.$format.' requested');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->github_request_url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->github_request_timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->github_request_useragent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->logger->debug('github request to ['.$this->github_request_url.'] resulted in ['.$code.']', [
            'category' => get_class($this),
        ]);

        if (200 !== $code) {
            throw new Exception('failed query github releases api');
        }

        $data = json_decode($data, true);

        if (!is_array($data) || !isset($data['assets']) || 0 === count($data['assets'])) {
            throw new Exception('no github release assets found');
        }

        foreach ($data['assets'] as $asset) {
            $this->logger->debug('check release asset ['.$asset['name'].'] for ['.$this->github_asset_mapping[$format].']', [
                'category' => get_class($this),
            ]);

            if (preg_match($this->github_asset_mapping[$format], $asset['name'])) {
                $this->logger->info('github asset ['.$asset['browser_download_url'].'] found', [
                    'category' => get_class($this),
                ]);

                return $asset['browser_download_url'];
            }
        }

        throw new Exception('no github release asset matches request format');
    }
}
