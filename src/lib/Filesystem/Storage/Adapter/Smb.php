<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage\Adapter;

use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Filesystem\Storage\Exception;
use Balloon\Server\User;
use Icewind\SMB\Exception as SMBException;
use Icewind\SMB\IFileInfo;
use Icewind\SMB\IShare;
use InvalidArgumentException;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

class Smb implements AdapterInterface
{
    /**
     * Options.
     */
    public const OPTION_SYSTEM_FOLDER = 'system_folder';
    public const OPTION_ROOT = 'root';

    /**
     * System folders.
     */
    protected const SYSTEM_TRASH = 'trash';
    protected const SYSTEM_TEMP = 'temp';

    /**
     * SMB share.
     *
     * @var IShare
     */
    protected $share;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * SMB Root directory within share.
     *
     * @var string
     */
    protected $root = '';

    /**
     * Balloon system folder.
     *
     * @var string
     */
    protected $system_folder = '.balloon';

    /**
     * SMB storage.
     */
    public function __construct(IShare $share, LoggerInterface $logger, array $config = [])
    {
        $this->share = $share;
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Get SMB share.
     */
    public function getShare(): IShare
    {
        return $this->share;
    }

    /**
     * Get root path.
     */
    public function getRoot(): string
    {
        return $this->root;
    }

    /**
     * Get system folder.
     */
    public function getSystemFolder(): string
    {
        return $this->system_folder;
    }

    /**
     * {@inheritdoc}
     */
    public function hasNode(NodeInterface $node): bool
    {
        $attributes = $node->getAttributes();

        try {
            if (isset($attributes['storage']['path'])) {
                return (bool) $this->share->stat($attributes['storage']['path']);
            }
        } catch (SMBException\NotFoundException $e) {
            return false;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFile(File $file, ?int $version = null): ?array
    {
        return $this->deleteNode($file);
    }

    /**
     * {@inheritdoc}
     */
    public function readonly(NodeInterface $node, bool $readonly = true): ?array
    {
        $path = $this->getPath($node);
        $this->share->setMode($path, IFileInfo::MODE_READONLY);

        return $node->getAttributes()['storage'];
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteFile(File $file, ?int $version = null): bool
    {
        if (false === $this->hasNode($file)) {
            $this->logger->debug('smb blob for file ['.$file->getId().'] was not found', [
                'category' => get_class($this),
            ]);

            return false;
        }

        return $this->share->del($this->getPath($file));
    }

    /**
     * {@inheritdoc}
     */
    public function openReadStream(File $file)
    {
        return $this->share->read($this->getPath($file));
    }

    /**
     * {@inheritdoc}
     */
    public function storeFile(File $file, ObjectId $session): array
    {
        $path = $this->getSystemPath(self::SYSTEM_TEMP).DIRECTORY_SEPARATOR.$session;

        $current = $file->getPath();
        $mount = $file->getFilesystem()->findNodeById($file->getMount())->getPath();
        $dest = substr($current, strlen($mount));

        $this->logger->debug('copy file from session ['.$session.'] in ['.$path.'] to ['.$dest.']', [
            'category' => get_class($this),
        ]);

        $hash = hash_init('md5');
        $size = 0;
        $stream = $this->share->read($path);

        while (!feof($stream)) {
            $buffer = fgets($stream, 65536);

            if ($buffer === false) {
                continue;
            }

            $size += mb_strlen($buffer, '8bit');
            hash_update($hash, $buffer);
        }

        fclose($stream);
        $md5 = hash_final($hash);

        $this->logger->debug('calculated hash ['.$md5.'] for temporary file ['.$session.']', [
            'category' => get_class($this),
        ]);

        $this->share->rename($path, $dest);

        return [
            'reference' => [
                'path' => $dest,
            ],
            'size' => $size,
            'hash' => $md5,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection(Collection $parent, string $name): array
    {
        $path = $this->getPath($parent).DIRECTORY_SEPARATOR.$name;
        $this->share->mkdir($path);

        return [
            'path' => $path,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteCollection(Collection $collection): ?array
    {
        return $this->deleteNode($collection);
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteCollection(Collection $collection): bool
    {
        if (false === $this->hasNode($collection)) {
            $this->logger->debug('smb collection ['.$collection->getId().'] was not found', [
                'category' => get_class($this),
            ]);

            return false;
        }

        return $this->share->rmdir($this->getPath($collection));
    }

    /**
     * {@inheritdoc}
     */
    public function rename(NodeInterface $node, string $new_name): ?array
    {
        $reference = $node->getAttributes()['storage'];

        if (!$node->isDeleted()) {
            $path = dirname($this->getPath($node)).DIRECTORY_SEPARATOR.$new_name;
            $this->share->rename($this->getPath($node), $path);
            $reference['path'] = $path;
        }

        return $reference;
    }

    /**
     * {@inheritdoc}
     */
    public function move(NodeInterface $node, Collection $parent): ?array
    {
        $reference = $node->getAttributes()['storage'];

        if (!$node->isDeleted()) {
            $path = $this->getPath($parent).DIRECTORY_SEPARATOR.$node->getName();
            $this->share->rename($this->getPath($node), $path);
            $reference['path'] = $path;
        }

        return $reference;
    }

    /**
     * {@inheritdoc}
     */
    public function undelete(NodeInterface $node): ?array
    {
        if (false === $this->hasNode($node)) {
            $this->logger->debug('smb node ['.$this->getPath($node).'] was not found for reference=['.$node->getId().']', [
                'category' => get_class($this),
            ]);

            return $node->getAttributes()['storage'];
        }

        $current = $node->getPath();
        $mount = $node->getFilesystem()->findNodeById($node->getMount())->getPath();
        $restore = substr($current, strlen($mount));

        $this->share->rename($this->getPath($node), $restore);
        $reference = $node->getAttributes()['storage'];
        $reference['path'] = $restore;

        return $reference;
    }

    /**
     * {@inheritdoc}
     */
    public function storeTemporaryFile($stream, User $user, ?ObjectId $session = null): ObjectId
    {
        $exists = $session;

        if ($session === null) {
            $session = new ObjectId();

            $this->logger->info('create new tempory storage file ['.$session.']', [
                'category' => get_class($this),
            ]);
            $path = $this->getSystemPath(self::SYSTEM_TEMP).DIRECTORY_SEPARATOR.$session;
        } else {
            $path = $this->getSystemPath(self::SYSTEM_TEMP).DIRECTORY_SEPARATOR.$session;

            try {
                $this->share->stat($path);
            } catch (SMBException\NotFoundException $e) {
                throw new Exception\SessionNotFound('temporary storage for this file is gone');
            }
        }

        $this->storeStream($stream, $path);

        return $session;
    }

    /**
     * Test connection to storage.
     */
    public function test(): bool
    {
        $this->share->dir('/');

        return true;
    }

    /**
     * Set options.
     */
    protected function setOptions(array $config = []): self
    {
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'root':
                case 'system_folder':
                    $this->{$option} = (string) $value;

                break;
                default:
                    throw new InvalidArgumentException('unknown option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * Create system folder if not exists.
     */
    protected function getSystemPath(string $name): string
    {
        $path = $this->root.DIRECTORY_SEPARATOR.$this->system_folder;

        try {
            $this->share->stat($path);
        } catch (SMBException\NotFoundException $e) {
            $this->logger->debug('create smb system folder ['.$path.']', [
                'category' => get_class($this),
            ]);

            $this->share->mkdir($path);
            $this->share->setMode($path, IFileInfo::MODE_HIDDEN);
        }

        $path .= DIRECTORY_SEPARATOR.$name;

        try {
            $this->share->stat($path);
        } catch (SMBException\NotFoundException $e) {
            $this->logger->debug('create smb system folder ['.$path.']', [
                'category' => get_class($this),
            ]);

            $this->share->mkdir($path);
        }

        return $path;
    }

    /**
     * Move node to trash folder.
     */
    protected function deleteNode(NodeInterface $node): array
    {
        $reference = $node->getAttributes()['storage'];

        if ($this->hasNode($node) === false) {
            $this->logger->debug('smb node ['.$this->getPath($node).'] was not found for reference=['.$node->getId().']', [
                'category' => get_class($this),
            ]);

            return $reference;
        }

        $path = $this->getSystemPath(self::SYSTEM_TRASH).DIRECTORY_SEPARATOR.$node->getId();
        $this->share->rename($this->getPath($node), $path);
        $reference['path'] = $path;

        return $reference;
    }

    /**
     * Get SMB path from node.
     */
    protected function getPath(NodeInterface $node): string
    {
        $attributes = $node->getAttributes();

        if (isset($attributes['mount']) && count($attributes['mount']) !== 0) {
            return $this->root;
        }

        if (!isset($attributes['storage']['path'])) {
            throw new Exception\BlobNotFound('no storage.path given for smb storage definiton');
        }

        return $attributes['storage']['path'];
    }

    /**
     * Store stream content.
     */
    protected function storeStream($stream, string $path): int
    {
        if ($stream === null) {
            $dest = $this->share->write($path);

            return 0;
        }

        $dest = $this->share->append($path);
        $bytes = stream_copy_to_stream($stream, $dest);
        fclose($dest);

        return $bytes;
    }
}
