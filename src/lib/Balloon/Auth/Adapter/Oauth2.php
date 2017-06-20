<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Auth\Adapter;

use \Psr\Log\LoggerInterface as Logger;
use Balloon\Config;
use Balloon\Exception;
use \League\OAuth2\Server\Storage\SessionInterface;

class Oauth2 implements AdapterInterface, SessionInterface
{
    /**
     * Identity
     *
     * @var string
     */
    protected $identity;


    /**
     * Config
     *
     * @var SimpleXMLElement
     */
    protected $config;


    /**
     * Resource server
     *
     * @var \League\OAuth2\Server\Resource
     */
    protected $resource;
    

   /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;

    
    /**
     * Attribute sync cache
     *
     * @var int
     */
    protected $attr_sync_cache = 0;

    
    /**
     * ldap resources
     *
     * @var Iterable
     */
    protected $ldap_resources = [];

    
    /**
     * auth server
     *
     * @var string
     */
    protected $auth_server;

    
    /**
     * client_id
     *
     * @var string
     */
    protected $client_id;

    
    /**
     * client_pw
     *
     * @var string
     */
    protected $client_secret;


    /**
     * Set config
     *
     * @param   Iterable $config
     * @param   Logger $logger
     * @return  void
     */
    public function __construct(?Iterable $config, Logger $logger)
    {
        $this->logger = $logger;
        $this->setOptions($config);
    }


    /**
     * Set options
     *
     * @param   Iterable
     * @return  Oauth2
     */
    public function setOptions(?Iterable $config): Oauth2
    {
        if ($config === null) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'auth_server':
                case 'client_id':
                case 'client_secret':
                case 'ldap_resources':
                    $this->{$option} = $value;
                break;
                
                case 'attr_sync_cache':
                    $this->attr_sync_cache = (int)$value;
                break;
            }
        }
        
        return $this;
    }


    /**
     * Get search
     *
     * @return Iterable
     */
    public function getLdapResources(): Iterable
    {
        return $this->ldap_resources;
    }


    /**
     * Get attribute sync cache
     *
     * @return int
     */
    public function getAttributeSyncCache(): int
    {
        return $this->attr_sync_cache;
    }


    /**
     * Authenticate
     *
     * @return bool
     */
    public function authenticate(): bool
    {
        if (!isset($_SERVER['HTTP_AUTHORIZATION']) && !isset($_GET['access_token'])) {
            $this->logger->debug('skip auth adapter ['.get_class($this).'], no http authorization header or access_token param found', [
                'category' => get_class($this)
            ]);
        
            return false;
        }

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
            $parts  = explode(' ', $header);
            
            if ($parts[0] == 'Bearer') {
                $this->logger->debug('found http bearer authorization header', [
                    'category' => get_class($this)
                ]);
                
                return $this->tokenAuth($parts[1]);
            } else {
                $this->logger->debug('http authorization header contains no bearer string or invalid authentication string', [
                    'category' => get_class($this)
                ]);
            
                return false;
            }
        } elseif (isset($_GET['access_token'])) {
            return $this->tokenAuth($_GET['access_token']);
        }
    }


    /**
     * Authenticate with access token
     *
     * @param   string $token
     * @return  bool
     */
    public function tokenAuth(string $token): bool
    {
        try {
            $this->resource = new \League\OAuth2\Server\Resource($this);
            return $this->resource->isValid();
        } catch (\Exception $e) {
            (new \Balloon\Http\Response())
                ->setHeader('WWW-Authenticate', 'Bearer realm="balloon", error="invalid_token", error_description="The access token expired"')
                ->setCode(401)
                ->setBody('Unauthorized')
                ->send();
        }
    }


    /**
     * Get identity
     *
     * @return string
     */
    public function getIdentity(): string
    {
        return $this->resource->getOwnerId();
    }

    
    /**
     * Get attribute map
     *
     * @return Iterable
     */
    public function getAttributeMap(): Iterable
    {
        return [];
    }


    /**
     * Get attributes
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return [];
    }


    /**
     * Validate access token
     *
     * @param   string $access_token
     * @return  array
     */
    public function validateAccessToken($access_token): array
    {
        $ch = curl_init($this->auth_server."?access_token=$access_token");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->client_id.':'.$this->client_secret);
        $re = curl_exec($ch);
        curl_close($ch);
        $jre = json_decode($re);
        
        if ($jre->status == "success") {
            return (array) $jre->data;
        } elseif ($jre->status == "fail") {
            throw new Exception('Error while validating the acces token. Auth server responded: ' . $jre->message);
        } else {
            throw new Exception('could not decode response: '.$re);
        }
    }

    public function createSession($clientId, $redirectUri, $type = 'user', $typeId = null, $authCode = null, $accessToken = null, $refreshToken = null, $accessTokenExpire = null, $stage = 'requested')
    {
    }
    public function updateSession($sessionId, $authCode = null, $accessToken = null, $refreshToken = null, $accessTokenExpire = null, $stage = 'requested')
    {
    }
    public function deleteSession($clientId, $type, $typeId)
    {
    }
    public function validateAuthCode($clientId, $redirectUri, $authCode)
    {
    }
    public function associateAuthCode($sessionId, $authCode, $expireTime)
    {
    }
    public function associateRefreshToken($accessTokenId, $refreshToken, $expireTime, $clientId)
    {
    }
    public function associateAccessToken($sessionId, $accessToken, $expireTime)
    {
    }
    public function removeAuthCode($sessionId)
    {
    }
    public function removeRefreshToken($refreshToken)
    {
    }
    public function associateRedirectUri($sessionId, $redirectUri)
    {
    }
    public function getAuthCodeScopes($oauthSessionAuthCodeId)
    {
    }
    public function associateAuthCodeScope($authCodeId, $scopeId)
    {
    }
    public function getAccessToken($sessionId)
    {
    }
    public function validateRefreshToken($refreshToken, $clientId)
    {
    }
    public function updateRefreshToken($sessionId, $newAccessToken, $newRefreshToken, $accessTokenExpires)
    {
    }
    public function associateScope($sessionId, $scopeId)
    {
    }
    public function getScopes($sessionId)
    {
        return [];
    }
}
