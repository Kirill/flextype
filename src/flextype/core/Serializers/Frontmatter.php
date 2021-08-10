<?php

declare(strict_types=1);

/**
 * Flextype (https://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

namespace Flextype\Serializers;

use function array_slice;
use function arrays;
use function cache;
use function count;
use function implode;
use function ltrim;
use function preg_replace;
use function preg_split;
use function registry;
use function serializers;
use function strings;

use const PHP_EOL;

class Frontmatter
{
    /**
     * Returns the FRONTMATTER representation of a value.
     *
     * @param mixed $input The PHP value.
     *
     * @return string A FRONTMATTER string representing the original PHP value.
     */
    public function encode($input): string
    {
        $headerSerializer = registry()->get('flextype.settings.serializers.frontmatter.encode.header.serializer');
        $allowed          = registry()->get('flextype.settings.serializers.frontmatter.encode.header.allowed');

        if ($headerSerializer === 'frontmatter') {
            $headerSerializer = 'yaml';
        }

        if (! in_array($headerSerializer,  $allowed)) {
            $headerSerializer = 'yaml';
        }

        if (isset($input['content'])) {
            $content = $input['content'];
            $input   = arrays($input)->delete('content')->toArray();
            $matter  = serializers()->{$headerSerializer}()->encode($input);
        } else {
            $content = '';
            $matter  = serializers()->{$headerSerializer}()->encode($input);
        }

        return '---' . "\n" . $matter . '---' . "\n" . $content;
    }

    /**
     * Takes a FRONTMATTER encoded string and converts it into a PHP variable.
     *
     * @param string $input A string containing FRONTMATTER.
     *
     * @return mixed The FRONTMATTER converted to a PHP value.
     */
    public function decode(string $input)
    {
        $headerSerializer = registry()->get('flextype.settings.serializers.frontmatter.decode.header.serializer');
        $cache            = registry()->get('flextype.settings.serializers.frontmatter.decode.cache');
        $allowed          = registry()->get('flextype.settings.serializers.frontmatter.encode.header.allowed');

        if ($headerSerializer === 'frontmatter') {
            $headerSerializer = 'yaml';
        }

        if (! in_array($headerSerializer,  $allowed)) {
            $headerSerializer = 'yaml';
        }

        $decode = static function (string $input) use ($headerSerializer) {
            // Remove UTF-8 BOM if it exists.
            $input = ltrim($input, "\xef\xbb\xbf");

            // Normalize line endings to Unix style.
            $input = (string) preg_replace("/(\r\n|\r)/", "\n", $input);

            // Parse Frontmatter and Body
            $parts = preg_split('/^[\s\r\n]?---[\s\r\n]?$/sm', PHP_EOL . strings($input)->trimLeft()->toString());

            if (count($parts) < 3) {
                return ['content' => strings($input)->trim()->toString()];
            }

            return serializers()->{$headerSerializer}()->decode(strings($parts[1])->trim()->toString(), false) + ['content' => strings(implode(PHP_EOL . '---' . PHP_EOL, array_slice($parts, 2)))->trim()->toString()];
        };

        if ($cache === true && registry()->get('flextype.settings.cache.enabled') === true) {
            $key = $this->getCacheID($input);

            if ($dataFromCache = cache()->get($key)) {
                return $dataFromCache;
            }

            $data = $decode($input);
            cache()->set($key, $data);

            return $data;
        }

        return $decode($input);
    }

    /**
     * Get Cache ID for frontmatter.
     *
     * @param  string $input Input.
     *
     * @return string Cache ID.
     *
     * @access public
     */
    public function getCacheID(string $input): string
    {
        return strings('frontmatter' . $input)->hash()->toString();
    }
}