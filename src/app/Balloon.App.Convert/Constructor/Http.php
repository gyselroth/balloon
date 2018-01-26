<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert\Constructor;

use Balloon\App\Convert\Api\Latest\Convert;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Server;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http
{
    /**
     * Constructor.
     *
     * @param Router             $router
     * @param AttributeDecorator $decorator
     * @param Server             $server
     */
    public function __construct(Router $router, AttributeDecorator $decorator, Server $server)
    {
        $fs = $server->getFilesystem();

        $router
            ->prependRoute(new Route('/api/v2/file/convert', Convert::class))
            ->prependRoute(new Route('/api/v2/file/{id:#([0-9a-z]{24})#}/convert', Convert::class));

        $decorator->addDecorator('master', function ($node) use ($fs, $decorator) {
            $master = $node->getAppAttribute('Balloon\\App\\Convert', 'master');
            if (null === $master) {
                return null;
            }

            try {
                return $decorator->decorate($fs->findNodeById($master), ['id', 'name', '_links']);
            } catch (\Exception $e) {
                return null;
            }
        });
    }
}
