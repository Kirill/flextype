<?php

use Flextype\Component\Filesystem\Filesystem;

beforeEach(function() {
    filesystem()->directory(PATH['project'] . '/entries')->create();
});

afterEach(function (): void {
    filesystem()->directory(PATH['project'] . '/entries')->delete();
});

test('CreatedAtField', function () {
    // 1
    entries()->create('foo', []);
    $created_at = entries()->fetch('foo')['created_at'];
    $this->assertTrue(strlen($created_at) > 0);
    $this->assertTrue((strtotime(date('Y-m-d H:i:s', $created_at)) === (int)$created_at));
});
