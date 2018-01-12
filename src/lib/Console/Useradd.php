<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Console;

use Balloon\Server;
use GetOpt\GetOpt;
use MongoDB\BSON\Binary;
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
     * @param Server          $server
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
            \GetOpt\Option::create('u', 'username', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Specify the username [REQUIRED]'),
            \GetOpt\Option::create('p', 'password', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Specify a password'),
            \GetOpt\Option::create('a', 'admin', GetOpt::NO_ARGUMENT)
                ->setDescription('Admin account flag'),
            \GetOpt\Option::create('A', 'avatar', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Set an avatar image (Path/URL to JPEG image)'),
            \GetOpt\Option::create('m', 'mail', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Mail address'),
            \GetOpt\Option::create('d', 'description', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('User description'),
            \GetOpt\Option::create('f', 'firstname', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Firstname'),
            \GetOpt\Option::create('l', 'lastname', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Lastname'),
            \GetOpt\Option::create('s', 'softquota', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Softquota in bytes'),
            \GetOpt\Option::create('h', 'hardquota', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Hardquota in bytes'),
            \GetOpt\Option::create('n', 'namespace', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('A namespace'),
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
        $options = $this->parseParams();
        $result = $this->server->addUser($this->getopt->getOption('username'), $options);

        $this->logger->info('new user ['.$result.'] created', [
            'category' => get_class($this),
        ]);

        return true;
    }

    /**
     * Parse params.
     *
     * @return arrray
     */
    protected function parseParams(): array
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

        if ($this->getopt->getOption('namespace') !== null) {
            $options['namespace'] = $this->getopt->getOption('namespace');
        }

        if ($this->getopt->getOption('description') !== null) {
            $options['description'] = $this->getopt->getOption('description');
        }

        if ($this->getopt->getOption('avatar') !== null) {
            $options['avatar'] = new Binary(file_get_contents($this->getopt->getOption('avatar')), Binary::TYPE_GENERIC);
        }

        if ($this->getopt->getOption('admin') !== null) {
            $options['admin'] = true;
        }

        return $options;
    }
}
