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
use Balloon\Filesystem\Acl\Exception as AclException;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Storage\Adapter\AdapterInterface as StorageInterface;
use Balloon\Filesystem\Storage\Exception as StorageException;
use Balloon\Hook;
use MimeType\MimeType;
use MongoDB\BSON\ObjectId;
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
     * Initialize file node.
     */
    public function __construct(array $attributes, Filesystem $fs, LoggerInterface $logger, Hook $hook, Acl $acl, StorageInterface $storage)
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
        if (null === $this->storage) {
            return null;
        }

        try {
            return $this->_storage->openReadStream($this);
        } catch (\Exception $e) {
            throw new Exception\NotFound(
                'storage blob is gone',
                Exception\NotFound::CONTENTS_NOT_FOUND,
                $e
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
            $session = $parent->temporarySession($this->get());
            $result = $parent->addFile($name, $session, [
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
            throw new AclException\Forbidden(
                'not allowed to restore node '.$this->name,
                AclException\Forbidden::NOT_ALLOWED_TO_RESTORE
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

        $current = $this->version;
        $new = $this->increaseVersion();

        $v = array_search($version, array_column($this->history, 'version'), true);
        if (false === $v) {
            throw new Exception('failed restore file to version '.$version.', version was not found');
        }

        $file = $this->history[$v]['storage'];

        $this->history[] = [
            'version' => $new,
            'changed' => $this->changed,
            'user' => $this->owner,
            'type' => self::HISTORY_RESTORE,
            'hash' => $this->history[$v]['hash'],
            'origin' => $this->history[$v]['version'],
            'storage' => $this->history[$v]['storage'],
            'storage_reference' => $this->history[$v]['storage_reference'],
            'size' => $this->history[$v]['size'],
            'mime' => isset($this->history[$v]['mime']) ? $this->history[$v]['mime'] : null,
        ];

        try {
            $this->deleted = false;
            $this->version = $new;
            $this->storage = $this->history[$v]['storage'];
            $this->storage_reference = $this->history[$v]['storage_reference'];

            $this->hash = null === $file ? self::EMPTY_CONTENT : $this->history[$v]['hash'];
            $this->mime = isset($this->history[$v]['mime']) ? $this->history[$v]['mime'] : null;
            $this->size = $this->history[$v]['size'];
            $this->changed = $this->history[$v]['changed'];

            $this->save([
                'deleted',
                'version',
                'storage',
                'storage_reference',
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
            throw new AclException\Forbidden(
                'not allowed to delete node '.$this->name,
                AclException\Forbidden::NOT_ALLOWED_TO_DELETE
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
            'storage_reference' => $this->storage_reference,
            'size' => $this->size,
            'hash' => $this->hash,
        ];

        $this->storage = $this->_storage->deleteFile($this);

        $result = $this->save([
            'version',
            'storage',
            'deleted',
            'history',
        ], [], $recursion, $recursion_first);

        $this->_hook->run('postDeleteFile', [$this, $force, $recursion, $recursion_first]);

        return $result;
    }

    /**
     * Check if file is temporary.
     */
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

        $blobs = array_column($this->history, 'storage');

        try {
            //do not remove blob if there are other versions linked against it
            if ($this->history[$key]['storage'] !== null && count(array_keys($blobs, $this->history[$key]['storage'])) === 1) {
                $this->_storage->forceDeleteFile($this, $version);
            }

            array_splice($this->history, $key, 1);

            $this->_logger->debug('removed version ['.$version.'] from file ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            return $this->save('history');
        } catch (StorageException\BlobNotFound $e) {
            $this->_logger->error('failed remove version ['.$version.'] from file ['.$this->_id.']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            return false;
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
            'storage_reference' => $this->storage_reference,
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
     * Change content (Sabe dav compatible method).
     */
    public function put($content): int
    {
        $this->_logger->debug('write new file content into temporary storage for file ['.$this->_id.']', [
            'category' => get_class($this),
        ]);

        $session = $this->_storage->storeTemporaryFile($content, $this->_user);

        return $this->setContent($session);
    }

    /**
     * Set content (temporary file).
     */
    public function setContent(ObjectId $session, array $attributes = []): int
    {
        $this->_logger->debug('set temporary file ['.$session.'] as file content for ['.$this->_id.']', [
            'category' => get_class($this),
        ]);

        $this->prePutFile($session);
        $result = $this->_storage->storeFile($this, $session);
        $this->storage = $result['reference'];
        $this->hash = $result['hash'];
        $this->size = $result['size'];

        if ($this->size === 0 && $this->getMount() === null) {
            $this->storage = null;
        } else {
            $this->storage = $result['reference'];
        }

        $this->mime = MimeType::getType($this->name);
        $this->increaseVersion();

        $this->addVersion($attributes)
             ->postPutFile();

        return $this->version;
    }

    /**
     * Completly remove file.
     */
    protected function _forceDelete(): bool
    {
        try {
            $this->_storage->forceDeleteFile($this);
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
        $max = $this->_fs->getServer()->getMaxFileVersion();
        if (count($this->history) >= $max) {
            $del = key($this->history);
            $this->_logger->debug('history limit ['.$max.'] reached, remove oldest version ['.$del.'] from file ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            $this->deleteVersion($this->history[$del]['version']);
        }

        ++$this->version;

        return $this->version;
    }

    /**
     * Pre content change checks.
     */
    protected function prePutFile(ObjectId $session): bool
    {
        if (!$this->_acl->isAllowed($this, 'w')) {
            throw new AclException\Forbidden(
                'not allowed to modify node',
                AclException\Forbidden::NOT_ALLOWED_TO_MODIFY
            );
        }

        $this->_hook->run('prePutFile', [$this, &$session]);

        if ($this->readonly) {
            throw new Exception\Conflict(
                'node is marked as readonly, it is not possible to change any content',
                Exception\Conflict::READONLY
            );
        }

        return true;
    }

    /**
     * Add new version.
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
                'storage_reference' => $this->storage_reference,
                'size' => $this->size,
                'mime' => $this->mime,
                'hash' => $this->hash,
            ];

            return $this;
        }

        $this->_logger->debug('added first file version [1] for file ['.$this->_id.']', [
            'category' => get_class($this),
        ]);

        $this->history[0] = [
            'version' => 1,
            'changed' => isset($attributes['changed']) ? $attributes['changed'] : new UTCDateTime(),
            'user' => $this->owner,
            'type' => self::HISTORY_CREATE,
            'storage' => $this->storage,
            'storage_reference' => $this->storage_reference,
            'size' => $this->size,
            'mime' => $this->mime,
            'hash' => $this->hash,
        ];

        return $this;
    }

    /**
     * Finalize put request.
     */
    protected function postPutFile(): self
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
                'storage_reference',
            ]);

            $this->_logger->debug('modifed file metadata ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            $this->_hook->run('postPutFile', [$this]);

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
