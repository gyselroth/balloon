<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

use Balloon\Filesystem;
use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Storage;
use Balloon\Hook;
use Balloon\Mime;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;
use Sabre\DAV\IFile;

class File extends AbstractNode implements IFile
{
    /**
     * History types.
     */
    const HISTORY_CREATE = 0;
    const HISTORY_EDIT = 1;
    const HISTORY_RESTORE = 2;
    const HISTORY_DELETE = 3;
    const HISTORY_UNDELETE = 4;

    /**
     * Empty content hash (NULL).
     */
    const EMPTY_CONTENT = 'd41d8cd98f00b204e9800998ecf8427e';

    /**
     * Temporary file patterns.
     *
     * @param array
     **/
    protected $temp_files = [
        '/^\._(.*)$/',     // OS/X resource forks
        '/^.DS_Store$/',   // OS/X custom folder settings
        '/^desktop.ini$/', // Windows custom folder settings
        '/^Thumbs.db$/',   // Windows thumbnail cache
        '/^.(.*).swpx$/',  // ViM temporary files
        '/^.(.*).swx$/',   // ViM temporary files
        '/^.(.*).swp$/',   // ViM temporary files
        '/^\.dat(.*)$/',   // Smultron seems to create these
        '/^~lock.(.*)#$/', // Windows 7 lockfiles
    ];

    /**
     * MD5 Hash of the content.
     *
     * @var string
     */
    protected $hash;

    /**
     * File version.
     *
     * @var int
     */
    protected $version = 0;

    /**
     * File size.
     *
     * @var int
     */
    protected $size = 0;

    /**
     * History.
     *
     * @var array
     */
    protected $history = [];

    /**
     * Storage.
     *
     * @var Storage
     */
    protected $_storage;

    /**
     * Storage attributes.
     *
     * @var mixed
     */
    protected $storage;

    /**
     * Initialize file node.
     */
    public function __construct(array $attributes, Filesystem $fs, LoggerInterface $logger, Hook $hook, Acl $acl, Storage $storage)
    {
        $this->_fs = $fs;
        $this->_server = $fs->getServer();
        $this->_db = $fs->getDatabase();
        $this->_user = $fs->getUser();
        $this->_logger = $logger;
        $this->_hook = $hook;
        $this->_storage = $storage;
        $this->_acl = $acl;

        foreach ($attributes as $attr => $value) {
            $this->{$attr} = $value;
        }

        $this->raw_attributes = $attributes;
    }

    /**
     * Read content and return ressource.
     *
     * @return resource
     */
    public function get()
    {
        try {
            if (null === $this->storage) {
                return null;
            }

            return $this->_storage->getFile($this, $this->storage);
        } catch (\Exception $e) {
            throw new Exception\NotFound(
                'content not found',
                Exception\NotFound::CONTENTS_NOT_FOUND
            );
        }
    }

    /**
     * Copy node.
     *
     * @param string $recursion
     */
    public function copyTo(Collection $parent, int $conflict = NodeInterface::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true): NodeInterface
    {
        $this->_hook->run(
            'preCopyFile',
            [$this, $parent, &$conflict, &$recursion, &$recursion_first]
        );

        if (NodeInterface::CONFLICT_RENAME === $conflict && $parent->childExists($this->name)) {
            $name = $this->getDuplicateName();
        } else {
            $name = $this->name;
        }

        if (NodeInterface::CONFLICT_MERGE === $conflict && $parent->childExists($this->name)) {
            $result = $parent->getChild($this->name);
            $result->put($this->get());
        } else {
            $result = $parent->addFile($name, $this->get(), [
                'created' => $this->created,
                'changed' => $this->changed,
                'deleted' => $this->deleted,
                'meta' => $this->meta,
            ], NodeInterface::CONFLICT_NOACTION, true);
        }

        $this->_hook->run(
            'postCopyFile',
            [$this, $parent, $result, $conflict, $recursion, $recursion_first]
        );

        return $result;
    }

    /**
     * Get history.
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Restore content to some older version.
     */
    public function restore(int $version): bool
    {
        if (!$this->_acl->isAllowed($this, 'w')) {
            throw new ForbiddenException(
                'not allowed to restore node '.$this->name,
                ForbiddenException::NOT_ALLOWED_TO_RESTORE
            );
        }

        $this->_hook->run('preRestoreFile', [$this, &$version]);

        if ($this->readonly) {
            throw new Exception\Conflict(
                'node is marked as readonly, it is not possible to change any content',
                Exception\Conflict::READONLY
            );
        }

        if ($this->version === $version) {
            throw new Exception('file is already version '.$version);
        }

        $v = array_search($version, array_column($this->history, 'version'), true);
        if (null === $v) {
            throw new Exception('failed restore file to version '.$version.', version was not found');
        }

        $file = $this->history[$v]['storage'];
        /*$exists = [];

        if (null !== $file) {
            try {
                $exists = $this->_storage->getFileMeta($this, $this->history[$v]['storage']);
            } catch (\Exception $e) {
                throw new Exception('could not restore to version '.$version.', version content does not exists');
            }
        }*/

        $current = $this->version;
        $new = $this->increaseVersion();

        $this->history[] = [
            'version' => $new,
            'changed' => $this->changed,
            'user' => $this->owner,
            'type' => self::HISTORY_RESTORE,
            'hash' => $this->history[$v]['hash'],
            'origin' => $this->history[$v]['version'],
            'storage' => $this->history[$v]['storage'],
            'storage_adapter' => $this->history[$v]['storage_adapter'],
            'size' => $this->history[$v]['size'],
            'mime' => isset($this->history[$v]['mime']) ? $this->history[$v]['mime'] : null,
        ];

        try {
            $this->deleted = false;
            $this->version = $new;
            $this->storage = $this->history[$v]['storage'];
            $this->storage_adapter = $this->history[$v]['storage_adapter'];

            $this->hash = null === $file ? self::EMPTY_CONTENT : $this->history[$v]['hash'];
            $this->mime = isset($this->history[$v]['mime']) ? $this->history[$v]['mime'] : null;
            $this->size = $this->history[$v]['size'];
            $this->changed = $this->history[$v]['changed'];

            $this->save([
                'deleted',
                'version',
                'storage',
                'storage_adapter',
                'hash',
                'mime',
                'size',
                'history',
                'changed',
            ]);

            $this->_hook->run('postRestoreFile', [$this, &$version]);

            $this->_logger->info('restored file ['.$this->_id.'] to version ['.$version.']', [
                'category' => get_class($this),
            ]);
        } catch (\Exception $e) {
            $this->_logger->error('failed restore file ['.$this->_id.'] to version ['.$version.']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }

        return true;
    }

    /**
     * Delete node.
     *
     * Actually the node will not be deleted (Just set a delete flag), set $force=true to
     * delete finally
     *
     * @param string $recursion
     */
    public function delete(bool $force = false, ?string $recursion = null, bool $recursion_first = true): bool
    {
        if (!$this->_acl->isAllowed($this, 'w')) {
            throw new ForbiddenException(
                'not allowed to delete node '.$this->name,
                ForbiddenException::NOT_ALLOWED_TO_DELETE
            );
        }

        $this->_hook->run('preDeleteFile', [$this, &$force, &$recursion, &$recursion_first]);

        if (true === $force || $this->isTemporaryFile()) {
            $result = $this->_forceDelete();
            $this->_hook->run('postDeleteFile', [$this, $force, $recursion, $recursion_first]);

            return $result;
        }

        $ts = new UTCDateTime();
        $this->deleted = $ts;
        $this->increaseVersion();

        $this->history[] = [
            'version' => $this->version,
            'changed' => $ts,
            'user' => ($this->_user === null) ? null : $this->_user->getId(),
            'type' => self::HISTORY_DELETE,
            'storage' => $this->storage,
            'storage_adapter' => $this->storage_adapter,
            'size' => $this->size,
            'hash' => $this->hash,
        ];

        $result = $this->save([
            'version',
            'deleted',
            'history',
        ], [], $recursion, $recursion_first);

        $this->_hook->run('postDeleteFile', [$this, $force, $recursion, $recursion_first]);

        return $result;
    }

    /**
     * Check if file is temporary.
     *
     **/
    public function isTemporaryFile(): bool
    {
        foreach ($this->temp_files as $pattern) {
            if (preg_match($pattern, $this->name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete version.
     */
    public function deleteVersion(int $version): bool
    {
        $key = array_search($version, array_column($this->history, 'version'), true);

        if (false === $key) {
            throw new Exception('version '.$version.' does not exists');
        }

        try {
            if ($this->history[$key]['storage'] !== null) {
                $this->_storage->deleteFile($this, $this->history[$key]['storage']);
            }

            array_splice($this->history, $key, 1);

            $this->_logger->debug('removed version ['.$version.'] from file ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            return $this->save('history');
        } catch (\Exception $e) {
            $this->_logger->error('failed remove version ['.$version.'] from file ['.$this->_id.']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Cleanup history.
     */
    public function cleanHistory(): bool
    {
        foreach ($this->history as $node) {
            $this->deleteVersion($node['version']);
        }

        return true;
    }

    /**
     * Get Attributes.
     */
    public function getAttributes(): array
    {
        return [
            '_id' => $this->_id,
            'name' => $this->name,
            'hash' => $this->hash,
            'directory' => false,
            'size' => $this->size,
            'version' => $this->version,
            'parent' => $this->parent,
            'acl' => $this->acl,
            'app' => $this->app,
            'meta' => $this->meta,
            'mime' => $this->mime,
            'owner' => $this->owner,
            'history' => $this->history,
            'shared' => $this->shared,
            'deleted' => $this->deleted,
            'changed' => $this->changed,
            'created' => $this->created,
            'destroy' => $this->destroy,
            'readonly' => $this->readonly,
            'storage_adapter' => $this->storage_adapter,
            'storage' => $this->storage,
        ];
    }

    /**
     * Get filename extension.
     */
    public function getExtension(): string
    {
        $ext = strrchr($this->name, '.');
        if (false === $ext) {
            throw new Exception('file does not have an extension');
        }

        return substr($ext, 1);
    }

    /**
     * Get file size.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get md5 sum of the file content,
     * actually the hash value comes from the database.
     */
    public function getETag(): string
    {
        return "'".$this->hash."'";
    }

    /**
     * Get hash.
     *
     * @return string
     */
    public function getHash(): ?string
    {
        return $this->hash;
    }

    /**
     * Get version.
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Change content.
     *
     * @param resource|string $file
     */
    public function put($file, bool $new = false, array $attributes = []): int
    {
        $this->_logger->debug('add contents for file ['.$this->_id.']', [
            'category' => get_class($this),
        ]);

        $this->validatePutRequest($file, $new, $attributes);
        $file = $this->createTemporaryFile($file, $stream);
        $new_hash = $this->verifyFile($file, $new);

        if ($this->hash === $new_hash) {
            $this->_logger->info('stop PUT execution, content checksums are equal for file ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            //Remove tmp file
            if (null !== $file) {
                unlink($file);
                fclose($stream);
            }

            return $this->version;
        }

        $this->hash = $new_hash;
        $max = (int) (string) $this->_fs->getServer()->getMaxFileVersion();
        if (count($this->history) >= $max) {
            $del = key($this->history);
            $this->_logger->debug('history limit ['.$max.'] reached, remove oldest version ['.$del.'] from file ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            $this->deleteVersion($this->history[$del]['version']);
        }

        //Write new content
        if ($this->size > 0) {
            $this->storage = $this->_storage->storeFile($this, $stream, $this->storage_adapter);
        } else {
            $this->storage = null;
        }

        //Update current version
        $this->increaseVersion();

        //Get meta attributes
        if (isset($attributes['mime'])) {
            $this->mime = $attributes['mime'];
        } elseif (null !== $file) {
            $this->mime = (new Mime())->getMime($file, $this->name);
        }

        //Remove tmp file
        if (null !== $file) {
            unlink($file);
            fclose($stream);
        }

        $this->_logger->debug('set mime ['.$this->mime.'] for content, file=['.$this->_id.']', [
            'category' => get_class($this),
        ]);

        $this->addVersion($attributes)
             ->postPutFile($file, $new, $attributes);

        return $this->version;
    }

    /**
     * Completly remove file.
     */
    protected function _forceDelete(): bool
    {
        try {
            $this->cleanHistory();
            $this->_db->storage->deleteOne([
                '_id' => $this->_id,
            ]);

            $this->_logger->info('removed file node ['.$this->_id.']', [
                'category' => get_class($this),
            ]);
        } catch (\Exception $e) {
            $this->_logger->error('failed delete file node ['.$this->_id.']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }

        return true;
    }

    /**
     * Increase version.
     */
    protected function increaseVersion(): int
    {
        ++$this->version;

        return $this->version;
    }

    /**
     * Create uuidv4.
     */
    protected function guidv4(string $data): string
    {
        assert(16 === strlen($data));

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Change content.
     *
     * @param resource|string $file
     */
    protected function validatePutRequest($file, bool $new = false, array $attributes = []): bool
    {
        if (!$this->_acl->isAllowed($this, 'w')) {
            throw new ForbiddenException(
                'not allowed to modify node',
                ForbiddenException::NOT_ALLOWED_TO_MODIFY
            );
        }

        $this->_hook->run('prePutFile', [$this, &$file, &$new, &$attributes]);

        if ($this->readonly) {
            throw new Exception\Conflict(
                'node is marked as readonly, it is not possible to change any content',
                Exception\Conflict::READONLY
            );
        }

        if ($this->isShareMember() && false === $new && 'w' === $this->_acl->getAclPrivilege($this->getShareNode())) {
            throw new ForbiddenException(
                'not allowed to overwrite node',
                ForbiddenException::NOT_ALLOWED_TO_OVERWRITE
            );
        }

        return true;
    }

    /**
     * Verify content to be added.
     *
     * @param string $path
     *
     * @return bool
     */
    protected function verifyFile(?string $path, bool $new = false): string
    {
        if (null === $path) {
            $this->size = 0;
            $new_hash = self::EMPTY_CONTENT;
        } else {
            $size = filesize($path);
            $this->size = $size;
            $new_hash = md5_file($path);

            if (!$this->_user->checkQuota($size)) {
                $this->_logger->warning('could not execute PUT, user quota is full', [
                    'category' => get_class($this),
                ]);

                if (true === $new) {
                    $this->_forceDelete();
                }

                throw new Exception\InsufficientStorage(
                    'user quota is full',
                    Exception\InsufficientStorage::USER_QUOTA_FULL
                );
            }
        }

        return $new_hash;
    }

    /**
     * Create temporary file.
     *
     * @param resource|string $file
     * @param resource        $stream
     *
     * @return string
     */
    protected function createTemporaryFile($file, &$stream): ?string
    {
        if (is_string($file)) {
            if (!is_readable($file)) {
                throw new Exception('file does not exists or is not readable');
            }

            $stream = fopen($file, 'r');
        } elseif (is_resource($file)) {
            $tmp = $this->_fs->getServer()->getTempDir().DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR.$this->_user->getId();
            if (!file_exists($tmp)) {
                mkdir($tmp, 0700, true);
            }

            $tmp_file = $tmp.DIRECTORY_SEPARATOR.$this->guidv4(openssl_random_pseudo_bytes(16));
            $stream = fopen($tmp_file, 'w+');
            $size = stream_copy_to_stream($file, $stream, ((int) $this->_fs->getServer()->getMaxFileSize() + 1));
            rewind($stream);
            fclose($file);

            if ($size > (int) $this->_fs->getServer()->getMaxFileSize()) {
                unlink($tmp_file);

                throw new Exception\InsufficientStorage(
                    'file size exceeded limit',
                    Exception\InsufficientStorage::FILE_SIZE_LIMIT
                );
            }

            $file = $tmp_file;
        } else {
            $file = null;
        }

        return $file;
    }

    /**
     * Add new version.
     *
     *
     * @return File
     */
    protected function addVersion(array $attributes = []): self
    {
        if (1 !== $this->version) {
            if (isset($attributes['changed'])) {
                if (!($attributes['changed'] instanceof UTCDateTime)) {
                    throw new Exception\InvalidArgument('attribute changed must be an instance of UTCDateTime');
                }

                $this->changed = $attributes['changed'];
            } else {
                $this->changed = new UTCDateTime();
            }

            $this->_logger->debug('added new history version ['.$this->version.'] for file ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            $this->history[] = [
                'version' => $this->version,
                'changed' => $this->changed,
                'user' => $this->_user->getId(),
                'type' => self::HISTORY_EDIT,
                'storage' => $this->storage,
                'storage_adapter' => $this->storage_adapter,
                'size' => $this->size,
                'mime' => $this->mime,
                'hash' => $this->hash,
            ];
        } else {
            $this->_logger->debug('added first file version [1] for file ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            $this->history[0] = [
                'version' => 1,
                'changed' => isset($attributes['changed']) ? $attributes['changed'] : new UTCDateTime(),
                'user' => $this->owner,
                'type' => self::HISTORY_CREATE,
                'storage' => $this->storage,
                'storage_adapter' => $this->storage_adapter,
                'size' => $this->size,
                'mime' => $this->mime,
                'hash' => $this->hash,
            ];
        }

        return $this;
    }

    /**
     * Finalize put request.
     *
     * @param resource|string $file
     *
     * @return File
     */
    protected function postPutFile($file, bool $new, array $attributes): self
    {
        try {
            $this->save([
                'size',
                'changed',
                'mime',
                'hash',
                'version',
                'history',
                'storage',
                'storage_adapter',
            ]);

            $this->_logger->debug('modifed file metadata ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            $this->_hook->run('postPutFile', [$this, $file, $new, $attributes]);

            return $this;
        } catch (\Exception $e) {
            $this->_logger->error('failed modify file metadata ['.$this->_id.']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
