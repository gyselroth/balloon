<?php
/**
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Balloon\App\CoreApiv3;

use Zend\Diactoros\Stream as ZendStream;

/**
 * Implementation of PSR HTTP streams
 */
class Stream extends ZendStream
{
    /**
     * @param string|resource $stream
     * @param string $mode Mode with which to open stream
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($stream, int $limit=1)
    {
        $this->limit = $limit;
        $this->resource = $stream;
        $this->stream = $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function getContents() : string
    {
        if (! $this->isReadable()) {
            throw Exception\UnreadableStreamException::dueToConfiguration();
        }

        $result = stream_get_contents($this->resource, $this->limit);
        if (false === $result) {
            throw Exception\UnreadableStreamException::dueToPhpError();
        }
        return $result;
    }
}
