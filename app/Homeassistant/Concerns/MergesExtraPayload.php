<?php

namespace App\Homeassistant\Concerns;

use App\Helpers\HomeassistantHelper;

/**
 * Shared plumbing for entity classes whose payload shape is too varied for
 * BaseEntity's generic builder (multiple named command/state topics,
 * platform-specific keys) - keeps each class's payload() to just the keys
 * that are actually its own, instead of hand-rolling topic prefixing and
 * null-filtering in every file.
 */
trait MergesExtraPayload
{
    private function withExtra(array $config, array $extra): array
    {
        foreach ($extra as $key => $value) {
            if ($value !== null && $value !== []) {
                $config[$key] = $value;
            }
        }

        return $config;
    }

    private function topic(?string $suffix): ?string
    {
        return $suffix === null ? null : HomeassistantHelper::deviceTopic($this->device) . '/' . $suffix;
    }
}
