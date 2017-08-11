<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Auth\Adapter\Basic;

use \Micro\Auth\Adapter\Basic\AbstractBasic;
use \Micro\Auth\Adapter\AdapterInterface;
use \MongoDB\Database;

class Db extends AbstractBasic
{
    /**
     * Db
     *
     * @var Database
     */
    protected $db;


    /**
     * Attributes
     */
    protected $attributes = [];


    /**
     * Set options
     *
     * @param   Iterable $config
     * @return  AdapterInterface
     */
    public function setOptions(?Iterable $config=null): AdapterInterface
    {
        if ($config === null) {
            return $this;
        }
    
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'mongodb':
                    $this->db = $value;
                break;
            }
        }

        return $this;
    }
    
      
    /**
     * Find identity
     *
     * @param   string $username
     * @return  array
     */
    public function findIdentity(string $username): ?array
    {
        $result = $this->db->user->findOne([
            'username' => $username
        ]);

        if($result === null) {
            return null;
        } else {
            $this->attributes = $result;
            return $this->attributes['username'];
        }
    }


    /**
     * Get attributes
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
