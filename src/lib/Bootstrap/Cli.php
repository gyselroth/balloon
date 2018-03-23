<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Bootstrap;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use GetOpt\GetOpt;
use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class Cli extends AbstractBootstrap
{
    /**
     * Getopt.
     *
     * @var GetOpt
     */
    protected $getopt;

    /**
     * Container.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * {@inheritdoc}
     */
    public function __construct(LoggerInterface $logger, GetOpt $getopt, ContainerInterface $container)
    {
        $this->logger = $logger;
        $this->getopt = $getopt;
        $this->container = $container;
        $this->reduceLogLevel();
        $this->setExceptionHandler();
    }

    /**
     * Process.
     *
     * @return Cli
     */
    public function process()
    {
        $this->getopt->addOption(['v', 'verbose', GetOpt::NO_ARGUMENT, 'Verbose']);

        // @codeCoverageIgnoreStart
        $this->getopt->process();
        $this->configureLogger($this->getopt->getOption('verbose'));
        // @codeCoverageIgnoreEnd

        $this->getopt->routeCommand($this->container);

        return $this;
    }

    /**
     * Remove logger.
     *
     * @return Cli
     */
    protected function reduceLogLevel(): self
    {
        foreach ($this->logger->getHandlers() as $handler) {
            if ($handler instanceof StreamHandler) {
                if ($handler->getUrl() === 'php://stderr' || $handler->getUrl() === 'php://stdout') {
                    $handler->setLevel(600);
                }
            } elseif ($handler instanceof FilterHandler) {
                $handler->setAcceptedLevels(1000, 1000);
            }
        }

        return $this;
    }

    /**
     * Configure cli logger.
     *
     * @return Cli
     */
    protected function configureLogger(?int $level = null): self
    {
        if (null === $level) {
            $level = 400;
        } else {
            $level = (4 - $level) * 100;
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
    }

    /**
     * Set exception handler.
     *
     * @return Cli
     */
    protected function setExceptionHandler(): self
    {
        set_exception_handler(function ($e) {
            $this->logger->emergency('uncaught exception: '.$e->getMessage(), [
                'category' => get_class($this),
                'exception' => $e,
            ]);
        });

        return $this;
    }
}
