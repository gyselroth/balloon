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
     * Get help.
     */
    public function help(): Group
    {
        echo "add\n";
        echo "Add a new group\n\n";

        echo "edit\n";
        echo "Edit a group\n\n";
        echo $this->getopt->getHelpText();

        return $this;
    }

    /*
     * Get operands
     *
     * @return array
     */
    public static function getOperands(): array
    {
        return [
            \GetOpt\Operand::create('action', \GetOpt\Operand::REQUIRED),
            \GetOpt\Operand::create('id', \GetOpt\Operand::OPTIONAL),
        ];
    }

    /**
     * Get group options.
     *
     * @return array
     */
    public static function getOptions(): array
    {
        return [
            \GetOpt\Option::create('g', 'name', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Specify the groupname [REQUIRED]'),
            \GetOpt\Option::create('m', 'member', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('A list of usernames to add to the group (comma separated)'),
            \GetOpt\Option::create('n', 'namespace', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('A namespace'),
        ];
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
    public function edit(): bool
    {
        $id = new ObjectId($this->getopt->getOperand('id'));
        $group = $this->server->getGroupById($id);

        $options = $this->parseParams();
        $options['member'] = $this->parseMemberUpdate($group);

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
     * @param Group $group
     *
     * @return arrray
     */
    protected function parseMemberUpdate(Group $group): array
    {
        $member = [];

        $remove = [];
        if ($this->getopt->getOption('remove') !== null) {
            $remove = explode(',', $this->getopt->getOption('remove'));
            $remove = array_map('trim', $remove);
        }

        foreach ($group->getResolvedMembers() as $user) {
            if (!in_array((string) $user->getId(), $remove)) {
                $member[] = $user;
            }
        }

        if ($this->getopt->getOption('append') !== null) {
            $append = explode(',', $this->getopt->getOption('append'));
            $append = array_map('trim', $append);

            foreach ($append as $user) {
                $member[] = $this->server->getUserById(new ObjectId($user));
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
                $member[] = $this->server->getUserById(new ObjectId($name));
            }
        }

        return $member;
    }
}
