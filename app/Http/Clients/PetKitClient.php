<?php

namespace App\Http\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\Response;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * PetKit API Client for Laravel
 *
 * PHP port of the py-petkit-api Python library
 * Provides functionality to interact with PetKit devices and API
 */
class PetKitClient
{
    private const ENDPOINTS = [
        'login' => 'user/login',
        'devices' => 'discovery/device_roster',
        'device_detail' => 'device_detail',
        'send_command' => '/device/deviceDetail',
        'pet_info' => '/pet/ownPets',
        'user_info' => '/user/user_info'
    ];
    private string $username;
    private string $password;
    private string $region;
    private string $timezone;
    private ?string $sessionToken = null;
    private array $petkitEntities = [];
    private array $authHeader;
    private array $headers;
    private string $baseUrl;

    // API Endpoints
    private array $clientOptions = [
        'platform' => 'android',
        'os' => '15.1',
        'model' => '23127PN0CG',
        'source' => 'app.petkit-android'
    ];

    public function __construct(
        string $username,
        string $password,
        string $region = 'US',
        string $timezone = 'UTC'
    )
    {
        $this->username = $username;
        $this->password = $password;
        $this->region = strtoupper($region);
        $this->timezone = $timezone;
        $this->baseUrl = $this->getBaseUrl($this->region);

        $this->headers = [
            'X-Client' => 'android(15.1; 23127PN0CG)',
            'Accept' => '*/*',
            'X-Timezone' => '2.0',
            'Accept-Language' => 'de-DE;q=1',
            'Accept-Encoding' => 'gzip, deflate',
            'X-Api-Version' => '12.4.1',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'User-Agent' => 'okhttp/3.12.11',
            'X-TimezoneId' => 'Europe/Madrid',
            'X-Locale' => 'de_DE',
        ];

        $this->authHeader= [
            'User-Agent' => 'PetKit/7.26.1 (iPhone; iOS 14.7.1; Scale/3.00)',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Accept-Language' => 'en-US,en;q=0.9',
            'X-Client-Version' => '7.26.1',
            'X-System-Version' => '14.7.1',
            'X-System-Name' => 'iOS'
        ];


    }

    public function getBaseUrl($region = 'DE')
    {
        $url = 'https://passport.petkt.com/v1/regionservers';

        $response = Http::get($url);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch region servers');
        }

        $collection = collect($response->json('result.list'));
        $base = $collection->filter(fn($item) => $item['id'] === Str::upper($region));

        if (empty($base)) {
            throw new \Exception('Failed to fetch region servers');
        }

        return $base->first()['gateway'];

    }

    /**
     * Fetch all devices and pets data
     */
    public function getDevicesData(): array
    {
        if (!$this->ensureAuthenticated()) {
            throw new \Exception('Authentication failed');
        }

        try {
            // Get devices
            $devicesResponse = Http::withHeaders($this->authHeader)
                ->get($this->baseUrl . 'group/family/list');

            foreach($devicesResponse->json('result') as $list) {
                if(empty($list['deviceList'])) {
                    continue;
                }

                foreach($list['deviceList'] as $device) {
                    $id = $device['deviceId'] ?? null;
                    $deviceData = $this->getDeviceDetail($id);

                    dd($deviceData, $device);
                }
            }
            // Get pets
            $petsResponse = Http::withHeaders($this->headers)
                ->get($this->baseUrl . self::ENDPOINTS['pet_info']);

            if ($devicesResponse->successful() && $petsResponse->successful()) {
                $devicesData = $devicesResponse->json();
                $petsData = $petsResponse->json();

                $this->processDevicesData($devicesData);
                $this->processPetsData($petsData);

                return $this->petkitEntities;
            }

            throw new \Exception('Failed to fetch devices data');
        } catch (\Exception $e) {
            logger()->error('Failed to get devices data: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper method to ensure user is authenticated
     */
    private function ensureAuthenticated(): bool
    {
        if (!$this->getSessionToken()) {
            return $this->login();
        }
        return true;
    }

    /**
     * Get or restore session token from cache
     */
    public function getSessionToken(): ?string
    {
        if (!$this->sessionToken) {
            $cachedToken = Cache::get('petkit_session_' . md5($this->username));
            if ($cachedToken) {
                $this->sessionToken = $cachedToken;
                $this->headers['X-Session'] = $this->sessionToken;
                $this->authHeader['X-Session'] = $this->sessionToken;

            }
        }

        return $this->sessionToken;
    }

    /**
     * Login to PetKit API and obtain session token
     */
    public function login(): bool
    {
        try {
            $response = Http::withHeaders($this->authHeader)
                ->post($this->baseUrl . self::ENDPOINTS['login'], [
                    'username' => $this->username,
                    'password' => md5($this->password),
                    'timezone' => $this->timezone,
                    'locale' => $this->getLocaleFromRegion(),
                    'encrypt' => 1
                ]);

            logger()->info('PetKit login response: ' . $response->body());
            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['result']['session'])) {
                    $this->sessionToken = $data['result']['session']['id'];
                    $this->authHeader['X-Session'] = $this->sessionToken;
                    $this->headers['X-Session'] = $this->sessionToken;


                    // Cache the session token for reuse
                    Cache::put('petkit_session_' . md5($this->username), $this->sessionToken, 3600);

                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            logger()->error('PetKit login failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get locale based on region
     */
    private function getLocaleFromRegion(): string
    {
        $locales = [
            'US' => 'en_US',
            'CN' => 'zh_CN',
            'FR' => 'fr_FR',
            'DE' => 'de_DE',
            'UK' => 'en_GB'
        ];

        return $locales[$this->region] ?? 'en_US';
    }

    /**
     * Process devices data from API response
     */
    private function processDevicesData(array $data): void
    {
        if (!isset($data['result']['devices'])) {
            return;
        }

        foreach ($data['result']['devices'] as $device) {
            $deviceId = $device['id'] ?? null;
            if (!$deviceId) {
                continue;
            }

            $this->petkitEntities[$deviceId] = [
                'type' => 'device',
                'name' => $device['name'] ?? 'Unknown Device',
                'device_type' => $device['type'] ?? 'unknown',
                'model' => $device['device_model'] ?? '',
                'mac' => $device['mac'] ?? '',
                'firmware' => $device['firmware'] ?? '',
                'hardware' => $device['hardware'] ?? '',
                'timezone' => $device['timezone'] ?? $this->timezone,
                'online' => $device['state'] ?? false,
                'raw_data' => $device
            ];
        }
    }

    /**
     * Process pets data from API response
     */
    private function processPetsData(array $data): void
    {
        if (!isset($data['result']['pets'])) {
            return;
        }

        foreach ($data['result']['pets'] as $pet) {
            $petId = $pet['id'] ?? null;
            if (!$petId) {
                continue;
            }

            $this->petkitEntities[$petId] = [
                'type' => 'pet',
                'name' => $pet['name'] ?? 'Unknown Pet',
                'avatar' => $pet['avatar'] ?? '',
                'birthday' => $pet['birthday'] ?? null,
                'weight' => $pet['weight'] ?? 0,
                'breed' => $pet['breed'] ?? '',
                'gender' => $pet['gender'] ?? 0,
                'raw_data' => $pet
            ];
        }
    }

    /**
     * Get detailed information about a specific device
     */
    public function getDeviceDetail(string $deviceId): ?array
    {
        if (!$this->ensureAuthenticated()) {
            return null;
        }

        try {
            $response = Http::withHeaders($this->authHeader)
                ->post($this->baseUrl . 't4/getDeviceRecord', [
                    'id' => $deviceId,
                    'date' => 20250901
                ]);

            dd($response->json(), $deviceId);
            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            logger()->error('Failed to get device detail: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send command to a PetKit device
     */
    public function sendApiRequest(string $deviceId, string $command, array $payload = []): bool
    {
        if (!$this->ensureAuthenticated()) {
            return false;
        }

        try {
            $requestData = array_merge([
                'id' => $deviceId,
                'kv' => $payload
            ], $payload);

            $response = Http::withHeaders($this->headers)
                ->post($this->baseUrl . $command, $requestData);

            return $response->successful();
        } catch (\Exception $e) {
            logger()->error('Failed to send API request: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all PetKit entities (devices and pets)
     */
    public function getPetkitEntities(): array
    {
        return $this->petkitEntities;
    }

    /**
     * Get specific entity by ID
     */
    public function getEntity(string $entityId): ?array
    {
        return $this->petkitEntities[$entityId] ?? null;
    }
}
