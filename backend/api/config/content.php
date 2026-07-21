<?php

return [
    'attachments' => [
        /*
         * Images remain fail-closed until both this flag and a runtime-verified
         * ContentImageProcessor implementation confirm safe metadata-stripping
         * re-encoding support.
         */
        'image_uploads_enabled' => (bool) env('CONTENT_IMAGE_UPLOADS_ENABLED', false),
    ],
];
