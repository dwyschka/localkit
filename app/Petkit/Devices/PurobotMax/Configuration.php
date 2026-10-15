<?php

namespace App\Petkit\Devices\PurobotMax;

use App\DTOs\DeviceConfigurationDTO;
use App\DTOs\MultiRangeDTO;
use App\Homeassistant\HASwitch;
use App\Homeassistant\Number;
use App\Homeassistant\Select;
use App\Homeassistant\Sensor;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;

/**
 * PetKit Purobot Max (T5) - a self-cleaning litter box with an odour
 * neutraliser (spray/"N50") module. No camera support here - that is left
 * for a later pass (the firmware does have one, see t5/config.txt's
 * [KAMERA]-tagged fields, which are deliberately not modeled below).
 *
 * Action codes (same value across start/stop/continue/end, only the MQTT
 * topic differs - see t5/actions.txt and t5/ctrl_actions.txt):
 *   0 clean, 1 dump, 2 spray (start-only - no stop/continue/end call for it
 *   was found anywhere in the decompiled app), 3 recover, 4 smooth,
 *   7 light (start/end only), 9 maintenance (start/end only).
 * Values 5 (calibration), 6 (sand-lack reset) and 8 (setup prep) exist as
 * internal workMode states but have no confirmed app-triggered start button,
 * so - same caution as with the T6 implementation - no button is offered
 * for them.
 *
 * Unlike the T6 ("Purobot Ultra"), ctrl_t5's dispatch_handler_trash_bag_auth
 * is an empty stub and there is no /dev_package_auth route at all: this
 * device has no waste-bag/NFC mechanism, so there is nothing to pack/door
 * here.
 */
class Configuration extends DeviceConfigurationDTO implements ConfigurationInterface
{
    // States
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

    public ?int $lastUsedByPetId;

    #[Sensor(
        technicalName: 'last_used_by',
        name: 'Last Used By',
        icon: 'mdi:paw',
        valueTemplate: '{{ value_json.states.lastUsedByName }}',
        entityCategory: 'diagnostic'
    )]
    public ?string $lastUsedByName;

    // The currently active cleaning workMode (0/1/3/4), as last reported by
    // work_start/work_continue - not exposed to Home Assistant. Used only to
    // know which value to echo back on pause/continue/terminate, since the
    // device does not accept those without the matching workMode.
    public ?int $workMode;


    // Meta
    #[HASwitch(
        technicalName: 'share_open',
        name: 'Share Open',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.shareOpen }}',
        commandTemplate: '{"shareOpen":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $shareOpen;

    #[Sensor(
        technicalName: 'type_code',
        name: 'Type Code',
        icon: 'mdi:identifier',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.typeCode }}'
    )]
    public int $typeCode;


    // Cleaning
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

    // Cloud-managed scheduled-cleaning/-spray timing list (id/type/time/
    // repeats per entry, type 0 = clean per T6RegularCleanSettingPresenter.
    // getFixTimeSetting()). Editing it goes through a separate cloud HTTP
    // endpoint (getFixTimeSetting/getFixT5TimeSetting), not MQTT - kept here
    // read-only purely so a settings save does not silently wipe it.
    public array $schedule;

    // Not in Localkit UI - not exposed to HA (range/meaning not verified)
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

    #[HASwitch(
        technicalName: 'bury',
        name: 'Waste Covering',
        commandTopic: 'setting/set',
        icon: 'mdi:broom',
        valueTemplate: '{{ value_json.settings.bury }}',
        commandTemplate: '{"bury":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $bury;

    #[HASwitch(
        technicalName: 'deep_clean',
        name: 'Deep Cleaning',
        commandTopic: 'setting/set',
        icon: 'mdi:broom',
        valueTemplate: '{{ value_json.settings.deepClean }}',
        commandTemplate: '{"deepClean":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $deepClean;

    // Confirmed to drive a real motor routine (pk_motor_set_tumbling_mode),
    // not just a flag - see t5/config.txt.
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

    // Litter type - resolved against OverseaToiletLitterSettingFragment,
    // which calls selectCatLitterStatus(sandType - 1): the app is 1-based
    // (0 = not set yet), with the panels declared in the order Bentonite(1),
    // Tofu(2), Mixed(3).
    #[Select(
        technicalName: 'sand_type',
        name: 'Litter Type',
        options: ['0', '1', '2', '3'],
        commandTopic: 'setting/set',
        icon: 'mdi:grain',
        valueTemplate: '{{ value_json.settings.sandType }}',
        commandTemplate: '{"sandType": {{value}}}',
        entityCategory: 'config'
    )]
    public int $sandType;

    // kg/lb display unit - confirmed real (T6SettingActivity's
    // ll_t6_weight_switch opens a unit picker), not tied to any particular
    // weight sensor.
    #[Select(
        technicalName: 'unit',
        name: 'Weight Unit',
        options: ['0', '1'],
        commandTopic: 'setting/set',
        icon: 'mdi:scale-balance',
        valueTemplate: '{{ value_json.settings.unit }}',
        commandTemplate: '{"unit": {{value}}}',
        entityCategory: 'config'
    )]
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

    // Device-internal, not exposed either by the vendor app
    public array $sandSetUseConfig;
    public array $sandFullWeight;

    // Target rest position of the cleaning mechanism - meaning of the
    // concrete values not verified, not exposed to HA
    public int $downpos;

    // Confirmed real and simple: T6SettingActivity's t6_screen_checkbox
    // calls updateDeviceSettings("lightMode", ...) directly - the device's
    // own screen/indicator light, not the toilet light above.
    #[HASwitch(
        technicalName: 'light_mode',
        name: 'Screen Light',
        commandTopic: 'setting/set',
        icon: 'mdi:brightness-6',
        valueTemplate: '{{ value_json.settings.lightMode }}',
        commandTemplate: '{"lightMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $lightMode;

    // No app screen reference found for this one (only the T6Settings model
    // getter/setter exist in the decompiled app) - meaning/effect unverified.
    public bool $softMode;

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

    // Wire key confirmed against a real device dump (t5/device_config.txt,
    // app_conf.toiletLight) - the app_conf section has no "_enable"/"_Enable"
    // suffix on this one, unlike the earlier guess.
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

    // Active-time schedule for the toilet light above - app screen
    // "Choose_light_screen_title" (T6LightSettingActivity). Confirmed real
    // wire key (t5/device_config.txt, app_conf.lightMultiRange).
    public MultiRangeDTO $lightMultiRange;

    // Presence/usage detection, NOT video recording on this device (there is
    // no camera path wired into this implementation). Wire key confirmed
    // against t5/device_config.txt (app_conf.toiletDetection) and against
    // T6IntelligentDetectionActivity, which also has no "_Enable" suffix.
    #[HASwitch(
        technicalName: 'toilet_detection',
        name: 'Toilet Use Detection',
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

    #[HASwitch(
        technicalName: 'disturb_mode',
        name: 'Do Not Disturb',
        commandTopic: 'setting/set',
        icon: 'mdi:bell-off',
        valueTemplate: '{{ value_json.settings.disturbMode }}',
        commandTemplate: '{"disturbMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $disturbMode;

    public MultiRangeDTO $distrubMultiRange;

    // Speech settings screen ("Speech_settings" / T6SpeechSettingsActivity).
    #[HASwitch(
        technicalName: 'tone_mode',
        name: 'Voice Prompts',
        commandTopic: 'setting/set',
        icon: 'mdi:account-voice',
        valueTemplate: '{{ value_json.settings.toneMode }}',
        commandTemplate: '{"toneMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $toneMode;

    #[HASwitch(
        technicalName: 'system_sound_enable',
        name: 'System Voice Announcements',
        commandTopic: 'setting/set',
        icon: 'mdi:bullhorn',
        valueTemplate: '{{ value_json.settings.systemSoundEnable }}',
        commandTemplate: '{"systemSoundEnable":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $systemSoundEnable;

    // Do-not-disturb period for the voice prompts above (separate from
    // disturbMode/distrubMultiRange, which only silences cleaning) - app
    // screen "Choose_do_not_disturb_title" (T6SpeechDoNotDisturbSettingActivity).
    public MultiRangeDTO $toneMultiRange;

    // soundEnable: no app screen reference found at all (only present in the
    // raw device dump) - not exposed. volume/selectedSound: the app reads
    // the actual slider values from a server-provided discrete list
    // (T6SpeechSettingsActivity.initVolume()), so the raw device integers
    // are not safe to expose as a plain 0-100 range - not exposed.
    public bool $soundEnable;
    public int $volume;
    public int $selectedSound;

    // "voice" (not "voiceDetection" - wire key fixed against
    // t5/device_config.txt): ties into AiVoiceActivity's per-pet AI voice
    // message feature (getVoice() > 0), not a simple on/off switch - not
    // exposed until that feature is modeled.
    public int $voice;

    // Cloud upload - not exposed to HA
    public bool $upload;


    // Odour neutraliser / spray module ("N50")
    #[HASwitch(
        technicalName: 'auto_spray',
        name: 'Auto Spray',
        commandTopic: 'setting/set',
        icon: 'mdi:spray',
        valueTemplate: '{{ value_json.settings.autoSpray }}',
        commandTemplate: '{"autoSpray":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $autoSpray;

    #[HASwitch(
        technicalName: 'deep_spray',
        name: 'Deep Spray',
        commandTopic: 'setting/set',
        icon: 'mdi:spray',
        valueTemplate: '{{ value_json.settings.deepSpray }}',
        commandTemplate: '{"deepSpray":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $deepSpray;

    #[Sensor(
        technicalName: 'fixed_time_spray',
        name: 'Scheduled Spray',
        icon: 'mdi:calendar-clock',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.fixedTimeSpray }}'
    )]
    public int $fixedTimeSpray;

    // Raw device cartridge counter - not exposed to HA; n50Durability/
    // n50NextChange below are the Localkit-side bookkeeping, same pattern as
    // the PuraMax (T4) implementation.
    public int $sprayDays;

    // No app screen reference found - not exposed.
    public bool $deodor_tip_en;

    #[Number(
        technicalName: 'n50Durability',
        name: 'N50 Durability',
        commandTopic: 'setting/set',
        icon: 'mdi:diamond-stone',
        valueTemplate: '{{ value_json.consumables.n50Durability }}',
        commandTemplate: '{"n50Durability":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 90,
        step: 1
    )]
    public int $n50Durability;

    #[Sensor(
        technicalName: 'durabilityInDays',
        name: 'Next N50 Change in Days',
        icon: 'mdi:update',
        unitOfMeasurement: 'd',
        valueTemplate: '{{ ((value_json.consumables.n50NextChange - as_timestamp(now())) / 86400) | round(1) }}',
        entityCategory: 'diagnostic'
    )]
    public mixed $n50NextChange;

    protected function rules(): array
    {
        return [
            'workingState' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
            'lastUsedByPetId' => ['nullable', 'integer'],
            'lastUsedByName' => ['nullable', 'string'],
            'workMode' => ['nullable', 'integer'],

            'shareOpen' => ['bool'],
            'typeCode' => ['integer'],

            'autoWork' => ['bool'],
            'autoIntervalMin' => ['integer', 'min:0'],
            'fixedTimeClear' => ['integer', 'min:0'],
            'schedule' => ['array'],
            'avoidRepeat' => ['bool'],
            'underweight' => ['bool'],
            'kitten' => ['bool'],
            'bury' => ['bool'],
            'deepClean' => ['bool'],
            'tumbling' => ['bool'],
            'lightest' => ['integer', 'min:0'],
            'stillTime' => ['integer', 'min:0'],
            'sandType' => ['integer'],
            'unit' => ['integer', 'in:0,1'],
            'sandSaving' => ['bool'],
            'sandSetUseConfig' => ['array'],
            'sandFullWeight' => ['array'],
            'downpos' => ['integer'],
            'lightMode' => ['bool'],
            'softMode' => ['bool'],
            'lightAssist' => ['bool'],
            'toiletLight' => ['bool'],
            'lightMultiRange' => ['array'],
            'toiletDetection' => ['bool'],
            'phDetection' => ['bool'],
            'manualLock' => ['bool'],
            'clickOkEnable' => ['bool'],
            'disturbMode' => ['bool'],
            'distrubMultiRange' => ['array'],
            'soundEnable' => ['bool'],
            'systemSoundEnable' => ['bool'],
            'volume' => ['integer', 'min:0'],
            'selectedSound' => ['integer'],
            'toneMode' => ['bool'],
            'toneMultiRange' => ['array'],
            'voice' => ['integer'],
            'upload' => ['bool'],

            'autoSpray' => ['bool'],
            'deepSpray' => ['bool'],
            'fixedTimeSpray' => ['integer', 'min:0'],
            'sprayDays' => ['integer', 'min:0'],
            'deodor_tip_en' => ['bool'],
            'n50Durability' => ['integer', 'min:0', 'max:90'],
            'n50NextChange' => ['nullable'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'workingState' => null,
            'error' => null,
            'lastUsedByPetId' => null,
            'lastUsedByName' => null,
            'workMode' => null,

            'shareOpen' => false,
            'typeCode' => 0,

            'autoWork' => true,
            'autoIntervalMin' => 0,
            'fixedTimeClear' => 0,
            'schedule' => [],
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
            'downpos' => 0,
            'lightMode' => true,
            'softMode' => false,
            'lightAssist' => true,
            'toiletLight' => false,
            'lightMultiRange' => [
                'name' => 'lightMultiRange',
                'ranges' => [['from' => 0, 'till' => 1440]],
            ],
            'toiletDetection' => true,
            'phDetection' => true,
            'manualLock' => false,
            'clickOkEnable' => true,
            'disturbMode' => false,
            'distrubMultiRange' => [
                'name' => 'distrubMultiRange',
                'ranges' => [['from' => 40, 'till' => 520]],
            ],
            'soundEnable' => true,
            'systemSoundEnable' => true,
            'volume' => 7,
            'selectedSound' => -1,
            'toneMode' => false,
            'toneMultiRange' => [
                'name' => 'toneMultiRange',
                'ranges' => [],
            ],
            'voice' => 0,
            'upload' => true,

            'autoSpray' => true,
            'deepSpray' => false,
            'fixedTimeSpray' => 0,
            'sprayDays' => 0,
            'deodor_tip_en' => true,
            'n50Durability' => 30,
            'n50NextChange' => 0,
        ];
    }

    protected function casts(): array
    {
        return [
            'workingState' => new StringCast(),
            'error' => new StringCast(),
            'lastUsedByPetId' => new IntegerCast(),
            'lastUsedByName' => new StringCast(),
            'workMode' => new IntegerCast(),

            'shareOpen' => new BooleanCast(),
            'typeCode' => new IntegerCast(),

            'autoWork' => new BooleanCast(),
            'autoIntervalMin' => new IntegerCast(),
            'fixedTimeClear' => new IntegerCast(),
            'schedule' => new ArrayCast(),
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
            'downpos' => new IntegerCast(),
            'lightMode' => new BooleanCast(),
            'softMode' => new BooleanCast(),
            'lightAssist' => new BooleanCast(),
            'toiletLight' => new BooleanCast(),
            'lightMultiRange' => new DTOCast(MultiRangeDTO::class),
            'toiletDetection' => new BooleanCast(),
            'phDetection' => new BooleanCast(),
            'manualLock' => new BooleanCast(),
            'clickOkEnable' => new BooleanCast(),
            'disturbMode' => new BooleanCast(),
            'distrubMultiRange' => new DTOCast(MultiRangeDTO::class),
            'soundEnable' => new BooleanCast(),
            'systemSoundEnable' => new BooleanCast(),
            'volume' => new IntegerCast(),
            'selectedSound' => new IntegerCast(),
            'toneMode' => new BooleanCast(),
            'toneMultiRange' => new DTOCast(MultiRangeDTO::class),
            'voice' => new IntegerCast(),
            'upload' => new BooleanCast(),

            'autoSpray' => new BooleanCast(),
            'deepSpray' => new BooleanCast(),
            'fixedTimeSpray' => new IntegerCast(),
            'sprayDays' => new IntegerCast(),
            'deodor_tip_en' => new BooleanCast(),
            'n50Durability' => new IntegerCast(),
        ];
    }

    public static function fromDevice(Device|BluetoothDevice $device): self
    {
        $config = $device->configuration;
        $data = [];

        $data['workingState'] = $device->working_state ?? null;
        $data['error'] = $device->error ?? null;

        if (isset($config['states'])) {
            $states = $config['states'];
            $data['lastUsedByPetId'] = $states['lastUsedByPetId'] ?? null;
            $data['lastUsedByName'] = $states['lastUsedByName'] ?? null;
            $data['workMode'] = $states['workMode'] ?? null;
        }

        if (isset($config['settings'])) {
            $settings = $config['settings'];

            $data['shareOpen'] = $settings['shareOpen'] ?? null;
            $data['typeCode'] = $settings['typeCode'] ?? null;

            $data['autoWork'] = $settings['autoWork'] ?? null;
            $data['autoIntervalMin'] = $settings['autoIntervalMin'] ?? null;
            $data['fixedTimeClear'] = $settings['fixedTimeClear'] ?? null;
            $data['schedule'] = $settings['schedule'] ?? null;
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
            $data['downpos'] = $settings['downpos'] ?? null;
            $data['lightMode'] = $settings['lightMode'] ?? null;
            $data['softMode'] = $settings['softMode'] ?? null;
            $data['lightAssist'] = $settings['lightAssist'] ?? null;
            $data['toiletLight'] = $settings['toiletLight'] ?? null;
            $data['lightMultiRange'] = $settings['lightMultiRange'] ?? null;
            $data['toiletDetection'] = $settings['toiletDetection'] ?? null;
            $data['phDetection'] = $settings['phDetection'] ?? null;
            $data['manualLock'] = $settings['manualLock'] ?? null;
            $data['clickOkEnable'] = $settings['clickOkEnable'] ?? null;
            $data['disturbMode'] = $settings['disturbMode'] ?? null;
            $data['distrubMultiRange'] = $settings['distrubMultiRange'] ?? null;
            $data['soundEnable'] = $settings['soundEnable'] ?? null;
            $data['systemSoundEnable'] = $settings['systemSoundEnable'] ?? null;
            $data['volume'] = $settings['volume'] ?? null;
            $data['selectedSound'] = $settings['selectedSound'] ?? null;
            $data['toneMode'] = $settings['toneMode'] ?? null;
            $data['toneMultiRange'] = $settings['toneMultiRange'] ?? null;
            $data['voice'] = $settings['voice'] ?? null;
            $data['upload'] = $settings['upload'] ?? null;

            $data['autoSpray'] = $settings['autoSpray'] ?? null;
            $data['deepSpray'] = $settings['deepSpray'] ?? null;
            $data['fixedTimeSpray'] = $settings['fixedTimeSpray'] ?? null;
            $data['sprayDays'] = $settings['sprayDays'] ?? null;
            $data['deodor_tip_en'] = $settings['deodor_tip_en'] ?? null;
        }

        if (isset($config['consumables'])) {
            $data['n50Durability'] = $config['consumables']['n50Durability'] ?? null;
            $data['n50NextChange'] = $config['consumables']['n50NextChange'] ?? null;
        }

        $filtered = array_filter($data, fn ($value) => $value !== null);

        return new self($filtered);
    }

    public function toArray(): array
    {
        return [
            'states' => [
                'state' => $this->workingState,
                'error' => $this->error,
                'lastUsedByPetId' => $this->lastUsedByPetId,
                'lastUsedByName' => $this->lastUsedByName,
                'workMode' => $this->workMode,
            ],
            'consumables' => [
                'n50Durability' => $this->n50Durability,
                'n50NextChange' => $this->n50NextChange,
            ],
            'settings' => [
                'shareOpen' => $this->shareOpen,
                'typeCode' => $this->typeCode,

                'autoWork' => $this->autoWork,
                'autoIntervalMin' => $this->autoIntervalMin,
                'fixedTimeClear' => $this->fixedTimeClear,
                'schedule' => $this->schedule,
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
                'downpos' => $this->downpos,
                'lightMode' => $this->lightMode,
                'softMode' => $this->softMode,
                'lightAssist' => $this->lightAssist,
                'toiletLight' => $this->toiletLight,
                'lightMultiRange' => $this->lightMultiRange,
                'toiletDetection' => $this->toiletDetection,
                'phDetection' => $this->phDetection,
                'manualLock' => $this->manualLock,
                'clickOkEnable' => $this->clickOkEnable,
                'disturbMode' => $this->disturbMode,
                'distrubMultiRange' => $this->distrubMultiRange,
                'soundEnable' => $this->soundEnable,
                'systemSoundEnable' => $this->systemSoundEnable,
                'volume' => $this->volume,
                'selectedSound' => $this->selectedSound,
                'toneMode' => $this->toneMode,
                'toneMultiRange' => $this->toneMultiRange,
                'voice' => $this->voice,
                'upload' => $this->upload,

                'autoSpray' => $this->autoSpray,
                'deepSpray' => $this->deepSpray,
                'fixedTimeSpray' => $this->fixedTimeSpray,
                'sprayDays' => $this->sprayDays,
                'deodor_tip_en' => $this->deodor_tip_en,
            ],
        ];
    }
}
