<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\ClamAv;

use Balloon\Filesystem\Node\File;
use Psr\Log\LoggerInterface;
use Socket\Raw\Factory as SocketFactory;
use Socket\Raw\Socket;
use Xenolope\Quahog\Client as ClamAv;
use Xenolope\Quahog\Exception\ConnectionException as ClamAvConnectionException;
use MongoDB\BSON\UTCDateTime;

class Scanner
{
    /**
     * States.
     */
    const FILE_INFECTED = 'FOUND';
    const FILE_OK = 'OK';

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
    protected $aggressiveness = 3;

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

    /*
     * Socket factory
     *
     * @var SocketFactory
     */
    protected $socket_factory;

    /**
     * Constructor.
     *
     * @param Database        $db
     * @param LoggerInterface $logger
     */
    public function __construct(SocketFactory $factory, LoggerInterface $logger, ?Iterable $config = null)
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
    public function setOptions(?Iterable $config = null): self
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
                        throw new Exception('invalid config value ['.(int) $value.'] for aggressiveness');
                    }
                    $this->aggressiveness = (int) $value;

                    break;
                case 'timeout':
                    $this->timeout = (int) $value;

                    break;
                break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * Scan file.
     *
     * @param File $file
     *
     * @return array
     */
    public function scan(File $file): array
    {
        $this->logger->debug('scan file ['.$file->getId().'] via clamav', [
            'category' => get_class($this),
        ]);

        if ($file->getSize() > $this->max_stream_size) {
            throw new Exception('file size of '.$file->getSize().' exceeds stream size ('.$this->max_stream_size.')');
        }

        try {
            $this->logger->debug('open clamav socket ['.$this->socket.']', [
                'category' => get_class($this),
            ]);

            $socket = $this->socket_factory->createClient($this->socket);
            $clamav = new ClamAv($socket, $this->timeout, PHP_NORMAL_READ);
        } catch (\Exception $e) {
            throw new Exception('scan of file ['.$file->getId().'] failed: '.$e->getMessage());
        }

        try {
            $result = $clamav->scanResourceStream($file->get());

            $this->logger->debug('scan result for file ['.$file->getId().']: '.$result['status'], [
                'category' => get_class($this),
            ]);

            if (self::FILE_OK === $result['status']) {
                return $result;
            }
            if (self::FILE_INFECTED === $result['status']) {
                $this->logger->debug('file ['.$file->getId().'] is infected ('.$result['reason'].')', [
                    'category' => get_class($this),
                ]);

                return $result;
            }
        } catch (ClamAvConnectionException $e) {
            throw new Exception('scan of file ['.$file->getId().'] failed: '.$e->getMessage());
        }

        throw new Exception('scan of file ['.$file->getId().'] failed: status='.$result['status'].', reason='.$result['reason']);
    }

    /**
     * Execute appropriate action on given file.
     *
     * @param File $file
     * @param array $result
     *
     * @return bool
     */
    public function handleFile(File $file, array $result): bool
    {
        if ($result['status'] === self::FILE_INFECTED) {
            switch ($this->aggressiveness) {
                case 0:
                    break;
                case 1:
                    $file->setAppAttributes(__NAMESPACE__, [
                        'quarantine' => true,
                        'scantime' => new UTCDateTime(),
                        'reason' => $result['reason']
                    ]);

                    break;
                case 2:
                    $file->setAppAttributes(__NAMESPACE__, [
                        'quarantine' => true,
                        'scantime' => new UTCDateTime(),
                        'reason' => $result['reason']
                    ]);

                    $file->delete();

                    break;
                case 3:
                default:
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
