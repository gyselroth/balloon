<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2\Models;

use Balloon\AttributeDecorator\AttributeDecoratorInterface;
use Balloon\Node\Acl;
use Balloon\Resource\ResourceInterface;
use Balloon\Rest\ModelFactoryInterface;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Balloon\User\Factory as UserFactory;
use Balloon\App\Api\v2\Models\UserFactory as UserModelFactory;
use Balloon\Collection\Factory as CollectionFactory;

class NodeFactory extends AbstractModelFactory
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Acl.
     *
     * @var Acl
     */
    protected $acl;

    /**
     * Role decorator.
     *
     * @var RoleAttributeDecorator
     */
    protected $role_decorator;

    public function __construct(UserFactory $user_factory, UserModelFactory $user_model_factory, CollectionFactory $collection_factory, Acl $acl)
    {
        $this->user_factory = $user_factory;
        $this->collection_factory = $collection_factory;
        $this->user_model_factory = $user_model_factory;
        $this->acl = $acl;

        $this->addEmbedded('owner', function($value, $request) use($user_factory, $user_model_factory) {
            return $user_model_factory->decorate($user_factory->getOne($value), $request);
        });

        /*
        $this->addCustomEmbedded('shareowner', function($resource, $request) use($user_factory, $user_model_factory) {
                return $decorator->decorate(
                        $server->getUserById($fs->findRawNode($node->getShareId())['owner']),
                        ['id', 'name', '_links']
                    );
            return $user_model_factory->decorate($user_factory->getOne($value), $request);
        });
         */

        $this->addEmbedded('lockowner', function($value, $request, $resource) use($user_factory, $user_model_factory) {
            if (!$resource->isLocked()) {
                return null;
            }

            $lock = $resource->getLock();
            return $user_model_factory->decorate($user_factory->getOne($lock['owner']), $request);
        });

        $this->addEmbedded('parent', function($value, $request) use($collection_factory) {
            return $this->decorate($collection_factory->getOne($request->getAttribute('identity'), $value), $request);
        });

        $this->addEmbedded('share', function($value, $request) use($collection_factory) {
            if ($resource->isShared() || !$resource->isSpecial()) {
                return null;
            }

            return $this->decorate($collection_factory->getOne($resource->getShareId(true)), $request);
        });
    }

    /**
     * Get Attributes.
     */
    protected function getAttributes(ResourceInterface $node, ServerRequestInterface $request): array
    {
        $attributes = $node->toArray();
        $user_factory = $this->user_factory;
        $user_model_factory = $this->user_model_factory;
        $acl = $this->acl;
        $collection_factory = $this->collection_factory;



        return [
            'name' => (string) $attributes['name'],
            'mime' => (string) $attributes['mime'],
            'readonly' => (bool) ($attributes['readonly'] ?? false),
            //'directory' => $node instanceof Collection,
           /* 'meta' => function ($node) {
                return (object) $node->getMetaAttributes();
           },*/
            'size' => function ($node) {
                return $node->getSize();
            },
            'path' => function ($node) {
                return $node->getPath();
            },
            'parent' => (string)$attributes['parent'] ?? null,
            'access' => function ($node) use ($acl, $request) {
                return $acl->getAclPrivilege($node, $request->getAttribute('user'));
            },
            /*'acl' => function ($node) use ($attributes) {
                if ($node->isShareMember() && count($attributes['acl']) > 0) {
                    return $node->getAcl();
                }

                return null;
            },*/
            'lock' => function ($node) use ($user_factory, $user_model_factory, $attributes) {
                if (!$node->isLocked()) {
                    return null;
                }

                $lock = $attributes['lock'];

                return [
                    'owner' => (string)$lock['owner'],
                    'created' => $lock['created']->toDateTime()->format('c'),
                    'expire' => $lock['expire']->toDateTime()->format('c'),
                    'id' => $lock['id'],
                ];
            },
            'share' => $node->getShareId(true),
            'sharename' => function ($node) {
                if (!$node->isShared()) {
                    return null;
                }

                return $node->getShareName();
            },
            'shareowner' => function ($node) use ($user_factory, $user_model_factory) {
                if (!$node->isSpecial()) {
                    return null;
                }

                /*return $user_model_factory->decorate(
                    $user_factory->getOne($node['owner']),
                    $sub_request
                );*/
                /*return $decorator->decorate(
                        $server->getUserById($fs->findRawNode($node->getShareId())['owner']),
                        ['id', 'name', '_links']
                    );*/
            },
            'owner' => (string)$attributes['owner'] ?? null,
            'destroy' => function ($node) use ($attributes) {
                if (!isset($attributes['destroy'])) {
                    return null;
                }

                return $attributes['destroy']->toDateTime()->format('c');
            },
        ];
    }

    /**
     * Get Attributes.
     */
    /*protected function getTypeAttributes(NodeInterface $node, array $attributes): array
    {
        $server = $this->server;
        $fs = $this->server->getFilesystem();

        if ($node instanceof File) {
            return [
                'version' => $attributes['version'],
                'hash' => $attributes['hash'],
            ];
        }

        return [
            'shared' => $node->isShared(),
            'reference' => $node->isReference(),
            'filter' => function ($node) use ($attributes) {
                if (null === $attributes['filter']) {
                    return null;
                }

                return json_decode($attributes['filter'], true, 512, JSON_THROW_ON_ERROR);
            },
            'mount' => function ($node) use ($fs, $attributes) {
                $mount = $node->getAttributes()['mount'];

                if (!$node->isMounted() && !$node->isReference()) {
                    return null;
                }

                if ($node->isReference()) {
                    $attributes = $fs->findRawNode($node->getShareId());
                    if (isset($attributes['mount']) && count($attributes['mount']) > 0) {
                        $mount = $attributes['mount'];
                        unset($mount['username'], $mount['password']);

                        return $mount;
                    }

                    return null;
                }

                if (!empty($mount['password'])) {
                    unset($mount['password']);
                    $mount['has_password'] = true;
                }

                return $mount;
            },
        ];
    }*/
}
