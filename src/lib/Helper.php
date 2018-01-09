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
     * Filter data.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public static function filter($data)
    {
        if (is_array($data)) {
            foreach ($data as &$elem) {
                $elem = self::filter($elem);
            }
        } else {
            $data = strip_tags($data);
        }

        return $data;
    }

    /**
     * Escape data.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public static function escape($data)
    {
        if (is_array($data)) {
            foreach ($data as &$elem) {
                $elem = self::escape($elem);
            }
        } elseif (is_string($data)) {
            $data = htmlspecialchars($data, ENT_COMPAT, 'UTF-8');
        }

        return $data;
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
