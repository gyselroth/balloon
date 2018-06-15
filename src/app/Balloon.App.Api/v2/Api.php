<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2;

use Balloon\App\Api\Controller;
use Micro\Http\Response;

class Api extends Controller
{
    /**
     * @api {get} / Server & API Status
     * @apiVersion 2.0.0
     * @apiName get
     * @apiGroup Api
     * @apiPermission none
     * @apiDescription Get server time and api status/version
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v2?pretty"
     *
     * @apiSuccess {string} name balloon identifier
     * @apiSuccess {number} api_version API Version
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "name": "balloon",
     *      "api_version": 2
     * }
     */
    public function get(): Response
    {
        $data = [
            'name' => 'balloon',
            'api_version' => 2,
        ];

        return (new Response())->setCode(200)->setBody($data);
    }
}
