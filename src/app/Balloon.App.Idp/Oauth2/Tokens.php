<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Idp\Oauth2;

use OAuth2\Request;
use OAuth2\Server as OAuth2Server;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Balloon\Rest\Helper;

class Tokens
{
    /**
     * Server.
     *
     * @var OAuth2Server
     */
    protected $server;

    /**
     * Initialize.
     */
    public function __construct(OAuth2Server $server)
    {
        $this->server = $server;
    }

    /**
     * Token endpoint.
     */
    public function post(ServerRequestInterface $request): ResponseInterface
    {
        $authrequest = Request::createFromGlobals();
        $response = $this->server->handleTokenRequest($authrequest);
        return Helper::response($request, $response->getParameters(), $response->getStatusCode());
    }
}
