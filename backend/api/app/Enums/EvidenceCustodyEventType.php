<?php

namespace App\Enums;

enum EvidenceCustodyEventType: string
{
    case Registered = 'registered';
    case MetadataUpdated = 'metadata_updated';
    case StatusChanged = 'status_changed';
    case Reviewed = 'reviewed';
    case FileUploaded = 'file_uploaded';
    case FileDownloaded = 'file_downloaded';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $eventType): string => $eventType->value,
            self::cases()
        );
    }
}
