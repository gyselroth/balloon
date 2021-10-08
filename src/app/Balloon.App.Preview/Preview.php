<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview;

use Balloon\Converter;
use Balloon\Filesystem\Node\File;
use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Preview
{
    /**
     * Stream limit.
     */
    protected const SIZE_LIMIT = 2097152;

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
     * Converter.
     *
     * @var Converter
     */
    protected $converter;

    /**
     * Constructor.
     */
    public function __construct(Database $db, Converter $converter, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->converter = $converter;
        $this->logger = $logger;
    }

    /**
     * Create preview.
     */
    public function createPreview(File $file): ObjectId
    {
        return $this->storePreview($file, $this->converter->createPreview($file));
    }

    /**
     * Set preview.
     */
    public function setPreview(File $file, $stream): ObjectId
    {
        return $this->storePreview($file, $stream);
    }

    /**
     * Get preview.
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

        throw new Exception\PreviewNotFound('preview does not exists');
    }

    /**
     * Delete preview.
     */
    public function deletePreview(File $file): bool
    {
        $bucket = $this->db->selectGridFSBucket(['bucketName' => 'thumbnail']);
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

            $file->unsetAppAttribute(__NAMESPACE__, 'preview');

            return true;
        }

        return false;
    }

    /**
     * Store new preview.
     */
    protected function storePreview(File $file, $content): ObjectId
    {
        $this->logger->info('store new preview for file ['.$file->getId().']', [
            'category' => get_class($this),
        ]);

        try {
            $preview = $file->getAppAttribute(__NAMESPACE__, 'preview');

            if ($preview instanceof ObjectId) {
                $this->deletePreview($file);
            }
        } catch (\Exception $e) {
            //ignore exception
        }

        try {
            $result = stream_get_contents($content, self::SIZE_LIMIT);
            $hash = md5($result);
            rewind($content);

            $found = $this->db->{'thumbnail.files'}->findOne([
                'md5' => $hash,
            ], ['_id', 'thumbnail']);

            if ($found) {
                $this->logger->debug('found existing preview ['.$found['_id'].'] with same hash, use stored preview', [
                    'category' => get_class($this),
                ]);

                $file->setAppAttribute(__NAMESPACE__, 'preview', $found['_id']);

                return $found['_id'];
            }

            $id = new ObjectId();
            $bucket = $this->db->selectGridFSBucket(['bucketName' => 'thumbnail']);
            $stream = $bucket->openUploadStream(null, ['_id' => $id]);
            $result = stream_copy_to_stream($content, $stream);

            if ($result !== false) {
                $file->setAppAttribute(__NAMESPACE__, 'preview', $id);
            }

            return $id;
        } catch (\Exception $e) {
            $file->unsetAppAttribute(__NAMESPACE__, 'preview');

            throw $e;
        }
    }
}
