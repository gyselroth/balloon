<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview\Database\Delta;

use \MongoDB\Database;
use \Psr\Log\LoggerInterface;
use \Balloon\Database\AbstractDelta;

class PreviewIntoApp extends AbstractDelta
{
    /**
     * Upgrade database
     *
     * @return bool
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
        if (isset($object['thumbnail'])) {
            return [
                '$unset' => 'thumbnail',
                '$set'  => ['app_attributes.Balloon_App_Preview.preview' => $object['thumbnail']]
            ];
        }

        return [];
    }
}
