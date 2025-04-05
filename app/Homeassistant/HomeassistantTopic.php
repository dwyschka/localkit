<?php

namespace App\Homeassistant;

use App\Helpers\HomeassistantHelper;
use Attribute;
use \App\Models\Device as DeviceModel;
#[Attribute(Attribute::TARGET_METHOD)]
class HomeassistantTopic
{

    public function __construct(
        public string $topic
    ) {
    }

    public function getTopic(DeviceModel $device): string
    {
        return sprintf('%s/%s', HomeassistantHelper::deviceTopic($device), $this->topic);
    }

}
