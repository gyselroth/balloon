<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Rest\Routes;

use Balloon\User;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Balloon\Rest\Helper;

class Auth
{
    /**
     * Entrypoint.
     */
    public function getIdentity(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        return Helper::response($request, ['identity' => $identity->getUsername()]);
    }
}
