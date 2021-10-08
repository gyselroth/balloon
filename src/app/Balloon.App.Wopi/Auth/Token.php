<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Wopi\Auth;

use Balloon\App\Wopi\SessionManager;
use Micro\Auth\Adapter\AbstractAdapter;
use Micro\Auth\Auth;

class Token extends AbstractAdapter
{
    /**
     * Attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Auth.
     *
     * @var Auth
     */
    protected $auth;

    /**
     * Session manager.
     *
     * @var SessionManager
     */
    protected $manager;

    /**
     * Set options.
     */
    public function __construct(SessionManager $manager, Auth $auth)
    {
        $this->manager = $manager;
        $this->auth = $auth;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(): bool
    {
        if (preg_match('#^/index.php/api/v2/office/wopi/files/#', $_SERVER['ORIG_SCRIPT_NAME']) === false) {
            return false;
        }

        $token = $_GET['access_token'] ?? null;

        if ($token === null) {
            return false;
        }

        $user = $this->manager->authenticate($token);

        $this->identifier = $user->getUsername();
        $this->attributes = $user->getAttributes();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
