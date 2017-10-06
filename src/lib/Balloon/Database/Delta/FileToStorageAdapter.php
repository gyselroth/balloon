<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Database\Delta;

use \MongoDB\Database;
use \Psr\Log\LoggerInterface;
use \Balloon\Async;
use \MongoDB\BSON\UTCDateTime;
use \Balloon\Database\AbstractDelta;

class FileToStorageAdapter extends AbstractDelta
{
    /**
     * Get collection
     *
     * @return string
     */
    public function getCollection(): string
    {
        return 'storage';
    }


    /**
     * Upgrade object
     *
     * @return array
     */
    public function upgradeObject(array $object): array
    {
        if($object['directory'] === true) {
            return [];
        }

        if(isset($object['file'])) {
            $file = $object['file'];
        } else {
            $file = null;
        }

        return [
            '$unset' => 'file',
            '$set' => [
                'storage' => [
                    'adapter' => 'gridfs',
                    'attributes' => $file
                ]
            ]
        ];
    }
}
