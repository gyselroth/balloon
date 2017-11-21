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

use Balloon\Database as DatabaseSetup;
use GetOpt\GetOpt;
use Psr\Log\LoggerInterface;

class Database implements ConsoleInterface
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
     * Container.
     *
     * @var DatabaseSetup
     */
    protected $db;

    /**
     * Constructor.
     *
     * @param DatabaseSetup $db
     * @param LoggerInterface $logger
     * @param GetOpt $getopt
     */
    public function __construct(DatabaseSetup $db, LoggerInterface $logger, GetOpt $getopt)
    {
        $this->db = $db;
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
        if (null !== $this->getopt->getOption('init')) {
            return $this->db->init();
        }
        if (null !== $this->getopt->getOption('upgrade')) {
            return $this->db->upgrade();
        }

        return false;
    }
}
