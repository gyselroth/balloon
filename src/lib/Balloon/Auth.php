<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Exception;
use Balloon\Config;
use Balloon\Auth\Adapter\AdapterInterface;
use \MongoDB\Database;
use \MongoDB\BSON\Binary;
use \Psr\Log\LoggerInterface as Logger;

class Auth
{
    /**
     * User adapter
     *
     * @var AdapterInterface
     */
    private $adapter;


    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;

    
    /**
     * MongoDB
     *
     * @var Database
     */
    protected $db;


    /**
     * Initialize
     *
     * @param   Logger $logger
     * @param   Plugin $plugin
     * @return  void
     */
    public function __construct(Logger $logger, Plugin $plugin, Database $db)
    {
        $this->logger = $logger;
        $this->plugin = $plugin;
        $this->db     = $db;
    }


    /**
     * Authenticate
     *
     * @param   array|AdapterInterface|Config $adapters
     * @return  bool
     */
    public function requireOne($adapters): bool
    {
        $result = false;
        if ($adapters instanceof AdapterInterface) {
            $adapters = [$adapters];
        } elseif ($adapters instanceof Config) {
            $spool = [];
            foreach ($adapters as $adapter) {
                if ($adapter->enabled === '1') {
                    $this->logger->info("enable authentication adapter [".$adapter->class."]", [
                        'category' => get_class($this)
                    ]);
                    
                    $spool[] = self::factory($adapter->class, $adapter->config, $this->logger, $this->db);
                } else {
                    $this->logger->info("skip disabled authentication adapter [".$adapter->class."]", [
                        'category' => get_class($this)
                    ]);
                }
            }

            $adapters = $spool;
        }

        $this->plugin->run('preAuthentication', [&$adapters]);

        foreach ((array)$adapters as $adapter) {
            try {
                if ($adapter instanceof AdapterInterface) {
                    $result = $adapter->authenticate();

                    if ($result === true) {
                        $this->adapter = $adapter;
                        $_SERVER['REMOTE_USER'] = $adapter->getIdentity();

                        $class = get_class($this->adapter);
                        $this->logger->info("user [{$adapter->getIdentity()}] authenticated over [{$class}]", [
                            'category' => get_class($this)
                        ]);

                        $this->plugin->run('validAuthentication', [$adapter->getIdentity(), $adapter]);
                        return true;
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error("failed authenticate user, unexcepted exception was thrown", [
                    'category' => get_class($this),
                    'exception'=> $e
                ]);
            }
        
            $this->logger->debug("auth adapter [".get_class($adapter)."] failed", [
                'category' => get_class($this)
            ]);
        }
        
        $this->logger->warning("all authentication adapter have failed", [
           'category' => get_class($this)
        ]);

        $this->plugin->run('invalidAuthentication', [$adapters]);
        
        if (isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER'] == '_logout') {
            (new \Balloon\Http\Response())
                ->setCode(401)
                ->setBody('Unauthorized')
                ->send();
        } else {
            if ($_SERVER['REQUEST_URI'] == '/api/auth') {
                $code = 403;
            } else {
                $code = 401;
            }

            (new \Balloon\Http\Response())
                ->setHeader('WWW-Authenticate', 'Basic realm="balloon"')
                ->setCode($code)
                ->setBody('Unauthorized')
                ->send();
        }

        return false;
    }


    /**
     * adapter factory
     *
     * @param   string $name
     * @param   Config|array $config
     * @param   Logger $logger
     * @return  AdapterInterface
     */
    public static function factory(string $name, $config, Logger $logger, Database $db): AdapterInterface
    {
        $name = (string)$name;

        if (!class_exists($name)) {
            $name = ucfirst($name);
            $name = "\Balloon\Auth\Adapter\\$name";
        }
                
        $adapter = new $name($config, $logger);
        if (is_callable([$adapter, 'setDatabase'])) {
            $adapter->setDatabase($db);
        }

        return $adapter;
    }


    /**
     * Get adapter
     *
     * @return AdapterInterface
     */
    public function getAdapter(): AdapterInterface
    {
        if (!$this->hasIdentity()) {
            throw new Exception\NotAuthenticated('no valid authentication yet',
                Exception\NotAuthenticated::NOT_AUTHENTICATED
            );
        } else {
            return $this->adapter;
        }
    }


    /**
     * Get identity
     *
     * @return string
     */
    public function getIdentity(): string
    {
        if (!$this->hasIdentity()) {
            throw new Exception\NotAuthenticated('no valid authentication yet',
                Exception\NotAuthenticated::NOT_AUTHENTICATED
            );
        } else {
            return strtolower($this->adapter->getIdentity());
        }
    }


    /**
     * Check if valid identity exists
     *
     * @return bool
     */
    public function hasIdentity(): bool
    {
        return ($this->adapter instanceof AdapterInterface);
    }

    
    /**
     * Prepare attributes
     *
     * @param  array $data
     * @return array
     */
    protected function mapAttributes(array $data): array
    {
        $map = $this->adapter->getAttributeMap();
        $attrs = [];

        foreach ($map as $attr => $value) {
            if (array_key_exists($value['attr'], $data)) {
                $this->logger->info('found user auth attribut mapping ['.$attr.'] => [('.$value['type'].') '.$value['attr'].']', [
                    'category' => get_class($this),
                ]);

                if ($value['type'] == 'array') {
                    $store = $data[$value['attr']];
                } else {
                    if (is_array($data[$value['attr']])) {
                        $store = $data[$value['attr']][0];
                    } else {
                        $store = $data[$value['attr']];
                    }
                }

                switch ($value['type']) {
                    case 'array':
                         $arr =  (array)$data[$value['attr']];
                          unset($arr['count']);
                          $attrs[$attr] = $arr;
                    break;
                        
                    case 'string':
                         $attrs[$attr]  = (string)$store;
                    break;
                                            
                    case 'int':
                         $attrs[$attr]  = (int)$store;
                    break;
                                            
                    case 'bool':
                         $attrs[$attr]  = (bool)$store;
                    break;
                    
                    case 'binary':
                         $attrs[$attr]  = new Binary($store, Binary::TYPE_GENERIC);
                    break;
                    
                    default:
                        $this->logger->error('unknown attribute type ['.$value['type'].'] for attribute ['.$attr.']; use one of [array,string,int,bool,binary]', [
                            'category' => get_class($this),
                        ]);
                    break;
                }
            } else {
                $this->logger->warning('auth attribute ['.$value['attr'].'] was not found from authentication adapter response', [
                    'category' => get_class($this),
                ]);
            }
        }

        return $attrs;
    }

    /**
     * Get attribute sync cache
     *
     * @return array
     */
    public function getAttributeSyncCache(): int
    {
        if (!($this->adapter instanceof AdapterInterface)) {
            throw new Exception\NotAuthenticated('no valid authentication yet',
                Exception\NotAuthenticated::NOT_AUTHENTICATED
            );
        } else {
            return $this->adapter->getAttributeSyncCache();
        }
    }


    /**
     * Get identity attributes
     *
     * @return array
     */
    public function getAttributes(): array
    {
        if (!($this->adapter instanceof AdapterInterface)) {
            throw new Exception\NotAuthenticated('no valid authentication yet',
                Exception\NotAuthenticated::NOT_AUTHENTICATED
            );
        } else {
            return $this->mapAttributes($this->adapter->getAttributes());
        }
    }
}
