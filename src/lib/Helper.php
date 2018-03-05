<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use MongoDB\BSON\UTCDateTime;
use stdClass;

class Helper
{
    /**
     * Convert UTCDateTime to unix ts.
     *
     * @param UTCDateTime $date
     *
     * @return stdClass
     */
    public static function DateTimeToUnix(?UTCDateTime $date): ?stdClass
    {
        if (null === $date) {
            return null;
        }

        $date = $date->toDateTime();
        $ts = new stdClass();
        $ts->sec = $date->format('U');
        $ts->usec = $date->format('u');

        return $ts;
    }

    /**
     * Check if param is a valid unix timestamp.
     *
     * @param string $timestamp
     *
     * @return bool
     */
    public static function isValidTimestamp(string $timestamp): bool
    {
        return ((string) (int) $timestamp === $timestamp)
            && ($timestamp <= PHP_INT_MAX)
            && ($timestamp >= ~PHP_INT_MAX);
    }
}
