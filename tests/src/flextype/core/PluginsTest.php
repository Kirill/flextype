<?php

declare(strict_types=1);

use Flextype\Flextype;
use Glowy\Strings\Strings;

beforeEach(function() {
    // Create sandbox plugin
    filesystem()->directory(PATH['project'])->ensureExists(0755, true);
    filesystem()->directory(PATH['project'] . '/plugins')->ensureExists(0755, true);
    filesystem()->directory(PATH['project'] . '/plugins/sandbox')->create(0755, true);
    filesystem()->directory(PATH['project'] . '/plugins/sandbox/lang/')->create(0755, true);
    filesystem()->file(PATH['project'] . '/plugins/sandbox/lang/en_US.yaml')->put('sandbox_title: Sandbox');
    filesystem()->file(PATH['project'] . '/plugins/sandbox/settings.yaml')->put('enabled: true');
    filesystem()->file(PATH['project'] . '/plugins/sandbox/plugin.yaml')->put('name: Sandbox');
    filesystem()->file(PATH['project'] . '/plugins/sandbox/plugin.php')->put('<?php ');
});

afterEach(function (): void {
    filesystem()->directory(PATH['project'] . '/plugins/sandbox')->delete();
});

test('get plugins list', function () {
    $this->assertTrue(is_array(plugins()->getPLuginsList()));
    $this->assertTrue(isset(plugins()->getPLuginsList()['sandbox']));
});

test('get plugins locales', function () {
    $this->assertTrue(is_array(plugins()->getLocales()));
    $this->assertTrue(isset(plugins()->getLocales()['en_US']));
});

test('get plugins dictionary', function () {
    $this->assertTrue(is_array(plugins()->getPluginsDictionary(plugins()->getPLuginsList(), 'en_US')));
    $this->assertTrue(isset(plugins()->getPluginsDictionary(plugins()->getPLuginsList(), 'en_US')['en_US']['sandbox_title']));
});

test('get plugins cache ID', function () {
    $md5 = plugins()->getPluginsCacheID(plugins()->getPLuginsList());
    $this->assertTrue(strlen($md5) == 32 && ctype_xdigit($md5));
});
