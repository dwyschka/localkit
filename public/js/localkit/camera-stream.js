/*
 * Live camera view for the device detail page.
 *
 * The devices only speak RTSP, which no browser can play, so localkit remuxes
 * the stream to MPEG-TS over plain HTTP (see CameraLiveController) and
 * mpegts.js hands those bytes to Media Source Extensions here. Nothing is
 * transcoded on the way, so what arrives is whatever the camera encoded -
 * usually H.264.
 *
 * Markup contract (resources/views/camera_stream.blade.php):
 *
 *   <div data-localkit-camera data-src="/camera/7/live.ts" data-poster="...">
 *     <video data-localkit-camera-video></video>
 *     <button data-localkit-camera-start>…</button>
 *     <p data-localkit-camera-status></p>
 *   </div>
 *
 * The stream is not started on page load. Every viewer holds an RTSP session
 * open on the device for as long as they watch, and the cameras only tolerate
 * a handful at a time - so playback begins when someone asks for it, and the
 * connection is torn down as soon as the element leaves the page.
 */
(function () {
    'use strict';

    var ATTACHED = '__localkitCameraAttached';
    var DESTROY = '__localkitCameraDestroy';
    var attached = [];

    function attach(container) {
        if (container[ATTACHED]) {
            return;
        }
        container[ATTACHED] = true;
        attached.push(container);

        var video = container.querySelector('[data-localkit-camera-video]');
        var startButton = container.querySelector('[data-localkit-camera-start]');
        var status = container.querySelector('[data-localkit-camera-status]');
        var src = container.getAttribute('data-src');
        var player = null;

        function say(message) {
            if (status) {
                status.textContent = message || '';
            }
        }

        function destroy() {
            if (!player) {
                return;
            }

            try {
                player.pause();
                player.unload();
                player.detachMediaElement();
                player.destroy();
            } catch (e) {
                // Tearing down a player that already errored throws; the
                // element is going away either way.
            }

            player = null;
        }

        function stop() {
            destroy();

            if (startButton) {
                startButton.hidden = false;
            }
            video.hidden = true;
        }

        function start() {
            if (!window.mpegts || !window.mpegts.isSupported()) {
                say('Live view needs Media Source Extensions - try Chrome, Edge or Firefox on desktop or Android.');
                if (startButton) {
                    startButton.disabled = true;
                }
                return;
            }

            destroy();
            say('Connecting to camera…');

            if (startButton) {
                startButton.hidden = true;
            }
            video.hidden = false;

            player = window.mpegts.createPlayer(
                { type: 'mpegts', isLive: true, url: src },
                {
                    enableWorker: true,
                    // The remux has no duration and no index, so the buffer
                    // only ever grows - chase the live edge instead of drifting
                    // further behind the camera the longer someone watches.
                    liveBufferLatencyChasing: true,
                    liveBufferLatencyMaxLatency: 2.0,
                    liveBufferLatencyMinRemain: 0.3,
                },
            );

            player.on(window.mpegts.Events.ERROR, function (type, detail) {
                // A 503 here means the device has no IP yet or ffmpeg could not
                // reach it; everything else is a stream that died mid-flight.
                say('Stream unavailable (' + type + ': ' + detail + ').');
                stop();
            });

            player.on(window.mpegts.Events.MEDIA_INFO, function () {
                say('');
            });

            player.attachMediaElement(video);
            player.load();

            var playback = video.play();
            if (playback && typeof playback.catch === 'function') {
                playback.catch(function () {
                    say('Press play to start the stream.');
                });
            }
        }

        if (startButton) {
            startButton.addEventListener('click', start);
        }

        // A player left attached to a <video> that Livewire has morphed away
        // keeps its RTSP session open on the device, so the container is torn
        // down the moment it leaves the document.
        container[DESTROY] = destroy;

        window.addEventListener('beforeunload', destroy);
    }

    function sync() {
        document.querySelectorAll('[data-localkit-camera]').forEach(attach);

        attached = attached.filter(function (container) {
            if (container.isConnected) {
                return true;
            }

            if (typeof container[DESTROY] === 'function') {
                container[DESTROY]();
            }

            return false;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sync);
    } else {
        sync();
    }

    // The Media section is inside a Filament form, so it arrives and leaves
    // through Livewire DOM morphs rather than page loads - watching the
    // document is the only reliable signal for both.
    if (typeof MutationObserver === 'function') {
        var pending = false;
        var observer = new MutationObserver(function () {
            if (pending) {
                return;
            }

            pending = true;
            window.requestAnimationFrame(function () {
                pending = false;
                sync();
            });
        });

        observer.observe(document.documentElement, { childList: true, subtree: true });
    }

    document.addEventListener('livewire:navigated', sync);
})();
