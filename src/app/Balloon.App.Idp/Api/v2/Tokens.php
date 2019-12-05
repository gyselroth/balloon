<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Idp\Api\v2;

use Balloon\Server;
use Micro\Http\Response;
use OAuth2\Request;
use OAuth2\Server as OAuth2Server;

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
    public function post(): Response
    {
        $request = Request::createFromGlobals();
        $response = $this->server->handleTokenRequest($request);
        $params = $response->getParameters();

        return (new Response())
             ->setCode($response->getStatusCode())
             ->setBody($params);
    }
}
