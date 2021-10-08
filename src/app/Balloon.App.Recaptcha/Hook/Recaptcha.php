<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Recaptcha\Hook;

use Balloon\App\Recaptcha\Exception;
use Balloon\Hook\AbstractHook;
use InvalidArgumentException;
use Micro\Auth\Adapter\None;
use Micro\Auth\Auth;
use Micro\Auth\Identity;
use MongoDB\Database;
use Psr\Log\LoggerInterface;
use ReCaptcha\ReCaptcha as CaptchaService;

class Recaptcha extends AbstractHook
{
    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Recaptcha.
     *
     * @var CaptchaService
     */
    protected $recaptcha;

    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Validate captcha.
     *
     * @var int
     */
    protected $recaptcha_threshold = 30;

    /**
     * Constructor.
     */
    public function __construct(LoggerInterface $logger, CaptchaService $recaptcha, Database $db, array $config = [])
    {
        $this->logger = $logger;
        $this->recaptcha = $recaptcha;
        $this->db = $db;
        $this->setOptions($config);
    }

    /**
     * Set options.
     */
    public function setOptions(array $config = [])
    {
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'recaptcha_threshold':
                    $this->{$option} = (int) $value;

                break;
                case 'hostname':
                    $this->recaptcha->setExpectedHostname($value);

                break;
                default:
                    throw new InvalidArgumentException('unknown option '.$option.' given');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postAuthentication(Auth $auth, ?Identity $identity = null): void
    {
        $username = $_POST['username'] ?? $_SERVER['username'] ?? null;

        if ($identity === null && $username !== null) {
            $this->logger->info('detected unsuccessful authentication for ['.$username.'], increase failed_auth counter', [
                'category' => get_class($this),
            ]);

            $this->db->user->updateOne([
                'username' => $username,
            ], [
                '$inc' => [
                    'failed_auth' => 1,
                ],
            ]);

            return;
        }

        if ($identity instanceof Identity && !($identity->getAdapter() instanceof None)) {
            $this->logger->info('detected successful authentication for ['.$identity->getIdentifier().']', [
                'category' => get_class($this),
            ]);

            $this->db->user->updateOne([
                'username' => $username,
            ], [
                '$set' => [
                    'failed_auth' => 0,
                ],
            ]);

            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function preAuthentication(Auth $auth): void
    {
        $username = $_POST['username'] ?? $_SERVER['username'] ?? null;

        if ($username === null) {
            return;
        }

        $result = $this->db->user->findOne([
            'username' => $username,
            'failed_auth' => [
                '$gte' => $this->recaptcha_threshold,
            ],
        ]);

        if ($result !== null) {
            $this->logger->info('max failed auth requests for user ['.$username.'] exceeded recaptcha threshold ['.$this->recaptcha_threshold.']', [
                'category' => get_class($this),
            ]);

            if (!isset($_GET['g-recaptcha-response'])) {
                throw new Exception\InvalidRecaptchaToken('valid recaptcha token `g-recaptcha-response` is required');
            }

            $resp = $this->recaptcha->verify($_GET['g-recaptcha-response'], $_SERVER['REMOTE_ADDR']);

            if ($resp->isSuccess()) {
                $this->logger->info('recaptcha token validation for ['.$username.'] succeeded', [
                    'category' => get_class($this),
                ]);
            } else {
                $this->logger->info('recaptcha token validation for ['.$username.'] failed', [
                    'category' => get_class($this),
                    'errors' => $resp->getErrorCodes(),
                ]);

                throw new Exception\InvalidRecaptchaToken('Valid recaptcha token `g-recaptcha-response` is required');
            }
        }
    }
}
