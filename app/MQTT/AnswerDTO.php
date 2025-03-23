<?php

namespace App\MQTT;

use Illuminate\Http\Resources\Json\JsonResource;

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
        return $this->message->toJson(JSON_UNESCAPED_SLASHES);
    }
}
