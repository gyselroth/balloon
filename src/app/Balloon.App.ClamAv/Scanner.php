<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\ClamAv;

use Balloon\Filesystem\Node\File;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;
use Socket\Raw\Factory as SocketFactory;
use Socket\Raw\Socket;
use Xenolope\Quahog\Client as ClamAv;
use Xenolope\Quahog\Exception\ConnectionException as ClamAvConnectionException;

class Scanner
{
    /**
     * States.
     */
    public const FILE_INFECTED = 'FOUND';
    public const FILE_OK = 'OK';

    /**
     * Socket.
     *
     * @var string
     */
    protected $socket = 'unix:///var/run/clamav/clamd.ctl';

    /**
     * Maximum Stream Size.
     *
     * @var int
     */
    protected $max_stream_size = 26214400;

    /**
     * Aggressiveness.
     *
     * @var int
     */
    protected $aggressiveness = 2;

    /**
     * Timeout.
     *
     * @var int
     */
    protected $timeout = 30;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Socket factory.
     *
     * @var SocketFactory
     */
    protected $socket_factory;

    /**
     * Constructor.
     */
    public function __construct(SocketFactory $factory, LoggerInterface $logger, ?iterable $config = null)
    {
        $this->logger = $logger;
        $this->socket_factory = $factory;
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return Scanner
     */
    public function setOptions(?iterable $config = null): self
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'socket':
                    $this->socket = (string) $value;

                    break;
                case 'max_stream_size':
                    $this->max_stream_size = (int) $value;

                    break;
                case 'aggressiveness':
                    if ((int) $value > 3 || (int) $value < 0) {
                        throw new InvalidArgumentException('invalid config value ['.(int) $value.'] for aggressiveness');
                    }
                    $this->aggressiveness = (int) $value;

                    break;
                case 'timeout':
                    $this->timeout = (int) $value;

                    break;
                default:
                    throw new InvalidArgumentException('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * Scan file.
     */
    public function scan(File $file): array
    {
        $this->logger->debug('scan file ['.$file->getId().'] via clamav', [
            'category' => static::class,
        ]);

        if ($file->getSize() > $this->max_stream_size) {
            throw new Exception\StreamTooBig('file size of '.$file->getSize().' exceeds stream size ('.$this->max_stream_size.')');
        }

        try {
            $this->logger->debug('open clamav socket ['.$this->socket.']', [
                'category' => static::class,
            ]);

            $socket = $this->socket_factory->createClient($this->socket);
            $clamav = new ClamAv($socket, $this->timeout, PHP_NORMAL_READ);
        } catch (\Exception $e) {
            throw new Exception\ScanFailed('scan of file ['.$file->getId().'] failed: '.$e->getMessage());
        }

        try {
            $result = $clamav->scanResourceStream($file->get());

            $this->logger->debug('scan result for file ['.$file->getId().']: '.$result['status'], [
                'category' => static::class,
            ]);

            if (self::FILE_OK === $result['status']) {
                return $result;
            }
            if (self::FILE_INFECTED === $result['status']) {
                $this->logger->debug('file ['.$file->getId().'] is infected ('.$result['reason'].')', [
                    'category' => static::class,
                ]);

                return $result;
            }
        } catch (ClamAvConnectionException $e) {
            throw new Exception\ScanFailed('scan of file ['.$file->getId().'] failed: '.$e->getMessage());
        }

        throw new Exception\ScanFailed('scan of file ['.$file->getId().'] failed: status='.$result['status'].', reason='.$result['reason']);
    }

    /**
     * Execute appropriate action on given file.
     *
     * @param string $reason
     */
    public function handleFile(File $file, string $status, ?string $reason = null): bool
    {
        if ($status === self::FILE_INFECTED) {
            switch ($this->aggressiveness) {
                case 0:
                    break;
                case 1:
                    $file->setAppAttributes(__NAMESPACE__, [
                        'quarantine' => true,
                        'scantime' => new UTCDateTime(),
                        'reason' => $reason,
                    ]);

                    break;
                default:
                case 2:
                    $file->setAppAttributes(__NAMESPACE__, [
                        'quarantine' => true,
                        'scantime' => new UTCDateTime(),
                        'reason' => $reason,
                    ]);

                    $file->delete();

                    break;
                case 3:
                    $file->delete(true);

                    break;
            }
        } else {
            $file->setAppAttributes(__NAMESPACE__, [
                'quarantine' => false,
                'scantime' => new UTCDateTime(),
            ]);
        }

        return true;
    }
}
