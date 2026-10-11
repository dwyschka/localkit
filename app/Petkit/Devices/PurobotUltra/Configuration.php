<?php

namespace App\Petkit\Devices\PurobotUltra;

use App\DTOs\DeviceConfigurationDTO;
use App\DTOs\MultiRangeDTO;
use App\Homeassistant\BinarySensor;
use App\Homeassistant\HASwitch;
use App\Homeassistant\Image;
use App\Homeassistant\Number;
use App\Homeassistant\Select;
use App\Homeassistant\Sensor;
use App\Management\Rtsp;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use App\Petkit\Interfaces\HasCamera;
use App\Homeassistant\Interfaces\Snapshot;
use App\Homeassistant\Interfaces\Video;
use Illuminate\Support\Facades\Storage;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;

/**
 * Configuration for the PetKit PuroBot Ultra (T6) - a camera equipped
 * self-cleaning litter box with a waste-packaging unit and a pan motor.
 *
 * NOTE: the property names must match the device's setting keys exactly, since
 * {@see \App\Petkit\Devices\PurobotUltra\Device::propertyChange()} diffs the
 * stored `settings` array and forwards the changed keys straight to the device.
 * That is why `timestamp_enable`, `bag_tip_en`, `spary_tip_en` and `log_upload`
 * are snake_case while their neighbours are not, and why `spary_tip_en` keeps
 * the firmware's own misspelling.
 *
 * The settings below are the complete `app_conf` section a real T6 reported
 * (firmware 951), key for key - 56 of them. Where this device and the T7 share
 * a key the entity definition is the T7's, unchanged; the thirteen keys only
 * this device has are marked where they appear.
 *
 * Not here, and deliberately:
 *
 *  - The pan motor. `userHomeMode.ptz` is the only place it shows up in
 *    app_conf, and that is an on/off override, not a movement command. Moving
 *    it means a dispatch message to the vendor's own `ctrl` daemon, which is
 *    what `rptz` in the raptor tree does - there is no MQTT service for it.
 *  - `user_sta` (litter_used_cnt, need_clean_cnt, pack_over_sta) and
 *    `go_wc_cnt`. They would make good sensors, but they appear in the
 *    device's config dump and nobody has seen which MQTT message carries
 *    them, so entities for them would never update.
 */
class Configuration extends DeviceConfigurationDTO implements ConfigurationInterface, Video, Snapshot, HasCamera
{
    public array $schedule;


    // States
    // States
    #[Sensor(
        technicalName: 'ip_address',
        name: 'IP Address',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.states.ipAddress }}',
        entityCategory: 'diagnostic'
    )]
    public string $ipAddress;

    // Internal only - not published to Home Assistant itself. This device
    // has no weight sensor or pet_discern topic, so nothing currently
    // resolves a real pet_id here; stays null until it does.
    public ?int $lastUsedByPetId;

    #[Sensor(
        technicalName: 'last_used_by',
        name: 'Last Used By',
        icon: 'mdi:paw',
        valueTemplate: '{{ value_json.states.lastUsedByName }}',
        entityCategory: 'diagnostic'
    )]
    public ?string $lastUsedByName;

    #[Sensor(
        technicalName: 'device_status',
        name: 'Device Status',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.states.state }}',
        entityCategory: 'diagnostic'
    )]
    public ?string $workingState;

    #[Sensor(
        technicalName: 'error',
        name: 'Error',
        icon: 'mdi:alert-circle',
        valueTemplate: "{{ 'Ok' if value_json.states.error is none else value_json.states.error }}",
        entityCategory: 'diagnostic'
    )]
    public ?string $error;

    #[BinarySensor(
        technicalName: 'lightning',
        name: 'Light',
        icon: 'mdi:lightbulb',
        deviceClass: 'light',
        valueTemplate: '{{ value_json.states.lightning }}',
        entityCategory: 'diagnostic',
        payloadOn: '1',
        payloadOff: '0'
    )]
    public bool $lightning;

    #[Image(
        technicalName: 'last_snapshot',
        name: 'Snapshot',
    )]
    public ?string $lastSnapshot;

    /*
     * The device's RTSP URL. Home Assistant's MQTT discovery has no RTSP
     * camera platform, so this cannot become a camera entity on its own - it is
     * published as a diagnostic sensor so the URL can be pasted into a
     * generic/ffmpeg camera. Derived from $ipAddress in toArray(), never stored
     * by the device.
     */
    #[Sensor(
        technicalName: 'stream_url',
        name: 'Stream URL',
        icon: 'mdi:video-wireless',
        valueTemplate: '{{ value_json.states.stream }}',
        entityCategory: 'diagnostic'
    )]
    public ?string $stream;


    // Meta
    // Device meta (kept for signup / device info payloads)
    #[BinarySensor(
        technicalName: 'share_open',
        name: 'Share Open',
        icon: 'mdi:toggle-switch',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.shareOpen }}',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $shareOpen;

    #[BinarySensor(
        technicalName: 'multi_config',
        name: 'Multi Config',
        icon: 'mdi:toggle-switch',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.multiConfig }}',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $multiConfig;

    #[BinarySensor(
        technicalName: 'auto_upgrade',
        name: 'Auto Upgrade',
        icon: 'mdi:cloud-download',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.autoUpgrade }}',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $autoUpgrade;

    #[Sensor(
        technicalName: 'type_code',
        name: 'Type Code',
        icon: 'mdi:identifier',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.typeCode }}'
    )]
    public int $typeCode;

    #[Sensor(
        technicalName: 'service_status',
        name: 'Service Status',
        icon: 'mdi:information-outline',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.serviceStatus }}'
    )]
    public int $serviceStatus;

    #[Sensor(
        technicalName: 'hertz',
        name: 'Hertz',
        icon: 'mdi:repeat',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.hertz }}'
    )]
    public int $hertz;


    // Display / camera
    // The OSD clock burned into the video. The T7 calls the same thing
    // 'timeDisplay'; this firmware's wire key is 'timestamp_enable', and the
    // property name has to be the wire key - propertyChange() forwards it
    // straight to the device.
    #[HASwitch(
        technicalName: 'timestamp_enable',
        name: 'Timestamp Overlay',
        commandTopic: 'setting/set',
        icon: 'mdi:clock-digital',
        valueTemplate: '{{ value_json.settings.timestamp_enable }}',
        commandTemplate: '{"timestamp_enable":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $timestamp_enable;

    #[HASwitch(
        technicalName: 'camera',
        name: 'Camera Switch',
        commandTopic: 'setting/set',
        icon: 'mdi:camera',
        valueTemplate: '{{ value_json.settings.camera }}',
        commandTemplate: '{"camera":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $camera;

    public MultiRangeDTO $cameraMultiRange;

    #[HASwitch(
        technicalName: 'microphone',
        name: 'Microphone',
        commandTopic: 'setting/set',
        icon: 'mdi:microphone',
        valueTemplate: '{{ value_json.settings.microphone }}',
        commandTemplate: '{"microphone":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $microphone;

    #[HASwitch(
        technicalName: 'night',
        name: 'Night Vision',
        commandTopic: 'setting/set',
        icon: 'mdi:moon-new',
        valueTemplate: '{{ value_json.settings.night }}',
        commandTemplate: '{"night":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $night;

    #[HASwitch(
        technicalName: 'camera_light',
        name: 'Camera Light',
        commandTopic: 'setting/set',
        icon: 'mdi:track-light',
        valueTemplate: '{{ value_json.settings.cameraLight }}',
        commandTemplate: '{"cameraLight":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $cameraLight;

    public bool $preLive;


    // Lights
    // Indicator / assist lights
    // Not in Localkit UI - not exposed to HA
    public bool $lightMode;

    public MultiRangeDTO $lightMultiRange;

    #[HASwitch(
        technicalName: 'light_assist',
        name: 'Light Assist for Cleaning',
        commandTopic: 'setting/set',
        icon: 'mdi:lightbulb-auto',
        valueTemplate: '{{ value_json.settings.lightAssist }}',
        commandTemplate: '{"lightAssist":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $lightAssist;

    #[HASwitch(
        technicalName: 'toilet_light',
        name: 'Toilet Light',
        commandTopic: 'setting/set',
        icon: 'mdi:lightbulb',
        valueTemplate: '{{ value_json.settings.toiletLight }}',
        commandTemplate: '{"toiletLight":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $toiletLight;

    public array $cameraRangeTable;


    // Do not disturb
    // Do not disturb / tone - not in Localkit UI - not exposed to HA
    public bool $toneMode;

    public MultiRangeDTO $toneMultiRange;

    public bool $disturbMode;

    public MultiRangeDTO $distrubMultiRange;


    // Lock and buttons
    // Lock
    #[HASwitch(
        technicalName: 'manual_lock',
        name: 'Child Lock',
        commandTopic: 'setting/set',
        icon: 'mdi:lock',
        valueTemplate: '{{ value_json.settings.manualLock }}',
        commandTemplate: '{"manualLock":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $manualLock;

    #[HASwitch(
        technicalName: 'click_ok_enable',
        name: 'OK Button',
        commandTopic: 'setting/set',
        icon: 'mdi:gesture-tap-button',
        valueTemplate: '{{ value_json.settings.clickOkEnable }}',
        commandTemplate: '{"clickOkEnable":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $clickOkEnable;


    // Cleaning
    // Cleaning behaviour
    #[HASwitch(
        technicalName: 'auto_work',
        name: 'Auto Cleaning',
        commandTopic: 'setting/set',
        icon: 'mdi:broom',
        valueTemplate: '{{ value_json.settings.autoWork }}',
        commandTemplate: '{"autoWork":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $autoWork;

    #[Select(
        technicalName: 'auto_interval_min',
        name: 'Time Interval Of Each Cleaning',
        options: ['0', '30', '60', '300', '600', '900', '1800', '3600'],
        commandTopic: 'setting/set',
        icon: 'mdi:timer-outline',
        valueTemplate: '{{ value_json.settings.autoIntervalMin }}',
        commandTemplate: '{"autoIntervalMin": {{value}}}',
        entityCategory: 'config'
    )]
    public int $autoIntervalMin;

    #[Sensor(
        technicalName: 'fixed_time_clear',
        name: 'Scheduled Cleaning',
        icon: 'mdi:calendar-clock',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.fixedTimeClear }}'
    )]
    public int $fixedTimeClear;

    #[HASwitch(
        technicalName: 'avoid_repeat',
        name: 'Avoid Repeated Cleaning',
        commandTopic: 'setting/set',
        icon: 'mdi:repeat',
        valueTemplate: '{{ value_json.settings.avoidRepeat }}',
        commandTemplate: '{"avoidRepeat":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $avoidRepeat;

    // Not in Localkit UI - not exposed to HA
    public bool $underweight;

    #[HASwitch(
        technicalName: 'kitten',
        name: 'Kitten Protection',
        commandTopic: 'setting/set',
        icon: 'mdi:cat',
        valueTemplate: '{{ value_json.settings.kitten }}',
        commandTemplate: '{"kitten":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $kitten;

    // Not in Localkit UI - not exposed to HA
    public bool $bury;

    public bool $deepClean;

    #[HASwitch(
        technicalName: 'tumbling',
        name: 'Tumbling',
        commandTopic: 'setting/set',
        icon: 'mdi:rotate-3d-variant',
        valueTemplate: '{{ value_json.settings.tumbling }}',
        commandTemplate: '{"tumbling":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $tumbling;

    #[Number(
        technicalName: 'lightest',
        name: 'Minimum Weight',
        commandTopic: 'setting/set',
        icon: 'mdi:scale',
        valueTemplate: '{{ value_json.settings.lightest }}',
        commandTemplate: '{"lightest":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 10000,
        step: 1
    )]
    public int $lightest;

    #[Select(
        technicalName: 'still_time',
        name: 'Delayed Cleaning',
        options: ['0', '30', '60', '300', '600', '900', '1800', '3600'],
        commandTopic: 'setting/set',
        icon: 'mdi:timer-outline',
        valueTemplate: '{{ value_json.settings.stillTime }}',
        commandTemplate: '{"stillTime": {{value}}}',
        entityCategory: 'config'
    )]
    public int $stillTime;

    // Litter Type - not exposed to HA
    public int $sandType;

    // No weight sensor on this device - not exposed to HA
    public int $unit;

    #[HASwitch(
        technicalName: 'sand_saving',
        name: 'Litter Saving',
        commandTopic: 'setting/set',
        icon: 'mdi:leaf',
        valueTemplate: '{{ value_json.settings.sandSaving }}',
        commandTemplate: '{"sandSaving":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $sandSaving;

    // Per-litter-type working thresholds, four triples. Device-internal; the
    // vendor app does not expose them either.
    public array $sandSetUseConfig;

    // Full-weight per litter type, five entries (the sandType index selects
    // one). Device-internal.
    public array $sandFullWeight;

    // Thresholds for the waste-packaging cycle. Device-internal; the matching
    // state is user_sta.pack_over_sta, which does not arrive over MQTT.
    public array $packageStandard;


    // Toilet
    // Toilet / spray (deodorant) settings
    #[HASwitch(
        technicalName: 'toilet_detection',
        name: 'Toilet Video Recording',
        commandTopic: 'setting/set',
        icon: 'mdi:toilet',
        valueTemplate: '{{ value_json.settings.toiletDetection }}',
        commandTemplate: '{"toiletDetection":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $toiletDetection;

    #[HASwitch(
        technicalName: 'soft_mode',
        name: 'Loose Stool Recognition',
        commandTopic: 'setting/set',
        icon: 'mdi:cog',
        valueTemplate: '{{ value_json.settings.softMode }}',
        commandTemplate: '{"softMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $softMode;

    #[HASwitch(
        technicalName: 'ph_detection',
        name: 'pH Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:test-tube',
        valueTemplate: '{{ value_json.settings.phDetection }}',
        commandTemplate: '{"phDetection":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $phDetection;


    // Detection
    // Detection
    #[HASwitch(
        technicalName: 'move_detection',
        name: 'Move Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:eye-arrow-left-outline',
        valueTemplate: '{{ value_json.settings.moveDetection }}',
        commandTemplate: '{"moveDetection":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $moveDetection;

    #[Number(
        technicalName: 'move_sensitivity',
        name: 'Move Sensitivity',
        commandTopic: 'setting/set',
        icon: 'mdi:speaker',
        valueTemplate: '{{ value_json.settings.moveSensitivity }}',
        commandTemplate: '{"moveSensitivity":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 9,
        step: 1
    )]
    public int $moveSensitivity;

    #[HASwitch(
        technicalName: 'pet_detection',
        name: 'Pet Appearance Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:cat',
        valueTemplate: '{{ value_json.settings.petDetection }}',
        commandTemplate: '{"petDetection":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $petDetection;

    // This firmware posts wander_over / wander events of its own (see
    // ctrl_t6's pk_event_pack_wander_* family), which the T7 does not have.
    #[HASwitch(
        technicalName: 'wander_detection',
        name: 'Wander Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:cat',
        valueTemplate: '{{ value_json.settings.wanderDetection }}',
        commandTemplate: '{"wanderDetection":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $wanderDetection;

    #[Number(
        technicalName: 'detect_interval',
        name: 'Detection Interval',
        commandTopic: 'setting/set',
        icon: 'mdi:timer-outline',
        unitOfMeasurement: 's',
        valueTemplate: '{{ value_json.settings.detectInterval }}',
        commandTemplate: '{"detectInterval":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 300,
        step: 1
    )]
    public int $detectInterval;

    public array $detectMultiRange;


    // Sound
    // Sound (no speaker on this device - not exposed to HA)
    public bool $soundEnable;

    public bool $systemSoundEnable;

    public int $volume;

    public int $selectedSound;

    public bool $voice;


    // Reminders
    #[HASwitch(
        technicalName: 'bag_tip',
        name: 'Bag Reminder',
        commandTopic: 'setting/set',
        icon: 'mdi:bag-personal',
        valueTemplate: '{{ value_json.settings.bag_tip_en }}',
        commandTemplate: '{"bag_tip_en":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $bag_tip_en;

    // 'spary' is the firmware's own spelling, and the property name has to be
    // the wire key - do not correct it here.
    #[HASwitch(
        technicalName: 'spray_tip',
        name: 'Spray Reminder',
        commandTopic: 'setting/set',
        icon: 'mdi:spray',
        valueTemplate: '{{ value_json.settings.spary_tip_en }}',
        commandTemplate: '{"spary_tip_en":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $spary_tip_en;


    // Home mode
    /**
     * Home mode: while the owner is home the device can be told to keep the
     * microphone, the camera and the pan motor off regardless of their own
     * switches. -1 on a member means "follow the normal setting".
     *
     * Kept as a plain array rather than a DTO because the device sends it
     * back verbatim and propertyChange() has to diff it as one value; the
     * nested keys are homeMode, mic, camera and ptz. The ptz member is the
     * only place the pan motor appears in app_conf at all - moving it is not
     * an app_conf operation, see rptz in the raptor tree.
     */
    public array $userHomeMode;


    // Upload
    // Cloud upload - not exposed to HA
    public bool $upload;

    #[HASwitch(
        technicalName: 'log_upload',
        name: 'Upload Logs',
        commandTopic: 'setting/set',
        icon: 'mdi:file-upload-outline',
        valueTemplate: '{{ value_json.settings.log_upload }}',
        commandTemplate: '{"log_upload":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'diagnostic'
    )]
    public bool $log_upload;

    public array $capacity;

    protected function rules(): array
    {
        return [
            'schedule' => ['array'],

            // States,

            // States
            'ipAddress' => ['string'],
            'lastUsedByPetId' => ['nullable', 'integer'],
            'lastUsedByName' => ['nullable', 'string'],
            'workingState' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
            'lightning' => ['bool'],
            'lastSnapshot' => ['nullable', 'string'],
            'stream' => ['nullable', 'string'],

            // Meta,

            // Meta
            'shareOpen' => ['bool'],
            'multiConfig' => ['bool'],
            'autoUpgrade' => ['bool'],
            'typeCode' => ['integer'],
            'serviceStatus' => ['integer'],
            'hertz' => ['integer', 'min:50', 'max:60'],

            // Display / camera,

            // Display / camera
            'timestamp_enable' => ['bool'],
            'camera' => ['bool'],
            'cameraMultiRange' => ['array'],
            'microphone' => ['bool'],
            'night' => ['bool'],
            'cameraLight' => ['bool'],
            'preLive' => ['bool'],

            // Lights,

            // Lights
            'lightMode' => ['bool'],
            'lightMultiRange' => ['array'],
            'lightAssist' => ['bool'],
            'toiletLight' => ['bool'],
            'cameraRangeTable' => ['array'],

            // Do not disturb
            'toneMode' => ['bool'],
            'toneMultiRange' => ['array'],
            'disturbMode' => ['bool'],
            'distrubMultiRange' => ['array'],

            // Lock,

            // Lock and buttons
            'manualLock' => ['bool'],

            // Cleaning,
            'clickOkEnable' => ['bool'],

            // Cleaning
            'autoWork' => ['bool'],
            'autoIntervalMin' => ['integer', 'min:0'],
            'fixedTimeClear' => ['integer', 'min:0'],
            'avoidRepeat' => ['bool'],
            'underweight' => ['bool'],
            'kitten' => ['bool'],
            'bury' => ['bool'],
            'deepClean' => ['bool'],
            'tumbling' => ['bool'],
            'lightest' => ['integer', 'min:0'],
            'stillTime' => ['integer', 'min:0'],
            'sandType' => ['integer', 'in:0,1,2'],
            'unit' => ['integer', 'in:0,1'],
            'sandSaving' => ['bool'],
            'sandSetUseConfig' => ['array', 'size:4'],
            'sandSetUseConfig.*' => ['array', 'size:3'],
            'sandFullWeight' => ['array', 'size:5'],
            'sandFullWeight.*' => ['integer', 'min:0'],
            'packageStandard' => ['array', 'size:4'],
            'packageStandard.*' => ['integer', 'min:0'],

            // Toilet
            'toiletDetection' => ['bool'],
            'softMode' => ['bool'],
            'phDetection' => ['bool'],

            // Detection
            'moveDetection' => ['bool'],
            'moveSensitivity' => ['integer', 'min:0', 'max:9'],
            'petDetection' => ['bool'],
            'wanderDetection' => ['bool'],
            'detectInterval' => ['integer', 'min:0'],
            'detectMultiRange' => ['array'],

            // Sound,

            // Sound
            'soundEnable' => ['bool'],
            'systemSoundEnable' => ['bool'],
            'volume' => ['integer', 'min:0', 'max:9'],
            'selectedSound' => ['integer'],
            'voice' => ['bool'],

            // Reminders
            'bag_tip_en' => ['bool'],
            'spary_tip_en' => ['bool'],

            // Home mode
            'userHomeMode' => ['array'],

            // Upload

            'upload' => ['bool'],
            'capacity' => ['array'],
            'log_upload' => ['bool'],
            'capacity' => ['array'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'schedule' => [],

            // States,

            // States
            'ipAddress' => '',
            'lastUsedByPetId' => null,
            'lastUsedByName' => null,
            'workingState' => null,
            'error' => null,
            'lightning' => false,
            'lastSnapshot' => null,
            'stream' => null,

            // Meta,

            // Meta
            'shareOpen' => false,
            'multiConfig' => true,
            'autoUpgrade' => false,
            'typeCode' => 0,
            'serviceStatus' => 2,
            'hertz' => 50,

            // Display / camera,

            // Display / camera
            'timestamp_enable' => true,
            'camera' => true,
            'cameraMultiRange' => [
                'name' => 'cameraMultiRange',
                'ranges' => [['from' => 0, 'till' => 1440]],
            ],
            'microphone' => true,
            'night' => true,
            'cameraLight' => true,
            'preLive' => true,

            // Lights,

            // Lights
            'lightMode' => true,
            'lightMultiRange' => [
                'name' => 'lightMultiRange',
                'ranges' => [['from' => 0, 'till' => 1440]],
            ],
            'lightAssist' => true,
            'toiletLight' => false,
            'cameraRangeTable' => $this->defaultRangeTable(),

            // Do not disturb
            'toneMode' => false,
            'toneMultiRange' => [
                'name' => 'toneMultiRange',
                'ranges' => [['from' => 1320, 'till' => 360]],
            ],
            'disturbMode' => false,
            'distrubMultiRange' => [
                'name' => 'distrubMultiRange',
                'ranges' => [['from' => 40, 'till' => 520]],
            ],

            // Lock,

            // Lock and buttons
            'manualLock' => false,

            // Cleaning,
            'clickOkEnable' => true,

            // Cleaning
            'autoWork' => true,
            'autoIntervalMin' => 0,
            'fixedTimeClear' => 0,
            'avoidRepeat' => true,
            'underweight' => false,
            'kitten' => false,
            'bury' => false,
            'deepClean' => false,
            'tumbling' => false,
            'lightest' => 0,
            'stillTime' => 1200,
            'sandType' => 0,
            'unit' => 0,
            'sandSaving' => false,
            'sandSetUseConfig' => [[40, 40, 85], [40, 40, 85], [40, 40, 85], [40, 40, 85]],
            'sandFullWeight' => [3500, 5800, 3000, 3500, 3500],
            'packageStandard' => [6, 9, 10, 22],

            // Toilet
            'toiletDetection' => true,
            'softMode' => false,
            'phDetection' => true,

            // Detection
            'moveDetection' => false,
            'moveSensitivity' => 0,
            'petDetection' => true,
            'wanderDetection' => true,
            'detectInterval' => 0,
            'detectMultiRange' => [],

            // Sound,

            // Sound
            'soundEnable' => true,
            'systemSoundEnable' => true,
            'volume' => 7,
            'selectedSound' => -1,
            'voice' => false,

            // Reminders
            'bag_tip_en' => true,
            'spary_tip_en' => false,

            // Home mode
            'userHomeMode' => [
                'homeMode' => 0,
                'mic' => -1,
                'camera' => -1,
                'ptz' => -1,
            ],

            // Upload

            'upload' => true,
            'capacity' => [
                ['name' => 'fullVideo'],
                ['name' => 'eventImage'],
                ['name' => 'dynamicVideo'],
            ],
            'log_upload' => false,
            'capacity' => [
                ['name' => 'fullVideo'],
                ['name' => 'eventImage'],
                ['name' => 'dynamicVideo'],
            ],
        ];
    }

    protected function casts(): array
    {
        return [
            'schedule' => new ArrayCast(),

            // States,

            // States
            'ipAddress' => new StringCast(),
            'lastUsedByPetId' => new IntegerCast(),
            'lastUsedByName' => new StringCast(),
            'workingState' => new StringCast(),
            'error' => new StringCast(),
            'lightning' => new BooleanCast(),
            'lastSnapshot' => new StringCast(),
            'stream' => new StringCast(),

            // Meta,

            // Meta
            'shareOpen' => new BooleanCast(),
            'multiConfig' => new BooleanCast(),
            'autoUpgrade' => new BooleanCast(),
            'typeCode' => new IntegerCast(),
            'serviceStatus' => new IntegerCast(),
            'hertz' => new IntegerCast(),

            // Display / camera,

            // Display / camera
            'timestamp_enable' => new BooleanCast(),
            'camera' => new BooleanCast(),
            'cameraMultiRange' => new DTOCast(MultiRangeDTO::class),
            'microphone' => new BooleanCast(),
            'night' => new BooleanCast(),
            'cameraLight' => new BooleanCast(),
            'preLive' => new BooleanCast(),

            // Lights,

            // Lights
            'lightMode' => new BooleanCast(),
            'lightMultiRange' => new DTOCast(MultiRangeDTO::class),
            'lightAssist' => new BooleanCast(),
            'toiletLight' => new BooleanCast(),
            'cameraRangeTable' => new ArrayCast(),

            // Do not disturb
            'toneMode' => new BooleanCast(),
            'toneMultiRange' => new DTOCast(MultiRangeDTO::class),
            'disturbMode' => new BooleanCast(),
            'distrubMultiRange' => new DTOCast(MultiRangeDTO::class),

            // Lock,

            // Lock and buttons
            'manualLock' => new BooleanCast(),

            // Cleaning,
            'clickOkEnable' => new BooleanCast(),

            // Cleaning
            'autoWork' => new BooleanCast(),
            'autoIntervalMin' => new IntegerCast(),
            'fixedTimeClear' => new IntegerCast(),
            'avoidRepeat' => new BooleanCast(),
            'underweight' => new BooleanCast(),
            'kitten' => new BooleanCast(),
            'bury' => new BooleanCast(),
            'deepClean' => new BooleanCast(),
            'tumbling' => new BooleanCast(),
            'lightest' => new IntegerCast(),
            'stillTime' => new IntegerCast(),
            'sandType' => new IntegerCast(),
            'unit' => new IntegerCast(),
            'sandSaving' => new BooleanCast(),
            'sandSetUseConfig' => new ArrayCast(),
            'sandFullWeight' => new ArrayCast(),
            'packageStandard' => new ArrayCast(),

            // Toilet
            'toiletDetection' => new BooleanCast(),
            'softMode' => new BooleanCast(),
            'phDetection' => new BooleanCast(),

            // Detection
            'moveDetection' => new BooleanCast(),
            'moveSensitivity' => new IntegerCast(),
            'petDetection' => new BooleanCast(),
            'wanderDetection' => new BooleanCast(),
            'detectInterval' => new IntegerCast(),
            'detectMultiRange' => new ArrayCast(),

            // Sound,

            // Sound
            'soundEnable' => new BooleanCast(),
            'systemSoundEnable' => new BooleanCast(),
            'volume' => new IntegerCast(),
            'selectedSound' => new IntegerCast(),
            'voice' => new BooleanCast(),

            // Reminders
            'bag_tip_en' => new BooleanCast(),
            'spary_tip_en' => new BooleanCast(),

            // Home mode
            'userHomeMode' => new ArrayCast(),

            // Upload

            'upload' => new BooleanCast(),
            'capacity' => new ArrayCast(),
            'log_upload' => new BooleanCast(),
            'capacity' => new ArrayCast(),
        ];
    }

    private function defaultRangeTable(): array
    {
        return array_map(fn (int $wday) => ['wday' => $wday, 'rangeSub' => [0]], range(0, 6));
    }

    public static function fromDevice(Device|BluetoothDevice $device): self
    {
        $config = $device->configuration;
        $data = [];

        // Load states
        $data['workingState'] = $device->working_state ?? null;
        $data['error'] = $device->error ?? null;

        if (isset($config['states'])) {
            $states = $config['states'];
            $data['ipAddress'] = $states['ipAddress'] ?? null;
            $data['lastUsedByPetId'] = $states['lastUsedByPetId'] ?? null;
            $data['lastUsedByName'] = $states['lastUsedByName'] ?? null;
            $data['lightning'] = $states['lightning'] ?? null;
            $data['lastSnapshot'] = $states['lastSnapshot'] ?? null;
            $data['stream'] = $states['stream'] ?? null;
        }

        if (isset($config['settings'])) {
            $settings = $config['settings'];

            // Meta
            $data['shareOpen'] = $settings['shareOpen'] ?? null;
            $data['multiConfig'] = $settings['multiConfig'] ?? null;
            $data['autoUpgrade'] = $settings['autoUpgrade'] ?? null;
            $data['typeCode'] = $settings['typeCode'] ?? null;
            $data['serviceStatus'] = $settings['serviceStatus'] ?? null;
            $data['hertz'] = $settings['hertz'] ?? null;

            // Display / camera
            $data['timestamp_enable'] = $settings['timestamp_enable'] ?? null;
            $data['camera'] = $settings['camera'] ?? null;
            $data['cameraMultiRange'] = $settings['cameraMultiRange'] ?? null;
            $data['microphone'] = $settings['microphone'] ?? null;
            $data['night'] = $settings['night'] ?? null;
            $data['cameraLight'] = $settings['cameraLight'] ?? null;
            $data['preLive'] = $settings['preLive'] ?? null;

            // Lights
            $data['lightMode'] = $settings['lightMode'] ?? null;
            $data['lightMultiRange'] = $settings['lightMultiRange'] ?? null;
            $data['lightAssist'] = $settings['lightAssist'] ?? null;
            $data['toiletLight'] = $settings['toiletLight'] ?? null;
            $data['cameraRangeTable'] = $settings['cameraRangeTable'] ?? null;

            // Do not disturb
            $data['toneMode'] = $settings['toneMode'] ?? null;
            $data['toneMultiRange'] = $settings['toneMultiRange'] ?? null;
            $data['disturbMode'] = $settings['disturbMode'] ?? null;
            $data['distrubMultiRange'] = $settings['distrubMultiRange'] ?? null;

            // Lock and buttons
            $data['manualLock'] = $settings['manualLock'] ?? null;
            $data['clickOkEnable'] = $settings['clickOkEnable'] ?? null;

            // Cleaning
            $data['autoWork'] = $settings['autoWork'] ?? null;
            $data['autoIntervalMin'] = $settings['autoIntervalMin'] ?? null;
            $data['fixedTimeClear'] = $settings['fixedTimeClear'] ?? null;
            $data['avoidRepeat'] = $settings['avoidRepeat'] ?? null;
            $data['underweight'] = $settings['underweight'] ?? null;
            $data['kitten'] = $settings['kitten'] ?? null;
            $data['bury'] = $settings['bury'] ?? null;
            $data['deepClean'] = $settings['deepClean'] ?? null;
            $data['tumbling'] = $settings['tumbling'] ?? null;
            $data['lightest'] = $settings['lightest'] ?? null;
            $data['stillTime'] = $settings['stillTime'] ?? null;
            $data['sandType'] = $settings['sandType'] ?? null;
            $data['unit'] = $settings['unit'] ?? null;
            $data['sandSaving'] = $settings['sandSaving'] ?? null;
            $data['sandSetUseConfig'] = $settings['sandSetUseConfig'] ?? null;
            $data['sandFullWeight'] = $settings['sandFullWeight'] ?? null;
            $data['packageStandard'] = $settings['packageStandard'] ?? null;

            // Toilet
            $data['toiletDetection'] = $settings['toiletDetection'] ?? null;
            $data['softMode'] = $settings['softMode'] ?? null;
            $data['phDetection'] = $settings['phDetection'] ?? null;

            // Detection
            $data['moveDetection'] = $settings['moveDetection'] ?? null;
            $data['moveSensitivity'] = $settings['moveSensitivity'] ?? null;
            $data['petDetection'] = $settings['petDetection'] ?? null;
            $data['wanderDetection'] = $settings['wanderDetection'] ?? null;
            $data['detectInterval'] = $settings['detectInterval'] ?? null;
            $data['detectMultiRange'] = $settings['detectMultiRange'] ?? null;

            // Sound
            $data['soundEnable'] = $settings['soundEnable'] ?? null;
            $data['systemSoundEnable'] = $settings['systemSoundEnable'] ?? null;
            $data['volume'] = $settings['volume'] ?? null;
            $data['selectedSound'] = $settings['selectedSound'] ?? null;
            $data['voice'] = $settings['voice'] ?? null;

            // Reminders
            $data['bag_tip_en'] = $settings['bag_tip_en'] ?? null;
            $data['spary_tip_en'] = $settings['spary_tip_en'] ?? null;

            // Home mode
            $data['userHomeMode'] = $settings['userHomeMode'] ?? null;

            // Upload
            $data['upload'] = $settings['upload'] ?? null;
            $data['log_upload'] = $settings['log_upload'] ?? null;
        }

        // Load schedule and capacity
        $data['schedule'] = $config['schedule'] ?? null;
        $data['capacity'] = $config['capacity'] ?? null;

        // detectMultiRange is a legitimate empty array; keep it if present
        $filtered = array_filter($data, fn ($value) => $value !== null);

        return new self($filtered);
    }

    public function toArray(): array
    {
        return [
            'states' => [
                'state' => $this->workingState,
                'error' => $this->error,
                'ipAddress' => $this->ipAddress,
                'lastUsedByPetId' => $this->lastUsedByPetId,
                'lastUsedByName' => $this->lastUsedByName,
                'lastSnapshot' => $this->lastSnapshot,
                'stream' => $this->streamUrl(),
                'lightning' => $this->lightning,
            ],
            'settings' => [
                // Display / camera
                'timestamp_enable' => $this->timestamp_enable,
                'camera' => $this->camera,
                'cameraMultiRange' => $this->cameraMultiRange,
                'microphone' => $this->microphone,
                'night' => $this->night,
                'cameraLight' => $this->cameraLight,
                'preLive' => $this->preLive,

                // Lights
                'lightMode' => $this->lightMode,
                'lightMultiRange' => $this->lightMultiRange,
                'lightAssist' => $this->lightAssist,
                'toiletLight' => $this->toiletLight,
                'cameraRangeTable' => $this->cameraRangeTable,

                // Do not disturb
                'toneMode' => $this->toneMode,
                'toneMultiRange' => $this->toneMultiRange,
                'disturbMode' => $this->disturbMode,
                'distrubMultiRange' => $this->distrubMultiRange,

                // Lock and buttons
                'manualLock' => $this->manualLock,
                'clickOkEnable' => $this->clickOkEnable,

                // Cleaning
                'autoWork' => $this->autoWork,
                'autoIntervalMin' => $this->autoIntervalMin,
                'fixedTimeClear' => $this->fixedTimeClear,
                'avoidRepeat' => $this->avoidRepeat,
                'underweight' => $this->underweight,
                'kitten' => $this->kitten,
                'bury' => $this->bury,
                'deepClean' => $this->deepClean,
                'tumbling' => $this->tumbling,
                'lightest' => $this->lightest,
                'stillTime' => $this->stillTime,
                'sandType' => $this->sandType,
                'unit' => $this->unit,
                'sandSaving' => $this->sandSaving,
                'sandSetUseConfig' => $this->sandSetUseConfig,
                'sandFullWeight' => $this->sandFullWeight,
                'packageStandard' => $this->packageStandard,

                // Toilet
                'toiletDetection' => $this->toiletDetection,
                'softMode' => $this->softMode,
                'phDetection' => $this->phDetection,

                // Detection
                'moveDetection' => $this->moveDetection,
                'moveSensitivity' => $this->moveSensitivity,
                'petDetection' => $this->petDetection,
                'wanderDetection' => $this->wanderDetection,
                'detectInterval' => $this->detectInterval,
                'detectMultiRange' => $this->detectMultiRange,

                // Sound
                'soundEnable' => $this->soundEnable,
                'systemSoundEnable' => $this->systemSoundEnable,
                'volume' => $this->volume,
                'selectedSound' => $this->selectedSound,
                'voice' => $this->voice,

                // Reminders
                'bag_tip_en' => $this->bag_tip_en,
                'spary_tip_en' => $this->spary_tip_en,

                // Home mode
                'userHomeMode' => $this->userHomeMode,

                // Upload
                'upload' => $this->upload,
                'log_upload' => $this->log_upload,

                // Meta - not part of app_conf, but the device expects them
                // in the same settings blob the other devices use.
                'shareOpen' => $this->shareOpen,
                'multiConfig' => $this->multiConfig,
                'autoUpgrade' => $this->autoUpgrade,
                'typeCode' => $this->typeCode,
                'serviceStatus' => $this->serviceStatus,
                'hertz' => $this->hertz,
            ],
            'schedule' => $this->schedule,
            'capacity' => $this->capacity,
        ];
    }

    public function toSnapshot(): ?string
    {
        if (is_null($this->lastSnapshot)) {
            return null;
        }

        return base64_encode(Storage::disk('snapshots')->get($this->lastSnapshot));
    }

    /**
     * The device's RTSP URL, or null while we have not learned its IP yet.
     * Derived rather than stored, so it follows the device across DHCP leases.
     */
    private function streamUrl(): ?string
    {
        if (empty($this->ipAddress)) {
            return null;
        }

        return app(Rtsp::class)->urlForIp($this->ipAddress);
    }
}
