<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use MongoDB\BSON\UTCDateTime;
use stdClass;

class Helper
{
    /**
     * Convert UTCDateTime to unix ts.
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
     */
    public static function isValidTimestamp(string $timestamp): bool
    {
        return ((string) (int) $timestamp === $timestamp)
            && ($timestamp <= PHP_INT_MAX)
            && ($timestamp >= ~PHP_INT_MAX);
    }

    /**
     * Multibyte unicode safe version of basename().
     *
     * @see http://php.net/manual/en/function.basename.php#121405
     */
    public static function mb_basename($path): string
    {
        if (preg_match('@^.*[\\\\/]([^\\\\/]+)$@s', $path, $matches)) {
            return $matches[1];
        }
        if (preg_match('@^([^\\\\/]+)$@s', $path, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
