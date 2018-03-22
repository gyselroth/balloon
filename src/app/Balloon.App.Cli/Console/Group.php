<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Cli\Console;

use Balloon\Server;
use GetOpt\GetOpt;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

class Group
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
     * Start.
     *
     * @return bool
     */
    public function add(): bool
    {
        $member = $this->parseMember();
        $result = $this->server->addGroup($this->getopt->getOption('name'), $member, $this->parseParams());

        $this->logger->info('new group ['.$result.'] created', [
            'category' => get_class($this),
       ]);

        return true;
    }

    /**
     * Start.
     *
     * @return bool
     */
    public function update(): bool
    {
        $id = new ObjectId($this->getopt->getOperand('id'));

        $options = $this->parseParams();
        $options['member'] = $this->parseMemberUpdate();

        if ($this->getopt->getOption('name') !== null) {
            $options['name'] = $this->getopt->getOption('name');
        }

        $group = $this->server->getGroupById($id);

        $this->logger->info('update group ['.$group->getId().']', [
            'category' => get_class($this),
        ]);

        $group->setAttributes($options);

        return true;
    }

    /**
     * Parse member.
     *
     * @return arrray
     */
    protected function parseMemberUpdate(): array
    {
        $group = $this->server->getGroupByName($this->getopt->getOption('name'));
        $member = [];

        $remove = [];
        if ($this->getopt->getOption('remove') !== null) {
            $remove = explode(',', $this->getopt->getOption('remove'));
            $remove = array_map('trim', $remove);
        }

        foreach ($group->getResolvedMembers() as $user) {
            if (!in_array($user->getUsername(), $remove)) {
                $member[] = $user;
            }
        }

        if ($this->getopt->getOption('append') !== null) {
            $append = explode(',', $this->getopt->getOption('append'));
            $append = array_map('trim', $append);

            foreach ($append as $user) {
                $member[] = $this->server->getUserByName($user);
            }
        }

        return $member;
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
