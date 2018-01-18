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
use GetOpt\GetOpt;
use Psr\Log\LoggerInterface;

class Console
{
    /**
     * Adapter.
     *
     * @var array
     */
    protected $adapter = [];

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

        $module = $this->getModule();

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
     * Get default adapter.
     *
     * @return array
     */
    public function getDefaultAdapter(): array
    {
        return [];
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
     * @param ConsoleInterface $adapter
     *
     * @return AdapterAwareInterface
     */
    public function injectAdapter($adapter, ?string $name = null): AdapterAwareInterface
    {
        if (!($adapter instanceof ConsoleInterface)) {
            throw new Exception('adapter needs to implement ConsoleInterface');
        }

        if (null === $name) {
            $name = get_class($adapter);
        }

        $this->logger->debug('inject console adapter ['.$name.'] of type ['.get_class($adapter).']', [
            'category' => get_class($this),
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
     * @return ConsoleInterface
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

    /**
     * Configure cli logger.
     *
     * @return Cli
     */
    protected function configureLogger(?int $level = null): self
    {
        if (null === $level) {
            $level = 4;
        } else {
            $level += 4;
        }

        if (!$this->logger->hasAdapter('stdout')) {
            $this->logger->injectAdapter(new Stdout([
                'level' => $level,
                'format' => '{date} [{context.category},{level}]: {message} {context.params} {context.exception}', ]), 'stdout');
        } else {
            $this->logger->getAdapter('stdout')->setOptions(['level' => $level]);
        }

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
        $help = "Balloon\n";
        $help .= "balloon (GLOBAL OPTIONS) [MODULE] (MODULE OPTIONS)\n\n";

        $help .= "Options:\n";
        foreach ($this->getopt->getOptionObjects() as $option) {
            $help .= '-'.$option->short().' --'.$option->long().' '.$option->description()."\n";
        }

        if (null === $module) {
            $help .= "\nModules:\n";
            $help .= "help (MODULE)\t Displays a reference for a module\n";
            foreach ($this->adapter as $name => $adapter) {
                $help .= $name."\t\t".$adapter->getDescription()."\n";
            }
        }

        return $help;
    }

    /**
     * Get module.
     *
     * @return ConsoleInterface
     */
    protected function getModule(): ?ConsoleInterface
    {
        foreach ($_SERVER['argv'] as $option) {
            if (isset($this->adapter[$option])) {
                return $this->adapter[$option];
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
