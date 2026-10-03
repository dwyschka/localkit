{{--
    Live camera view for the device detail page.

    The device serves only RTSP, which no browser can play, so the stream is
    remuxed to MPEG-TS by CameraLiveController and played by mpegts.js - see
    public/js/localkit/camera-stream.js, which drives the markup below and is
    loaded panel-wide from PetkitPanelProvider.

    Playback starts on click, not on load: each viewer holds an RTSP session
    open on the device for as long as they watch, and the cameras only tolerate
    a handful at a time. Until then the cached thumbnail stands in as a poster.

    All styling is scoped and self-contained on purpose. The panel does not
    load resources/css/app.css (only welcome.blade.php does), so none of the
    project's Tailwind utilities exist here - a class like `rounded-lg` or
    `absolute` simply does nothing inside Filament. Colours come from
    Filament's own custom properties so the component follows the theme, and
    dark mode keys off the `.dark` class Filament puts on <html>.

    Expects: $live (MPEG-TS URL), $poster (thumbnail URL), $rtsp (the device's
    RTSP URL, shown so it can be pasted into VLC or Home Assistant).
--}}
@php
    $live = $live ?? null;
    $poster = $poster ?? null;
    $rtsp = $rtsp ?? null;
@endphp

@once
    <style>
        .lk-cam {
            --lk-radius: 0.75rem;
            --lk-border: var(--color-gray-200, #e5e7eb);
            --lk-muted: var(--color-gray-500, #6b7280);
            --lk-surface: var(--color-gray-100, #f3f4f6);
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            width: 100%;
        }

        .dark .lk-cam {
            --lk-border: var(--color-gray-700, #374151);
            --lk-muted: var(--color-gray-400, #9ca3af);
            --lk-surface: var(--color-gray-800, #1f2937);
        }

        /* Fixed 16:9 frame, so switching between poster and <video> does not
           make the whole form jump. */
        .lk-cam__frame {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            border-radius: var(--lk-radius);
            border: 1px solid var(--lk-border);
            background: #0b0f19;
        }

        .lk-cam__video {
            position: absolute;
            inset: 0;
            display: block;
            width: 100%;
            height: 100%;
            background: #000;
            object-fit: contain;
        }

        .lk-cam__cover {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0;
            border: 0;
            cursor: pointer;
            background: transparent;
            appearance: none;
        }

        .lk-cam__poster {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(0.55);
            transition: filter 150ms ease, transform 300ms ease;
        }

        .lk-cam__cover:hover .lk-cam__poster {
            filter: brightness(0.7);
            transform: scale(1.02);
        }

        /* Play affordance. Sits above the poster and keeps its own contrast
           regardless of how bright the frame behind it is. */
        .lk-cam__play {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1.1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1;
            color: #fff;
            background: rgba(17, 24, 39, 0.72);
            border: 1px solid rgba(255, 255, 255, 0.28);
            backdrop-filter: blur(6px);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
            transition: background 150ms ease, transform 150ms ease;
        }

        .lk-cam__cover:hover .lk-cam__play {
            background: var(--color-primary-600, #2563eb);
            border-color: transparent;
            transform: translateY(-1px);
        }

        .lk-cam__cover:focus-visible {
            outline: 2px solid var(--color-primary-500, #3b82f6);
            outline-offset: -3px;
        }

        .lk-cam__play svg {
            width: 1.05rem;
            height: 1.05rem;
            flex: none;
        }

        /* "LIVE" badge, only while the stream is actually running. */
        .lk-cam__badge {
            position: absolute;
            top: 0.6rem;
            left: 0.6rem;
            z-index: 2;
            display: none;
            align-items: center;
            gap: 0.4rem;
            padding: 0.2rem 0.55rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: #fff;
            background: rgba(17, 24, 39, 0.72);
            backdrop-filter: blur(6px);
        }

        .lk-cam[data-state="playing"] .lk-cam__badge {
            display: inline-flex;
        }

        .lk-cam__dot {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 9999px;
            background: #ef4444;
            animation: lk-cam-pulse 1.6s ease-in-out infinite;
        }

        @keyframes lk-cam-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.25; }
        }

        @media (prefers-reduced-motion: reduce) {
            .lk-cam__dot { animation: none; }
            .lk-cam__poster, .lk-cam__play { transition: none; }
        }

        /* Footer: status line on the left, RTSP URL on the right. Wraps to two
           rows on narrow screens instead of squashing the URL. */
        .lk-cam__meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem 1rem;
            font-size: 0.75rem;
            color: var(--lk-muted);
        }

        .lk-cam__status:empty {
            display: none;
        }

        .lk-cam__url {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-left: auto;
        }

        .lk-cam__url code {
            user-select: all;
            padding: 0.15rem 0.45rem;
            border-radius: 0.375rem;
            border: 1px solid var(--lk-border);
            background: var(--lk-surface);
            font-size: 0.6875rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            color: inherit;
            white-space: nowrap;
        }
    </style>
@endonce

@if($live)
    <div class="lk-cam" data-localkit-camera data-src="{{ $live }}" data-state="idle">
        <div class="lk-cam__frame">
            <span class="lk-cam__badge"><span class="lk-cam__dot"></span>LIVE</span>

            <video
                class="lk-cam__video"
                data-localkit-camera-video
                hidden
                muted
                autoplay
                playsinline
                controls
                @if($poster) poster="{{ $poster }}" @endif
            ></video>

            <button type="button" class="lk-cam__cover" data-localkit-camera-start aria-label="Start live view">
                @if($poster)
                    {{-- Still frame as the backdrop, so the tile is not just a
                         black box while nobody is watching. --}}
                    <img class="lk-cam__poster" src="{{ $poster }}" alt="" />
                @endif

                <span class="lk-cam__play">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.3 2.8A1 1 0 0 0 4.8 3.7v12.6a1 1 0 0 0 1.5.9l10.4-6.3a1 1 0 0 0 0-1.8L6.3 2.8Z" />
                    </svg>
                    Live view
                </span>
            </button>
        </div>

        <div class="lk-cam__meta">
            <span class="lk-cam__status" data-localkit-camera-status></span>

            @if($rtsp)
                <span class="lk-cam__url">RTSP <code>{{ $rtsp }}</code></span>
            @endif
        </div>
    </div>
@else
    <p class="lk-cam__empty" style="font-size:0.875rem;color:var(--color-gray-500,#6b7280);">
        No camera stream available on this device
    </p>
@endif
