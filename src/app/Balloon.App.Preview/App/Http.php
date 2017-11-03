<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview\App;

use Balloon\App\Preview\Api\v1\Preview;
use Balloon\App\Preview\App;
use Micro\Http\Router;
use Micro\Http\Router\Route;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Http extends App
{
    /**
     * Constructor.
     *
     * @param Router $router
     * @param Hook   $hook
     */
    public function __construct(Database $db, LoggerInterface $logger, Router $router)
    {
        parent::__construct($db, $logger);

        $router
            ->prependRoute(new Route('/api/v1/file/preview', Preview::class))
            ->prependRoute(new Route('/api/v1/file/{id:#([0-9a-z]{24})#}/preview', Preview::class));
    }
}
