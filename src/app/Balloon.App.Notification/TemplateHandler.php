<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Balloon\Server\User;

class TemplateHandler
{
    /**
     * Subject.
     *
     * @var string
     */
    protected $subject;

    /**
     * Message.
     *
     * @var string
     */
    protected $message;

    /**
     * Node.
     *
     * @var NodeInterface
     */
    protected $node;

    /**
     * Attribute decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Role Attribute decorator.
     *
     * @var RoleAttributeDecorator
     */
    protected $role_decorator;

    /**
     * Template storage.
     *
     * @var array
     */
    protected $templates = [];

    /**
     * Mail template storage.
     *
     * @var array
     */
    protected $mail_templates = [];

    /**
     * Asset directory.
     *
     * @var string
     */
    protected $asset_dir = __DIR__.DIRECTORY_SEPARATOR.'assets';

    /**
     * Fallback locale.
     *
     * @var string
     */
    protected $fallback_locale = 'en_US';

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Constructor.
     *
     * @param Server                 $server
     * @param AttributeDecorator     $decorator
     * @param RoleAttributeDecorator $role_decorator
     */
    public function __construct(Server $server, AttributeDecorator $decorator, RoleAttributeDecorator $role_decorator)
    {
        $this->server = $server;
        $this->decorator = $decorator;
        $this->role_decorator = $role_decorator;
    }

    /**
     * Set config.
     *
     * @param iterable $config
     *
     * @return TemplateHandler
     */
    public function setOptions(?Iterable $config = null): self
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'asset_dir':
                case 'fallback_locale':
                    $this->{$option} = (string) $value;

                break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    public function parseBodyTemplate(string $notification, User $user, NodeInterface $node): string
    {
        return $this->parseTemplate($notification, 'body', $user, $node);
    }

    public function parseMailBodyTemplate(string $notification, User $user, NodeInterface $node): string
    {
        return $this->parseMailTemplate($notification, $user, $node);
    }

    public function parseSubjectTemplate(string $notification, User $user, NodeInterface $node): string
    {
        return $this->parseTemplate($notification, 'subject', $user, $node);
    }

    /*public function parseMessage(string $message, User $user): string
    {
        $template = $this->load($notification);
        if (!isset($template[$type])) {
            throw new Exception('type '.$type.' does not exists in template');
        }

        return $this->decorate($this->decorateUser($template[$type], $user));
    }*/

    protected function load(string $notification)
    {
        if (isset($this->templates[$notification])) {
            return $this->templates[$notification];
        }
        $path = $this->asset_dir.DIRECTORY_SEPARATOR.$notification.'.json';
        if (!is_readable($path)) {
            throw new Exception($path.' is not readable or does not exists');
        }

        return $this->templates[$notification] = json_decode(file_get_contents($path), true);
    }

    protected function loadMailTemplate(string $notification, string $locale)
    {
        if (isset($this->mail_templates[$notification.$locale])) {
            return $this->mail_templates[$notification.$locale];
        }
        $path = $this->asset_dir.DIRECTORY_SEPARATOR.'mail'.DIRECTORY_SEPARATOR.$notification.DIRECTORY_SEPARATOR.$locale.'.tpl';
        if (!is_readable($path)) {
            throw new Exception($path.' is not readable or does not exists');
        }

        return $this->mail_templates[$notification.$locale] = file_get_contents($path);
    }

    protected function parseMailTemplate(string $notification, User $user, NodeInterface $node): string
    {
        $locale = $user->getAttributes()['locale'];

        try {
            $template = $this->loadMailTemplate($notification, $locale);
        } catch (\Exception $e) {
            $template = $this->loadMailTemplate($notification, $this->fallback_locale);
        }

        return $this->decorate($this->decorateUser($this->decorateNode($template, $node), $user));
    }

    protected function parseTemplate(string $notification, string $type, User $user, NodeInterface $node): string
    {
        $template = $this->load($notification);
        $locale = $user->getAttributes()['locale'];

        if (!isset($template[$locale])) {
            $locale = $this->fallback_locale;
        }

        if (!isset($template[$locale])) {
            throw new Exception('locale '.$locale.' does not exists in template');
        }

        if (!isset($template[$locale][$type])) {
            throw new Exception('type '.$type.' does not exists in template');
        }

        return $this->decorate($this->decorateUser($this->decorateNode($template[$locale][$type], $node), $user));
    }

    /**
     * Replace variables.
     *
     * @param string $type
     * @param User   $user
     */
    protected function decorate(string $template): string
    {
        return preg_replace('/(\{server_url})/', $this->server->getServerUrl(), $template);
    }

    /**
     * Replace variables.
     *
     * @param string $type
     * @param User   $user
     */
    protected function decorateNode(string $template, NodeInterface $node): string
    {
        $decorator = $this->decorator;

        return preg_replace_callback('/(\{node\.(([a-z]\.*)+)\})/', function ($match) use ($node, $decorator) {
            $attrs = $decorator->decorate($node, [$match[2]]);
            if (isset($attrs[$match[2]])) {
                return $attrs[$match[2]];
            }

            return '';
        }, $template);
    }

    /**
     * Replace variables.
     *
     * @param string $type
     * @param User   $user
     */
    protected function decorateUser(string $template, User $user): string
    {
        $role_decorator = $this->role_decorator;

        return preg_replace_callback('/(\{user\.(([a-z]\.*)+)\})/', function ($match) use ($user, $role_decorator) {
            $attrs = $role_decorator->decorate($user, [$match[2]]);
            if (isset($attrs[$match[2]])) {
                return $attrs[$match[2]];
            }

            return '';
        }, $template);
    }
}
