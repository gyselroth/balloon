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
use Balloon\App\Wopi\HostManager;
use Balloon\App\Wopi\SessionManager;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

/**
 * Implements the full WOPI protocol.
 *
 * @see https://wopi.readthedocs.io/projects/wopirest/en/latest
 */
class Files
{
    /**
     * WOPI operations.
     */
    const WOPI_GET_LOCK = 'GET_LOCK';
    const WOPI_LOCK = 'LOCK';
    const WOPI_REFRESH_LOCK = 'REFRESH_LOCK';
    const WOPI_UNLOCK = 'UNLOCK';
    const WOPI_PUT = 'PUT';
    const WOPI_PUT_RELATIVE = 'PUT_RELATIVE';
    const WOPI_RENAME_FILE = 'RENAME_FILE';
    const WOPI_DELETE = 'DELETE';
    const WOPI_PUT_USERINFO = 'PUT_USERINFO';

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
    protected $session_manager;

    /**
     * Host manager.
     *
     * @var HostManager
     */
    protected $host_manager;

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
    public function __construct(SessionManager $session_manager, HostManager $host_manager, Server $server, AttributeDecorator $decorator, LoggerInterface $logger)
    {
        $this->session_manager = $session_manager;
        $this->host_manager = $host_manager;
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
        $session = $this->session_manager->getByToken($file, $access_token);

        $this->logger->info('incoming POST wopi operation [{operation}] width id [{identifier}]', [
            'category' => get_class($this),
            'operation' => $session->getAttributes(),
            'test' => $_SERVER,
        ]);

        $this->validateProof();

        return (new Response())->setCode(200)->setBody($session->getAttributes(), true);
    }

    /**
     * Lock file.
     */
    public function post(ObjectId $id, string $access_token): Response
    {
        $file = $this->server->getFilesystem()->getNode($id, File::class);
        $session = $this->session_manager->getByToken($file, $access_token);

        $op = $_SERVER['HTTP_X_WOPI_OVERRIDE'] ?? null;
        $identifier = $_SERVER['HTTP_X_WOPI_LOCK'] ?? null;
        $previous = $_SERVER['HTTP_X_WOPI_OLDLOCK'] ?? null;
        $_SERVER['HTTP_LOCK_TOKEN'] = $identifier;

        $this->logger->info('incoming POST wopi operation [{operation}] width id [{identifier}]', [
            'category' => get_class($this),
            'operation' => $op,
            'test' => $_SERVER,
            'identifier' => $identifier,
            'previous' => $previous,
        ]);

        $this->validateProof();
        $response = (new Response())
            ->setCode(200)
            ->setHeader('X-WOPI-ItemVersion', (string) $file->getVersion());

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
                    if (!$file->isLocked()) {
                        $response->setCode(409)
                            ->setHeader('X-WOPI-Lock', '');

                        return $response;
                    }

                    $file->unlock($identifier);

                break;
                case self::WOPI_RENAME_FILE:
                    return $this->renameFile($file, $response);

                break;
                case self::WOPI_DELETE:
                    $file->delete();

                break;
                case self::WOPI_PUT_RELATIVE:
                    return $this->putRelative($file, $response);

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
        } catch (Exception\Locked | Exception\LockIdMissmatch | Exception\Forbidden $e) {
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
        $op = $_SERVER['HTTP_X_WOPI_OVERRIDE'] ?? null;
        $identifier = $_SERVER['HTTP_X_WOPI_LOCK'] ?? null;
        $previous = $_SERVER['HTTP_X_WOPI_OLDLOCK'] ?? null;
        $_SERVER['HTTP_LOCK_TOKEN'] = $identifier;

        $this->logger->info('incoming POST wopi operation [{operation}] width id [{identifier}]'.json_encode($_SERVER), [
            'category' => get_class($this),
            'operation' => $op,
            'identifier' => $identifier,
        ]);

        $this->validateProof();
        $file = $this->server->getFilesystem()->getNode($id, File::class);
        $session = $this->session_manager->getByToken($file, $access_token);
        $response = (new Response())
            ->setHeader('X-WOPI-ItemVersion', (string) $file->getVersion());

        if (!$file->isLocked() && $file->getSize() > 0) {
            return $response
                ->setCode(409)
                ->setBody(new Exception\NotLocked('file needs to be locked first'));
        }

        try {
            $content = fopen('php://input', 'rb');
            $result = $file->put($content, false);

            return $response->setCode(200)->setBody($result);
        } catch (Exception\Locked | Exception\LockIdMissmatch $e) {
            $lock = $file->getLock();

            return $response
                ->setCode(409)
                ->setHeader('X-WOPI-Lock', $lock['id'])
                ->setHeader('X-WOPI-LockFailureReason', $e->getMessage())
                ->setBody($e);
        }
    }

    /**
     * Get document contents.
     */
    public function getContents(ObjectId $id, string $access_token): Response
    {
        $file = $this->server->getFilesystem()->getNode($id, File::class);
        $session = $this->session_manager->getByToken($file, $access_token);
        $this->validateProof();
        $stream = $file->get();

        $response = (new Response())
            ->setCode(200)
            ->setHeader('X-WOPI-ItemVersion', (string) $file->getVersion())
            ->setBody(function () use ($stream) {
                if ($stream === null) {
                    echo '';

                    return;
                }

                while (!feof($stream)) {
                    echo fread($stream, 8192);
                }
            });

        return $response;
    }

    /**
     * Validate proof.
     */
    protected function validateProof(): bool
    {
        if (isset($_SERVER['HTTP_X_WOPI_PROOF'])) {
            $data = [
                'proof' => $_SERVER['HTTP_X_WOPI_PROOF'],
                'proof-old' => $_SERVER['HTTP_X_WOPI_PROOFOLD'] ?? '',
                'access-token' => $access_token,
                'host-url' => 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
                'timestamp' => $_SERVER['HTTP_X_WOPI_TIMESTAMP'] ?? '',
            ];

            return $this->host_manager->verifyWopiProof($data);
        }

        return false;
    }

    /**
     * Put relative file.
     */
    protected function putRelative(File $file, Response $response): Response
    {
        $suggested = $_SERVER['HTTP_X_WOPI_SUGGESTEDTARGET'] ?? null;
        $relative = $_SERVER['HTTP_X_WOPI_RELATIVETARGET'] ?? null;
        $conversion = $_SERVER['HTTP_X_WOPI_FILECONVERSION'] ?? null;
        $overwrite = (bool) ($_SERVER['HTTP_X_WOPI_OVERWRITERELATIVETARGET'] ?? false);
        $size = $_SERVER['HTTP_X_WOPI_SIZE'] ?? null;

        $parent = $file->getParent();
        $content = fopen('php://input', 'rb');

        $this->logger->debug('wopi PutRelative request', [
            'category' => get_class($this),
            'X-Wopi-SuggestedTarget' => $suggested,
            'X-Wopi-RelativeTarget' => $relative,
            'X-Wopi-OverwriteRelativeTarget' => $overwrite,
            'X-Wopi-FileConversion' => $conversion,
        ]);

        if ($suggested !== null && $relative !== null) {
            return $response
                ->setCode(400)
                ->setBody(new \Balloon\Exception('X-WOPI-RelativeTarget and X-WOPI-SuggestedTarget must not both be present'));
        }
        if ($suggested !== null) {
            if ($suggested[0] === '.') {
                $suggested = $file->getName().$suggested;
            }

            try {
                $name = $suggested;
                $new = $parent->addFile($name);
                $new->put($content, false);
            } catch (Exception\Conflict $e) {
                $name = $parent->getDuplicateName($name);
                $new = $parent->addFile($name);
                $new->put($content, false);
            }
        } elseif ($relative !== null) {
            try {
                $new = $parent->addFile($relative);
                $new->put($content, false);
            } catch (Exception\Conflict $e) {
                if ($e->getCode() === Exception\Conflict::NODE_WITH_SAME_NAME_ALREADY_EXISTS && $overwrite === true) {
                    $new = $parent->getChild($relative);
                    $new->put($content, false);
                } else {
                    return $response
                        ->setCode(409)
                        ->setBody($e);
                }
            }
        } else {
            return $response
                ->setCode(400)
                ->setBody(new \Balloon\Exception('One of X-WOPI-RelativeTarget or X-WOPI-SuggestedTarget must be present'));
        }

        $session = $this->session_manager->create($new, $this->server->getUserById($new->getOwner()));

        $response->setBody([
            'Name' => $new->getName(),
            'Url' => $session->getWopiUrl(),
        ]);

        return $response;
    }

    /**
     * Rename file.
     */
    protected function renameFile(File $file, Response $response): Response
    {
        $name = $_SERVER['HTTP_X_WOPI_REQUESTEDNAME'] ?? null;

        try {
            $ext = $file->getExtension();
            $full = $name.'.'.$ext;
        } catch (\Exception $e) {
        }

        try {
            $file->setName($full);
        } catch (Exception\Conflict $e) {
            return (new Response())
                ->setCode(400)
                ->setHeader('X-WOPI-InvalidFileNameError', (string) $e->getMessage())
                ->setBody($e);
        }

        $response->setBody([
            'Name' => $name,
        ]);

        return $response;
    }
}
