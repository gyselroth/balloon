<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
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
            \GetOpt\Command::create('jobs', Console\Jobs::class)
                ->addOptions(Console\Jobs::getOptions())
                ->addOperands(Console\Jobs::getOperands()),
            \GetOpt\Command::create('upgrade', Console\Upgrade::class)
                ->addOptions(Console\Upgrade::getOptions())
                ->addOperands(Console\Upgrade::getOperands()),
            \GetOpt\Command::create('key', Console\Key::class)
                ->addOptions(Console\Key::getOptions())
                ->addOperands(Console\Key::getOperands()),
        ]);
    }
}
