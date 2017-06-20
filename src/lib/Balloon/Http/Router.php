<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Http;

use Balloon\Exception;
use Balloon\Helper;
use \Psr\Log\LoggerInterface as Logger;
use Balloon\Http\Router\Route;
use \ReflectionMethod;

class Router
{
    /**
     * Requested route
     *
     * @var string
     */
    protected $path;


    /**
     * HTTP verb
     *
     * @var string
     */
    protected $verb;


    /**
     * Installed routes
     *
     * @var array
     */
    protected $routes = [];


    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;


    /**
     * Init router
     *
     * @param   array $server
     * @param   Logger $logger
     * @return  void
     */
    public function __construct(array $server, Logger $logger)
    {
        $this->logger = $logger;
        
        if (isset($server['PATH_INFO'])) {
            $this->setPath($server['PATH_INFO']);
        }
        
        if (isset($server['REQUEST_METHOD'])) {
            $this->setVerb($server['REQUEST_METHOD']);
        }
    }
    

    /**
     * Prepend route
     *
     * @param   Route $route
     * @return  Router
     */
    public function prependRoute(Route $route): Router
    {
        array_unshift($this->routes, $route);
        $route->setRouter($this);
        return $this;
    }


    /**
     * Add route
     *
     * @param   Route $route
     * @return  Router
     */
    public function addRoute(Route $route): Router
    {
        $this->routes[] = $route;
        $route->setRouter($this);
        return $this;
    }

    
    /**
     * Clear routing table
     *
     * @return Router
     */
    public function clearRoutingTable(): Router
    {
        $this->routes = [];
        return $this;
    }

    
    /**
     * Get active routes
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
    
    
    /**
     * Set HTTP verb
     *
     * @param   string $verb
     * @return  Router
     */
    public function setVerb(string $verb): Router
    {
        $this->verb = strtolower($verb);
        return $this;
    }


    /**
     * Get http verb
     *
     * @return string
     */
    public function getVerb(): string
    {
        return $this->verb;
    }


    /**
     * Set routing path
     *
     * @param   string $path
     * @return  Router
     */
    public function setPath(string $path): Router
    {
        $path = rtrim(trim($path), '/');
        $this->path = (string)$path;
        return $this;
    }


    /**
     * Get path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }
 

    /**
     * Build method name
     *
     * @param   string $name
     * @return  string
     */
    protected function _buildMethodName(string $name): string
    {
        $result = $this->verb;
        $split = explode('-', $name);
        foreach ($split as $part) {
            $result .= ucfirst($part);
        }

        return $result;
    }


    /**
     * Execute router
     *
     * @param  array $constructor
     * @return bool
     */
    public function run(array $constructor=[]): bool
    {
        $this->logger->info('execute requested route ['.$this->path.']', [
            'category' => get_class($this),
        ]);

        try {
            $match = false;
            foreach ($this->routes as $key => $route) {
                if ($route->match()) {
                    $callable = $route->getCallable($constructor);
                    
                    if (is_callable($callable)) {
                        $match = true;
                        $this->logger->info('found matching route, execute ['.$route->getClass().'::'.$callable[1].']', [
                            'category' => get_class($this),
                        ]);

                        $params = $this->getParams($route->getClass(), $callable[1], $route->getParams());
                        $response = call_user_func_array($callable, $params);
                        
                        if (!$route->continueAfterMatch()) {
                            break;
                        }
                    }
                } else {
                    $this->logger->debug('requested path ['.$this->path.'] does not match route ['.$route->getPath().']', [
                         'category' => get_class($this),
                    ]);
                }
            }
            
            if ($match === false) {
                throw new Exception\InvalidArgument($this->verb.' '.$this->path.' could not be routed, no matching routes found');
            } else {
                if ($response instanceof Response) {
                    $this->logger->info('send http response ['.$response->getCode().']', [
                        'category' => get_class($this),
                    ]);

                    $response->send();
                } else {
                    $this->logger->debug('callback did not return a response, route exectuted successfully', [
                        'category' => get_class($this),
                    ]);
                }
            }

            return true;
        } catch (\Exception $e) {
            return $this->sendException($e);
        }
    }


    /**
     * Sends a exception response to the client
     *
     * @param   \Exception $exception
     * @return  void
     */
    public function sendException(\Exception $exception): void
    {
        $message = $exception->getMessage();
        $msg = [
            'error'   => get_class($exception),
            'message' => $message,
            'code'    => $exception->getCode()
        ];

        switch (get_class($exception)) {
           case 'Balloon\\Exception\\InvalidArgument':
           case 'Balloon\\Exception\\Conflict':
               $code = 400;
           break;
           case 'Balloon\\Exception\\NotFound':
               $code = 404;
           break;
           case 'Balloon\\Exception\\Forbidden':
               $code = 403;
           break;
           case 'Balloon\\Exception\\InsufficientStorage':
               $code = 507;
           break;
           default:
              $code = 500;
           break;
        }
        
        $this->logger->error('uncaught exception '.$message.']', [
            'category' => get_class($this),
            'exception' => $exception,
        ]);
        
        (new Response())
            ->setCode($code)
            ->setBody($msg)
            ->send();
    }


    /**
     * Check if method got params and combine these with
     * $_REQUEST
     *
     * @param   string $class
     * @param   string $method
     * @param   array $parsed_params
     * @return  array
     */
    protected function getParams(string $class, string $method, array $parsed_params): array
    {
        try {
            $return      = [];
            $meta        = new ReflectionMethod($class, $method);
            $params      = $meta->getParameters();
            $json_params = [];
            
            if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {
                $body = file_get_contents('php://input');
                if (!empty($body)) {
                    $json_params = json_decode($body, true);
                } else {
                    $parts  = explode('&', $_SERVER['QUERY_STRING']);
                    if (!empty($parts)) {
                        $json_params = json_decode(urldecode($parts[0]), true);
                    }
                }
                if ($json_params === null) {
                    throw new Exception\InvalidArgument('invalid json input given');
                }

                $request_params = array_merge($json_params, $parsed_params);
            } else {
                $request_params = array_merge($parsed_params, $_REQUEST);
            }
            
            foreach ($params as $param) {
                if ($optional = $param->isOptional()) {
                    $default = $param->getDefaultValue();
                } else {
                    $default = null;
                }

                if (isset($request_params[$param->name]) && $request_params[$param->name] !== '') {
                    if (is_bool($default)) {
                        $return[$param->name] = Helper::boolParam($request_params[$param->name]);
                    } elseif (is_int($default)) {
                        $return[$param->name] = (int)$request_params[$param->name];
                    } elseif (is_array($default)) {
                        $return[$param->name] = (array)$request_params[$param->name];
                    } else {
                        $return[$param->name] = $request_params[$param->name];
                    }
                } elseif (isset($json_params[$param->name])) {
                    $return[$param->name] = $json_params[$param->name];
                } else {
                    $return[$param->name] = $default;
                }

                if ($return[$param->name] === null && $optional === false) {
                    throw new Exception\InvalidArgument('misssing required parameter '.$param->name);
                }
            }
            
            return $return;
        } catch (\ReflectionException $e) {
            throw new Exception\InvalidArgument('misssing or invalid required request parameter');
        }
    }
}
