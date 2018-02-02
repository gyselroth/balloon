<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Bootstrap;

use Balloon\Console;
use Composer\Autoload\ClassLoader as Composer;
use Psr\Log\LoggerInterface;

class Cli extends AbstractBootstrap
{
    /**
     * Adapter.
     *
     * @var array
     */
    protected $adapter = [];

    /**
     * {@inheritdoc}
     */
    public function __construct(Composer $composer)
    {
        parent::__construct($composer);
        $this->setExceptionHandler();
        $this->container->get(Console::class)->parseCmd();
    }

    /**
     * Set exception handler.
     *
     * @return Cli
     */
    protected function setExceptionHandler(): self
    {
        set_exception_handler(function ($e) {
            $logger = $this->container->get(LoggerInterface::class);
            $logger->emergency('uncaught exception: '.$e->getMessage(), [
                'category' => get_class($this),
                'exception' => $e,
            ]);
        });

        return $this;
    }
}
