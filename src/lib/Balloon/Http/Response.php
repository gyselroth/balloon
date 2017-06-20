<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Http;

use Balloon\Http;
use Balloon\Exception;
use Balloon\Config\Xml;
use \SimpleXMLElement;

class Response
{
    /**
     * Output format
     *
     * @var string
     */
    protected $output_format = 'json';


    /**
     * Possible output formats
     */
    const OUTPUT_FORMATS = ['json', 'xml', 'text'];


    /**
     * Human readable output
     *
     * @var bool
     */
    protected $pretty_format = false;


    /**
     * Headers
     *
     * @var array
     */
    protected $headers = [];


    /**
     * Code
     *
     * @var int
     */
    protected $code = 200;


    /**
     * Body
     *
     * @var string
     */
    protected $body;


    /**
     * body only
     *
     * @var bool
     */
    protected $body_only = false;


    /**
     * Init response
     *
     * @return void
     */
    public function __construct()
    {
        $this->setupFormats();
    }


    /**
     * Set header
     *
     * @param   string $header
     * @param   string $value
     * @return  Response
     */
    public function setHeader(string $header, string $value): Response
    {
        $this->headers[$header] = $value;
        return $this;
    }


    /**
     * Get headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    
    /**
     * Send headers
     *
     * @return  Response
     */
    public function sendHeaders(): Response
    {
        foreach ($this->headers as $header => $value) {
            header($header.': '.$value);
        }

        return $this;
    }
    

    /**
     * Set response code
     *
     * @param   int $code
     * @return  Response
     */
    public function setCode(int $code): Response
    {
        if (!array_key_exists($code, Http::STATUS_CODES)) {
            throw new Exception\InvalidArgument('invalid http code set');
        }
        
        $this->code = $code;
        return $this;
    }
    

    /**
     * Get response code
     *
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }
 

    /**
     * Set body
     *
     * @param  mixed $body
     * @param  bool $body_only
     * @return Response
     */
    public function setBody($body, bool $body_only=false): Response
    {
        $this->body = $body;
        $this->body_only = $body_only;
        return $this;
    }

    
    /**
     * Get body
     *
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }


    /**
     * Sends the actual response.
     *
     * @return  void
     */
    public function send(): void
    {
        $this->sendHeaders();
        $status = Http::STATUS_CODES[$this->code];
        header('HTTP/1.0 ' . $this->code . ' ' . $status, true, $this->code);

        if ($this->body === null && $this->code == 204) {
            $this->terminate();
        }
        
        if ($this->body_only === false && $this->output_format !== 'text') {
            $body = [
                'data'   => $this->body,
                'status' => intval($this->code)
            ];

            $body = array_reverse($body, true);
        } else {
            $body = $this->body;
        }
        
        switch ($this->output_format) {
            default:
            case 'json':
                echo $this->asJSON($body);
            break;
            
            case 'xml':
                echo $this->asXML($body);
            break;

            case 'text':
                echo $body;
            break;
        }

        $this->terminate();
    }


    /**
     * Get output format
     *
     * @return string
     */
    public function getOutputFormat(): string
    {
        return $this->output_format;
    }


    /**
     * Convert response to human readable output
     *
     * @param   bool $format
     * @return  Response
     */
    public function setPrettyFormat(bool $format): Response
    {
        $this->pretty_format = (bool)$format;
        return $this;
    }


    /**
     * Set header Content-Length $body.
     *
     * @param  string $body
     * @return Response
     */
    public function setContentLength(string $body): Response
    {
        header('Content-Length: '.strlen($body));
        return $this;
    }


    /**
     * Converts $body to pretty json.
     *
     * @param  mixed $body
     * @return string
     */
    public function asJSON($body): string
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($this->pretty_format) {
            $result = json_encode($body, JSON_PRETTY_PRINT);
        } else {
            $result = json_encode($body);
        }

        $this->setContentLength($result);

        return $result;
    }


    /**
    * Converts mixed data to XML
    *
    * @param    mixed $data
    * @param    SimpleXMLElement $xml
    * @param    string|int $child_name
    * @return   string
    */
    public function toXML($data, SimpleXMLElement $xml, $child_name): string
    {
        if (is_object($data)) {
            $data = (array)$data;
        }

        if (is_array($data)) {
            foreach ($data as $k => $v) {
                if (is_array($v) || is_object($v)) {
                    (is_int($k)) ? $this->toXML($v, $xml->addChild($child_name), $child_name) : $this->toXML($v, $xml->addChild(strtolower($k)), $child_name);
                } else {
                    $v = (string)$v;
                    (is_int($k)) ? $xml->addChild($child_name, $v) : $xml->addChild(strtolower((string)$k), $v);
                }
            }
        } else {
            $xml->addChild($child_name, $data);
        }

        return $xml->asXML();
    }


    /**
     * Converts response to xml.
     *
     * @param   mixed $body
     * @return  string
     */
    public function asXML($body): string
    {
        header('Content-Type: application/xml; charset=utf-8');

        $root = new SimpleXmlElement('<response></response>');
        $raw = $this->toXML($body, $root, 'node');
        if ($this->pretty_format) {
            $raw = $this->prettyXml($raw);
        }

        $this->setContentLength($raw);
        return $raw;
    }


    /**
     * Pretty formatted xml
     *
     * @param   string $xml
     * @return  string
     */
    public function prettyXml(string $xml): string
    {
        $domxml = new \DOMDocument('1.0');
        $domxml->preserveWhiteSpace = false;
        $domxml->formatOutput = true;
        $domxml->loadXML($xml);

        return $domxml->saveXML();
    }


    /**
     * Set the current output format.
     *
     * @param  string $foramt a key of $outputForms
     * @return Response
     */
    public function setOutputFormat(string $format): Response
    {
        $this->output_format = $format;
        return $this;
    }

    
    /**
     * Abort after response
     *
     * @return void
     */
    public function terminate(): void
    {
        exit();
    }


    /**
     * Setup formats.
     *
     * @return Response
     */
    public function setupFormats(): Response
    {
        $pretty = array_key_exists('pretty', $_GET) && ($_GET['pretty'] != 'false' && $_GET['pretty'] != '0');
        $this->setPrettyFormat($pretty);

        //through HTTP_ACCEPT
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], '*/*') === false) {
            foreach (self::OUTPUT_FORMATS as $format) {
                if (strpos($_SERVER['HTTP_ACCEPT'], $format) !== false) {
                    $this->output_format = $format;
                    break;
                }
            }
        }

        return $this;
    }
}
