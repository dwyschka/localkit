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

}
