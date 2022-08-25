<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Idp\Hook;

use Balloon\App\Idp\Exception;
use Balloon\Hook\AbstractHook;
use Balloon\Server\RoleInterface;
use Balloon\Server\User;
use Dolondro\GoogleAuthenticator\GoogleAuthenticator;
use Dolondro\GoogleAuthenticator\Secret;
use Dolondro\GoogleAuthenticator\SecretFactory;
use Micro\Auth\Adapter\Basic\BasicInterface;
use Micro\Auth\Identity;
use Psr\Log\LoggerInterface;

class MultiFactorAuth extends AbstractHook
{
    /**
     * Issuer.
     */
    public const ISSUER = 'balloon';

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Secret factory.
     *
     * @var SecretFactory
     */
    protected $secret_factory;

    /**
     * Secret.
     *
     * @var Secret
     */
    protected $secret;

    /**
     * Google authenticator.
     *
     * @var GoogleAuthenticator
     */
    protected $authenticator;

    /**
     * Recovery codes.
     *
     * @var array
     */
    protected $recovery_codes = [];

    /**
     * Constructor.
     */
    public function __construct(LoggerInterface $logger, SecretFactory $secret_factory, GoogleAuthenticator $authenticator)
    {
        $this->logger = $logger;
        $this->secret_factory = $secret_factory;
        $this->authenticator = $authenticator;
    }

    /**
     * {@inheritdoc}
     */
    public function preUpdateUser(User $user, array &$attributes = []): void
    {
        $existing = $user->getAttributes();

        if (isset($attributes['multi_factor_auth'])) {
            if ($attributes['multi_factor_auth'] === true) {
                $attributes['multi_factor_auth'] = false;

                if (isset($attributes['multi_factor_validate']) && $this->authenticator->authenticate($existing['google_auth_secret'], $attributes['multi_factor_validate'])) {
                    $attributes['multi_factor_auth'] = true;
                    unset($attributes['multi_factor_validate']);

                    list($codes, $hash) = $this->generateRecoveryCodes();
                    $this->recovery_codes = $codes;
                    $attributes['multi_factor_recovery'] = $hash;

                    return;
                }

                if (!isset($attributes['multi_factor_validate'])) {
                    $secret = $this->secret_factory->create(self::ISSUER, $user->getUsername());
                    $attributes['google_auth_secret'] = $secret->getSecretKey();
                    $this->secret = $secret;
                }

                unset($attributes['multi_factor_validate']);
            } else {
                $attributes['google_auth_secret'] = null;
                $attributes['multi_factor_recovery'] = [];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function preDecorateRole(RoleInterface $role, array &$attributes = []): void
    {
        if (!($role instanceof User)) {
            return;
        }

        $mfa = $role->getAttributes()['multi_factor_auth'];
        $attributes['multi_factor_auth'] = $mfa;

        if ($this->secret !== null) {
            $attributes['multi_factor_uri'] = $this->secret->getUri();
        }

        if ($this->recovery_codes != []) {
            $attributes['multi_factor_recovery'] = $this->recovery_codes;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function preServerIdentity(Identity $identity, ?User &$user): void
    {
        if (null === $user || (preg_match('#^/index.php/api/v2/tokens#', $_SERVER['ORIG_SCRIPT_NAME']) && isset($_POST['grant_type']) && (preg_match('#_mfa$#', $_POST['grant_type']) || $_POST['grant_type'] === 'refresh_token'))) {
            return;
        }

        if (($identity->getAdapter() instanceof BasicInterface || preg_match('#^/index.php/api/v2/tokens#', $_SERVER['ORIG_SCRIPT_NAME'])) && $user->getAttributes()['multi_factor_auth'] === true) {
            throw new Exception\MultiFactorAuthenticationRequired('multi-factor authentication required');
        }

        $this->logger->debug('multi-factor authentication is not required for user ['.$user->getId().']', [
            'category' => static::class,
        ]);
    }

    /**
     * Generate one time recovery codes.
     */
    protected function generateRecoveryCodes(): array
    {
        $list = [];
        $hash = [];

        for ($i = 0; $i < 10; ++$i) {
            $list[$i] = bin2hex(random_bytes(6));
            $hash[$i] = hash('sha512', (string) $list[$i]);
        }

        return [$list, $hash];
    }
}
