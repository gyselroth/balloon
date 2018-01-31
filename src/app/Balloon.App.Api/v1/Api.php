<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v1;

use Micro\Http\Response;

class Api
{
    /**
     * @api {get} / Server & API Status
     * @apiVersion 1.0.0
     * @apiName get
     * @apiGroup Api
     * @apiPermission none
     * @apiDescription Get server time and api status/version
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1?pretty"
     *
     * @apiSuccess {number} status Status Code
     * @apiSuccess {object} data API/Server information
     * @apiSuccess {float} data.version API Version
     * @apiSuccess {number} data.server_timestamp Server timestamp in unix format (seconds since 1970-01-01 00:00:00)
     * @apiSuccess {string} data.server_timezone Server timezone
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": {
     *         "version": 1,
     *         "server_timestamp": 1423660181,
     *         "server_timezone": "Europe\/Berlin",
     *     }
     * }
     *
     * @return Response
     */
    public function get(): Response
    {
        $data = [
            'version' => 1,
            'server_timestamp' => time(),
            'server_timezone' => date_default_timezone_get(),
        ];

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $data,
        ]);
    }

    /**
     * @api {get} /about API Information
     * @apiVersion 1.0.0
     * @apiName getAbout
     * @apiGroup Rest
     * @apiPermission none
     * @apiDescription Get various API information
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1/about?pretty"
     *
     * @apiSuccess {number} status Status Code
     * @apiSuccess {object} data API information
     * @apiSuccess {string} data.description API description
     * @apiSuccess {string} data.copyright Copyright
     * @apiSuccess {string} data.license License
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": {
     *          "description": "This is the balloon API Interface...",
     *          "copyright": "gyselroth Gmbh 2012 - 2016",
     *          "license": "GPLv3",
     *     }
     * }
     *
     * @return Response
     */
    public function getAbout(): Response
    {
        $data = [
            'description' => 'Balloon API: See /api/v1/help or /doc for any more information.',
            'copyright' => 'gyselroth Gmbh 2012 - '.date('Y'),
            'license' => 'GPLv3',
        ];

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $data,
        ]);
    }

    /**
     * @api {get} /version API Version
     * @apiVersion 1.0.0
     * @apiName getVersion
     * @apiGroup Rest
     * @apiPermission none
     * @apiDescription Get API Version
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/version?pretty"
     *
     * @apiSuccess {number} status Status Code
     * @apiSuccess {number} data API version
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": 1
     * }
     *
     * @return Response
     */
    public function getVersion(): Response
    {
        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => 1,
        ]);
    }

    /**
     * @api {get} /help API Help Reference
     * @apiVersion 1.0.0
     * @apiName getAbout
     * @apiGroup Rest
     * @apiPermission none
     * @apiDescription API realtime reference (Automatically search all possible API methods)
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1/help?pretty"
     *
     * @apiSuccess {number} status Status Code
     * @apiSuccess {object} data API Reference
     *
     * @return Response
     */
    public function getHelp(): Response
    {
        $api = [];
        $controllers = ['Rest', 'User', 'Node', 'File', 'Collection', 'Admin\\User'];
        $prefix = ['GET', 'POST', 'DELETE', 'PUT', 'HEAD'];

        foreach ($controllers as $controller) {
            $ref = new \ReflectionClass('Balloon\\Rest\\v1\\'.$controller);
            $methods = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                $name = $this->camelCase2Dashes($method->name);
                $parts = explode('-', $name);
                $verb = strtoupper($parts[0]);
                $func = substr($name, strlen($verb) + 1);
                $url = '/rest/'.strtolower($controller).'/'.$func;
                $doc = $this->parsePhpDoc($method->getDocComment());

                if (!in_array($verb, $prefix)) {
                    continue;
                }

                $api[$controller][$name] = [
                    'url' => substr(str_replace('\\', '/', $url), 5),
                    'method' => $verb,
                    'return' => strtoupper($doc['return']),
                ];

                if ($name == 'get-help') {
                    $api[$controller][$name]['url'] = '/rest/help';
                }
            }
        }

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => $api,
        ]);
    }

    /**
     * Parse php doc.
     *
     * @param string $data
     *
     * @return array
     */
    protected function parsePhpDoc($data)
    {
        $data = trim(preg_replace('/\r?\n *\* */', ' ', $data));
        preg_match_all('/@([a-z]+)\s+(.*?)\s*(?=$|@[a-z]+\s)/s', $data, $matches);
        $info = array_combine($matches[1], $matches[2]);
        if (isset($info['return'])) {
            $info['return'] = substr($info['return'], 0, -2);
        } else {
            $info['return'] = 'void';
        }

        return $info;
    }

    /**
     * Convert camelCase to dashes.
     *
     * @param string $value
     *
     * @return string
     */
    protected function camelCase2Dashes($value)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $value));
    }
}
