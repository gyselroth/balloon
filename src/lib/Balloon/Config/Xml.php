<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Config;

use \Balloon\Config;
use \SimpleXMLElement;

class Xml implements ConfigInterface
{
    /**
     * Store
     *
     * @var SimpleXML
     */
    private $store;


    /**
     * Load config
     *
     * @param   string $config
     * @param   string $env
     * @return  void
     */
    public function __construct(string $config, string $env='production')
    {
        $config = simplexml_load_file($config);
        if ($this->store === false) {
            throw new Exception('failed load xml configuration');
        }
        
        $store = (array)$config->children();
        if (!isset($store[$env])) {
            throw new Exception('env '.$env.' is not configured');
        }
        
        $config = $store[$env];

        foreach ($store as $reg) {
            $result = $reg->xpath('/config/'.$reg->getName().'//*[@inherits]');
            while (list(, $node) = each($result)) {
                $path = (string)$node->attributes()->inherits;

                if ($path === '') {
                    continue;
                }

                $xpath = '/config/'.$reg->getName().'/'.str_replace('.', '/', $path).'';
                $found = $reg->xpath($xpath);
                if (count($found) !== 1) {
                    continue;
                }
                
                $found = array_shift($found);
                $this->appendSimplexml($node, $found, false);
            }
        }
        
        $attrs = $store[$env]->attributes();
        if (isset($attrs['inherits'])) {
            if (!isset($store[(string)$attrs['inherits']])) {
                throw new Exception('parent env '.$attrs['inherits'].' is not configured');
            } else {
                $this->appendSimplexml($config, $store[(string)$attrs['inherits']]);
            }
        }
        
        $this->store = $config;
    }


    /**
     * Merge xml tree's
     *
     * @param   SimpleXMLElement $simmplexml_to
     * @param   SimpleXMLElement $simplexml_from
     * @param   bool $replace
     * @return  bool
     */
    protected function appendSimplexml(SimpleXMLElement &$simplexml_to, SimpleXMLElement &$simplexml_from, bool $replace=true): bool
    {
        if (count($simplexml_from->children()) === 0) {
            if ($replace === true && count($simplexml_to->children()) === 0) {
                $simplexml_to[0] = htmlspecialchars((string)$simplexml_from);
            }
        }
            
        $attrs = $simplexml_to->attributes();
        foreach ($simplexml_from->attributes() as $attr_key => $attr_value) {
            if (!isset($attrs[$attr_key])) {
                $simplexml_to->addAttribute($attr_key, (string)$attr_value);
            } elseif ($replace===true) {
                $simplexml_to->attributes()->{$attr_key} = (string)$attr_value;
            }
        }

        foreach ($simplexml_from->children() as $simplexml_child) {
            if (count($simplexml_child->children()) === 0) {
                if (!isset($simplexml_to->{$simplexml_child->getName()})) {
                    $simplexml_to->addChild($simplexml_child->getName(), htmlspecialchars((string)$simplexml_child));
                } elseif ($replace === true && count($simplexml_to->{$simplexml_child->getName()}->children()) === 0) {
                    $simplexml_to->{$simplexml_child->getName()} = htmlspecialchars((string)$simplexml_child);
                }
            } else {
                $this->appendSimplexml($simplexml_to->{$simplexml_child->getName()}, $simplexml_child, $replace);
            }

            $attrs = $simplexml_to->{$simplexml_child->getName()}->attributes();
            foreach ($simplexml_child->attributes() as $attr_key => $attr_value) {
                if (!isset($attrs[$attr_key])) {
                    $simplexml_to->{$simplexml_child->getName()}->addAttribute($attr_key, (string)$attr_value);
                } elseif ($replace===true) {
                    $simplexml_to->{$simplexml_child->getName()}->attributes()->{$attr_key} = (string)$attr_value;
                }
            }
        }

        return true;
    }


    /**
     * Get entire simplexml
     *
     * @return SimpleXMLElement
     */
    public function getRaw(): SimpleXMLElement
    {
        return $this->store;
    }

    
    /**
     * Get from config
     *
     * @param   string $name
     * @return  SimpleXMLElement
     */
    public function __get(string $name): SimpleXMLElement
    {
        return $this->store->{$name};
    }


    /**
     * Add config tree and merge it
     *
     * @param   ConfigInterface $config
     * @return  Xml
     */
    public function merge($config): Xml
    {
        $merge = $config->getRaw();
        $this->appendSimplexml($this->store, $merge);
        
        $result = $this->store->xpath('//*[@inherits]');
        while (list(, $node) = each($result)) {
            $path = (string)$node->attributes()->inherits;
            
            if ($path === '') {
                continue;
            }

            $xpath = '//'.str_replace('.', '/', $path);
            $found = $this->store->xpath($xpath);
            if (count($found) !== 1) {
                continue;
            }

            $found = array_shift($found);
            $this->appendSimplexml($node, $found);
        }
        
        return $this;
    }


    /**
     * Get xml as config
     *
     * @param   SimpleXMLElement $xml
     * @return  Config
     */
    public function map($xml=null): Config
    {
        if ($xml === null) {
            $xml = $this->store;
        }

        $config = new Config();
        foreach ($xml->getNamespaces() + array(null) as $prefix => $namespace) {
            foreach ($xml->attributes($namespace) as $key => $value) {
                if (is_string($prefix)) {
                    $key = $prefix . '.' . $key;
                }

                if ($key === 'inherits') {
                    continue;
                }

                $config[$key] = (string)$value;
            }
        }

        foreach ($xml as $name => $element) {
            $value = $element->children() ? $this->map($element) : trim((string)$element);
            if ($value || $value === '0') {
                if (!isset($arr[$name])) {
                    $config[$name] = $value;
                } else {
                    foreach ((array) $value as $k => $v) {
                        if (is_numeric($k)) {
                            $config[$name][] = $v;
                        } else {
                            $config[$name][$k] = array_merge(
                                (array) $config[$name][$k],
                                (array) $v
                            );
                        }
                    }
                }
            } else {
                $config[$name] = new Config();
            }
        }
        if ($content = trim((string) $xml)) {
            $config[] = $content;
        }

        return $config;
    }
}
