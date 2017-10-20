<?php declare(strict_types=1);
/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert;

use \Balloon\App\Convert\Hook;
use \Micro\Http\Router;
use \Micro\Http\Router\Route;
use \Balloon\App\Convert\Api\v1\Convert;
use \Balloon\Converter;

class Http extends App
{
    /**
     * Constructor
     *
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $router
            ->prependRoute(new Route('/api/v1/file/convert', Convert::class))
            ->prependRoute(new Route('/api/v1/file/{id:#([0-9a-z]{24})#}/convert', Convert::class));
    }
}
