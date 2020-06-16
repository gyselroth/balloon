<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Markdown\Converter\Adapter;

use Balloon\App\Office\Converter\Adapter\Office;
use Balloon\Converter\Exception;
use Balloon\Filesystem\Node\File;
use GuzzleHttp\ClientInterface as GuzzleHttpClientInterface;
use Parsedown;
use Psr\Log\LoggerInterface;

class Markdown extends Office
{
    /**
     * Parsedown.
     *
     * @var \Parsedown
     */
    protected $parser;

    /**
     * Formats.
     *
     * @var array
     */
    protected $source_formats = [
        'markdown' => 'text/markdown',
    ];

    /**
     * Initialize.
     */
    public function __construct(Parsedown $parser, GuzzleHttpClientInterface $client, LoggerInterface $logger, array $config = [])
    {
        parent::__construct($client, $logger, $config);
        $this->parser = $parser;
        $this->parser->setSafeMode(true);
    }

    /**
     * {@inheritdoc}
     */
    public function match(File $file): bool
    {
        foreach ($this->source_formats as $format => $mimetype) {
            if ($file->getContentType() === $mimetype) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFormats(File $file): array
    {
        if (in_array($file->getContentType(), $this->locked_formats, true)) {
            return [
                array_search($file->getContentType(), $this->locked_formats, true),
            ];
        }

        return array_keys($this->formats['text']);
    }

    /**
     * {@inheritdoc}
     */
    public function createPreview(File $file)
    {
        return $this->createPreviewFromStream(
            $this::getStreamFromString($this->parseMarkdownToHtml($file))
        );
    }

    /**
     * {@inheritdoc}
     */
    public function convert(File $file, string $format)
    {
        $tmpHtmlFile = $this::getStreamFromString($this->parseMarkdownToHtml($file));
        if ('html' === $format) {
            $dest = stream_get_meta_data($tmpHtmlFile)['uri'];

            if (!file_exists($dest) || filesize($dest) <= 0) {
                throw new Exception('failed get '.$format);
            }

            return $tmpHtmlFile;
        }

        return $this->convertFromStream($tmpHtmlFile, $format);
    }

    /**
     * Turn string into a stream.
     */
    protected static function getStreamFromString(string $string)
    {
        $stream = tmpfile();
        fwrite($stream, $string);
        rewind($stream);

        return $stream;
    }

    /**
     * Parse Markdown file to HTML.
     */
    protected function parseMarkdownToHtml(File $file): string
    {
        return $this->parser->text(\stream_get_contents($file->get()));
    }
}
