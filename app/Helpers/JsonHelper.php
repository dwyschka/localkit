<?php

namespace App\Helpers;

class JsonHelper
{

    public static function difference($array1, $array2, $checkValues = true) {
        $result = [];

        foreach ($array1 as $key => $value) {
            // If the key doesn't exist in array2
            if (!array_key_exists($key, $array2)) {
                $result[$key] = $value;
                continue;
            }

            // If both values are arrays, recurse
            if (is_array($value) && is_array($array2[$key])) {
                $recursiveDiff = self::difference($value, $array2[$key], $checkValues);
                if (count($recursiveDiff) > 0) {
                    $result[$key] = $recursiveDiff;
                }
                continue;
            }

            // If we're checking values and they're different
            if ($checkValues && $value !== $array2[$key]) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
