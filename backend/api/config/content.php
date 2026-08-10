<?php

return [
    'attachments' => [
        /*
         * Images remain fail-closed unless a runtime-verified ContentImageProcessor
         * confirms safe metadata-stripping re-encoding support. The environment flag
         * may still disable image uploads explicitly.
         */
        'image_uploads_enabled' => (bool) env('CONTENT_IMAGE_UPLOADS_ENABLED', true),
        'cover_max_bytes' => 5 * 1024 * 1024,
        'inline_image_max_bytes' => 10 * 1024 * 1024,
        'attachment_max_bytes' => 10 * 1024 * 1024,
        'alt_text_max_length' => 500,
        'max_image_source_bytes' => 10 * 1024 * 1024,
        'max_image_dimension' => 6000,
        'max_image_pixels' => 24_000_000,
        // Large editorial images are reduced before private storage. Source files remain bounded
        // so image processing cannot exhaust server memory.
        'image_optimization_trigger_bytes' => 2 * 1024 * 1024,
        'optimized_image_max_dimension' => 2560,
        'jpeg_quality' => 82,
        'webp_quality' => 80,
        'png_compression' => 8,
        'orphan_media_retention_hours' => max(
            24,
            (int) env('CONTENT_ORPHAN_MEDIA_RETENTION_HOURS', 168),
        ),
    ],
];
