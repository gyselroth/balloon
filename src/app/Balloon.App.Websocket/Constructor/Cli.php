<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Websocket\Constructor;

use Balloon\App\Websocket\Console;
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
            \GetOpt\Command::create('websocket-server', Console\WebsocketServer::class)
                ->addOptions(Console\WebsocketServer::getOptions())
                ->addOperands(Console\WebsocketServer::getOperands()),
        ]);
    }
}
