@php
    $cfg = $device->configuration ?? [];
    $percent = data_get($cfg, 'litter.percent');
    $weight  = data_get($cfg, 'litter.weight');
    $n50next = data_get($cfg, 'consumables.n50NextChange');
    $n50days = null;
    if (! empty($n50next)) {
        try { $n50days = (int) round(now()->diffInDays(\Illuminate\Support\Carbon::parse($n50next), false)); } catch (\Throwable $e) {}
    }
    $uses = $device->exists ? $device->histories()->whereDate('created_at', now()->toDateString())->where('type', 'IN_USE')->count() : 0;
    $connected = (string) $device->mqtt_connected === '1';
    $typeName = match ($device->device_type) {
        't4'  => 'Petkit Pura Max',
        'd4'  => 'Petkit FreshElement Solo',
        'd4h' => 'Petkit YumShare Solo',
        default => $device->device_type,
    };
    $isLitter = $device->device_type === 't4';

    $stats = [];
    if ($isLitter) {
        $stats[] = ['v' => $percent !== null ? $percent . '%' : '–', 'k' => 'Litter level'];
        $stats[] = ['v' => $n50days !== null ? $n50days : '–', 'k' => 'N50 days left'];
        $stats[] = ['v' => $uses, 'k' => 'Uses today'];
        $stats[] = ['v' => $weight !== null ? number_format($weight / 1000, 1) . ' kg' : '–', 'k' => 'Litter weight'];
    } else {
        $stats[] = ['v' => $device->firmware ?? '–', 'k' => 'Firmware'];
        $stats[] = ['v' => $uses, 'k' => 'Events today'];
    }
@endphp

<div style="display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap;
            background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.08);border-radius:0.9rem;padding:18px 20px">
    <div style="display:flex;align-items:center;gap:15px">
        <span style="width:52px;height:52px;border-radius:14px;flex:none;display:flex;align-items:center;justify-content:center;
                     background:rgba(245,158,11,.14);color:#fbbf24">
            @if ($isLitter)
                @svg('heroicon-o-archive-box', ['style' => 'width:27px;height:27px'])
            @else
                @svg('heroicon-o-inbox-stack', ['style' => 'width:27px;height:27px'])
            @endif
        </span>
        <div>
            <div style="font-size:19px;font-weight:800;letter-spacing:-.02em;" class="text-gray-950 dark:text-white">{{ $device->name }}</div>
            <div style="display:flex;align-items:center;gap:9px;margin-top:6px;flex-wrap:wrap;color:#a1a1aa;font-size:13px">
                <span style="display:inline-flex;align-items:center;height:22px;padding:0 9px;border-radius:6px;background:rgba(255,255,255,.07);font-weight:600">{{ $typeName }}</span>
                <span style="width:3px;height:3px;border-radius:50%;background:#71717a"></span>
                <span style="display:inline-flex;align-items:center;gap:6px;font-weight:600;color:{{ $connected ? '#4ade80' : '#f87171' }}">
                    <span style="width:8px;height:8px;border-radius:50%;background:currentColor"></span>{{ $connected ? 'Connected' : 'Disconnected' }}
                </span>
                <span style="width:3px;height:3px;border-radius:50%;background:#71717a"></span>
                <span>Firmware {{ $device->firmware ?? '–' }}</span>
            </div>
        </div>
    </div>
    <div style="display:flex;gap:26px;flex-wrap:wrap">
        @foreach ($stats as $s)
            <div style="display:flex;flex-direction:column;gap:5px;min-width:72px">
                <span style="font-size:19px;font-weight:800;letter-spacing:-.01em;" class="text-gray-950 dark:text-white">{{ $s['v'] }}</span>
                <span style="font-size:12px;color:#71717a;font-weight:500">{{ $s['k'] }}</span>
            </div>
        @endforeach
    </div>
</div>
