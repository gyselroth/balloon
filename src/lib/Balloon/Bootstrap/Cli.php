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

class Cli extends AbstractBootstrap
{
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
        $this->start($this->parseOptions());
        return true;
    }


    /**
     * Configure cli logger
     *
     * @return Cli
     */
    protected function configureLogger(array $options=[]): Cli
    {
        $level = 2;
        if (isset($options['verbose'])) {
            $value = $options['verbose'];
            if ($value === false) {
                $level = 4;
            } elseif (is_array($value) && count($value) === 2) {
                $level = 5;
            } elseif (is_array($value) && count($value) === 3) {
                $level = 6;
            } elseif (is_array($value) && count($value) === 4) {
                $level = 7;
            } else {
                $level = 7;
            }
        }

        if (!$this->logger->hasAdapter('stdout')) {
            $this->logger->addAdapter('stdout', Stdout::class, [
                'level'  => $level,
                'format' => '{date} [{context.category},{level}]: {message} {context.params} {context.exception}']);
        } else {
            $this->logger->getAdapter('stdout')->setOptions(['level' => $level]);
        }

        return $this;
    }


    /**
     * Show help
     *
     * @return void
     */
    protected function showHelp(): void
    {
        echo "Balloon\n\n";
        echo "-h Shows this message\n";
    }


    /**
     * Parse cmd options
     *
     * @return array
     */
    protected function parseOptions(): array
    {
        $options = $this->prepareOptions(getopt('hdqa::v::', [
            'help',
            'daemon',
            'apps',
            'queue'
        ]));

        $this->configureLogger($options);

        if (isset($options['help'])) {
            $this->showHelp() & exit();
        }

        $this->logger->info('processing incomming cli request', [
            'category' => get_class($this),
            'options'  => $options
        ]);

        if (posix_getuid() == 0) {
            $this->logger->warning('cli should not be executed as root', [
                'category' => get_class($this),
            ]);
        }

        return $options;
    }


    /**
     * Prepare & sort options
     *
     * @param  array $options
     * @return array
     */
    protected function prepareOptions(array $options=[]): array
    {
        $priority = [
            'help'   => 'h',
            'verbose'=> 'v',
            'apps'   => 'a',
            'queue'  => 'q',
            'daemon' => 'd',
        ];

        $set = [];
        foreach ($priority as $option => $value) {
            if (isset($options[$option])) {
                $set[$option] = $options[$option];
            } elseif (isset($options[$value])) {
                $set[$option] = $options[$value];
            }
        }

        return $set;
    }


    /**
     * Start
     *
     * @param  array $options
     * @return bool
     */
    protected function start(array $options=[]): bool
    {
        $this->app = new App(App::CONTEXT_CLI, $this->composer, $this->server, $this->logger, $this->option_app);

        if (isset($options['apps'])) {
            $apps = explode(',', $options['apps']);
        } else {
            $apps = [];
        }

        if (!isset($options['queue'])) {
            $this->logger->debug("skip job queue execution", [
                'category' => get_class($this),
            ]);
        }

        if (isset($options['daemon'])) {
            $this->fireupDaemon($options);
        } else {
            if (isset($options['queue'])) {
                $cursor = $this->async->getCursor(false);
                $this->async->start($cursor, $this->server);
            }

            foreach ($this->app->getApps($apps) as $app) {
                $app->start();
            }
        }

        return true;
    }


    /**
     * Fire up daemon
     *
     * @param  array $options
     * @return bool
     */
    protected function fireupDaemon(array $options=[]): bool
    {
        $this->logger->info("daemon execution requested, fire up daemon", [
            'category' => get_class($this),
        ]);

        $cursor = $this->async->getCursor(true);
        while (true) {
            if (isset($options['queue'])) {
                $this->async->start($cursor, $this->server);
            }
        }

        return true;
    }


    /**
     * Set exception handler
     *
     * @return Cli
     */
    protected function setExceptionHandler(): Cli
    {
        set_exception_handler(function ($e) {
            $this->logger->emergency('uncaught exception: '.$e->getMessage(), [
                'category' => get_class($this),
                'exception' => $e
            ]);
        });

        return $this;
    }
}
