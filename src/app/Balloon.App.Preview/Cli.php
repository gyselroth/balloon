<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview;

use Balloon\Converter;
use Balloon\Converter\Result;
use Balloon\Filesystem\Node\File;
use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Cli extends App
{
    /**
     * Preview image format.
     */
    const PREVIEW_FORMAT_IMAGE = 'png';

    /**
     * Converter.
     *
     * @var Converter
     */
    protected $converter;

    /**
     * Constructor.
     *
     * @param Router $router
     * @param Hook   $hook
     */
    public function __construct(Database $db, LoggerInterface $logger, Converter $converter)
    {
        parent::__construct($db, $logger);
        $this->converter = $converter;
    }

    /**
     * Create preview.
     *
     * @param File $file
     *
     * @return ObjectId
     */
    public function createPreview(File $file): ObjectId
    {
        $this->logger->debug('create preview for file ['.$file->getId().']', [
            'category' => get_class($this),
        ]);

        try {
            $result = $this->converter->convert($file, self::PREVIEW_FORMAT_IMAGE);
            $hash = md5_file($result->getPath());

            $found = $this->db->{'thumbnail.files'}->findOne([
                'md5' => $hash,
            ], ['_id', 'thumbnail']);

            if ($found) {
                $this->logger->debug('found existing preview ['.$found['_id'].'] with same hash, use stored preview', [
                    'category' => get_class($this),
                ]);

                $file->setAppAttribute($this, 'preview', $found['_id']);

                return $found['_id'];
            }

            return $this->storePreview($file, $result);
        } catch (\Exception $e) {
            $file->unsetAppAttribute($this, 'preview');

            throw $e;
        }
    }

    /**
     * Store new preview.
     *
     * @param File   $file
     * @param Result $content
     *
     * @return ObjectId
     */
    protected function storePreview(File $file, Result $content): ObjectId
    {
        if (!$file->isAllowed('w')) {
            throw new Exception\Forbidden(
                'not allowed to modify node',
                Exception\Forbidden::NOT_ALLOWED_TO_MODIFY
            );
        }

        try {
            $id = new ObjectId();
            $bucket = $this->db->selectGridFSBucket(['bucketName' => 'thumbnail']);
            $stream = $bucket->openUploadStream(null, ['_id' => $id]);
            fwrite($stream, $content->getContents());
            fclose($stream);

            $file->setAppAttribute($this, 'preview', $id);

            $this->logger->info('stored new preview ['.$id.'] for file ['.$file->getId().']', [
                'category' => get_class($this),
            ]);

            return $id;
        } catch (\Exception $e) {
            $this->logger->error('failed store preview for file ['.$file->getId().']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
