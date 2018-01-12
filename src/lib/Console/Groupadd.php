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
use Psr\Log\LoggerInterface;

class Groupadd implements ConsoleInterface
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
        return 'Add a new group';
    }

    /**
     * Set options.
     *
     * @return ConsoleInterface
     */
    public function setOptions(): ConsoleInterface
    {
        $this->getopt->addOptions([
            \GetOpt\Option::create('g', 'name', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Specify the groupname [REQUIRED]'),
            \GetOpt\Option::create('m', 'member', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('A list of usernames to add to the group (comma separated)'),
            \GetOpt\Option::create('n', 'namespace', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('A namespace'),
            \GetOpt\Option::create('d', 'description', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('A description'),
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
        $member = $this->parseMember();
        $result = $this->server->addGroup($this->getopt->getOption('name'), $member, $this->parseParams());

        $this->logger->info('new group ['.$result.'] created', [
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
        if ($this->getopt->getOption('namespace') !== null) {
            $options['namespace'] = $this->getopt->getOption('namespace');
        }

        if ($this->getopt->getOption('description') !== null) {
            $options['description'] = $this->getopt->getOption('description');
        }

        return $options;
    }

    /**
     * Parse member.
     *
     * @return arrray
     */
    protected function parseMember(): array
    {
        $member = [];
        if ($this->getopt->getOption('member') !== null) {
            $list = explode(',', $this->getopt->getOption('member'));
            $list = array_map('trim', $list);

            foreach ($list as $name) {
                $member[] = $this->server->getUserByName($name);
            }
        }

        return $member;
    }
}
