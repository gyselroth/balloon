<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2;

use Balloon\AttributeDecorator\Pager;
use Balloon\Server;
use Balloon\Server\AttributeDecorator;
use Balloon\Server\User;
use Balloon\Server\User\Exception;
use Micro\Http\Response;
use MongoDB\BSON\Binary;
use function MongoDB\BSON\fromJSON;
use MongoDB\BSON\ObjectId;
use function MongoDB\BSON\toPHP;

class Users
{
    /**
     * User.
     *
     * @var User
     */
    protected $user;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Initialize.
     */
    public function __construct(Server $server, AttributeDecorator $decorator)
    {
        $this->user = $server->getIdentity();
        $this->server = $server;
        $this->decorator = $decorator;
    }

    /**
     * Get user instance.
     */
    public function _getUser(string $id, bool $require_admin = true): User
    {
        $user = $this->server->getUserById(new ObjectId($id));

        if ($user->getId() == $this->user->getId() || $require_admin === false) {
            return $user;
        }

        if ($this->user->isAdmin()) {
            return $user;
        }

        throw new Exception\NotAdmin('submitted parameters require admin privileges');
    }

    /**
     * Who am I?
     */
    public function getWhoami(array $attributes = []): Response
    {
        $result = $this->decorator->decorate($this->user, $attributes);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Node attribute summary.
     */
    public function getNodeAttributeSummary(array $attributes = [], int $limit = 25): Response
    {
        $result = $this->user->getNodeAttributeSummary($attributes, $limit);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Group membership.
     */
    public function getGroups(string $id, array $attributes = [], int $offset = 0, int $limit = 20): Response
    {
        $user = $this->_getUser($id);
        $result = $user->getResolvedGroups($offset, $limit);
        $uri = '/api/v2/users/'.$user->getId().'/groups';
        $pager = new Pager($this->decorator, $result, $attributes, $offset, $limit, $uri);
        $result = $pager->paging();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * User attributes.
     *
     * @param null|mixed $query
     */
    public function get(?string $id = null, $query = null, array $attributes = [], int $offset = 0, int $limit = 20): Response
    {
        if ($id === null) {
            if ($query === null) {
                $query = [];
            } elseif (is_string($query)) {
                $query = toPHP(fromJSON($query), [
                    'root' => 'array',
                    'document' => 'array',
                    'array' => 'array',
                ]);
            }

            $result = $this->server->getUsers($query, $offset, $limit);
            $pager = new Pager($this->decorator, $result, $attributes, $offset, $limit, '/api/v2/users');
            $result = $pager->paging();
        } else {
            $result = $this->decorator->decorate($this->_getUser($id, false), $attributes);
        }

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Get user avatar.
     */
    public function getAvatar(string $id): Response
    {
        $avatar = $this->_getUser($id, false)->getAttributes()['avatar'];
        if ($avatar instanceof Binary) {
            return (new Response())
                ->setOutputFormat('text')
                ->setBody($avatar->getData())
                ->setHeader('Content-Type', 'image/png');
        }

        return (new Response())->setCode(404);
    }

    /**
     * Create user.
     */
    public function post(string $username, ?string $password = null, ?int $soft_quota = null, ?int $hard_quota = null, ?string $avatar = null, ?string $mail = null, ?bool $admin = false, ?string $namespace = null, ?string $locale = null): Response
    {
        if (!$this->user->isAdmin()) {
            throw new Exception\NotAdmin('submitted parameters require admin privileges');
        }

        $attributes = compact('password', 'soft_quota', 'hard_quota', 'avatar', 'mail', 'admin', 'namespace', 'locale');
        $attributes = array_filter($attributes, function ($attribute) {return !is_null($attribute); });

        if (isset($attributes['avatar'])) {
            $attributes['avatar'] = new Binary(base64_decode($attributes['avatar']), Binary::TYPE_GENERIC);
        }

        $id = $this->server->addUser($username, $attributes);
        $result = $this->decorator->decorate($this->server->getUserById($id));

        return (new Response())->setBody($result)->setCode(201);
    }

    /**
     * Change attributes.
     */
    public function patch(string $id, ?string $username = null, ?string $password = null, ?int $soft_quota = null, ?int $hard_quota = null, ?string $avatar = null, ?string $mail = null, ?bool $admin = null, ?string $namespace = null, ?string $locale = null, ?bool $multi_factor_auth = null): Response
    {
        $attributes = compact('username', 'password', 'soft_quota', 'hard_quota', 'avatar', 'mail', 'admin', 'namespace', 'locale', 'multi_factor_auth');
        $attributes = array_filter($attributes, function ($attribute) {return !is_null($attribute); });

        if (isset($attributes['avatar'])) {
            $attributes['avatar'] = new Binary(base64_decode($attributes['avatar']), Binary::TYPE_GENERIC);
        }

        $user = $this->_getUser($id);
        $user->setAttributes($attributes);
        $result = $this->decorator->decorate($user);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Delete user.
     */
    public function delete(string $id, bool $force = false): Response
    {
        $user = $this->_getUser($id);

        if ($user->getId() === $this->user->getId()) {
            throw new Exception\InvalidArgument('can not delete yourself', Exception\InvalidArgument::CAN_NOT_DELETE_OWN_ACCOUNT);
        }

        $user->delete($force);

        return (new Response())->setCode(204);
    }

    /**
     * Enable user account.
     */
    public function postUndelete(string $id): Response
    {
        $user = $this->_getUser($id);
        $user->undelete();

        return (new Response())->setCode(200)->setBody($user);
    }
}
