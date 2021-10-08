<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview\Constructor;

use Balloon\App\Preview\Api\v1;
use Balloon\App\Preview\Api\v2;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\Collection;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http
{
    /**
     * Constructor.
     */
    public function __construct(Router $router, AttributeDecorator $decorator)
    {
        $router
            ->prependRoute(new Route('/api/v1/file/preview(/|\z)', v1\Preview::class))
            ->prependRoute(new Route('/api/v1/file/{id:#([0-9a-z]{24})#}/preview(/|\z)', v1\Preview::class))
            ->prependRoute(new Route('/api/v2/files/preview(/|\z)', v2\Preview::class))
            ->prependRoute(new Route('/api/v2/files/{id:#([0-9a-z]{24})#}/preview(/|\z)', v2\Preview::class));

        $decorator->addDecorator('preview', function ($node) {
            if ($node instanceof Collection) {
                return null;
            }

            $preview = $node->getAppAttribute('Balloon\\App\\Preview', 'preview');

            if ($preview !== null) {
                return true;
            }

            return false;
        });
    }
}
