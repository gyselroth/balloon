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

use Balloon\Migration as Migrator;
use GetOpt\GetOpt;
use Psr\Log\LoggerInterface;

class Migration implements ConsoleInterface
{
    /**
     * Getopt.
     *
     * @var GetOpt
     */
    protected $getopt;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Migration.
     *
     * @var Migrator
     */
    protected $migration;

    /**
     * Constructor.
     *
     * @param Migrator        $migration
     * @param LoggerInterface $logger
     * @param GetOpt          $getopt
     */
    public function __construct(Migrator $migration, LoggerInterface $logger, GetOpt $getopt)
    {
        $this->migration = $migration;
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
            \GetOpt\Option::create('u', 'upgrade'),
            \GetOpt\Option::create('f', 'force'),
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
        if (null !== $this->getopt->getOption('upgrade')) {
            return $this->migration->start((bool) $this->getopt->getOption('force'));
        }

        return false;
    }
}
