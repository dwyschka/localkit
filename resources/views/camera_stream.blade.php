{{--
    Live camera view for the device detail page.

    The device serves only RTSP, which no browser can play, so the stream is
    remuxed to MPEG-TS by CameraLiveController and played by mpegts.js - see
    public/js/localkit/camera-stream.js, which drives the markup below and is
    loaded panel-wide from PetkitPanelProvider.

    Playback starts on click, not on load: each viewer holds an RTSP session
    open on the device for as long as they watch, and the cameras only tolerate
    a handful at a time. Until then the cached thumbnail stands in as a poster.

    Expects: $live (MPEG-TS URL), $poster (thumbnail URL), $rtsp (the device's
    RTSP URL, shown so it can be pasted into VLC or Home Assistant).
--}}
@php
    $live = $live ?? null;
    $poster = $poster ?? null;
    $rtsp = $rtsp ?? null;
@endphp

@if($live)
    <div
        data-localkit-camera
        data-src="{{ $live }}"
        class="space-y-2"
    >
        <div class="relative overflow-hidden rounded-lg bg-gray-900 shadow-lg" style="aspect-ratio: 16 / 9;">
            <video
                data-localkit-camera-video
                hidden
                muted
                autoplay
                playsinline
                controls
                @if($poster) poster="{{ $poster }}" @endif
                class="absolute inset-0 h-full w-full bg-black"
            ></video>

            <button
                type="button"
                data-localkit-camera-start
                class="absolute inset-0 flex h-full w-full items-center justify-center"
                aria-label="Start live view"
            >
                @if($poster)
                    {{-- Still frame as the backdrop, so the tile is not just a
                         black box while nobody is watching. --}}
                    <img
                        src="{{ $poster }}"
                        alt=""
                        class="absolute inset-0 h-full w-full object-cover opacity-60"
                    />
                @endif

                <span class="relative flex items-center gap-2 rounded-full bg-white/90 px-4 py-2 text-sm font-medium text-gray-900 shadow">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.3 2.8A1 1 0 0 0 4.8 3.7v12.6a1 1 0 0 0 1.5.9l10.4-6.3a1 1 0 0 0 0-1.8L6.3 2.8Z" />
                    </svg>
                    Live view
                </span>
            </button>
        </div>

        <p data-localkit-camera-status class="text-sm text-gray-500 dark:text-gray-400"></p>

        @if($rtsp)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                RTSP: <code class="select-all">{{ $rtsp }}</code>
            </p>
        @endif
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">No camera stream available on this device</p>
@endif
