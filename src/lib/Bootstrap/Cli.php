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
     * Cli.
     */
    public function __construct(LoggerInterface $logger, GetOpt $getopt, ContainerInterface $container)
    {
        $this->logger = $logger;
        $this->getopt = $getopt;
        $this->container = $container;
        $this->reduceLogLevel();

        $this->setExceptionHandler();
        $this->setErrorHandler();
    }

    /**
     * Process.
     */
    public function process(): Cli
    {
        $this->getopt->addOption(['v', 'verbose', GetOpt::NO_ARGUMENT, 'Verbose']);
        $this->getopt->addOption(['h', 'help', GetOpt::NO_ARGUMENT, 'Help']);

        try {
            $this->getopt->process();
        } catch (\Exception $e) {
            $this->logger->debug('Some cli input failed', [
                'category' => get_class($this),
                'exception' => $e,
            ]);
        }

        if ($this->getopt->getOption('help')) {
            echo $this->getopt->getHelpText();

            return $this;
        }

        $this->configureLogger($this->getopt->getOption('verbose'));
        $this->routeCommand();

        return $this;
    }

    /**
     * Execute class action.
     */
    protected function executeCommand($command)
    {
        $action = $this->getopt->getOperand('action');
        if (is_callable([&$command, $action])) {
            return call_user_func_array([&$command, $action], []);
        }
        if ($action === null && is_callable([&$command, '__invoke'])) {
            return $command();
        }
        if (is_callable([&$command, 'help'])) {
            return call_user_func_array([&$command, 'help'], []);
        }

        echo $this->getopt->getHelpText();
    }

    /**
     * Route command.
     */
    protected function routeCommand()
    {
        $cmd = $this->getopt->getCommand();
        if ($cmd === null) {
            echo $this->getopt->getHelpText();

            return null;
        }

        $handler = $cmd->getHandler();
        $class = $this->container->get($handler);

        return $this->executeCommand($class);
    }

    /**
     * Remove logger.
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
