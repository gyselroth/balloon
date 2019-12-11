<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage\Adapter;

use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Filesystem\Storage\Exception;
use Balloon\Server\User;
use Balloon\Session\SessionInterface;
use InvalidArgumentException;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

class NamedLocalStorage implements AdapterInterface
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
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * local Root directory within share.
     *
     * @var string
     */
    protected $root = '/tmp';

    /**
     * Balloon system folder.
     *
     * @var string
     */
    protected $system_folder = '.balloon';

    /**
     * local storage.
     */
    public function __construct(LoggerInterface $logger, array $config = [])
    {
        $this->share = $share;
        $this->logger = $logger;
        $this->setOptions($config);
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

        return isset($attributes['storage']['path']) && file_exists($attributes['storage']['path']);
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

        if ($readonly === true) {
            chmod($this->root.DIRECTORY_SEPARATOR.$path, 0440);

            return $node->getAttributes()['storage'];
        }

        chmod($this->root.DIRECTORY_SEPARATOR.$path, 0640);

        return $node->getAttributes()['storage'];
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteFile(File $file, ?int $version = null): bool
    {
        if (false === $this->hasNode($file)) {
            $this->logger->debug('blob for file ['.$file->getId().'] was not found', [
                'category' => get_class($this),
            ]);

            return false;
        }

        return unlink($this->root.DIRECTORY_SEPARATOR.$this->getPath($file));
    }

    /**
     * {@inheritdoc}
     */
    public function openReadStream(File $file)
    {
        return fopen($this->root.DIRECTORY_SEPARATOR.$this->getPath($file), 'r');
    }

    /**
     * {@inheritdoc}
     */
    public function storeFile(File $file, SessionInterface $session): array
    {
        $path = $this->getSystemPath(self::SYSTEM_TEMP).DIRECTORY_SEPARATOR.$session->getId();

        $current = $file->getPath();
        $mount = $file->getFilesystem()->findNodeById($file->getMount())->getPath();
        $dest = substr($current, strlen($mount));

        $this->logger->debug('copy file from session ['.$session->getId().'] in ['.$path.'] to ['.$dest.']', [
            'category' => get_class($this),
        ]);

        rename($path, $this->root.DIRECTORY_SEPARATOR.$dest);

        return [
            'reference' => [
                'path' => $dest,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection(Collection $parent, string $name): array
    {
        $path = $this->getPath($parent).DIRECTORY_SEPARATOR.$name;
        mkdir($this->root.DIRECTORY_SEPARATOR.$path);

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
            $this->logger->debug('local storage collection ['.$collection->getId().'] was not found', [
                'category' => get_class($this),
            ]);

            return false;
        }

        return $this->rrmdir($this->root.DIRECTORY_SEPARATOR.$this->getPath($collection));
    }

    /**
     * {@inheritdoc}
     */
    public function rename(NodeInterface $node, string $new_name): ?array
    {
        $reference = $node->getAttributes()['storage'];

        if (!$node->isDeleted()) {
            $path = join('/', [rtrim(dirname($this->getPath($node)), '/'), $new_name]);
            rename($this->root.DIRECTORY_SEPARATOR.$this->getPath($node), $this->root.DIRECTORY_SEPARATOR.$path);
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
            $path = join('/', [rtrim($this->getPath($parent), '/'), $node->getName()]);
            rename($this->root.DIRECTORY_SEPARATOR.$this->getPath($node), $this->root.DIRECTORY_SEPARATOR.$path);
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
            $this->logger->debug('node ['.$this->getPath($node).'] was not found for reference=['.$node->getId().']', [
                'category' => get_class($this),
            ]);

            return $node->getAttributes()['storage'];
        }

        $current = $node->getPath();
        $mount = $node->getFilesystem()->findNodeById($node->getMount())->getPath();
        $restore = substr($current, strlen($mount));

        rename($this->root.DIRECTORY_SEPARATOR.$this->getPath($node), $this->root.DIRECTORY_SEPARATOR.$restore);
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

            if (!file_exists($path)) {
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
        return file_exists($this->root);
    }

    /**
     * Recursive directory removal.
     */
    protected function rrmdir($dir): bool
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (is_dir($dir.'/'.$object) && !is_link($dir.'/'.$object)) {
                        rrmdir($dir.'/'.$object);
                    } else {
                        unlink($dir.'/'.$object);
                    }
                }
            }

            return rmdir($dir);
        }

        return false;
    }

    /**
     * Set options.
     */
    protected function setOptions(array $config = []): self
    {
        foreach ($config as $option => $value) {
            switch ($option) {
                case self::OPTION_SYSTEM_FOLDER:
                case self::OPTION_ROOT:
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

        if (!file_exists($path)) {
            $this->logger->debug('create local storage system folder ['.$path.']', [
                'category' => get_class($this),
            ]);

            mkdir($path);
        }

        $path .= DIRECTORY_SEPARATOR.$name;

        if (!file_exists($path)) {
            $this->logger->debug('create local storage system folder ['.$path.']', [
                'category' => get_class($this),
            ]);

            mkdir($path);
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
            $this->logger->debug('node ['.$this->getPath($node).'] was not found for reference=['.$node->getId().']', [
                'category' => get_class($this),
            ]);

            return $reference;
        }

        $path = $this->getSystemPath(self::SYSTEM_TRASH).DIRECTORY_SEPARATOR.$node->getId();
        rename($this->root.DIRECTORY_SEPARATOR.$this->getPath($node), $this->root.DIRECTORY_SEPARATOR.$path);
        $reference['path'] = $path;

        return $reference;
    }

    /**
     * Get local path from node.
     */
    protected function getPath(NodeInterface $node): string
    {
        $attributes = $node->getAttributes();

        if ($node->isRoot() || isset($attributes['mount']) && count($attributes['mount']) !== 0) {
            return '';
        }

        if (!isset($attributes['storage']['path'])) {
            throw new Exception\BlobNotFound('no storage.path given for local storage definiton');
        }

        return $attributes['storage']['path'];
    }

    /**
     * Store stream content.
     */
    protected function storeStream($stream, string $path): int
    {
        if ($stream === null) {
            touch($path);

            return 0;
        }

        $dest = fopen($path, 'a');
        $bytes = stream_copy_to_stream($stream, $dest);
        fclose($dest);

        return $bytes;
    }
}
