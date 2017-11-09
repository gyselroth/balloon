<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\App\AppInterface;
use Balloon\App\Exception;
use Composer\Autoload\ClassLoader as Composer;
use Micro\Container;
use Psr\Log\LoggerInterface;
use Micro\Container\AdapterAwareInterface;

class App implements AdapterAwareInterface
{
    /**
     * Apps.
     *
     * @var array
     */
    protected $adapter = [];

    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Init app manager.
     *
     * @param LoggerInterface $logger
     * @param iterable        $config
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        //$this->setOptions($config);
    }


    /**
     * Get default adapter
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
        return isset($this->adapter[$name]);
    }

    /**
     * Inject adapter.
     *
     * @param AdapterInterface $adapter
     *
     * @return AdapterInterface
     */
    public function injectAdapter($adapter, ?string $name=null): AdapterAwareInterface
    {
        if(!($adapter instanceof AppInterface)) {
            throw new Exception('adapter needs to implement AppInterface');
        }

        if($name === null) {
            $name = get_class($adapter);
        }

        $this->logger->debug('inject app ['.$name.'] of type ['.get_class($adapter).']', [
            'category' => get_class($this)
        ]);

        if ($this->hasAdapter($name)) {
            throw new Exception('adapter '.$name.' is already registered');
        }

        $this->adapter[$name] = $adapter;

        return $this;
    }

    /**
     * Get adapter.
     *
     * @param string $name
     *
     * @return AdapterInterface
     */
    public function getAdapter(string $name)
    {
        if (!$this->hasAdapter($name)) {
            throw new Exception('adapter '.$name.' is not registered');
        }

        return $this->adapter[$name];
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
        if (empty($adapter)) {
            return $this->adapter;
        }
        $list = [];
        foreach ($adapter as $name) {
            if (!$this->hasAdapter($name)) {
                throw new Exception('adapter '.$name.' is not registered');
            }
            $list[$name] = $this->adapter[$name];
        }

        return $list;
    }
}
