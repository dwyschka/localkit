<?php

namespace App\Http\Resources\MQTT;

use stdClass;
use App\Helpers\MQTTTopic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The ack the device waits for after a message it sent with `sys.ack: 1`.
 *
 * Captured from the real cloud:
 *
 *   {"code":200,"data":{},"id":"107","message":"success",
 *    "method":"thing.event.work_start.post","version":"1.0"}
 *
 * `method` matters: the device's own `thing/event/<event>/post` messages carry no
 * top-level `method` (only `id`, `version`, `params`, `sys`), so reading it
 * off the incoming message yields null - and a JSON null in that slot is what
 * the firmware's cJSON parser reads back through valuestring with no NULL
 * check (same hazard schedule.md §3d documents for 'id'/'re'). It is derived
 * from the topic instead, which is where the information actually is.
 */
class SuccessResource extends JsonResource
{
    protected ?string $replyTopic = null;

    /**
     * The topic of the message being acked (the `.../post`, not the
     * `.../post_reply`) - used to derive `method` when the message has none.
     */
    public function forTopic(?string $topic): self
    {
        $this->replyTopic = $topic;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = new stdClass();
        $id = $this->resource->id ?? null;

        return [
            'code' => 200,
            'data' => $data,
            'id' => is_scalar($id) ? (string) $id : $id,
            'message' => "success",
            'method' => $this->resource->method
                ?? ($this->replyTopic !== null ? MQTTTopic::topic2Method($this->replyTopic) : null),
            'version' => "1.0"
        ];
    }

}
