<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\MediaFile;
use App\Petkit\Storage\DeviceObjectStorage;
use App\Petkit\Storage\MediaDecryptor;
use App\Petkit\Storage\VideoRemuxer;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives the metadata report the camera sends after uploading one or more
 * capture files to object storage (see DeviceObjectStorage /
 * ObjectStorageController). Body is `application/x-www-form-urlencoded`
 * with a single `fileInfos` field holding a JSON array.
 *
 * This is also the earliest point the object's AES IV is known (the PUT
 * upload itself carries no metadata), so it's where each object gets
 * decrypted in place - the storage disk ends up holding plaintext only,
 * and MediaFileController never has to touch the key.
 *
 * Must always answer 200 - the device treats anything else as the report
 * being lost and does not retry.
 */
class DevUploadFileInfoV2Controller extends Controller
{
    public function __construct(private readonly DeviceObjectStorage $storage)
    {
    }

    public function __invoke(string $deviceType, Request $request): JsonResponse
    {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        $fileInfos = json_decode((string) $request->input('fileInfos', '[]'), true) ?: [];

        Log::info('dev_upload_file_info_v2 received', ['deviceId' => $deviceId, 'fileInfos' => $fileInfos]);

        foreach ($fileInfos as $fileInfo) {
            if (empty($fileInfo['fileId'])) {
                continue;
            }

            // One bad file (ffmpeg hiccup, decrypt failure, whatever) must
            // not abort the rest of the batch - this endpoint has to answer
            // 200 no matter what, or the device treats the *entire* report
            // as lost and won't retry any of it (see class docblock).
            try {
                $this->processFileInfo($deviceType, $deviceId, $device, $fileInfo);
            } catch (Throwable $e) {
                Log::error('dev_upload_file_info_v2 entry failed, skipping', [
                    'fileId' => $fileInfo['fileId'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new JsonResponse(['result' => []]);
    }

    private function processFileInfo(string $deviceType, string $deviceId, Device $device, array $fileInfo): void
    {
        // CLOUD_STORAGE segments are ~4s chunks of one continuous recording
        // (each segment's startTime == the previous one's endTime) that
        // arrive spread across several of these reports over time, not all
        // at once - EVENT_PREVIEW/EVENT_VIDEO are standalone files and go
        // through the normal per-file path below.
        if (config('localkit.storage.decrypt_on_upload')
            && ($fileInfo['moduleType'] ?? null) === 'CLOUD_STORAGE'
            && ! empty($fileInfo['eventId'])
            && ! empty($fileInfo['aesIv'])
        ) {
            $this->appendCloudStorageSegment($deviceType, $deviceId, $device, $fileInfo);

            return;
        }

        $objectKey = sprintf('%s/%s/%s', $deviceType, $deviceId, $fileInfo['fileId']);

        $media = MediaFile::updateOrCreate(
            ['file_id' => $fileInfo['fileId']],
            [
                'device_id' => $device->id,
                'event_id' => $fileInfo['eventId'] ?? null,
                'module_type' => $fileInfo['moduleType'] ?? null,
                'file_type' => $fileInfo['fileType'] ?? null,
                'object_key' => $objectKey,
                'aes_iv' => $fileInfo['aesIv'] ?? null,
                'pet_score' => $fileInfo['petScore'] ?? null,
                'eat_score' => $fileInfo['eatScore'] ?? null,
                'move_score' => $fileInfo['moveScore'] ?? null,
                'feed_score' => $fileInfo['feedScore'] ?? null,
                'start_time' => $fileInfo['startTime'] ?? null,
                'end_time' => $fileInfo['endTime'] ?? null,
                'duration' => $fileInfo['duration'] ?? null,
                'size' => $fileInfo['storageSpace'] ?? null,
            ]
        );

        // Guard against decrypting twice: the device can retry this report
        // (e.g. after a network hiccup) for a file we already decrypted in
        // place, and running AES-CBC decryption again on already-plaintext
        // bytes would corrupt them beyond recovery.
        if (config('localkit.storage.decrypt_on_upload') && ! $media->decrypted && ! empty($fileInfo['aesIv'])) {
            $this->decryptInPlace($media, $objectKey, (string) $fileInfo['aesIv']);
        }
    }

    /**
     * Decrypts one CLOUD_STORAGE segment and appends it (plain MPEG-TS
     * concatenates byte-for-byte, no container-level merge needed) onto the
     * running combined stream for its event, replacing whatever MP4 was
     * remuxed from the combined stream so far. One MediaFile row per event
     * (file_id "event_{eventId}"), not one per ~4s segment.
     */
    private function appendCloudStorageSegment(string $deviceType, string $deviceId, Device $device, array $fileInfo): void
    {
        $disk = $this->storage->disk();
        $segmentKey = sprintf('%s/%s/%s', $deviceType, $deviceId, $fileInfo['fileId']);

        if (! $disk->exists($segmentKey)) {
            Log::warning('Media decrypt-in-place skipped, object not found on disk', [
                'object' => $segmentKey,
            ]);

            return;
        }

        try {
            $plain = MediaDecryptor::decrypt(
                $disk->get($segmentKey),
                (string) config('localkit.storage.aes_key'),
                (string) $fileInfo['aesIv'],
            );
        } catch (Throwable $e) {
            Log::warning('Media decrypt-in-place failed, object left encrypted', [
                'object' => $segmentKey,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $combinedFileId = 'event_' . $fileInfo['eventId'];
        $combinedTsKey = sprintf('%s/%s/%s.ts', $deviceType, $deviceId, $combinedFileId);

        $existed = $disk->exists($combinedTsKey);
        $previousSize = $existed ? $disk->size($combinedTsKey) : 0;

        $combined = $existed ? $disk->get($combinedTsKey) . $plain : $plain;
        $disk->put($combinedTsKey, $combined);
        $disk->delete($segmentKey);

        Log::info('CLOUD_STORAGE segment appended', [
            'event' => $combinedFileId,
            'segment' => $fileInfo['fileId'],
            'started_new' => ! $existed,
            'previous_size' => $previousSize,
            'combined_size' => strlen($combined),
        ]);

        $media = MediaFile::firstOrNew(['file_id' => $combinedFileId]);
        $media->fill([
            'device_id' => $device->id,
            'event_id' => $fileInfo['eventId'],
            'module_type' => 'CLOUD_STORAGE',
            'file_type' => $fileInfo['fileType'] ?? 'video/x-mpg',
            'object_key' => $combinedTsKey,
            'start_time' => $media->start_time ?? ($fileInfo['startTime'] ?? null),
            'end_time' => $fileInfo['endTime'] ?? $media->end_time,
            'duration' => ($media->duration ?? 0) + (int) ($fileInfo['duration'] ?? 0),
            'size' => strlen($combined),
            'decrypted' => true,
        ]);
        $media->save();

        try {
            $mp4Key = VideoRemuxer::mp4Key($combinedTsKey);
            $disk->put($mp4Key, VideoRemuxer::toMp4($combined));
            $media->update(['object_key' => $mp4Key]);
        } catch (Throwable $e) {
            Log::warning('TS to MP4 remux failed at upload time', [
                'object' => $combinedTsKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function decryptInPlace(MediaFile $media, string $objectKey, string $aesIv): void
    {
        $disk = $this->storage->disk();

        try {
            if (! $disk->exists($objectKey)) {
                Log::warning('Media decrypt-in-place skipped, object not found on disk', [
                    'object' => $objectKey,
                ]);

                return;
            }

            $plain = MediaDecryptor::decrypt(
                $disk->get($objectKey),
                (string) config('localkit.storage.aes_key'),
                $aesIv,
            );

            $disk->put($objectKey, $plain);
            $media->update(['decrypted' => true]);

            if (str_ends_with($objectKey, '.ts')) {
                $this->remuxToMp4($disk, $media, $objectKey, $plain);
            }
        } catch (Throwable $e) {
            Log::warning('Media decrypt-in-place failed, object left encrypted', [
                'object' => $objectKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remuxes the just-decrypted .ts to MP4, stores it under the sibling key
     * (see VideoRemuxer::mp4Key()), deletes the original .ts, and repoints
     * $media->object_key at the MP4 - MediaFileController and everything
     * that links to a clip (PetkitActivities) reference the MP4 from here
     * on, not the .ts. Failure here doesn't affect $media->decrypted or
     * object_key - the .ts is left in place if the remux didn't succeed.
     */
    private function remuxToMp4(Filesystem $disk, MediaFile $media, string $tsObjectKey, string $plainTs): void
    {
        try {
            $mp4Key = VideoRemuxer::mp4Key($tsObjectKey);

            $disk->put($mp4Key, VideoRemuxer::toMp4($plainTs));
            $disk->delete($tsObjectKey);
            $media->update(['object_key' => $mp4Key]);
        } catch (Throwable $e) {
            Log::warning('TS to MP4 remux failed at upload time', [
                'object' => $tsObjectKey,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
