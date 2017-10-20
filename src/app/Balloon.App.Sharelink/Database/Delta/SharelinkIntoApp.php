<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Sharelink\Database\Delta;

use Balloon\Database\AbstractDelta;
use MongoDB\Database;

class SharelinkIntoApp extends AbstractDelta
{
    /**
     * Upgrade database.
     *
     * @return bool
     */
    public function getCollection(): string
    {
        return 'storage';
    }

    /**
     * Upgrade object.
     *
     * @return array
     */
    public function upgradeObject(array $object): array
    {
        if (isset($object['sharelink'])) {
            return [
                '$unset' => 'sharelink',
                '$set' => ['app_attributes.Balloon_App_Sharelink' => $object['sharelink']],
            ];
        }

        return [];
    }
}
