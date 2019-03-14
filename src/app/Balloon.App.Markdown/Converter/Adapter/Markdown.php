<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Markdown\Converter\Adapter;

use Balloon\Converter\Adapter\Office;
use Balloon\Converter\Exception;
use Balloon\Filesystem\Node\File;
use Psr\Log\LoggerInterface;

class Markdown extends Office
{
    /**
     * Preview format.
     */
    const PREVIEW_FORMAT = 'png';

    /**
     * preview max size.
     *
     * @var int
     */
    protected $preview_max_size = 500;

    /**
     * Parsedown.
     *
     * @var Parsedown
     */
    protected $parser;

    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

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
     *
     * @param Parsedown       $parser markdown parser
     * @param LoggerInterface $logger PSR-3 Logger
     * @param iterable        $config
     */
    public function __construct(\Parsedown $parser, LoggerInterface $logger, ?Iterable $config = null)
    {
        $this->parser = $parser;
        $this->parser->setSafeMode(true);
        $this->logger = $logger;
        $this->setOptions($config);
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
    public function matchPreview(File $file): bool
    {
        return $this->match($file);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFormats(File $file): array
    {
        return array_keys($this->formats['text']);
    }

    /**
     * {@inheritdoc}
     */
    public function createPreview(File $file)
    {
        return parent::createPreviewFromStream(
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

            return new Result($dest, $tmpHtmlFile);
        }

        return parent::convertFromStream($tmpHtmlFile, $format);
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
