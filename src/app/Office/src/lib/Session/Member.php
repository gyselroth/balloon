<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office\Session;

use Balloon\Config;
use Balloon\User;
use Balloon\Logger;
use Balloon\Filesystem;
use \MongoDB\BSON\ObjectId;
use \MongoDB\BSON\UTCDateTime;
use \MongoDB\BSON\Serializable as BSONSerializable;
use Balloon\App\Office\Session;

class Member implements BSONSerializable
{
    /**
     * Session
     *
     * @var Session
     */
    protected $session;

    
    /**
     * Valid until
     *
     * @var UTCDateTime
     */
    protected $ttl;


    /**
     * User
     *
     * @var User
     */
    protected $user;

        
    /**
     * Access token
     *
     * @var string
     */
    protected $access_token;


    /**
     * New session
     *
     * @param   User $user
     * @param   int $ttl
     * @param   Session $session
     * @return  void
     */
    public function __construct(User $user, $ttl=3600, ?Session $session=null)
    {
        $this->user = $user;

        if ($session === null) {
            $this->access_token = $this->createToken();
            $ts         = (time() + $ttl) * 1000;
            $this->ttl  = new UTCDateTime($ts);
        } else {
            $this->setSession($session);
        }
    }


    /**
     * Serialize session member
     *
     * @return array
     */
    public function bsonSerialize(): array
    {
        return [
            'ttl'  => $this->ttl,
            'user' => $this->user->getId(),
            'access_token' => $this->access_token,
        ];
    }

    
    /**
     * Set session
     *
     * @param  Session $session
     * @return Member
     */
    public function setSession(Session $session): Member
    {
        $this->session = $session;
        return $this;
    }


    /**
     * Get document
     *
     * @return Document
     */
    public function getSession(): Document
    {
        return $this->session;
    }
    

    /**
     * Get user
     *
     * @retrun User
     */
    public function getUser(): User
    {
        return $this->user;
    }
    

    /**
     * Get valid until
     *
     * @return UTCDateTime
     */
    public function getTTL(): UTCDateTime
    {
        return $this->ttl;
    }


    /**
     * Get session attributes
     *
     * @return array
     */
    public function getAttributes(): array
    {
        $doc   = $this->session->getDocument();
        $attrs = $doc->getNode()->getAttribute(['name', 'version']);
        $attributes = [
            'BaseFileName'     => $attrs['name'],
            'Size'             => $doc->getSize(),
            'Version'          => $attrs['version'],
            'OwnerId'          => (string)$doc->getNode()->getOwner(),
            'UserId'           => (string)$this->user->getId(),
            'UserFriendlyName' => $this->user->getUsername(),
            'UserCanWrite'     => true,
            'PostMessageOrigin'=> null
        ];

        return $attributes;
    }


    /**
     * Get Session
     *
     * @param  Filesystem $fs
     * @param  Logger $logger
     * @param  ObjectId $session_id
     * @param  string $access_token
     * @return Member
     */
    public static function getByAccessToken(Filesystem $fs, Logger $logger, ObjectId $session_id, string $access_token): Member
    {
        $session = Session::getByAccessToken($fs, $session_id, $access_token);
        foreach ($session->getMember() as $member) {
            if ($member['access_token'] === $access_token) {
                return new self(new User($member['user'], $logger, $fs), 0, $session);
            }
        }
    }

    
    /**
     * Create access token
     *
     * @return string
     */
    protected function createToken()
    {
        return md5(uniqid(rand().(string)$this->user->getId(), true));
    }


    /**
     * Get WOPI token
     *
     * @return string
     */
    public function getAccessToken()
    {
        return $this->access_token;
    }
}
