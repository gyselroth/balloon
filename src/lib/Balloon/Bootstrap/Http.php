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

use \Micro\Http\Router;
use \Micro\Http\Response;
use \Balloon\User;
use \Balloon\Auth;
use \Balloon\Auth\Adapter\None as AuthNone;
use \Balloon\App\AppInterface;
use \Micro\Config;

class Http extends AbstractCore
{
    /**
     * option: auth
     *
     * @var Config
     */
    protected $option_auth;


    /**
     * Init bootstrap
     *
     * @return bool
     */
    public function init(): bool
    {
        parent::init();
        $this->setExceptionHandler();

        $this->logger->info('processing incoming http ['. $_SERVER['REQUEST_METHOD'].'] request to ['.$_SERVER['REQUEST_URI'].']', [
            'category' => get_class($this),
        ]);

        $this->router = new Router($_SERVER, $this->logger);
        $this->auth = new Auth($this->logger, $this->pluginmgr, $this->db);

        $this->loadApps();
        $this->auth->requireOne($this->option_auth);
        $this->user = new User($this->auth, $this->logger, $this->fs, true, false);

        if (!($this->auth->getAdapter() instanceof AuthNone)) {
            $this->fs->setUser($this->user);
        }

        $this->router->run();

        return true;
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
                case 'auth':
                    if (isset($value['adapter'])) {
                        $this->option_auth = $value->adapter;
                    }
                    break;
            }
        }
    
        return $this;
    }



    /**
     * Load apps
     *
     * @return bool
     */
    protected function loadApps(): bool
    {
        foreach ($this->option_apps as $app) {
            $ns = (string)$app->class;
            $name = substr($ns, strrpos($ns, '\\') + 1);
            $this->composer->addPsr4($ns.'\\', APPLICATION_PATH."/src/app/$name/src/lib");
            #$this->composer->addPsr4('Balloon\\App\\Elasticsearch\\', APPLICATION_PATH."/src/app/Elasticsearch/src/lib");
            


##var_dump($name);
#var_dump($ns.'\\');
#var_dump(APPLICATION_PATH."/src/app/$name/src/lib");
            $class = $ns.'\\Init';
#var_dump($class);

            if (isset($app['enabled']) && $app['enabled'] != "1") {
                $this->logger->debug('skip disabled app ['.$class.']', [
                   'category' => get_class($this)
                ]);
                continue;
            }
            
            $this->logger->info('inject app ['.$class.']', [
                'category' => get_class($this)
            ]);

            $app = new $class($this->composer, $this->config, $this->router, $this->logger, $this->fs, $this->auth);

            if (!($app instanceof AppInterface)) {
                throw new Exception('app '.$class.' is required to implement AppInterface');
            }
        }

        return true;
    }


    /**
     * Set exception handler
     *
     * @return Http
     */
    protected function setExceptionHandler(): Http
    {
        set_exception_handler(function ($e) {
            $this->logger->emergency('uncaught exception: '.$e->getMessage(), [
                'category' => get_class($this),
                'exception' => $e
            ]);

            (new Response)
                ->setCode(500)
                ->setBody([
                    'error'   => get_class($e),
                    'message' => $e->getMessage()
                ])
                ->send();
        });

        return $this;
    }
}
