<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch\Constructor;

use Balloon\App\Elasticsearch\Console;
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
            \GetOpt\Command::create('elasticsearch', Console\Elasticsearch::class)
                ->addOptions(Console\Elasticsearch::getOptions())
                ->addOperands(Console\Elasticsearch::getOperands()),
        ]);
    }
}
