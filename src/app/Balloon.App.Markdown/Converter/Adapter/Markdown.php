<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Markdown\Converter\Adapter;

use Balloon\Converter\Adapter\AbstractOffice;
use Balloon\Converter\Exception;
use Balloon\Filesystem\Node\File;
use Psr\Log\LoggerInterface;

class Markdown extends AbstractOffice
{
    /**
     * Parsedown.
     *
     * @var Parsedown
     */
    protected $parser;

    /**
     * AbstractOffice.
     *
     * @var AbstractOffice
     */
    protected $officeConverter;

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
     * @param Parsedown       $parser          markdown parser
     * @param AbstractOffice  $officeConverter office converter
     * @param LoggerInterface $logger          PSR-3 Logger
     */
    public function __construct(
        \Parsedown $parser,
        AbstractOffice $officeConverter,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
        $this->parser = $parser;
        $this->parser->setSafeMode(true);
        $this->officeConverter = $officeConverter;
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
        return array_keys($this->officeConverter->formats['text']);
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
     * Create preview from stream.
     *
     * @param resource $stream
     *
     * @return resource
     */
    protected function createPreviewFromStream($stream)
    {
        $this->officeConverter->createPreviewFromStream($stream);
    }

    /**
     * Convert from stream.
     *
     * @param resource $stream
     *
     * @return resource
     */
    protected function convertFromStream($stream, string $format)
    {
        return $this->officeConverter->convertFromStream($stream, $format);
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
