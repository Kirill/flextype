<?php

declare(strict_types=1);

/**
 * Flextype (https://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

emitter()->addListener('onMediaFetchSingleHasResult', static function (): void {

    if (! registry()->get('flextype.settings.entries.media.fields.modified_at.enabled')) {
        return;
    }

    if (content()->registry()->get('fetch.data.modified_at') !== null) {
        return;
    }

    content()->registry()->set('fetch.data.modified_at', (int) filesystem()->file(content()->getFileLocation(content()->registry()->get('fetch.id')))->lastModified());
});