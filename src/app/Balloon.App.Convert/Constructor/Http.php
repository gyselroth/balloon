<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert\Constructor;

use Balloon\App\Convert\Api\v2\Convert;
use Balloon\App\Convert\AttributeDecorator as ConvertAttributeDecorator;
use Balloon\App\Convert\Converter;
use Balloon\Filesystem\Node\AttributeDecorator as NodeAttributeDecorator;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http
{
    /**
     * Constructor.
     */
    public function __construct(Router $router, NodeAttributeDecorator $node_decorator, ConvertAttributeDecorator $convert_decorator, Converter $converter)
    {
        $router
            ->prependRoute(new Route('/api/v2/files/convert(/|\z)', Convert::class))
            ->prependRoute(new Route('/api/v2/files/{id:#([0-9a-z]{24})#}/convert(/|\z)', Convert::class));

        $node_decorator->addDecorator('master', function ($node) use ($convert_decorator, $converter) {
            $master = $node->getAppAttribute('Balloon\\App\\Convert', 'master');
            if (null === $master) {
                return null;
            }

            try {
                $slave = $converter->getSlave($master);

                return $convert_decorator->decorate($slave, ['id', 'format', '_links', 'master']);
            } catch (\Exception $e) {
                return null;
            }
        });
    }
}
