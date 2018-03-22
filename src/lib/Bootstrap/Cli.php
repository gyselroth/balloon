<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Bootstrap;

use Composer\Autoload\ClassLoader as Composer;
use GetOpt\GetOpt;
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
        $this->container->get(GetOpt::class)
            ->process()
            ->routeCommand($this->container);
    }

    /**
     * Configure cli logger.
     *
     * @return Cli
     */
    /*$this->getopt->addOption(['v', 'verbose', GetOpt::NO_ARGUMENT, 'Verbose']);
    protected function configureLogger(?int $level = null): self
    {
        if (null === $level) {
            $level = 400;
        } else {
            $level = (4 - $level) * 100;
        }

        //disable any existing stdout/sterr log handlers
        foreach ($this->logger->getHandlers() as $handler) {
            if ($handler instanceof StreamHandler) {
                if ($handler->getUrl() === 'php://stderr' || $handler->getUrl() === 'php://stdout') {
                    $handler->setLevel(1000);
                }
            } elseif ($handler instanceof FilterHandler) {
                $handler->setAcceptedLevels(1000, 1000);
            }
        }

        $formatter = new ColoredLineFormatter();
        $handler = new StreamHandler('php://stderr', Logger::EMERGENCY);
        $handler->setFormatter($formatter);
        $this->logger->pushHandler($handler);

        $handler = new StreamHandler('php://stdout', $level);
        $filter = new FilterHandler($handler, $level, Logger::ERROR);
        $handler->setFormatter($formatter);

        $this->logger->pushHandler($filter);

        return $this;
    }*/

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
