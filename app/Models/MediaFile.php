<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metadata for a camera capture uploaded via `dev_upload_file_info_v2`.
 *
 * The bytes themselves live on the object storage disk (see
 * DeviceObjectStorage) under `object_key`; this row is what lets
 * MediaFileController find them by `file_id`. DevUploadFileInfoV2Controller
 * decrypts the object in place as soon as `aes_iv` is known, so `decrypted`
 * tracks whether that's happened yet.
 */
class MediaFile extends Model
{
    protected $fillable = [
        'file_id', 'device_id', 'event_id', 'module_type', 'file_type',
        'object_key', 'segments', 'aes_iv', 'decrypted',
        'pet_score', 'eat_score', 'move_score', 'feed_score',
        'start_time', 'end_time', 'duration', 'size',
    ];

    protected $casts = [
        'decrypted' => 'boolean',
        'segments' => 'array',
    ];

    public function isVideo(): bool
    {
        return str_ends_with($this->file_id, '.ts') || str_contains((string) $this->file_type, 'video');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
