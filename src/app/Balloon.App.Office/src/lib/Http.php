<?php
/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office;

use \Micro\Http\Router\Route;
use \Balloon\App\AbstractApp;
use \Balloon\App\AppInterface;
use \Balloon\App\Office\Hook;
use \Balloon\App\Office\Api\v1\Document;
use \Balloon\App\Office\Api\v1\Session;
use \Balloon\App\Office\Api\v1\User;
use \Balloon\App\Office\Api\v1\Wopi\Document as WopiDocument;
use \Micro\Auth;
use \Micro\Auth\Adapter\None as AuthNone;
use \Balloon\Hook\AbstractHook;

class Http extends AbstractApp
{
    /**
     * Lolaflet
     *
     * @var string
     */
    protected $loleaflet = 'https://localhost:9980/loleaflet/dist/loleaflet.html';


    /**
     * Token ttl
     *
     * @var int
     */ 
    protected $token_ttl = 1800;

   
    /**
     * Set options
     *
     * @param  Iterable $config
     * @return AppInterface
     */
    public function setOptions(?Iterable $config=null): AppInterface
    {
        if($config === null) {
            return $this;
        }
        
        foreach($config as $option => $value) {
            switch($option) {
                case 'loleaflet':              
                    $this->loleaflet = (string)$value; 
                break;
                
                case 'token_ttl':              
                    $this->token_ttl = (int)$value; 
                break;
            }
        }
            
        return $this;
    }

    
    /**
     * Get loleaflet
     *
     * @return string
     */
    public function getLoleaflet(): string
    {
        return $this->loleaflet;
    }
    

    /**
     * Get token ttl
     *
     * @return int
     */
    public function getTokenTtl(): int
    {
        return $this->token_ttl;
    }


    /**
     * Init
     *
     * @return bool
     */
    public function init(): bool
    {
        $this->server->getHook()->injectHook(new class($this->logger) extends AbstractHook {
            public function preAuthentication(Auth $auth): void
            {
                $skip = [
                    '/index.php/api/v1/app/office/wopi/document',
                    '/index.php/api/v1/app/office/wopi/document/contents'
                ];

                foreach ($skip as $path) {
                    if (preg_match('#^'.$path.'#', $_SERVER["ORIG_SCRIPT_NAME"])) {
                        $auth->injectAdapter('none' ,(new AuthNone($this->logger)) );
                        break;
                    }
                }
            }
        });

        $this->router->prependRoute(new Route('/api/v1/app/office', $this, 'start'));
        return true;
    }


    /**
     * Start
     *
     * @return bool
     */
    public function start(): bool
    {
        $this->router
            ->clearRoutingTable()
            ->appendRoute(new Route('/api/v1/app/office/document', Document::class))
            ->appendRoute(new Route('/api/v1/app/office/user', User::class))
            ->appendRoute(new Route('/api/v1/app/office/session', Session::class))
            ->appendRoute(new Route('/api/v1/app/office/wopi/document/{id:#([0-9a-z]{24})#}', WopiDocument::class))
            ->appendRoute(new Route('/api/v1/app/office/wopi/document', WopiDocument::class))
            ->run([$this->server, $this->logger]);

        return true;
    }
}
