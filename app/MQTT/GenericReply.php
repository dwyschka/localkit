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
            message: SuccessResource::make($message)
        );
    }
}
