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

use Glowy\Arrays\Arrays as Collection;

// Directive: @var()
emitter()->addListener('onEntriesFetchSingleField', static function (): void {

    if (! registry()->get('flextype.settings.entries.directives.vars.enabled')) {
        return;
    }
    
    $field  = entries()->registry()->get('methods.fetch.field');
    $result = entries()->registry()->get('methods.fetch.result');

    if (is_string($field['value'])) {
        $field['value'] = preg_replace_callback('/@var\((.*?)\)/s', function($matches) use ($result) {
            return collection($result['vars'])->get($matches[1]);
        }, $field['value']);
    }

    entries()->registry()->set('methods.fetch.field.key', $field['key']);
    entries()->registry()->set('methods.fetch.field.value', $field['value']);
});