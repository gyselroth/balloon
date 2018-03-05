<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Console;

use Balloon\Migration as Migrator;
use GetOpt\GetOpt;
use Psr\Log\LoggerInterface;

class Upgrade implements ConsoleInterface
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
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Execute migration scripts between upgrades';
    }

    /**
     * Set options.
     *
     * @return ConsoleInterface
     */
    public function setOptions(): ConsoleInterface
    {
        $this->getopt->addOptions([
            \GetOpt\Option::create('f', 'force')->setDescription('Force apply deltas even if a delta has already been applied before'),
            \GetOpt\Option::create('i', 'ignore')->setDescription('Do not abort if any error is encountered'),
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
        return $this->migration->start(
            (bool) $this->getopt->getOption('force'),
            (bool) $this->getopt->getOption('ignore')
        );
    }
}
