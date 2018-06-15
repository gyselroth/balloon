<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office;

class Template
{
    /**
     * templates.
     */
    const TEMPLATES = [
        'xlsx' => 'Office Open XML Spreadsheet',
        'xls' => 'Microsoft Excel 97-2003',
        'xlt' => 'Microsoft Excel 97-2003 Template',
        'csv' => 'Text CSV',
        'ods' => 'ODF Spreadsheet',
        'ots' => 'ODF Spreadsheet Template',
        'docx' => 'Office Open XML Text',
        'doc' => 'Microsoft Word 97-2003',
        'dot' => 'Microsoft Word 97-2003 Template',
        'odt' => 'ODF Textdocument',
        'ott' => 'ODF Textdocument Template',
        'pptx' => 'Office Open XML Presentation',
        'ppt' => 'Microsoft Powerpoint 97-2003',
        'potm' => 'Microsoft Powerpoint 97-2003 Template',
        'odp' => 'ODF Presentation',
        'otp' => 'ODF Presentation Template',
    ];

    /**
     * Type.
     *
     * @var string
     */
    protected $type;

    /**
     * Open template.
     */
    public function __construct(string $type)
    {
        if (!array_key_exists($type, self::TEMPLATES)) {
            throw new Exception\UnsupportedType('unsupported file type');
        }

        $this->type = $type;
    }

    /**
     * Get template size.
     */
    public function getSize(): int
    {
        return filesize($this->getTemplate());
    }

    /**
     * Open template stream.
     *
     * @return resource
     */
    public function get()
    {
        return fopen($this->getTemplate(), 'r');
    }

    /**
     * Get path to template.
     */
    protected function getTemplate(): string
    {
        return __DIR__.DIRECTORY_SEPARATOR.'assets'
          .DIRECTORY_SEPARATOR.'template.'.$this->type;
    }
}
