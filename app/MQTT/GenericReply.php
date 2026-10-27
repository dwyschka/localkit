<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\SuccessResource;
use Illuminate\Support\Facades\Log;

class GenericReply
{

    public static function reply(string $topic, mixed $message): AnswerDTO
    {
        return new AnswerDTO(
            topic: sprintf('%s_reply', $topic),
            // The incoming topic, not the reply topic: it is what the ack's
            // 'method' is derived from when the message carries none.
            message: SuccessResource::make($message)->forTopic($topic)
        );
    }
}
