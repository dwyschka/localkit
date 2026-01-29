<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class History extends Model
{
    protected $table = 'history';
    protected $fillable = ['messageId', 'message', 'pet_id', 'device_id', 'parameters', 'type'];

    protected $casts = [
        'parameters' => 'array',
    ];

    public function pet(): HasOne {
        return $this->hasOne(Pet::class, 'id', 'pet_id');
    }

    public function duration(): float {
        return $this->created_at->diffInSeconds($this->updated_at);
    }

    public function message(): string {

        switch($this->type) {
            case 'IN_USE':
                return $this->createInUseMessage();
            case 'CLEANING':
                return $this->createCleaningMessage();
            case 'MAINTENANCE':
                return $this->createMaintenanceMessage();
        }
        return '';
    }

    public function title(): string {

        return __(sprintf('petkit.history.%s_title', Str::lower($this->type)));
    }

    private function createInUseMessage()
    {
        $params = $this->parameters;

        $duration = $params['time_out'] - $params['time_in'];

        return __('petkit.history.in_use', [
            'name' => $this->pet?->name ?? __('petkit.unknown'),
            'weight' => round($params['pet_weight'] / 1000, 2),
            'duration' => $duration,
        ]);
    }

    private function createCleaningMessage()
    {
        return __('petkit.history.cleaning', [
            'name' => $this->pet?->name ?? __('petkit.unknown')
        ]);
    }

    private function createMaintenanceMessage()
    {
        $params = $this->parameters;

        $duration = 0;
        if(isset($params['over_time']) && isset($params['start_time'])) {
            $duration = $params['over_time'] - $params['start_time'];
        }


        return __('petkit.history.maintenance', [
            'duration' => $duration
        ]);
    }
}
