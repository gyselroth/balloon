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

use \Balloon\Plugin\PluginInterface;
use \Balloon\Filesystem;
use \Balloon\Plugin\Exception;
use \Psr\Log\LoggerInterface as Logger;

class Plugin
{
    /**
     * Task plugins
     *
     * @var array
     */
    protected $plugins = [];


    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;


    /**
     * Init queue
     *
     * @param   Logger $logger
     * @return  void
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }


    /**
     * Register plugin
     *
     * @param   array|string|Config|PluginInterface $name
     * @param   object|array $config
     * @return  bool
     */
    public function registerPlugin($name, $config=null): bool
    {
        if ($name instanceof Config) {
            foreach ($name as $id => $config) {
                if ($config->enabled != '1') {
                    $this->logger->debug('skip disabled plugin ['.$config->class.']', [
                        'category' => get_class($this),
                    ]);
                    
                    continue;
                }
                $this->registerPlugin((string)$config->class, $config->config);
            }

            return true;
        }
        
        if ($name instanceof PluginInterface) {
            if (isset($this->plugins[$name->getName()])) {
                throw new Exception('plugin '.$name->getName().' is already registered');
            }
            
            $this->logger->info('register plugin ['.$name->getName().']', [
                'category' => get_class($this),
            ]);

            $this->plugins[$name->getName()] = $name;
        } elseif (is_string($name)) {
            if (!class_exists($name)) {
                throw new Exception("plugin class $name was not found");
            }
            
            $plug = new $name($config, $this->logger);
            if (isset($this->plugins[$plug->getName()])) {
                throw new Exception('plugin '.$plug->getName().' is already registered');
            }
            
            if (!($plug instanceof PluginInterface)) {
                throw new Exception('plugin '.$name.' does not implement \Balloon\Plugin\PluginInterface');
            }

            $this->logger->info('register plugin ['.$plug->getName().']', [
                'category' => get_class($this),
            ]);

            $this->plugins[$plug->getName()] = $plug;
        }

        return true;
    }


    /**
     * Run plugin method
     *
     * @param   string $method
     * @param   array $context
     * @return  bool
     */
    public function run(string $method, array $context=[]): bool
    {
        $this->logger->debug('execute plugins hooks for ['.$method.']', [
            'category' => get_class($this),
        ]);

        $args = [];
        foreach ($context as $k => &$arg) {
            $args[$k] = &$arg;
        }

        foreach ($this->plugins as $plugin) {
            $this->logger->debug('found registered plugin hoook, execute ['.get_class($plugin).'::'.$method.']', [
                'category' => get_class($this),
            ]);

            call_user_func_array([$plugin, $method], $args);
        }

        return true;
    }
}
