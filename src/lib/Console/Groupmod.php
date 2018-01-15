<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Console;

use GetOpt\GetOpt;

class Groupmod extends Groupadd
{
    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Modify group';
    }

    /**
     * Set options.
     *
     * @return ConsoleInterface
     */
    public function setOptions(): ConsoleInterface
    {
        parent::setOptions();

        $this->getopt->addOptions([
            \GetOpt\Option::create('N', 'new-name', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Specify the groupname [REQUIRED]'),
            \GetOpt\Option::create('a', 'append', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('A list of usernames to add to the group (comma separated)'),
            \GetOpt\Option::create('r', 'remove', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('A list of usernames to remove from the group (comma separated)'),
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
        $options['member'] = $this->parseMember();

        if ($this->getopt->getOption('new-name') !== null) {
            $options['name'] = $this->getopt->getOption('new-name');
        }

        $group = $this->server->getGroupbyName($this->getopt->getOption('name'));

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
    protected function parseMember(): array
    {
        $group = $this->server->getGroupByName($this->getopt->getOption('name'));
        $member = [];

        $remove = [];
        if ($this->getopt->getOption('remove') !== null) {
            $remove = explode(',', $this->getopt->getOption('remove'));
            $remove = array_map('trim', $remove);
        }

        foreach ($group->getResolvedMember() as $user) {
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
}
