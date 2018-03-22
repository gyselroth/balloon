<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Cli\Constructor;

use Balloon\App\Cli\Console;
use GetOpt\GetOpt;

class Cli
{
    /**
     * GetOpt.
     *
     * @var GetOpt
     */
    protected $getopt;

    /**
     * Constructor.
     *
     * @param GetOpt $getopt
     */
    public function __construct(GetOpt $getopt)
    {
        $this->getopt = $getopt;

        $getopt->addCommands([
            \GetOpt\Command::create('help', [&$this, 'help'])
                ->addOperand(\GetOpt\Operand::create('command')),
            \GetOpt\Command::create('user add', [Console\User::class, 'add'])
                ->addOptions($this->getUserOptions()),
            \GetOpt\Command::create('user edit', [Console\User::class, 'edit'])
                ->addOperand(\GetOpt\Operand::create('id'))
                ->addOptions($this->getUserOptions()),
            \GetOpt\Command::create('group add', [Console\Group::class, 'add'])
                ->addOptions($this->getUserOptions()),
            \GetOpt\Command::create('group edit', [Console\Group::class, 'edit'])
                ->addOperand(\GetOpt\Operand::create('id'))
                ->addOptions($this->getUserOptions()),
            \GetOpt\Command::create('jobs listen', [Console\Jobs::class, 'listen'])
                ->addOptions($this->getUserOptions()),
            \GetOpt\Command::create('jobs once', [Console\Jobs::class, 'once'])
                ->addOptions($this->getUserOptions()),
            \GetOpt\Command::create('upgrade start', [Console\Upgrade::class, 'start'])
                ->addOptions($this->getUpgradeOptions()),
        ]);
    }

    /**
     * Display help.
     */
    public function help()
    {
        foreach ($this->getopt->getCommands() as $cmd) {
            echo $cmd->getName()."\n";
        }
    }

    /**
     * Get upgrade options.
     *
     * @return array
     */
    protected function getUpgradeOptions(): array
    {
        return [
            \GetOpt\Option::create('f', 'force')->setDescription('Force apply deltas even if a delta has already been applied before'),
            \GetOpt\Option::create('i', 'ignore')->setDescription('Do not abort if any error is encountered'),
            \GetOpt\Option::create('d', 'delta', \GetOpt\GetOpt::REQUIRED_ARGUMENT)->setDescription('Specify specific deltas (comma separated)'),
        ];
    }

    /**
     * Get group options.
     *
     * @return array
     */
    protected function getGroupOptions(): array
    {
        return [
            \GetOpt\Option::create('g', 'name', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Specify the groupname [REQUIRED]'),
            \GetOpt\Option::create('m', 'member', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('A list of usernames to add to the group (comma separated)'),
            \GetOpt\Option::create('n', 'namespace', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('A namespace'),
            \GetOpt\Option::create('d', 'description', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('A description'),
        ];
    }

    /**
     * Get user options.
     *
     * @return array
     */
    protected function getUserOptions(): array
    {
        return [
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
            \GetOpt\Option::create('L', 'locale', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('A Locale (Example: en_US)'),
        ];
    }
}
