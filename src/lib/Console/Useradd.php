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

use Balloon\Server;
use GetOpt\GetOpt;
use Psr\Log\LoggerInterface;

class Useradd implements ConsoleInterface
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
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger
     * @param GetOpt          $getopt
     */
    public function __construct(Server $server, LoggerInterface $logger, GetOpt $getopt)
    {
        $this->server = $server;
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
        return 'Add a new user';
    }

    /**
     * Set options.
     *
     * @return ConsoleInterface
     */
    public function setOptions(): ConsoleInterface
    {
        $this->getopt->addOptions([
            \GetOpt\Option::create('u', 'username', GetOpt::REQUIRED_ARGUMENT),
            \GetOpt\Option::create('p', 'password', GetOpt::REQUIRED_ARGUMENT),
            \GetOpt\Option::create('a', 'admin', GetOpt::NO_ARGUMENT),
            \GetOpt\Option::create('m', 'mail', GetOpt::REQUIRED_ARGUMENT),
            \GetOpt\Option::create('f', 'firstname', GetOpt::REQUIRED_ARGUMENT),
            \GetOpt\Option::create('l', 'lastname', GetOpt::REQUIRED_ARGUMENT),
            \GetOpt\Option::create('s', 'softquota', GetOpt::REQUIRED_ARGUMENT),
            \GetOpt\Option::create('h', 'hardquota', GetOpt::REQUIRED_ARGUMENT),
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
        $options = [];
        if ($this->getopt->getOption('firstname') !== null) {
            $options['first_name'] = $this->getopt->getOption('firstname');
        }

        if ($this->getopt->getOption('lastname') !== null) {
            $options['last_name'] = $this->getopt->getOption('lastname');
        }

        if ($this->getopt->getOption('mail') !== null) {
            $options['mail'] = $this->getopt->getOption('mail');
        }

        if ($this->getopt->getOption('softquota') !== null) {
            $options['soft_quota'] = $this->getopt->getOption('softquota');
        }

        if ($this->getopt->getOption('hardquota') !== null) {
            $options['hard_quota'] = $this->getopt->getOption('hardquota');
        }

        if ($this->getopt->getOption('admin') !== null) {
            $options['admin'] = true;
        }

        $result = $this->server->addUser($this->getopt->getOption('username'), $this->getopt->getOption('password'), $options);

        $this->logger->info('new user ['.$result.'] created', [
            'category' => get_class($this),
        ]);

        return true;
    }
}
