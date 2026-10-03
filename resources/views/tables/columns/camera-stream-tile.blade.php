{{--
    Still-frame preview for the device list. A cached thumbnail rather than a
    live stream (see CameraThumbnailController) - the list shows every device
    at once and each live view costs an RTSP session on the device.

    Expects: $thumbnail (thumbnail URL, or null when the device has no camera
    or no known IP).
--}}
@php($thumbnail = $thumbnail ?? null)

@if($thumbnail)
    <img
        src="{{ $thumbnail }}"
        alt="Camera snapshot"
        style="width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border: 0;"
        class="rounded-lg shadow-lg"
    />
@endif
