<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Mime\Exception;

class Mime
{
    /**
     * Mime type db.
     *
     * @var string
     */
    protected $db = '/etc/mime.types';

    /**
     * Set path.
     *
     * @param iterable $config
     */
    public function __construct(?Iterable $config = null)
    {
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return Mime
     */
    public function setOptions(?Iterable $config = null): self
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'db':
                    $this->db = (string) $value;

                break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * et mime.
     *
     * @param string $path
     * @param string $name
     *
     * @return string
     */
    public function getMime(string $path, string $name): string
    {
        try {
            return $this->getMimeTypeFromExtension($name);
        } catch (\Exception $e) {
            return $this->getMimeFromContents($path);
        }
    }

    /**
     * Determine mime from contents.
     *
     * @return string
     */
    public function getMimeFromContents(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);

        return $mime;
    }

    /**
     * Get mimetype with string (file has not to be exists).
     *
     * @param string $name
     *
     * @return string
     */
    public function getMimeTypeFromExtension(string $name): string
    {
        if (!is_readable($this->db)) {
            throw new Exception('mime database '.$this->db.' was not found or is not readable');
        }

        $fileext = strrchr($name, '.');
        if (false === $fileext) {
            throw new Exception('file name given contains no extension');
        }

        $fileext = substr($fileext, 1);
        if (empty($fileext)) {
            throw new Exception('file name given contains no extension');
        }

        $regex = "/^([\w\+\-\.\/]+)\s+(\w+\s)*($fileext\s)/i";
        $lines = file($this->db);

        foreach ($lines as $line) {
            if ('#' === substr($line, 0, 1)) {
                continue;
            }

            $line = rtrim($line).' ';
            if (!preg_match($regex, $line, $matches)) {
                continue;
            }

            return $matches[1];
        }

        throw new Exception('extension '.$fileext.' not found in mime db');
    }
}
