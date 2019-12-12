<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\CoreApiv3\v3;

use Balloon\Acl;
use Balloon\Rest\Helper;
use Balloon\User;
use Balloon\AccessRule;
use Balloon\AccessRule\Factory as AccessRuleFactory;
use Balloon\App\CoreApiv3\v3\Models\AccessRuleFactory as AccessRuleModelFactory;
use Fig\Http\Message\StatusCodeInterface;
use Lcobucci\ContentNegotiation\UnformattedResponse;
use MongoDB\BSON\ObjectId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rs\Json\Patch;
use Zend\Diactoros\Response;
use Balloon\Group\Factory as GroupFactory;
use Balloon\App\CoreApiv3\v3\Models\GroupFactory as GroupModelFactory;

class AccessRules
{
    /**
     * AccessRule factory.
     *
     * @var AccessRuleFactory
     */
    protected $access_rule_factory;

    /**
     * Init.
     */
    public function __construct(AccessRuleFactory $access_rule_factory, AccessRuleModelFactory $access_rule_model_factory, Acl $acl)
    {
        $this->access_rule_factory = $access_rule_factory;
        $this->access_rule_model_factory = $access_rule_model_factory;
        $this->acl = $acl;
    }

    /**
     * Entrypoint.
     */
    public function getAll(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $query = $request->getQueryParams();

        if (isset($query['watch'])) {
            $cursor = $this->access_rule_factory->watch(null, true, $query['query'], (int) $query['offset'], (int) $query['limit'], $query['sort']);

            return Helper::watchAll($request, $identity, $this->acl, $cursor, $this->access_rule_model_factory);
        }

        $access_rules = $this->access_rule_factory->getAll($query['query'], $query['offset'], $query['limit'], $query['sort']);

        return Helper::getAll($request, $identity, $this->acl, $access_rules, $this->access_rule_model_factory);
    }

    /**
     * Entrypoint.
     */
    public function getOne(ServerRequestInterface $request, User $identity, ObjectId $access_rule): ResponseInterface
    {
        $resource = $this->access_rule_factory->getOne($access_rule);

        return Helper::getOne($request, $identity, $resource, $this->access_rule_model_factory);
    }

    /**
     * Delete access_rule.
     */
    public function delete(ServerRequestInterface $request, User $identity, ObjectId $access_rule): ResponseInterface
    {
        $this->access_rule_factory->deleteOne($access_rule);
        return (new Response())->withStatus(StatusCodeInterface::STATUS_NO_CONTENT);
    }

    /**
     * Add new access_rule.
     */
    public function post(ServerRequestInterface $request, User $identity): ResponseInterface
    {
        $body = $request->getParsedBody();
        $resource = $this->access_rule_factory->add($body);
        return Helper::getOne($request, $identity, $resource, $this->access_rule_model_factory, StatusCodeInterface::STATUS_CREATED);
    }

    /**
     * Patch.
     */
    public function patch(ServerRequestInterface $request, User $identity, ObjectId $access_rule): ResponseInterface
    {
        $body = $request->getParsedBody();
        $query = $request->getQueryParams();
        $access_rule = $this->access_rule_factory->getOne($access_rule);
        $update = Helper::patch($request, $access_rule);
        $this->access_rule_factory->update($access_rule, $update);
        return Helper::getOne($request, $identity, $access_rule, $this->access_rule_model_factory);
    }
}
