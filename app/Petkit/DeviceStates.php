<?php

namespace App\Petkit;

enum DeviceStates: string
{
    case IDLE = 'IDLE';
    case WORKING = 'WORKING';
    case ERROR = 'ERROR';
    case OFFLINE = 'OFFLINE';
    case ONLINE = 'ONLINE';
    case CLEANING = 'CLEANING';
    case MAINTENANCE = 'MAINTENANCE';
    case UPDATING = 'UPDATING';
    case PET_IN = 'IN USE';

    /**
     * Returns all raw device state string values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Determines whether the given activity type represents a device-level state rather than pet activity.
     */
    public static function isDeviceState(?string $type): bool
    {
        return $type !== null && in_array($type, self::values(), true);
    }
}
