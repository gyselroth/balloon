<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview\Database\Delta;

use Balloon\Database\Delta\AbstractDelta;

class Installation extends AbstractDelta
{
    /**
     * Initialize database.
     *
     * @return bool
     */
    public function init(): bool
    {
        $this->db->selectCollection('thumbnail.files')->createIndex(['md5' => 1], ['unique' => true]);

        return true;
    }
}
