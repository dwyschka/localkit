<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevOtaCheckResource;
use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevUploadLogTokenController extends Controller
{
    public function __invoke(string $deviceType, Request $request)
    {

        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        /*
         * Looks like a s3 request, example is below, currently unsupported.. but to be featurecomplete
         * {"result":{"type":"ali","data":{"token":"CAISkgN1q6Ft5B2yfSjIr5r9M9Tyqo5Q8JaASlGEqWM0NcNdubDnjDz2IHhMe3BqBesfsPowmG9S6f8flr56U54ASFGBddF34tFQ6hjkeZWEocux4OS3ElF0hjPBZSTg1er+Ps8RJbG0I4W+CT3tkit03sG1F1GLVECkNpukkINuas9tMCCzcTtBAqUxRG5ls9RIDWbNEvyvPxX2+EuyanBloQ1hk2hyxL2iy8mHkHrkgUb91/Ueqva0QNutZNI+O4xkAZXnnr5ue7bC0TIV8ARN7q55gelO+DHOpMDYG0NW71CLLq2W0KU2fV4pOPBmRPYb8KCjxaEigIGJydSrkSQqFPpOTiHSSLqnxMb5A+6zPr47D+2jYyuUiIzUb8er7F97Oy1CaRk1csIgb3JtTAQ2TT2fLbS8vUrVJx2kT6WVWgbYZHSqYT4ClPn9xDjnK93xuU5wsU9p8s+2sLA8ia0soA7HYkFhkgK16YzvOaFZRj6gV20Wk3McmAMOAH+5cfOASZGymOQrDa+bODYu3CmvFcsu2wMagAF/375v8kcfs3ews0qgso4FV7iL4AprfQi+EZrwDbnQgBiPkMmv1mYvZxswrRgfiXju5EGcLOKe/D0Ogb0/rj6PlU77INhXrYBbUszD1jj7n/u18TdA374jyeoddnKCtYUk2L9125EV68QEmcVp5AJ0jMO4owf5V4KJBFYkvzv/cCAA","bucketName":"petkit-storage-binary-prod-eu","pathPrefix":"t7-log/2026/7/19","endPoint":"oss-eu-central-1.aliyuncs.com","secret":"2AnDm9s38khLtjBybK9tg7XHq3QecB8q72GT72pGCKmK","keyId":"STS.NYHxnFGQqGTkHw5Mca9LqVuLn"},"key":"t7-log/2026/7/19/6a5be7ba0a5c180001308f43WiGEbJ1Yh","token":"n9mmVEuBrO_IkIENoFQjBd8Y1qNAD-_tB0ALUFTY:wZTBBPT5dKXHK6pucXAT0ZnShYc=:eyJzY29wZSI6InBldGtpdC1wLXVzOnQ3LWxvZy8yMDI2LzcvMTkvNmE1YmU3YmEwYTVjMTgwMDAxMzA4ZjQzV2lHRWJKMVloIiwicmV0dXJuQm9keSI6IntcInVybFwiOiBcImh0dHA6Ly9maWxlNS11cy5wZXRraXQuY24vdDctbG9nLzIwMjYvNy8xOS82YTViZTdiYTBhNWMxODAwMDEzMDhmNDNXaUdFYkoxWWhcIixcInNpemVcIjogJChmc2l6ZSksXCJuYW1lXCI6ICQoZm5hbWUpfSIsImRlYWRsaW5lIjoxNzg0NDA5Nzk0fQ=="}}
         */
        return new JsonResponse([
            'result' => [
                'type' => 'ali',
                'data' => [
                    'token'      => '',
                    'bucketName' => '',
                    'pathPrefix' => '',
                    'endPoint'   => '',
                    'secret'     => '',
                    'keyId'      => '',
                ],
                'key'   => '',
                'token' => '',
            ],
        ]);
    }
}
