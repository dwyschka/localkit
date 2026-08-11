@php($streams = $streams ?? (isset($stream) ? ['camera' => $stream] : []))
<div class="text-center space-y-4">
    @forelse($streams as $name => $url)
        <div>
            <p class="text-sm font-medium mb-1">{{ $name }}</p>
            <img
                style="margin: 0 auto"
                src="{{ $url }}"
                width="640"
                height="360"
                alt="{{ $name }}"
                class="rounded-lg shadow-lg"
            />
        </div>
    @empty
        <p class="text-sm text-gray-500">No streams available on this device</p>
    @endforelse
</div>
