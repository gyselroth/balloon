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
     */
    public function __construct(GetOpt $getopt)
    {
        $this->getopt = $getopt;
        $getopt->addCommands([
            \GetOpt\Command::create('users', Console\User::class)
                ->addOptions(Console\User::getOptions())
                ->addOperands(Console\User::getOperands()),
            \GetOpt\Command::create('groups', Console\Group::class)
                ->addOptions(Console\Group::getOptions())
                ->addOperands(Console\User::getOperands()),
            \GetOpt\Command::create('jobs', Console\Jobs::class)
                ->addOptions(Console\Jobs::getOptions())
                ->addOperands(Console\User::getOperands()),
            \GetOpt\Command::create('upgrade', Console\Upgrade::class)
                ->addOptions(Console\Upgrade::getOptions())
                ->addOperands(Console\User::getOperands()),
        ]);
    }
}
