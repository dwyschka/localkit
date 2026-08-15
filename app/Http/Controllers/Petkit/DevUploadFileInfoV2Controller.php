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

        return new JsonResponse(['result' => []]);
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
