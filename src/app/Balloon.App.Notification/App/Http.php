<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\App;

use Balloon\App\Notification\App;
use Psr\Log\LoggerInterface;
use Micro\Http\Router;
use Micro\Http\Router\Route;
use Balloon\App\Notification\Api\v1\Notification as Api;

class Http extends App
{
    /**
     * Constructor
     *
     * @param LoggerInterace $logger
     * @param Router $router
     * @param Iterable $config
     */
    public function __construct(LoggerInterface $logger, Router $router,  ?Iterable $config=null)
    {
        parent::__construct($logger, $config);
        $router
            ->prependRoute(new Route('/api/v1/user/notification', Api::class))
            ->prependRoute(new Route('/api/v1/user/{id:#([0-9a-z]{24})#}/notification', Api::class));
    }
}
