<?php

namespace App\MQTT;

use Illuminate\Http\Resources\Json\JsonResource;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

class AnswerDTO extends ValidatedDTO
{

    public string $topic;
    public JsonResource $message;

    protected function rules(): array
    {
        return [
            'topic' => ['required', 'string'],
            'message' => ['required'],
        ];
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function casts(): array
    {
        return [
            'topic' => new StringCast(),
        ];
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getMessage(): string
    {
        return $this->message->toJson(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
