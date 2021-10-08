<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification;

class Template
{
    /**
     * Template path.
     *
     * @var string
     */
    protected $template;
    /**
     * Variables.
     *
     * @var array
     */
    private $variables = [];

    /**
     * Current locale i18n data.
     *
     * @var array
     */
    private $locale = [];

    /**
     * Constructor.
     */
    public function __construct(string $path, array $locale, array $variables)
    {
        $this->locale = $locale;
        $this->variables = $variables;

        if (is_readable($path)) {
            $this->template = $path;
        } else {
            throw new Exception\TemplateNotFound('template '.$path.' not found');
        }
    }

    /**
     * Get variable by key.
     */
    public function __get(string $key)
    {
        if (isset($this->variables[$key])) {
            return $this->variables[$key];
        }
    }

    /**
     * Render template.
     */
    public function render(): string
    {
        ob_start();
        require $this->template;

        return ob_get_clean();
    }

    /**
     * Trabslate locale string.
     */
    public function translate(string $key): string
    {
        if (isset($this->locale[$key])) {
            return $this->locale[$key];
        }

        return $key;
    }
}
