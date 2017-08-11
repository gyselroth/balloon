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

class Cli extends AbstractBootstrap
{
    /**
     * Init bootstrap
     *
     * @return bool
     */
    public function init(): bool
    {
        parent::init();

        $this->setExceptionHandler();

        $this->logger->info('processing incomming cli request', [
            'category' => get_class($this),
        ]);

        if (posix_getuid() == 0) {
            throw new Exception('cli is not allowed to call as root');
        }

        $this->loadApps();
        return true;
    }


    /**
     * Load apps
     *
     * @return bool
     */
    protected function loadApps(): bool
    {
        foreach ($this->option_apps as $app) {
            $ns = ltrim((string)$app->class, '\\');
            $name = substr($ns, strrpos($ns, '\\') + 1);
            $this->composer->addPsr4($ns.'\\', APPLICATION_PATH."/src/app/$name/src/lib");
            $class = $ns.'\\Cli';

            if (isset($app['enabled']) && $app['enabled'] != "1") {
                $this->logger->debug('skip disabled app ['.$class.']', [
                   'category' => get_class($this)
                ]);
                continue;
            }
            
            if(class_exists($class)) {
                $this->logger->info('inject app ['.$class.']', [
                    'category' => get_class($this)
                ]);

                $app = new $class($this->composer, $app->config, $this->server, $this->logger);

                if (!($app instanceof AppInterface)) {
                    throw new Exception('app '.$class.' is required to implement AppInterface');
                }
            } else {
                $this->logger->debug('app ['.$class.'] does not exists, skip it', [
                    'category' => get_class($this)
                ]);
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

                
            if (file_exists($this->pid_file)) {
                $this->logger->info('remove cli pid file', [
                    'category' => get_class($this),
                ]);
                
                unlink($this->pid_file);
            }
        });

        return $this;
    }
}
