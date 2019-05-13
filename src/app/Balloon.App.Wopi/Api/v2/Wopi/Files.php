<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Wopi\Api\v2\Wopi;

use Balloon\App\Wopi\Exception\UnknownWopiOperation as UnknownWopiOperationException;
use Balloon\App\Wopi\SessionManager;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

class Files
{
    /**
     * WOPI operations.
     */
    const WOPI_GET_LOCK = 'GET_LOCK';
    const WOPI_LOCK = 'LOCK';
    const WOPI_REFRESH_LOCK = 'REFRESH_LOCK';
    const WOPI_UNLOCK = 'UNLOCK';

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Session manager.
     *
     * @var SessionManager
     */
    protected $manager;

    /**
     * Attribute decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     */
    public function __construct(SessionManager $manager, Server $server, AttributeDecorator $decorator, LoggerInterface $logger)
    {
        $this->manager = $manager;
        $this->server = $server;
        $this->decorator = $decorator;
        $this->logger = $logger;
    }

    /**
     * Get document sesssion information.
     */
    public function get(ObjectId $id, string $access_token): Response
    {
        $file = $this->server->getFilesystem()->getNode($id, File::class);
        $session = $this->manager->getByToken($file, $access_token);

        return (new Response())->setCode(200)->setBody($session->getAttributes(), true);
    }

    /**
     * Lock file.
     */
    public function post(ObjectId $id, string $access_token): Response
    {
        $file = $this->server->getFilesystem()->getNode($id, File::class);
        $session = $this->manager->getByToken($file, $access_token);
        $fs = $this->server->getFilesystem($session->getUser());
        $file->setFilesystem($fs);

        $op = $_SERVER['HTTP_X_WOPI_OVERRIDE'] ?? null;
        $identifier = $_SERVER['HTTP_X_WOPI_LOCK'] ?? null;
        $previous = $_SERVER['HTTP_X_WOPI_OLDLOCK'] ?? null;

        $this->logger->info('incoming POST wopi operation [{operation}] width id [{identifier}]'.json_encode($_SERVER), [
            'category' => get_class($this),
            'operation' => $op,
            'identifier' => $identifier,
        ]);

        $response = (new Response())->setCode(200);

        try {
            switch ($op) {
                case self::WOPI_GET_LOCK:
                    $lock = $file->getLock();
                    $response->setHeader('X-WOPI-Lock', $lock['id']);
                    $response->setBody($this->decorator->decorate($file, ['lock'])['lock']);

                break;
                case self::WOPI_LOCK:
                    if ($previous !== null) {
                        $file->unlock($previous);
                    }

                    $file->lock($identifier);
                    $response->setBody($this->decorator->decorate($file, ['lock'])['lock']);

                break;
                case self::WOPI_REFRESH_LOCK:
                    $file->lock($identifier, 1800);

                break;
                case self::WOPI_UNLOCK:
                    $file->unlock($identifier);

                break;
                case null:
                    throw new MissingWopiOperationException('no wopi operation provided');

                break;
                default:
                    throw new UnknownWopiOperationException('unknown wopi operation '.$op);
            }
        } catch (Exception\NotLocked $e) {
            return (new Response())
                ->setCode(200)
                ->setHeader('X-WOPI-Lock', '')
                ->setBody($e);
        } catch (Exception\Locked | Exception\LockIdMismatch | Exception\Forbidden $e) {
            $lock = $file->getLock();

            return (new Response())
                ->setCode(409)
                ->setHeader('X-WOPI-ItemVersion', (string) $file->getVersion())
                ->setHeader('X-WOPI-Lock', $lock['id'])
                ->setHeader('X-WOPI-LockFailureReason', $e->getMessage())
                ->setBody($e);
        }

        return $response;
    }

    /**
     * Save document contents.
     */
    public function postContents(ObjectId $id, string $access_token): Response
    {
        /**
         * X-WOPI-Override – The string PUT. Required.
         * X-WOPI-Lock – A string provided by the WOPI client in a previous Lock request. Note that this header will not be included during document creation.
         *
         *
         * clone:
         * X-WOPI-Override – The string PUT_RELATIVE. Required.
         *
         *
         * rename:.
         * X-WOPI-Override – The string RENAME_FILE. Required.
         * X-WOPI-Lock – A string provided by the WOPI client that the host must use to identify the lock on the file.
         * X-WOPI-RequestedName – A UTF-7 encoded string that is a file name, not including the file extension.
         *
         *delete:
         * X-WOPI-Override – The string DELETE. Required.
         *
         *
         * put user info
         * X-WOPI-Override – The string PUT_USER_INFO. Required.
         */
        $file = $this->server->getFilesystem()->getNode($id, File::class);
        $session = $this->manager->getByToken($file, $access_token);
        $fs = $this->server->getFilesystem($session->getUser());
        $file->setFilesystem($fs);

        ini_set('auto_detect_line_endings', '1');
        $content = fopen('php://input', 'rb');
        $result = $file->put($content, false);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Get document contents.
     */
    public function getContents(ObjectId $id, string $access_token): void
    {
        $file = $this->server->getFilesystem()->getNode($id, File::class);
        $session = $this->manager->getByToken($file, $access_token);
        $stream = $file->get();

        while (!feof($stream)) {
            echo fread($stream, 8192);
        }

        exit();
    }
}
