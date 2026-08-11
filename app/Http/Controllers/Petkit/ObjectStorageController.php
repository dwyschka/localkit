<?php

namespace App\Http\Controllers\Petkit;

use App\Http\Controllers\Controller;
use App\Petkit\Storage\DeviceObjectStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * OCI-compatible object storage emulation.
 *
 * PetKit cameras upload their captures with a plain HTTP PUT against the
 * pre-authenticated URL handed out by `dev_oss_sts_info_new_v2`, and read them
 * back with a GET against the domain URL. Both funnel through here and are
 * persisted to the configured Garage S3 disk. The `{namespace}`/`{bucket}`
 * path segments are cosmetic; every object is keyed by its full path.
 */
class ObjectStorageController extends Controller
{
    public function __construct(private readonly DeviceObjectStorage $storage)
    {
    }

    /**
     * Store an uploaded object (PUT/POST to the pre-authenticated URL).
     */
    public function put(Request $request, string $object): Response
    {
        $contents = $request->getContent(asResource: true);

        try {
            $stored = $this->storage->disk()->put($object, $contents);
        } catch (Throwable $e) {
            $stored = false;
            Log::error('Object storage upload failed', ['object' => $object, 'error' => $e->getMessage()]);
        }

        if (! $stored) {
            return response('Storage backend unavailable', Response::HTTP_BAD_GATEWAY);
        }

        return response('', Response::HTTP_OK)
            ->header('ETag', '"' . md5($object) . '"');
    }

    /**
     * Serve a stored object (GET against the domain URL).
     */
    public function get(string $object): StreamedResponse|Response
    {
        $disk = $this->storage->disk();

        try {
            if (! $disk->exists($object)) {
                return response('Not Found', Response::HTTP_NOT_FOUND);
            }

            return $disk->response($object);
        } catch (Throwable $e) {
            Log::error('Object storage read failed', ['object' => $object, 'error' => $e->getMessage()]);

            return response('Storage backend unavailable', Response::HTTP_BAD_GATEWAY);
        }
    }
}
