<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Console\ConsoleInterface;
use Bramus\Monolog\Formatter\ColoredLineFormatter;
use GetOpt\GetOpt;
use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class Console
{
    /**
     * Module.
     *
     * @var array
     */
    protected $module = [];

    /**
     * Getopt.
     *
     * @var GetOpt
     */
    protected $getopt;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Init.
     *
     * @param GetOpt          $getopt
     * @param LoggerInterface $logger
     */
    public function __construct(GetOpt $getopt, LoggerInterface $logger)
    {
        $this->getopt = $getopt;
        $this->logger = $logger;
    }

    /**
     * Parse cmd.
     *
     * @return Console
     */
    public function parseCmd(): self
    {
        $this->getopt->addOption(['v', 'verbose', GetOpt::NO_ARGUMENT, 'Verbose']);

        $module = $this->parseModule();

        if ($module instanceof ConsoleInterface) {
            $module->setOptions();
        }

        $this->getopt->process();

        if (null === $module || in_array('help', $_SERVER['argv'], true)) {
            die($this->getHelp($module));
        }

        $this->initGlobalOptions();
        $this->start($module);

        return $this;
    }

    /**
     * Has module.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasModule(string $name): bool
    {
        return isset($this->module[$name]);
    }

    /**
     * Inject module.
     *
     * @param ConsoleInterface $module
     *
     * @return Console
     */
    public function injectModule(ConsoleInterface $module, ?string $name = null): self
    {
        if (null === $name) {
            $name = get_class($module);
        }

        $this->logger->debug('inject console module ['.$name.'] of type ['.get_class($module).']', [
            'category' => get_class($this),
        ]);

        if ($this->hasModule($name)) {
            throw new Exception('module '.$name.' is already registered');
        }

        $this->module[$name] = $module;

        return $this;
    }

    /**
     * Get module.
     *
     * @param string $name
     *
     * @return ConsoleInterface
     */
    public function getModule(string $name): ConsoleInterface
    {
        if (!$this->hasModule($name)) {
            throw new Exception('module '.$name.' is not registered');
        }

        return $this->module[$name];
    }

    /**
     * Get modules.
     *
     * @param array $modules
     *
     * @return ConsoleInterface[]
     */
    public function getModules(array $modules = []): array
    {
        if (empty($module)) {
            return $this->module;
        }
        $list = [];
        foreach ($module as $name) {
            if (!$this->hasModule($name)) {
                throw new Exception('module '.$name.' is not registered');
            }
            $list[$name] = $this->module[$name];
        }

        return $list;
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
    }

    /**
     * Show help.
     *
     * @param ConsoleInterface $module
     *
     * @return string
     */
    protected function getHelp(?ConsoleInterface $module = null): string
    {
        $help = "balloon\n\n";
        $help .= "balloon (GLOBAL OPTIONS) [MODULE] (MODULE OPTIONS)\n\n";

        $help .= "Options:\n";
        foreach ($this->getopt->getOptionObjects() as $option) {
            $help .= '-'.$option->short().' --'.$option->long().' '.$option->description()."\n";
        }

        if (null === $module) {
            $help .= "\nModules:\n";
            $help .= "help (MODULE)\t Displays a reference for a module\n";
            foreach ($this->module as $name => $module) {
                $help .= $name."\t\t".$module->getDescription()."\n";
            }
        }

        return $help;
    }

    /**
     * Get module.
     *
     * @return ConsoleInterface
     */
    protected function parseModule(): ?ConsoleInterface
    {
        foreach ($_SERVER['argv'] as $option) {
            if (isset($this->module[$option])) {
                return $this->module[$option];
            }
        }

        return null;
    }

    /**
     * Parse cmd options.
     *
     * @return Console
     */
    protected function initGlobalOptions(): self
    {
        $this->configureLogger($this->getopt->getOption('verbose'));

        $this->logger->info('processing incomming cli request', [
            'category' => get_class($this),
            'options' => $this->getopt->getOptions(),
        ]);

        if (0 === posix_getuid()) {
            $this->logger->warning('cli should not be executed as root', [
                'category' => get_class($this),
            ]);
        }

        return $this;
    }

    /**
     * Start.
     *
     * @param array $options
     *
     * @return bool
     */
    protected function start(ConsoleInterface $module): bool
    {
        return $module->start();
    }
}
