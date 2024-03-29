<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

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
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Inject hook.
     */
    public function injectHook(HookInterface $hook): self
    {
        $this->logger->debug('inject hook ['.get_class($hook).']', [
            'category' => static::class,
        ]);

        if ($this->hasHook(get_class($hook))) {
            throw new Exception('hook '.get_class($hook).' is already registered');
        }

        $this->hook[get_class($hook)] = $hook;

        return $this;
    }

    /**
     * Has hook.
     */
    public function hasHook(string $class): bool
    {
        return isset($this->hook[$class]);
    }

    /**
     * Get hook.
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
     */
    public function getHooks(array $hooks = []): array
    {
        if (empty($hooks)) {
            return $this->hook;
        }
        $list = [];
        foreach ($hooks as $class) {
            if (!$this->hasHook($class)) {
                throw new Exception('hook '.$class.' is not registered');
            }
            $list[$class] = $this->hook[$class];
        }

        return $list;
    }

    /**
     * Run hook method.
     */
    public function run(string $method, array $context = []): bool
    {
        $this->logger->debug('execute hooks hooks for ['.$method.']', [
            'category' => static::class,
        ]);

        $args = [];
        foreach ($context as $k => &$arg) {
            $args[$k] = &$arg;
        }

        foreach ($this->hook as $hook) {
            $this->logger->debug('found registered hook, execute ['.get_class($hook).'::'.$method.']', [
                'category' => static::class,
            ]);

            call_user_func_array([$hook, $method], $args);
        }

        return true;
    }

    /**
     * Remove hook.
     */
    protected function removeHook(string $class): bool
    {
        if ($this->hasHook($class)) {
            unset($this->hook[$class]);

            return true;
        }

        throw new Exception('hook '.$class.' is not registered');
    }
}
