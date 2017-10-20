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
use \Psr\Log\LoggerInterface;
use \Micro\Container\AdapterAwareInterface;
use \Balloon\Hook\Delta;

class Hook implements AdapterAwareInterface
{
    /**
     * Default hooks
     */
    const DEFAULT_ADAPTER = [
        Delta::class => []
    ];


    /**
     * Hooks
     *
     * @var array
     */
    protected $hook = [];


    /**
     * LoggerInterface
     *
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * Init hook manager
     *
     * @param   LoggerInterface $logger
     * @return  void
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    /**
     * Inject hook
     *
     * @param  HookInterface $adapter
     * @return Hook
     */
    public function injectHook(HookInterface $hook) : Hook
    {
        if ($this->hasHook(get_class($hook))) {
            throw new Exception('hook '.get_class($hook).' is already registered');
        }

        $this->hook[get_class($hook)] = $hook;
        return $this;
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


    /**
     * Has adapter
     *
     * @param  string $name
     * @return bool
     */
    public function hasAdapter(string $name): bool
    {
        return $this->hasHook($name);
    }


    /**
     * Inject adapter
     *
     * @param  string $name
     * @param  AdapterInterface $adapter
     * @return AdapterInterface
     */
    public function injectAdapter(string $name, HookInterface $adapter): Hook
    {
        return $this->injectHook($adapter);
    }


    /**
     * Get adapter
     *
     * @param  string $name
     * @return AdapterInterface
     */
    public function getAdapter(string $name): AppInterface
    {
        return $this->getApp($name);
    }


    /**
     * Get adapters
     *
     * @param  array $adapters
     * @return array
     */
    public function getAdapters(array $adapters = []): array
    {
        return $this->getHook($adapters);
    }
}
