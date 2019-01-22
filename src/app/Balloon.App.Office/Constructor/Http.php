<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office\Constructor;

use Balloon\App\Office\Api\v1;
use Balloon\App\Office\Api\v2;
use Balloon\Hook;
use Balloon\Hook\AbstractHook;
use InvalidArgumentException;
use Micro\Auth\Adapter\None as AuthNone;
use Micro\Auth\Auth;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http
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
     * WOPI URL.
     *
     * @var string
     */
    protected $wopi_url = 'https://localhost';

    /**
     * Constructor.
     *
     * @param iterable $config
     */
    public function __construct(Hook $hook, Router $router, ?Iterable $config = null)
    {
        $this->setOptions($config);

        $hook->injectHook(new class() extends AbstractHook {
            public function preAuthentication(Auth $auth): void
            {
                $skip = [
                    '/index.php/api/v2/office/wopi/document',
                    '/index.php/api/v2/office/wopi/document/contents',
                    '/index.php/api/v1/app/office/wopi/document',
                    '/index.php/api/v1/app/office/wopi/document/contents',
                ];

                foreach ($skip as $path) {
                    if (preg_match('#^'.$path.'#', $_SERVER['ORIG_SCRIPT_NAME'])) {
                        $auth->injectAdapter(new AuthNone());

                        break;
                    }
                }
            }
        });

        $router
            ->prependRoute(new Route('/api/v1/app/office/document', v1\Document::class))
            ->prependRoute(new Route('/api/v1/app/office/session', v1\Session::class))
            ->prependRoute(new Route('/api/v1/app/office/wopi/document', v1\Wopi\Document::class))
            ->prependRoute(new Route('/api/v1/app/office/wopi/document/{id:#([0-9a-z]{24})#}', v1\Wopi\Document::class))
            ->prependRoute(new Route('/api/v2/office/documents', v2\Documents::class))
            ->prependRoute(new Route('/api/v2/office/sessions', v2\Sessions::class))
            ->prependRoute(new Route('/api/v2/office/wopi/document', v2\Wopi\Document::class))
            ->prependRoute(new Route('/api/v2/office/wopi/document/{id:#([0-9a-z]{24})#}', v2\Wopi\Document::class));
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return Http
     */
    public function setOptions(?Iterable $config = null): self
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'loleaflet':
                case 'wopi_url':
                    $this->{$option} = (string) $value;

                break;
                case 'token_ttl':
                    $this->token_ttl = (int) $value;

                break;
                default:
                    throw new InvalidArgumentException('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * Get WOPI url.
     */
    public function getWopiUrl(): string
    {
        return $this->wopi_url;
    }

    /**
     * Get loleaflet.
     */
    public function getLoleaflet(): string
    {
        return $this->loleaflet;
    }

    /**
     * Get token ttl.
     */
    public function getTokenTtl(): int
    {
        return $this->token_ttl;
    }
}
