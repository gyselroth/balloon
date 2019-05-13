<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Balloon\Server\User;
use InvalidArgumentException;

class TemplateHandler
{
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
     * Loaded locales.
     *
     * @var array
     */
    protected $locales = [];

    /**
     * Server.
     *
     * @var string
     */
    protected $server;

    /**
     * Server context.
     *
     * @var array
     */
    protected $context = [];

    /**
     * Constructor.
     */
    public function __construct(Server $server, AttributeDecorator $decorator, RoleAttributeDecorator $role_decorator)
    {
        $this->decorator = $decorator;
        $this->role_decorator = $role_decorator;
        $this->context['server_url'] = $server->getServerUrl();
    }

    /**
     * Set config.
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
                    throw new InvalidArgumentException('invalid option '.$option.' given');
             }
        }

        return $this;
    }

    /**
     * Parse body.
     */
    public function getBody(string $notification, array $context = []): string
    {
        return $this->parseString($notification, 'body', $context + $this->context);
    }

    /**
     * Parse subject.
     */
    public function getSubject(string $notification, array $context = []): string
    {
        return $this->parseString($notification, 'subject', $context + $this->context);
    }

    /**
     * Render template.
     */
    public function renderTemplate(string $notification, string $template, array $context = []): string
    {
        $path = $this->asset_dir.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.$template;

        $context += [
            'subject' => $this->parseString($notification, 'subject', $context + $this->context),
            'body' => $this->parseString($notification, 'body', $context + $this->context),
            'type' => $notification,
        ];

        $template = new Template($path, $this->getLocale($context), $context + $this->context);

        return $template->render();
    }

    /**
     * Load locale.
     */
    protected function loadLocale(string $locale): ?array
    {
        if (isset($this->locales[$locale])) {
            return $this->locales[$locale];
        }

        $path = $this->asset_dir.DIRECTORY_SEPARATOR.'locales'.DIRECTORY_SEPARATOR.$locale.'.json';
        if (is_readable($path)) {
            $i18n = json_decode(file_get_contents($path));

            if (json_last_error() !== 0) {
                throw new Exception\TemplateInvalidLocale('locale '.$locale.' is invalid json');
            }

            return $this->locales[$locale] = json_decode(file_get_contents($path), true);
        }

        return null;
    }

    /**
     * Get correct locale for context.
     */
    protected function getLocale(array $context)
    {
        $locale = null;

        if (isset($context['user'])) {
            $locale = $context['user']->getAttributes()['locale'];
        } elseif (isset($context['sender'])) {
            $locale = $context['sender']->getAttributes()['locale'];
        } else {
            $locale = $this->fallback_locale;
        }

        $i18n = $this->loadLocale($locale);

        if ($i18n === null && $locale !== $this->fallback_locale) {
            $locale = $this->fallback_locale;
            $i18n = $this->loadLocale($locale);
        }

        if ($i18n === null) {
            throw new Exception\TemplateInvalidLocale('locale '.$locale.' does not exists');
        }

        return $i18n;
    }

    /**
     * Parse locale string.
     */
    protected function parseString(string $notification, string $type, array $context): string
    {
        $i18n = $this->getLocale($context);

        if (!isset($i18n['type'][$notification][$type])) {
            throw new Exception\TemplateInvalidLocale('locale does not have a type '.$type);
        }

        $string = $i18n['type'][$notification][$type];

        foreach ($context as $key => $value) {
            if ($value instanceof User) {
                $string = $this->decorateResource($string, $value, $key, $this->role_decorator);
            } elseif ($value instanceof NodeInterface) {
                $string = $this->decorateResource($string, $value, $key, $this->decorator);
            }
        }

        return $this->decorate($string, $context);
    }

    /**
     * Replace variables.
     */
    protected function decorate(string $template, array $context): string
    {
        return preg_replace_callback('/\{([^}\.]*)\}/', function ($match) use ($context) {
            $key = $match[1];

            if (isset($context[$key])) {
                return $context[$key];
            }
        }, $template);
    }

    /**
     * Replace variables.
     */
    protected function decorateResource(string $template, $resource, string $prefix, $decorator): string
    {
        return preg_replace_callback('/(\{'.$prefix.'\.(([a-z]\.*)+)\})/', function ($match) use ($resource, $decorator) {
            $key = explode('.', $match[2]);
            $key = array_shift($key);
            $attrs = $decorator->decorate($resource, [$key]);

            return $this->getArrayValue($attrs, $match[2]);
        }, $template);
    }

    /**
     * Get array value via string path.
     */
    protected function getArrayValue(iterable $array, string $path, string $separator = '.')
    {
        if (isset($array[$path])) {
            return $array[$path];
        }
        $keys = explode($separator, $path);

        foreach ($keys as $key) {
            if (!isset($array[$key])) {
                return '';
            }

            $array = $array[$key];
        }

        return $array;
    }
}
