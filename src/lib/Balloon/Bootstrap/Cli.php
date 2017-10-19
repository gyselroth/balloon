<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Bootstrap;

use \Balloon\App\AppInterface;
use \Balloon\App;
use \Micro\Config;
use \Composer\Autoload\ClassLoader as Composer;
use \Micro\Log\Adapter\Stdout;
use \Balloon\Console\Async;
use \Balloon\Console\Database;
use \Balloon\Console\ConsoleInterface;
use \GetOpt\GetOpt;
use \Psr\Log\LoggerInterface;

class Cli extends AbstractBootstrap
{
    /**
     * Console modules
     *
     * @var array
     */
    protected $module = [
        'async'    => Async::class,
        'database' => Database::class
    ];


    /**
     * Init bootstrap
     *
     * @param  Composer $composer
     * @param  Config $config
     * @return void
     */
    public function __construct(Composer $composer, ?Config $config=null)
    {
        parent::__construct($composer, $config);
        $this->setExceptionHandler();

        $this->container->add(GetOpt::class, function(){
            return new GetOpt([
                ['v', 'verbose', GetOpt::NO_ARGUMENT, 'Verbose'],
            ]);
        });

        $getopt = $this->container->get(getopt::class);
        $module = $this->getModule($getopt);
        $getopt->process();

        if ($module === null || in_array('help', $_SERVER['argv'])) {
            die($this->getHelp($getopt, $module));
        }

        $this->initGlobalOptions($getopt);
        $this->start($module);
    }


    /**
     * Configure cli logger
     *
     * @return Cli
     */
    protected function configureLogger(?int $level=null): Cli
    {
        if ($level === null) {
            $level = 4;
        } else {
            $level += 4;
        }

        $logger = $this->container->get(LoggerInterface::class);

        if (!$logger->hasAdapter('stdout')) {
            $logger->addAdapter('stdout', Stdout::class, [
                'level'  => $level,
                'format' => '{date} [{context.category},{level}]: {message} {context.params} {context.exception}']);
        } else {
            $logger->getAdapter('stdout')->setOptions(['level' => $level]);
        }

        return $this;
    }


    /**
     * Show help
     *
     * @param  GetOpt $getopt
     * @param  ConsoleInterface $module
     * @return string
     */
    protected function getHelp(GetOpt $getopt, ?ConsoleInterface $module=null): string
    {
        $help  = "Balloon\n";
        $help .= "balloon (GLOBAL OPTIONS) [MODULE] (MODULE OPTIONS)\n\n";

        $help .= "Options:\n";
        foreach ($getopt->getOptionObjects() as $option) {
            $help .= '-'.$option->short().' --'.$option->long().' '.$option->description()."\n";
        }

        if ($module === null) {
            $help .= "\nModules:\n";
            $help .= "help (MODULE)\t Displays a reference for module\n";
            $help .= "async\t\t Handles asynchronous jobs\n";
            $help .= "database\t Initialize and upgrade database\n\n";
        }

        return $help;
    }


    /**
     * Get module
     *
     * @param  GetOpt $getopt
     * @return ConsoleInterface
     */
    protected function getModule(GetOpt $getopt): ?ConsoleInterface
    {
        foreach ($_SERVER['argv'] as $option) {
            if (isset($this->module[$option])) {
                return $this->container->get($this->module[$option]);
            }
        }

        return null;
    }


    /**
     * Parse cmd options
     *
     * @param  GetOpt $getopt
     * @return Cli
     */
    protected function initGlobalOptions(GetOpt $getopt): Cli
    {
        $this->configureLogger($getopt->getOption('verbose'));

        $logger = $this->container->get(LoggerInterface::class);
        $logger->info('processing incomming cli request', [
            'category' => get_class($this),
            'options'  => $getopt->getOptions()
        ]);

        if (posix_getuid() == 0) {
            $logger->warning('cli should not be executed as root', [
                'category' => get_class($this),
            ]);
        }

        return $this;
    }


    /**
     * Start
     *
     * @param  array $options
     * @return bool
     */
    protected function start(ConsoleInterface $module): bool
    {
        return $module->start();
    }


    /**
     * Set exception handler
     *
     * @return Cli
     */
    protected function setExceptionHandler(): Cli
    {
        set_exception_handler(function ($e) {
            $logger = $this->container->get(LoggerInterface::class);
            $logger->emergency('uncaught exception: '.$e->getMessage(), [
                'category' => get_class($this),
                'exception' => $e
            ]);
        });

        return $this;
    }
}
