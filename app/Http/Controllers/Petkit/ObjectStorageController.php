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
 * OSS-protocol object storage emulation.
 *
 * PetKit cameras upload their captures with a plain HTTP PUT carrying Aliyun
 * OSS-style headers (Authorization: OSS <key>:<sig>, x-oss-security-token,
 * x-oss-object-acl) against the pre-authenticated URL handed out by
 * `dev_oss_sts_info_new_v2`, and read them back with a GET against the
 * domain URL. We don't verify those headers - the URL's random token is the
 * only thing that gates access here - so any well-formed PUT is accepted.
 * The `{namespace}`/`{bucket}` path segments are cosmetic; every object is
 * keyed by its full path.
 */
class ObjectStorageController extends Controller
{
    public function __construct(private readonly DeviceObjectStorage $storage)
    {
    }

    /**
     * Store an uploaded object (PUT/POST to the pre-authenticated URL).
     *
     * Proxies the request body straight through to the backing disk as a
     * stream, so the upload isn't buffered fully into memory.
     */
    public function put(Request $request, string $token, string $namespace, string $bucket, string $object): Response
    {
        $contents = $request->getContent(asResource: true);

        try {
            $stored = $contents !== false && $this->storage->disk()->put($object, $contents);
        } catch (Throwable $e) {
            $stored = false;
            Log::error('Object storage upload failed', ['object' => $object, 'error' => $e->getMessage()]);
        }

        Log::info('Object storage upload', [
            'object' => $object,
            'stored' => $stored,
            'size' => $request->header('Content-Length'),
        ]);

        if (! $stored) {
            return response('Storage backend unavailable', Response::HTTP_BAD_GATEWAY);
        }

        return response('', Response::HTTP_OK)
            ->header('ETag', '"' . md5($object) . '"');
    }

    /**
     * Serve a stored object (GET against the domain URL).
     */
    public function get(string $namespace, string $bucket, string $object): StreamedResponse|Response
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
