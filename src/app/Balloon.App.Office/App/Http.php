<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office\App;

use Balloon\App\AppInterface;
use Balloon\App\Office\Api\v1\Document;
use Balloon\App\Office\Api\v1\Session;
use Balloon\App\Office\Api\v1\User;
use Balloon\App\Office\Api\v1\Wopi\Document as WopiDocument;
use Balloon\App\Office\App;
use Balloon\App\Office\Exception;
use Balloon\Hook;
use Balloon\Hook\AbstractHook;
use Micro\Auth;
use Micro\Auth\Adapter\None as AuthNone;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http implements AppInterface
{
    /**
     * Lolaflet.
     *
     * @var string
     */
    protected $loleaflet = 'https://localhost:9980/loleaflet/dist/loleaflet.html';

    /**
     * Token ttl.
     *
     * @var int
     */
    protected $token_ttl = 1800;

    /**
     * Constructor.
     *
     * @param Hook
     * @param Router
     * @param iterable $config
     */
    public function __construct(Hook $hook, Router $router, ?Iterable $config = null)
    {
        $this->setOptions($config);

        $hook->injectHook(new class() extends AbstractHook {
            public function preAuthentication(Auth $auth): void
            {
                $skip = [
                    '/index.php/api/v1/app/office/wopi/document',
                    '/index.php/api/v1/app/office/wopi/document/contents',
                ];

                foreach ($skip as $path) {
                    if (preg_match('#^'.$path.'#', $_SERVER['ORIG_SCRIPT_NAME'])) {
                        $auth->injectAdapter('none', (new AuthNone()));

                        break;
                    }
                }
            }
        });

        $router
            ->prependRoute(new Route('/api/v1/app/office/document', Document::class))
            ->prependRoute(new Route('/api/v1/app/office/session', Session::class))
            ->prependRoute(new Route('/api/v1/app/office/wopi/document/{id:#([0-9a-z]{24})#}', WopiDocument::class))
            ->prependRoute(new Route('/api/v1/app/office/wopi/document', WopiDocument::class));
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return AppInterface
     */
    public function setOptions(?Iterable $config = null): AppInterface
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'loleaflet':
                    $this->loleaflet = (string) $value;

                break;
                case 'token_ttl':
                    $this->token_ttl = (int) $value;

                break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * Get loleaflet.
     *
     * @return string
     */
    public function getLoleaflet(): string
    {
        return $this->loleaflet;
    }

    /**
     * Get token ttl.
     *
     * @return int
     */
    public function getTokenTtl(): int
    {
        return $this->token_ttl;
    }
}
