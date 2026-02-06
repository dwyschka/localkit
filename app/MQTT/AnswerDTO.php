<?php

namespace App\MQTT;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class AnswerDTO
{


    public function __construct(
        protected string $topic,
        protected JsonResource $message
    )
    {
    }

    public function getTopic(): string {
        return $this->topic;
    }

    public function getMessage(): string {
        return Str::replace(PHP_EOL, '', $this->message->toJson(JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
