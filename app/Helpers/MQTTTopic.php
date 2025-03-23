<?php

namespace App\Helpers;

class MQTTTopic
{

    public static function topic2Method(string $topic) {
        [,$method] = explode('/thing/', $topic);
        $method = str_replace('/', '.', $method);
        $method = str_replace('_reply', '', $method);

        return $method;
    }
}
