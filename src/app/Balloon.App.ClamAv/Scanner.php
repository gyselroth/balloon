<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\ClamAv;

use Balloon\Filesystem\Node\File;
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
    const FILE_INFECTED = 0;
    const FILE_OK = 1;

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

    /**
     * Socket.
     *
     * @var Socket
     */
    protected $clamav_socket;

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
     * @return int
     */
    public function scan(File $file): int
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

            if (!($this->clamav_socket instanceof Socket)) {
                $this->clamav_socket = $this->socket_factory->createClient($this->socket);
            }
        } catch (\Exception $e) {
            throw new Exception('scan of file ['.$file->getId().'] failed: '.$e->getMessage());
        }

        try {
            // Create a new instance of the Client
            $clamav = new ClamAv($this->socket, $this->timeout, PHP_NORMAL_READ);

            // Scan file
            $result = $clamav->scanResourceStream($file->get());

            $this->logger->debug('scan result for file ['.$file->getId().']: '.$result['status'], [
                'category' => get_class($this),
            ]);

            if ('OK' === $result['status']) {
                return self::FILE_OK;
            }
            if ('FOUND' === $result['status']) {
                $this->logger->debug('file ['.$file->getId().'] is infected ('.$result['reason'].')', [
                    'category' => get_class($this),
                ]);

                return self::FILE_INFECTED;
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
     * @param bool $infected
     *
     * @return bool
     */
    public function handleFile(File $file, $infected = false): bool
    {
        if ($infected) {
            switch ($this->aggressiveness) {
                case 0:
                    break;
                case 1:
                    $file->setAppAttribute(__NAMESPACE__, 'quarantine', true);

                    break;
                case 2:
                    $file->setAppAttribute(__NAMESPACE__, 'quarantine', true);
                    $file->delete();

                    break;
                case 3:
                default:
                    $file->delete(true);

                    break;
            }
        } else {
            $file->setAppAttribute(__NAMESPACE__, 'quarantine', false);
        }

        return true;
    }
}
