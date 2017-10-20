<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Console;

use Balloon\App;
use Balloon\Async as AsyncQueue;
use GetOpt\GetOpt;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class Async implements ConsoleInterface
{
    /**
     * App.
     *
     * @var App
     */
    protected $app;

    /**
     * Logger.
     *
     * @var Logger
     */
    protected $logger;

    /**
     * Getopt.
     *
     * @var GetOpt
     */
    protected $getopt;

    /**
     * Async.
     *
     * @var Async
     */
    protected $async;

    /**
     * Container.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Constructor.
     *
     * @param App   $app
     * @param Async $async
     */
    public function __construct(App $app, AsyncQueue $async, LoggerInterface $logger, ContainerInterface $container, GetOpt $getopt)
    {
        $this->app = $app;
        $this->async = $async;
        $this->logger = $logger;
        $this->getopt = $getopt;
        $this->async = $async;
        $this->container = $container;
        $this->setOptions();
    }

    /**
     * Set options.
     *
     * @return ConsoleInterface
     */
    public function setOptions(): ConsoleInterface
    {
        $this->getopt->addOptions([
            \GetOpt\Option::create('q', 'queue'),
            \GetOpt\Option::create('d', 'daemon'),
        ]);

        return $this;
    }

    /**
     * Start.
     *
     * @return bool
     */
    public function start(): bool
    {
        if (null === $this->getopt->getOption('queue')) {
            $this->logger->debug('skip job queue execution', [
                'category' => get_class($this),
            ]);
        }

        if (null !== $this->getopt->getOption('daemon')) {
            $this->fireupDaemon();
        } else {
            if (null !== $this->getopt->getOption('queue')) {
                $cursor = $this->async->getCursor(false);
                $this->async->start($cursor, $this->container);
            }

            foreach ($this->app->getApps() as $app) {
                $app->start();
            }
        }

        return true;
    }

    /**
     * Fire up daemon.
     *
     * @return bool
     */
    protected function fireupDaemon(): bool
    {
        $this->logger->info('daemon execution requested, fire up daemon', [
            'category' => get_class($this),
        ]);

        $cursor = $this->async->getCursor(true);
        while (true) {
            if (null !== $this->getopt->getOption('queue')) {
                $this->async->start($cursor, $this->container);
            }
        }

        return true;
    }
}
