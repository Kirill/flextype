<?php

use Flextype\Component\Filesystem\Filesystem;

beforeEach(function() {
    filesystem()->directory(PATH['project'] . '/entries')->create();
});

afterEach(function (): void {
    filesystem()->directory(PATH['project'] . '/entries')->delete();
});

test('PublishedAtField', function () {
    entries()->create('foo', []);

    $published_at = entries()->fetch('foo')['published_at'];

    $this->assertTrue(strlen($published_at) > 0);
    $this->assertTrue((strtotime(date('Y-m-d H:i:s', $published_at)) === (int)$published_at));
});
