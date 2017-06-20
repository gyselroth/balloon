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

use Balloon\Config;

class Cli extends AbstractCore
{
    /**
     * pid
     *
     * @var string
     */
    protected $pid_file = '/tmp/.balloon_cli.pid';


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

        $this->loadApps();

        if ($this->checkPid()) {
            $this->queuemgr->execute($this->fs);
        }
        
        $this->pluginmgr->run('cli', [$this->fs]);
        return unlink($this->pid_file);
    }


    /**
     * Set options
     *
     * @param  Config $config
     * @return AbstractCore
     */
    public function setOptions(Config $config): AbstractCore
    {
        parent::setOptions($config);
        foreach ($config->children() as $option => $value) {
            switch ($option) {
                case 'pid_file':
                    $this->pid_file = $value;
                    break;
            }
        }
    
        return $this;
    }


    /**
     * Check pid
     *
     * @return bool
     */
    public function checkPid(): bool
    {
        if (posix_getuid() == 0) {
            throw new Exception('cli is not allowed to call as root');
        }

        $pid = getmypid();
        
        if (file_exists($this->pid_file)) {
            throw new Exception("cli still running? pid_file found in {$this->pid_file}");
        } else {
            file_put_contents($this->pid_file, $pid);
        }

        return true;
    }


    /**
     * Load apps
     *
     * @return bool
     */
    protected function loadApps(): bool
    {
        foreach ($this->option_apps->children() as $name => $app) {
            $this->composer->addPsr4("Balloon\\App\\$name\\", APPLICATION_PATH."/src/app/$name/src/lib");
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
