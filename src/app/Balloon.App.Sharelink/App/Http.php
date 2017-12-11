<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Sharelink\App;

use Balloon\App\AppInterface;
use Balloon\App\Sharelink\Api\v1\ShareLink;
use Balloon\App\Sharelink\Sharelink as Share;
use Balloon\Exception;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\Collection;
use Balloon\Hook;
use Balloon\Hook\AbstractHook;
use Micro\Auth\Adapter\None as AuthNone;
use Micro\Auth\Auth;
use Micro\Http\Response;
use Micro\Http\Router;
use Micro\Http\Router\Route;
use Psr\Log\LoggerInterface;

class Http implements AppInterface
{
    /**
     * Sharelink.
     *
     * @var Share
     */
    protected $sharelink;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Init.
     *
     * @param Router             $router
     * @param Hook               $hook
     * @param Share              $sharelink
     * @param AttributeDecorator $decorator
     * @param LoggerInterface    $logger
     */
    public function __construct(Router $router, Hook $hook, Share $sharelink, AttributeDecorator $decorator, LoggerInterface $logger)
    {
        $router
            ->appendRoute(new Route('/share', $this, 'start'))
            ->prependRoute(new Route('/api/v1/(node|file|collection)/share-link', ShareLink::class))
            ->prependRoute(new Route('/api/v1/(node|file|collection)/{id:#([0-9a-z]{24})#}/share-link', ShareLink::class));

        $hook->injectHook(new class() extends AbstractHook {
            public function preAuthentication(Auth $auth): void
            {
                if (preg_match('#^/index.php/share#', $_SERVER['ORIG_SCRIPT_NAME'])) {
                    $auth->injectAdapter(new AuthNone());
                }
            }
        });

        $decorator->addDecorator('sharelink', function ($node, $attributes) use ($sharelink) {
            return $sharelink->isSharelink($node);
        });

        $this->logger = $logger;
        $this->sharelink = $sharelink;
    }

    /**
     * Start.
     *
     * @return bool
     */
    public function start(): bool
    {
        if (isset($_GET['t']) && !empty($_GET['t'])) {
            $token = $_GET['t'];
            if (isset($_GET['download'])) {
                $download = (bool) $_GET['download'];
            } else {
                $download = false;
            }

            try {
                $node = $this->sharelink->findNodeWithShareToken($token);
                $share = $node->getAppAttributes('Balloon\\App\\Sharelink');

                if (array_key_exists('password', $share)) {
                    $valid = false;
                    if (isset($_POST['password'])) {
                        $valid = hash('sha256', $_POST['password']) === $share['password'];
                    }

                    if (false === $valid) {
                        echo "<form method=\"post\">\n";
                        echo    "Password: <input type=\"password\" name=\"password\"/>\n";
                        echo    "<input type=\"submit\" value=\"Submit\"/>\n";
                        echo "</form>\n";
                        exit();
                    }
                }

                if ($node instanceof Collection) {
                    $mime = 'application/zip';
                    $stream = $node->getZip();
                    $name = $node->getName().'.zip';
                } else {
                    $mime = $node->getContentType();
                    $stream = $node->get();
                    $name = $node->getName();
                }

                if (true === $download || preg_match('#html#', $mime)) {
                    header('Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode($name));
                    header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
                    header('Content-Type: application/octet-stream');
                    header('Content-Length: '.$node->getSize());
                    header('Content-Transfer-Encoding: binary');
                } else {
                    header('Content-Disposition: inline; filename="'.$name.'"');
                    header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
                    header('Content-Type: '.$mime);
                }

                if (null === $stream) {
                    exit();
                }

                while (!feof($stream)) {
                    echo fread($stream, 8192);
                }
            } catch (\Exception $e) {
                $this->logger->error("failed load node with access token [$token]", [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                (new Response())
                    ->setOutputFormat('text')
                    ->setCode(404)
                    ->setBody('Token is invalid or share link is expired')
                    ->send();
            }
        } else {
            (new Response())
                ->setOutputFormat('text')
                ->setCode(401)
                ->setBody('No token submited')
                ->send();
        }

        return true;
    }
}
