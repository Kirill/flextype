<?php

declare(strict_types=1);

 /**
 * Flextype - Hybrid Content Management System with the freedom of a headless CMS 
 * and with the full functionality of a traditional CMS!
 * 
 * Copyright (c) Sergey Romanenko (https://awilum.github.io)
 *
 * Licensed under The MIT License.
 *
 * For full copyright and license information, please see the LICENSE
 * Redistributions of files must retain the above copyright notice.
 */

namespace Flextype\Parsers;

use Exception;
use Thunder\Shortcode\ShortcodeFacade;

use function cache;
use function count;
use function file_exists;
use function is_array;
use function registry;
use function strings;

final class Shortcodes
{
    /**
     * Shortcodes instance.
     */
    private static ?Shortcodes $instance = null;

    /**
     * Shortcode facade.
     */
    private $shortcodeFacade = null;

    /**
     * Shortcode should not be cloneable.
     */
    protected function __clone()
    {
        throw new Exception('Cannot clone a Shortcode.');
    }

    /**
     * Shortcode should not be restorable from strings.
     */
    public function __wakeup(): void
    {
        throw new Exception('Cannot unserialize a Shortcode.');
    }

    /**
     * Shortcode construct.
     */
    protected function __construct()
    {
        $this->shortcodeFacade = new ShortcodeFacade();
    }

    /**
     * Gets the instance via lazy initialization (created on first usage).
     */
    public static function getInstance(): Shortcodes
    {
        if (static::$instance === null) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    /**
     * Shortcode facade.
     */
    public function facade(): ShortcodeFacade
    {
        return $this->shortcodeFacade;
    }

    /**
     * Init Shortcodes
     * 
     * @param array $shortcodes Shortcoes to init.
     * 
     * @return void
     */
    public function initShortcodes(array $shortcodes): void
    {
        if (
            ! isset($shortcodes) ||
            ! is_array($shortcodes) ||
            count($shortcodes) <= 0
        ) {
            return;
        }

        foreach ($shortcodes as $shortcode) {
            if (! isset($shortcode['enabled'])) {
                continue;
            }

            if (! $shortcode['enabled']) {
                continue;
            }

            if (! file_exists(ROOT_DIR . $shortcode['path'])) {
                continue;
            }

            include_once ROOT_DIR . $shortcode['path'];
        }
    }

    /**
     * Add shortcode handler.
     *
     * @param string   $name    Shortcode.
     * @param callable $handler Handler.
     *
     * @access public
     */
    public function addHandler(string $name, callable $handler)
    {
        return $this->facade()->addHandler($name, $handler);
    }

    /**
     * Add event handler.
     *
     * @param string   $name    Event.
     * @param callable $handler Handler.
     *
     * @access public
     */
    public function addEventHandler(string $name, callable $handler)
    {
        return $this->facade()->addEventHandler($name, $handler);
    }

    /**
     * Parses text into shortcodes.
     *
     * @param string $input A text containing SHORTCODE
     *
     * @access public
     */
    public function parseText(string $input)
    {
        return $this->facade()->parse($input);
    }

    /**
     * Parse and processes text to replaces shortcodes.
     *
     * @param string $input A text containing SHORTCODE
     *
     * @access public
     */
    public function parse(string $input)
    {
        $cache = registry()->get('flextype.settings.parsers.shortcodes.cache');

        if ($cache === true && registry()->get('flextype.settings.cache.enabled') === true) {
            $key = $this->getCacheID($input);

            if ($dataFromCache = cache()->get($key)) {
                return $dataFromCache;
            }

            $data = $this->facade()->process($input);
            cache()->set($key, $data);

            return $data;
        }

        return $this->facade()->process($input);
    }

    /**
     * Get Cache ID for shortcode.
     *
     * @param  string $input Input.
     *
     * @return string Cache ID.
     *
     * @access public
     */
    public function getCacheID(string $input): string
    {
        return strings('shortcode' . $input)->hash()->toString();
    }
}
