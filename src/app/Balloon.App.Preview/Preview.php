<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview;

use Balloon\Exception;
use Balloon\Filesystem\Node\File;
use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Preview
{
    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Constructor.
     *
     * @param Database        $db
     * @param LoggerInterface $logger
     */
    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Get preview.
     *
     * @return string
     */
    public function getPreview(File $file): string
    {
        $preview = $file->getAppAttribute(__NAMESPACE__, 'preview');
        if ($preview instanceof ObjectId) {
            try {
                $stream = $this->db->selectGridFSBucket(['bucketName' => 'thumbnail'])
                    ->openDownloadStream($preview);
                $contents = stream_get_contents($stream);
                fclose($stream);

                return $contents;
            } catch (\Exception $e) {
                $this->logger->warning('failed download preview from gridfs for file ['.$file->getId().']', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        throw new Exception\NotFound(
            'preview does not exists',
            Exception\NotFound::PREVIEW_NOT_FOUND
        );
    }

    /**
     * Delete preview.
     *
     * @param File $file
     *
     * @return bool
     */
    public function deletePreview(File $file): bool
    {
        $bucket = $this->db->selectGridFSBucket(['bucketName' => 'thumbnail']);

        try {
            $preview = $file->getAppAttribute(__NAMESPACE__, 'preview');
            if ($preview instanceof ObjectId) {
                $references = $this->db->{'thumbnail.files'}->count([
                    'apps' => [__NAMESPACE__ => ['preview' => $preview]],
                ]);

                if (1 === $references) {
                    $this->logger->debug('delete preview ['.$preview.'] from file ['.$file->getId().']', [
                        'category' => get_class($this),
                    ]);

                    $bucket->delete($preview);
                } else {
                    $this->logger->debug('do not remove preview blob ['.$preview.'] from file ['.$file->getId().'], there are still other references left', [
                        'category' => get_class($this),
                    ]);
                }

                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('failed to remove preview from file ['.$file->getId().']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
