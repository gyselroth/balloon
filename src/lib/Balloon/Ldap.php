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

use Balloon\Ldap\Exception;
use \Psr\Log\LoggerInterface as Logger;

class Ldap
{
    /**
     * Connection resource
     *
     * @var resource
     */
    protected $connection;


    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;


    /**
     * Host
     *
     * @var string
     */
    protected $host = '127.0.0.1';


    /**
     * Port
     *
     * @var int
     */
    protected $port = 389;


    /**
     * Options
     *
     * @var array
     */
    protected $config = [];


    /**
     * Binddn
     *
     * @var string
     */
    protected $binddn;


    /**
     * Bindpw
     *
     * @var string
     */
    protected $bindpw;
    

    /**
     * Basedn
     *
     * @var string
     */
    protected $basedn;


    /**
     * tls
     *
     * @var bool
     */
    protected $tls=false;


    /**
     *  Options
     *
     * @var array
     */
    protected $options = [];


    /**
     * construct
     *
     * @param   Iterable $config
     * @param   Logger $logger
     * @return  resource
     */
    public function __construct(?Iterable $config, Logger $logger)
    {
        $this->setOptions($config);
        $this->logger = $logger;
    }


    /**
     * Connect
     *
     * @return Ldap
     */
    public function connect(): Ldap
    {
        $this->connection = ldap_connect($this->host, $this->port);

        if ($this->tls === true) {
            ldap_start_tls($this->connection);
        }
        foreach ($this->options as $opt => $value) {
            ldap_set_option($this->connection, constant($value['attr']), $value['value']);
        }

        if ($this->connection) {
            $bind = ldap_bind($this->connection, $this->binddn, $this->bindpw);
            if ($bind) {
                $this->logger->info('bind to ldap server ['.$this->host.'] with binddn ['.$this->binddn.'] was succesful', [
                    'category' => get_class($this),
                ]);

                return $this;
            } else {
                throw new Exception('failed bind to ldap server, error: '.ldap_error($this->connection));
            }
        } else {
            throw new Exception('failed connect to ldap server '.$this->host);
        }

        return $this;
    }

    
    /**
     * Set options
     *
     * @param  Iterable $config
     * @return Ldap
     */
    public function setOptions(?Iterable $config): Ldap
    {
        if ($config === null) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'host':
                    $this->host = (string)$value;
                    break;
                case 'port':
                    $this->port = (int)$value;
                    break;
                case 'options':
                    $this->options = $value;
                    break;
                case 'username':
                    $this->binddn = (string)$value;
                    break;
                case 'password':
                    $this->bindpw = (string)$value;
                    break;
                case 'basedn':
                    $this->basedn = (string)$value;
                    break;
                case 'tls':
                    $this->tls = (bool)(int)$value;
                    break;
            }
        }
    
        return $this;
    }


    /**
     * Get connection
     *
     * @return resource
     */
    public function getResource()
    {
        if (!is_resource($this->connection)) {
            $this->connect();
        }
    
        return $this->connection;
    }
}
