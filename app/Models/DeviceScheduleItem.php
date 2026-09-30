<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceScheduleItem extends Model
{
    protected $fillable = ['device_schedule_id', 'item_id', 't', 'a', 'a1', 'a2'];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DeviceSchedule::class, 'device_schedule_id');
    }
}
