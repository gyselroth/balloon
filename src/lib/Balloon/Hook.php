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

use \Balloon\Hook\Exception;
use \Balloon\Hook\HookInterface;
use \Psr\Log\LoggerInterface as Logger;

class Hook
{
    /**
     * Hooks
     *
     * @var array
     */
    protected $hook = [];


    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;


    /**
     * Init hook manager
     *
     * @param   Logger $logger
     * @return  void
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }


    /**
     * Register hook
     *
     * @param   string $class
     * @param   Iterable $config
     * @return  bool
     */
    public function registerHook(string $class, ?Iterable $config=null): bool
    {
        if (!class_exists($class)) {
            throw new Exception("hook class $class was not found");
        }
            
        $hook = new $class($this->logger, $config);
        if (isset($this->hook[$class])) {
            throw new Exception('hook '.$class.' is already registered');
        }
            
        if (!($hook instanceof HookInterface)) {
            throw new Exception('hook '.$class.' does not implement HookInterface');
        }

        $this->logger->info('register hook ['.$class.']', [
             'category' => get_class($this),
        ]);

        $this->hook[$class] = $hook;

        return true;
    }


    /**
     * Inject hook
     *
     * @param  HookInterface $adapter
     * @return bool
     */
    public function injectHook(HookInterface $hook) : bool
    {
        if ($this->hasHook(get_class($hook))) {
            throw new Exception('hook '.get_class($hook).' is already registered');
        }
            
        $this->hook[get_class($hook)] = $hook;
        return true;
    }


    /**
     * Has hook
     *
     * @param  string $class
     * @return bool
     */
    public function hasHook(string $class): bool
    {
        return isset($this->hook[$class]);
    }


    /**
     * Get hook
     *
     * @param  string $class
     * @return HookInterface
     */
    public function getHook(string $class): HookInterface
    {
        if (!$this->hasHook($class)) {
            throw new Exception('hook '.$class.' is not registered');
        }

        return $this->hook[$class];
    }


    /**
     * Get hooks
     *
     * @param  array $hooks
     * @return array
     */
    public function getHooks(array $hooks = []): array
    {
        if (empty($hook)) {
            return $this->hook;
        } else {
            $list = [];
            foreach ($hook as $class) {
                if (!$this->hasHook($class)) {
                    throw new Exception('hook '.$class.' is not registered');
                }
                $list[$class] = $this->hook[$class];
            }

            return $list;
        }
    }


    /**
     * Run hook method
     *
     * @param   string $method
     * @param   array $context
     * @return  bool
     */
    public function run(string $method, array $context=[]): bool
    {
        $this->logger->debug('execute hooks hooks for ['.$method.']', [
            'category' => get_class($this),
        ]);

        $args = [];
        foreach ($context as $k => &$arg) {
            $args[$k] = &$arg;
        }

        foreach ($this->hook as $hook) {
            $this->logger->debug('found registered hook, execute ['.get_class($hook).'::'.$method.']', [
                'category' => get_class($this),
            ]);

            call_user_func_array([$hook, $method], $args);
        }

        return true;
    }
}
