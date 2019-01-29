<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use Balloon\Bootstrap\ContainerBuilder;
use Composer\Autoload\ClassLoader as Composer;
use ErrorException;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;
use TaskScheduler\Worker;
use TaskScheduler\WorkerFactoryInterface;
use TaskScheduler\WorkerManager;

class WorkerFactory implements WorkerFactoryInterface
{
    /**
     * Composer.
     *
     * @var Composer
     */
    protected $composer;

    /**
     * Construct.
     */
    public function __construct(Composer $composer)
    {
        $this->composer = $composer;
    }

    /**
     * {@inheritdoc}
     */
    public function buildWorker(ObjectId $id): Worker
    {
        $dic = ContainerBuilder::get($this->composer);
        $this->setErrorHandler($dic->get(LoggerInterface::class));

        return $dic->make(Worker::class, [
            'id' => $id,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function buildManager(): WorkerManager
    {
        $dic = ContainerBuilder::get($this->composer);
        $this->setErrorHandler($dic->get(LoggerInterface::class));

        return $dic->get(WorkerManager::class);
    }

    /**
     * Set error handler.
     */
    protected function setErrorHandler(LoggerInterface $logger): self
    {
        set_error_handler(function ($severity, $message, $file, $line) use ($logger) {
            $log = $message.' in '.$file.':'.$line;

            switch ($severity) {
                case E_ERROR:
                case E_USER_ERROR:
                    $logger->error($log, [
                        'category' => get_class($this),
                    ]);

                break;
                case E_WARNING:
                case E_USER_WARNING:
                    $logger->warning($log, [
                        'category' => get_class($this),
                    ]);

                break;
                default:
                    $logger->debug($log, [
                        'category' => get_class($this),
                    ]);

                break;
            }

            if (error_reporting() !== 0) {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }
        });

        return $this;
    }
}
