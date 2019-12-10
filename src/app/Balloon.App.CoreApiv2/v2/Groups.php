<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\CoreApiv2\v2;

use Balloon\AttributeDecorator\Pager;
use Balloon\Server;
use Balloon\Server\AttributeDecorator;
use Balloon\Server\Group;
use Balloon\Server\User;
use Balloon\Server\User\Exception;
use Micro\Http\Response;
use function MongoDB\BSON\fromJSON;
use MongoDB\BSON\ObjectId;
use function MongoDB\BSON\toPHP;

class Groups
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
     * Attribute decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Initialize.
     *
     * @param AttributeDecorator
     */
    public function __construct(Server $server, AttributeDecorator $decorator)
    {
        $this->user = $server->getIdentity();
        $this->server = $server;
        $this->decorator = $decorator;
    }

    /**
     * Get group instance.
     */
    public function _getGroup(string $id, bool $require_admin = false): Group
    {
        if (true === $require_admin && !$this->user->isAdmin()) {
            throw new Exception\NotAdmin('submitted parameters require admin privileges');
        }

        return $this->server->getGroupById(new ObjectId($id));
    }

    /**
     * Get group member.
     */
    public function getMembers(string $id, array $attributes = [], int $offset = 0, int $limit = 20): Response
    {
        $group = $this->_getGroup($id);
        $result = $group->getResolvedMembers($offset, $limit);
        $uri = '/api/v2/groups/'.$group->getId().'/members';
        $pager = new Pager($this->decorator, $result, $attributes, $offset, $limit, $uri);
        $result = $pager->paging();

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Get group attributes.
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

            $result = $this->server->getGroups($query, $offset, $limit);
            $pager = new Pager($this->decorator, $result, $attributes, $offset, $limit, '/api/v2/groups');
            $result = $pager->paging();
        } else {
            $result = $this->decorator->decorate($this->_getGroup($id), $attributes);
        }

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Create group.
     */
    public function post(string $name, array $member = [], ?string $namespace = null): Response
    {
        if (!$this->user->isAdmin()) {
            throw new Exception\NotAdmin('submitted parameters require admin privileges');
        }

        $attributes = compact('namespace');
        $attributes = array_filter($attributes, function ($attribute) {return !is_null($attribute); });

        $id = $this->server->addGroup($name, $member, $attributes);
        $result = $this->decorator->decorate($this->server->getGroupById($id));

        return (new Response())->setBody($result)->setCode(201);
    }

    /**
     * Change group attributes.
     */
    public function patch(string $id, ?string $name = null, ?array $member = null, ?string $namespace = null): Response
    {
        $attributes = compact('namespace', 'name', 'member');
        $attributes = array_filter($attributes, function ($attribute) {return !is_null($attribute); });

        $group = $this->_getGroup($id, true);
        $group->setAttributes($attributes);
        $result = $this->decorator->decorate($group);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Delete group.
     */
    public function delete(string $id, bool $force = false): Response
    {
        $group = $this->_getGroup($id, true);
        $group->delete($force);

        return (new Response())->setCode(204);
    }

    /**
     * Restore group.
     */
    public function postUndelete(string $id): Response
    {
        $this->_getGroup($id, true)->undelete();

        return (new Response())->setCode(204);
    }
}
