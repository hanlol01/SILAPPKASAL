<?php

return [
    'attachments' => [
        /*
         * Images remain fail-closed until both this flag and a runtime-verified
         * ContentImageProcessor implementation confirm safe metadata-stripping
         * re-encoding support.
         */
        'image_uploads_enabled' => (bool) env('CONTENT_IMAGE_UPLOADS_ENABLED', false),
        'cover_max_bytes' => 5 * 1024 * 1024,
        'inline_image_max_bytes' => 10 * 1024 * 1024,
        'attachment_max_bytes' => 10 * 1024 * 1024,
        'alt_text_max_length' => 500,
        'max_image_source_bytes' => 10 * 1024 * 1024,
        'max_image_dimension' => 6000,
        'max_image_pixels' => 24_000_000,
        'orphan_media_retention_hours' => max(
            24,
            (int) env('CONTENT_ORPHAN_MEDIA_RETENTION_HOURS', 168),
        ),
    ],
];
