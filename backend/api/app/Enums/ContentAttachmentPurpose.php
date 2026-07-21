<?php

namespace App\Enums;

enum ContentAttachmentPurpose: string
{
    case Cover = 'cover';
    case InlineImage = 'inline_image';
    case Attachment = 'attachment';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
