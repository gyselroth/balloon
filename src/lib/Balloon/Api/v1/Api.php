<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Api\v1;

use \Balloon\Exception;
use \Balloon\Api\Controller;
use \Micro\Http\Response;

class Api extends Controller
{
    /**
     * @api {get} / Server & API Status
     * @apiVersion 1.0.6
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
     * @apiSuccess {string} data.name balloon identifier
     * @apiSuccess {number} data.api_version API Version
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": {
     *         "name": "balloon",
     *         "api_version": 1
     *     }
     * }
     *
     * @return Response
     */
    public function get(): Response
    {
        $data = [
            'name'        => 'balloon',
            'api_version' => 1
        ];
        
        return (new Response())->setCode(200)->setBody($data);
    }


    /**
     * @api {get} /help API Help Reference
     * @apiVersion 1.0.6
     * @apiName getAbout
     * @apiGroup Api
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
        $controllers = ['Api', 'User', 'Node', 'File', 'Collection', 'Admin\\User'];
        $prefix = ['GET', 'POST', 'DELETE', 'PUT', 'HEAD'];
    
        foreach ($controllers as $controller) {
            $ref = new \ReflectionClass('Balloon\\Api\\v1\\'.$controller);
            $methods = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                $name  = $this->camelCase2Dashes($method->name);
                $parts = explode('-', $name);
                $verb  = strtoupper($parts[0]);
                $func  = substr($name, strlen($verb)+1);
                $url   = '/rest/'.strtolower($controller).'/'.$func;
                $doc   = $this->parsePhpDoc($method->getDocComment());

                if (!in_array($verb, $prefix)) {
                    continue;
                }

                $api[$controller][$name] = [
                    'url'    => substr(str_replace('\\', '/', $url), 5),
                    'method' => $verb,
                    'return' => strtoupper($doc['return'])
                ];

                if ($name == 'get-help') {
                    $api[$controller][$name]['url'] = '/rest/help';
                }
            }
        }

        return (new Response())->setCode(200)->setBody($api);
    }


    /**
     * Parse php doc
     *
     * @param  string $data
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
     * Convert camelCase to dashes
     *
     * @param  string $value
     * @return string
     */
    protected function camelCase2Dashes($value)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $value));
    }
}
