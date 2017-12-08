<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert\App;

use Balloon\App\AppInterface;
use Balloon\App\Convert\Api\v1\Convert;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Server;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http implements AppInterface
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
            ->prependRoute(new Route('/api/v1/file/convert', Convert::class))
            ->prependRoute(new Route('/api/v1/file/{id:#([0-9a-z]{24})#}/convert', Convert::class));

        $decorator->addDecorator('master', function ($node, $attributes) use ($fs, $decorator) {
            $master = $node->getAppAttribute('Balloon\\App\\Convert', 'master');
            if (null === $master) {
                return null;
            }

            try {
                return $decorator->decorate($fs->findNodeById($master), $attributes['attributes']);
            } catch (\Exception $e) {
                return null;
            }
        });
    }
}
