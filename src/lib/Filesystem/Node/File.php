<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

use Balloon\Filesystem;
use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Acl\Exception as AclException;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Storage\Exception as StorageException;
use Balloon\Hook;
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
    public function __construct(array $attributes, Filesystem $fs, LoggerInterface $logger, Hook $hook, Acl $acl, Collection $parent)
    {
        $this->_fs = $fs;
        $this->_server = $fs->getServer();
        $this->_db = $fs->getDatabase();
        $this->_user = $fs->getUser();
        $this->_logger = $logger;
        $this->_hook = $hook;
        $this->_acl = $acl;
        $this->_parent = $parent;

        foreach ($attributes as $attr => $value) {
            $this->{$attr} = $value;
        }

        $this->raw_attributes = $attributes;
    }

    /**
     * Read content and return ressource.
     */
    public function get()
    {
        if (null === $this->storage) {
            return null;
        }

        try {
            return $this->_parent->getStorage()->openReadStream($this);
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
     */
    public function copyTo(Collection $parent, int $conflict = NodeInterface::CONFLICT_NOACTION, ?string $recursion = null, bool $recursion_first = true, int $deleted = NodeInterface::DELETED_EXCLUDE): NodeInterface
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

            if ($result instanceof Collection) {
                $result = $this->copyToCollection($result, $name);
            } else {
                $stream = $this->get();
                if ($stream !== null) {
                    $result->put($stream);
                }
            }
        } else {
            $result = $this->copyToCollection($parent, $name);
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
        return array_values($this->history);
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

        $v = array_search($version, array_column($this->history, 'version'), true);
        if (false === $v) {
            throw new Exception('failed restore file to version '.$version.', version was not found');
        }

        $file = $this->history[$v]['storage'];
        $latest = $this->version + 1;

        $this->history[] = [
            'version' => $latest,
            'changed' => $this->changed,
            'user' => $this->owner,
            'type' => self::HISTORY_RESTORE,
            'hash' => $this->history[$v]['hash'],
            'origin' => $this->history[$v]['version'],
            'storage' => $this->history[$v]['storage'],
            'size' => $this->history[$v]['size'],
            'mime' => isset($this->history[$v]['mime']) ? $this->history[$v]['mime'] : null,
        ];

        try {
            $this->deleted = false;
            $this->storage = $this->history[$v]['storage'];

            $this->hash = null === $file ? self::EMPTY_CONTENT : $this->history[$v]['hash'];
            $this->mime = isset($this->history[$v]['mime']) ? $this->history[$v]['mime'] : null;
            $this->size = $this->history[$v]['size'];
            $this->changed = $this->history[$v]['changed'];
            $new = $this->increaseVersion();
            $this->version = $new;

            $this->save([
                'deleted',
                'version',
                'storage',
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
        $this->storage = $this->_parent->getStorage()->deleteFile($this);

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
                $this->_parent->getStorage()->forceDeleteFile($this, $version);
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

        $session = $this->_parent->getStorage()->storeTemporaryFile($content, $this->_user);

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

        $previous = $this->version;
        $storage = $this->storage;
        $this->prePutFile($session);
        $result = $this->_parent->getStorage()->storeFile($this, $session);
        $this->storage = $result['reference'];

        if ($this->isDeleted() && $this->hash === $result['hash']) {
            $this->deleted = false;
            $this->save(['deleted']);
        }

        $this->deleted = false;

        if ($this->hash === $result['hash']) {
            $this->_logger->debug('do not update file version, hash identical to existing version ['.$this->hash.' == '.$result['hash'].']', [
                'category' => get_class($this),
            ]);

            return $this->version;
        }

        $this->hash = $result['hash'];
        $this->size = $result['size'];

        if ($this->size === 0 && $this->getMount() === null) {
            $this->storage = null;
        } else {
            $this->storage = $result['reference'];
        }

        $this->increaseVersion();

        if (isset($attributes['changed'])) {
            if (!($attributes['changed'] instanceof UTCDateTime)) {
                throw new Exception\InvalidArgument('attribute changed must be an instance of UTCDateTime');
            }

            $this->changed = $attributes['changed'];
        } else {
            $this->changed = new UTCDateTime();
        }

        if ($result['reference'] != $storage || $previous === 0) {
            $this->addVersion($attributes);
        }

        $this->postPutFile();

        return $this->version;
    }

    /**
     * Copy to collection.
     */
    protected function copyToCollection(Collection $parent, string $name): NodeInterface
    {
        $result = $parent->addFile($name, null, [
            'created' => $this->created,
            'changed' => $this->changed,
            'meta' => $this->meta,
        ], NodeInterface::CONFLICT_NOACTION, true);

        $stream = $this->get();

        if ($stream !== null) {
            $session = $parent->getStorage()->storeTemporaryFile($stream, $this->_server->getUserById($this->getOwner()));
            $result->setContent($session);
            fclose($stream);
        }

        return $result;
    }

    /**
     * Completly remove file.
     */
    protected function _forceDelete(): bool
    {
        try {
            $this->_parent->getStorage()->forceDeleteFile($this);
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
            $this->_logger->debug('history limit ['.$max.'] reached, remove oldest version ['.$this->history[$del]['version'].'] from file ['.$this->_id.']', [
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
            $this->_logger->debug('added new history version ['.$this->version.'] for file ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            $this->history[] = [
                'version' => $this->version,
                'changed' => $this->changed,
                'user' => $this->_user->getId(),
                'type' => self::HISTORY_EDIT,
                'storage' => $this->storage,
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
                'deleted',
                'mime',
                'hash',
                'version',
                'history',
                'storage',
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
