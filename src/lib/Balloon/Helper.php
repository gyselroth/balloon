<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use \Balloon\Exception;
use \MongoDB\BSON\UTCDateTime;
use \MongoDB\Model\BSONArray;
use \MongoDB\Model\BSONDocument;
use \Stdclass;

class Helper
{
    /**
     * Convert UTCDateTime to unix ts
     *
     * @param  UTCDateTime $date
     * @return StdClass
     */
    public static function DateTimeToUnix(?UTCDateTime $date): ?Stdclass
    {
        if ($date === null) {
            return null;
        }

        $date = $date->toDateTime();
        $ts = new StdClass();
        $ts->sec = $date->format('U');
        $ts->usec = $date->format('u');
        return $ts;
    }


    /**
     * Search array element
     *
     * @param   mixed $values
     * @param   mixed $key
     * @param   array $array
     * @return  void
     */
    public static function searchArray($value, $key, array $array)
    {
        foreach ($array as $k => $val) {
            if ($val[$key] == $value) {
                return $k;
            }
        }

        return null;
    }


    /**
     * Filter data
     *
     * @param  mixed $data
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
     * Escape data
     *
     * @param  mixed $data
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
     * Check if param is a valid unix timestamp
     *
     * @param  string $timestamp
     * @return bool
     */
    public static function isValidTimeStamp(string $timestamp): bool
    {
        return ((string) (int) $timestamp === $timestamp)
            && ($timestamp <= PHP_INT_MAX)
            && ($timestamp >= ~PHP_INT_MAX);
    }

    /**
     * Combine two arrays, merging duplicate keys
     *
     * @param  array $keys
     * @param  array $values
     * @return array
     */
    public static function array_combine_recursive($keys, $values)
    {
        // Sort keys but keep index association
        asort($keys);
        // Sort values the same order the keys are sorted and reindex them
        $values = array_values(array_replace($keys, $values));
        // reindex keys
        $keys = array_values($keys);
        // remove duplicate from keys
        $unique_keys = array_unique($keys);

        $combined = [];
        $length = count($keys);

        for($i=0; $i < $length; $i++) {
            if (!array_key_exists($i, $unique_keys)) {
                $combined[$keys[$i]][] = $values[$i];
            } elseif (!array_key_exists($i+1, $unique_keys) && $i+1 !== $length) {
                $combined[$keys[$i]] = [$values[$i]];
            } else {
                $combined[$keys[$i]] = $values[$i];
            }
        }
        return $combined;
    }

    /**
     * Convert camelCase to dashes
     *
     * @param  string $value
     * @return string
     */
    public static function camelCase2Dashes($value)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $value));
    }
}
