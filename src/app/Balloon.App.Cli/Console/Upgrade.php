<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Cli\Console;

use Balloon\Migration as Migrator;
use GetOpt\GetOpt;
use Psr\Log\LoggerInterface;

class Upgrade
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
     * Start.
     *
     * @return bool
     */
    public function start(): bool
    {
        $deltas = $this->getopt->getOption('delta');
        if ($deltas === null) {
            $deltas = [];
        } else {
            $deltas = explode(',', $deltas);
        }

        return $this->migration->start(
            (bool) $this->getopt->getOption('force'),
            (bool) $this->getopt->getOption('ignore'),
            $deltas
        );
    }
}
