<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Hook\AutoDestroy;
use Balloon\Hook\CleanTrash;
use Balloon\Hook\Delta;
use Balloon\Hook\Exception;
use Balloon\Hook\HookInterface;
use Psr\Log\LoggerInterface;

class Hook
{
    /**
     * Hooks.
     *
     * @var array
     */
    protected $hook = [];

    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Init hook manager.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Inject hook.
     *
     * @param HookInterface $adapter
     *
     * @return AdapterAwareInterface
     */
    public function injectHook(HookInterface $hook): AdapterAwareInterface
    {
        $this->logger->debug('inject hook ['.get_class($hook).']', [
            'category' => get_class($this),
        ]);

        if ($this->hasHook(get_class($hook))) {
            throw new Exception('hook '.get_class($hook).' is already registered');
        }

        $this->hook[get_class($hook)] = $hook;

        return $this;
    }

    /**
     * Has hook.
     *
     * @param string $class
     *
     * @return bool
     */
    public function hasHook(string $class): bool
    {
        return isset($this->hook[$class]);
    }

    /**
     * Get hook.
     *
     * @param string $class
     *
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
     * Get hooks.
     *
     * @param array $hooks
     *
     * @return array
     */
    public function getHooks(array $hooks = []): array
    {
        if (empty($hook)) {
            return $this->hook;
        }
        $list = [];
        foreach ($hook as $class) {
            if (!$this->hasHook($class)) {
                throw new Exception('hook '.$class.' is not registered');
            }
            $list[$class] = $this->hook[$class];
        }

        return $list;
    }

    /**
     * Run hook method.
     *
     * @param string $method
     * @param array  $context
     *
     * @return bool
     */
    public function run(string $method, array $context = []): bool
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
     * Get default adapter.
     *
     * @return array
     */
    public function getDefaultAdapter(): array
    {
        return self::DEFAULT_ADAPTER;
    }

    /**
     * Has adapter.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasAdapter(string $name): bool
    {
        return $this->hasHook($name);
    }

    /**
     * Inject adapter.
     *
     * @param mixed  $adapter
     * @param string $name
     *
     * @return AdapterAwareInterface
     */
    public function injectAdapter($adapter, ?string $name = null): AdapterAwareInterface
    {
        return $this->injectHook($adapter);
    }

    /**
     * Get adapter.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getAdapter(string $name)
    {
        return $this->getHook($name);
    }

    /**
     * Get adapters.
     *
     * @param array $adapters
     *
     * @return array
     */
    public function getAdapters(array $adapters = []): array
    {
        return $this->getHook($adapters);
    }
}
