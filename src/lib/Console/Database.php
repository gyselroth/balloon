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

use Balloon\Database as BalloonDatabase;

class Database implements ConsoleInterface
{
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
    public function __construct(App $app, Async $async, LoggerInterface $logger, ContainerInterface $container, GetOpt $getopt)
    {
        $this->server = $app;
        $this->logger = $logger;
        $this->getopt = $getopt;
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
            \GetOpt\Option::create('i', 'init'),
            \GetOpt\Option::create('u', 'upgrade'),
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
        $db = new BalloonDatabase($this->server, $this->logger);
        if (null !== $this->getopt->getOption('init')) {
            return $db->init();
        }
        if (null !== $this->getopt->getOption('upgrade')) {
            return $db->upgrade();
        }

        return false;
    }
}
