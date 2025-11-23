<?php

declare(strict_types=1);

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Repositories\MemoryRepository;

return [

    /*
    |--------------------------------------------------------------------------
    | Default MQTT Connection
    |--------------------------------------------------------------------------
    |
    | This setting defines the default MQTT connection returned when requesting
    | a connection without name from the facade.
    |
    */

    'default_connection' => 'localkit',

    /*
    |--------------------------------------------------------------------------
    | MQTT Connections
    |--------------------------------------------------------------------------
    |
    | These are the MQTT connections used by the application. You can also open
    | an individual connection from the application itself, but all connections
    | defined here can be accessed via name conveniently.
    |
    */

    'connections' => [
        'publisher' => [
            'host' => env('LOCALKIT_BROKER_HOST'),
            'port' => env('LOCALKIT_BROKER_PORT', 1883),
            'protocol' => MqttClient::MQTT_3_1_1,
            'client_id' => 'localkit_publisher',
            'use_clean_session' => false,
            'enable_logging' => env('MQTT_ENABLE_LOGGING', false),
            'log_channel' => env('MQTT_LOG_CHANNEL', null),
            'repository' => MemoryRepository::class,
            'connection_settings' => [
                'tls' => [
                    'enabled' => true,
                    'allow_self_signed_certificate' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'ca_file' => null,
                    'ca_path' => null,
                    'client_certificate_file' => null,
                    'client_certificate_key_file' => null,
                    'client_certificate_key_passphrase' => null,
                ],
                'auth' => [
                    'username' => null,
                    'password' => null,
                ],
                'last_will' => [
                    'topic' => null,
                    'message' => null,
                    'quality_of_service' => 0,
                    'retain' => false,
                ],
                'connect_timeout' => 60,
                'socket_timeout' => 5,
                'resend_timeout' => 10,
                'keep_alive_interval' =>60,
                'auto_reconnect' => [
                    'enabled' => true,
                    'max_reconnect_attempts' =>3,
                    'delay_between_reconnect_attempts' => 0,
                ],

            ],

        ],

        'localkit' => [
            'host' => env('LOCALKIT_BROKER_HOST'),
            'port' => env('LOCALKIT_BROKER_PORT', 1883),
            'protocol' => MqttClient::MQTT_3_1_1,
            'client_id' => 'localkit',
            'use_clean_session' => false,
            'enable_logging' => env('MQTT_ENABLE_LOGGING', false),
            'log_channel' => env('MQTT_LOG_CHANNEL', null),
            'repository' => MemoryRepository::class,
            'connection_settings' => [
                'tls' => [
                    'enabled' => true,
                    'allow_self_signed_certificate' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'ca_file' => null,
                    'ca_path' => null,
                    'client_certificate_file' => null,
                    'client_certificate_key_file' => null,
                    'client_certificate_key_passphrase' => null,
                ],
                'auth' => [
                    'username' => null,
                    'password' => null,
                ],
                'last_will' => [
                    'topic' => null,
                    'message' => null,
                    'quality_of_service' => 0,
                    'retain' => false,
                ],
                'connect_timeout' => 60,
                'socket_timeout' => 5,
                'resend_timeout' => 10,
                'keep_alive_interval' =>60,
                'auto_reconnect' => [
                    'enabled' => true,
                    'max_reconnect_attempts' =>3,
                    'delay_between_reconnect_attempts' => 0,
                ],

            ],

        ],

        'homeassistant' => [

            // The host and port to which the client shall connect.
            'host' => env('HOMEASSISTANT_HOST'),
            'port' => env('HOMEASSISTANT_PORT', 1883),

            // The MQTT protocol version used for the connection.
            'protocol' => MqttClient::MQTT_3_1_1,

            // A specific client id to be used for the connection. If omitted,
            // a random client id will be generated for each new connection.
            'client_id' => env('HOMEASSISTANT_CLIENT_ID').'_dev',

            // Whether a clean session shall be used and requested by the client.
            // A clean session will let the broker forget about subscriptions and
            // queued messages when the client disconnects. Also, if available,
            // data of a previous session will be deleted when connecting.
            'use_clean_session' => env('HOMEASSISTANT_CLEAN_SESSION', false),

            // Whether logging shall be enabled. The default logger will be used
            // with the log level as configured.
            'enable_logging' => env('HOMEASSISTANT_ENABLE_LOGGING', false),

            // Which logging channel to use for logs produced by the MQTT client.
            // If left empty, the default log channel or stack is being used.
            'log_channel' => env('HOMEASSISTANT_LOG_CHANNEL', null),

            // Defines which repository implementation shall be used. Currently,
            // only a MemoryRepository is supported.
            'repository' => MemoryRepository::class,

            // Additional settings used for the connection to the broker.
            // All of these settings are entirely optional and have sane defaults.
            'connection_settings' => [

                // The TLS settings used for the connection. Must match the specified port.
                'tls' => [
                    'enabled' => env('HOMEASSISTANT_TLS_ENABLED', false),
                    'allow_self_signed_certificate' => env('HOMEASSISTANT_TLS_ALLOW_SELF_SIGNED_CERT', true),
                    'verify_peer' => env('HOMEASSISTANT_TLS_VERIFY_PEER', false),
                    'verify_peer_name' => env('HOMEASSISTANT_TLS_VERIFY_PEER_NAME', false),
                    'ca_file' => env('HOMEASSISTANT_TLS_CA_FILE'),
                    'ca_path' => env('HOMEASSISTANT_TLS_CA_PATH'),
                    'client_certificate_file' => env('HOMEASSISTANT_TLS_CLIENT_CERT_FILE'),
                    'client_certificate_key_file' => env('HOMEASSISTANT_TLS_CLIENT_CERT_KEY_FILE'),
                    'client_certificate_key_passphrase' => env('HOMEASSISTANT_TLS_CLIENT_CERT_KEY_PASSPHRASE'),
                ],

                // Credentials used for authentication and authorization.
                'auth' => [
                    'username' => env('HOMEASSISTANT_AUTH_USERNAME'),
                    'password' => env('HOMEASSISTANT_AUTH_PASSWORD'),
                ],

                // Can be used to declare a last will during connection. The last will
                // is published by the broker when the client disconnects abnormally
                // (e.g. in case of a disconnect).
                'last_will' => [
                    'topic' => env('HOMEASSISTANT_LAST_WILL_TOPIC'),
                    'message' => env('HOMEASSISTANT_LAST_WILL_MESSAGE'),
                    'quality_of_service' => env('HOMEASSISTANT_LAST_WILL_QUALITY_OF_SERVICE', 0),
                    'retain' => env('HOMEASSISTANT_LAST_WILL_RETAIN', false),
                ],

                // The timeouts (in seconds) used for the connection. Some of these settings
                // are only relevant when using the event loop of the MQTT client.
                'connect_timeout' => env('HOMEASSISTANT_CONNECT_TIMEOUT', 60),
                'socket_timeout' => env('HOMEASSISTANT_SOCKET_TIMEOUT', 5),
                'resend_timeout' => env('HOMEASSISTANT_RESEND_TIMEOUT', 10),

                // The interval (in seconds) in which the client will send a ping to the broker,
                // if no other message has been sent.
                'keep_alive_interval' => env('HOMEASSISTANT_KEEP_ALIVE_INTERVAL', 60),

                // Additional settings for the optional auto-reconnect. The delay between reconnect attempts is in seconds.
                'auto_reconnect' => [
                    'enabled' => env('HOMEASSISTANT_AUTO_RECONNECT_ENABLED', true),
                    'max_reconnect_attempts' => env('HOMEASSISTANT_AUTO_RECONNECT_MAX_RECONNECT_ATTEMPTS', 3),
                    'delay_between_reconnect_attempts' => env('HOMEASSISTANT_AUTO_RECONNECT_DELAY_BETWEEN_RECONNECT_ATTEMPTS', 0),
                ],

            ],

        ],
        'homeassistant-publisher' => [

            // The host and port to which the client shall connect.
            'host' => env('HOMEASSISTANT_HOST'),
            'port' => env('HOMEASSISTANT_PORT', 1883),

            // The MQTT protocol version used for the connection.
            'protocol' => MqttClient::MQTT_3_1_1,

            // A specific client id to be used for the connection. If omitted,
            // a random client id will be generated for each new connection.
            'client_id' => env('HOMEASSISTANT_CLIENT_ID').'_publisher_dev',

            // Whether a clean session shall be used and requested by the client.
            // A clean session will let the broker forget about subscriptions and
            // queued messages when the client disconnects. Also, if available,
            // data of a previous session will be deleted when connecting.
            'use_clean_session' => env('HOMEASSISTANT_CLEAN_SESSION', false),

            // Whether logging shall be enabled. The default logger will be used
            // with the log level as configured.
            'enable_logging' => env('HOMEASSISTANT_ENABLE_LOGGING', false),

            // Which logging channel to use for logs produced by the MQTT client.
            // If left empty, the default log channel or stack is being used.
            'log_channel' => env('HOMEASSISTANT_LOG_CHANNEL', null),

            // Defines which repository implementation shall be used. Currently,
            // only a MemoryRepository is supported.
            'repository' => MemoryRepository::class,

            // Additional settings used for the connection to the broker.
            // All of these settings are entirely optional and have sane defaults.
            'connection_settings' => [

                // The TLS settings used for the connection. Must match the specified port.
                'tls' => [
                    'enabled' => env('HOMEASSISTANT_TLS_ENABLED', false),
                    'allow_self_signed_certificate' => env('HOMEASSISTANT_TLS_ALLOW_SELF_SIGNED_CERT', true),
                    'verify_peer' => env('HOMEASSISTANT_TLS_VERIFY_PEER', false),
                    'verify_peer_name' => env('HOMEASSISTANT_TLS_VERIFY_PEER_NAME', false),
                    'ca_file' => env('HOMEASSISTANT_TLS_CA_FILE'),
                    'ca_path' => env('HOMEASSISTANT_TLS_CA_PATH'),
                    'client_certificate_file' => env('HOMEASSISTANT_TLS_CLIENT_CERT_FILE'),
                    'client_certificate_key_file' => env('HOMEASSISTANT_TLS_CLIENT_CERT_KEY_FILE'),
                    'client_certificate_key_passphrase' => env('HOMEASSISTANT_TLS_CLIENT_CERT_KEY_PASSPHRASE'),
                ],

                // Credentials used for authentication and authorization.
                'auth' => [
                    'username' => env('HOMEASSISTANT_AUTH_USERNAME'),
                    'password' => env('HOMEASSISTANT_AUTH_PASSWORD'),
                ],

                // Can be used to declare a last will during connection. The last will
                // is published by the broker when the client disconnects abnormally
                // (e.g. in case of a disconnect).
                'last_will' => [
                    'topic' => env('HOMEASSISTANT_LAST_WILL_TOPIC'),
                    'message' => env('HOMEASSISTANT_LAST_WILL_MESSAGE'),
                    'quality_of_service' => env('HOMEASSISTANT_LAST_WILL_QUALITY_OF_SERVICE', 0),
                    'retain' => env('HOMEASSISTANT_LAST_WILL_RETAIN', false),
                ],

                // The timeouts (in seconds) used for the connection. Some of these settings
                // are only relevant when using the event loop of the MQTT client.
                'connect_timeout' => env('HOMEASSISTANT_CONNECT_TIMEOUT', 60),
                'socket_timeout' => env('HOMEASSISTANT_SOCKET_TIMEOUT', 5),
                'resend_timeout' => env('HOMEASSISTANT_RESEND_TIMEOUT', 10),

                // The interval (in seconds) in which the client will send a ping to the broker,
                // if no other message has been sent.
                'keep_alive_interval' => env('HOMEASSISTANT_KEEP_ALIVE_INTERVAL', 60),

                // Additional settings for the optional auto-reconnect. The delay between reconnect attempts is in seconds.
                'auto_reconnect' => [
                    'enabled' => env('HOMEASSISTANT_AUTO_RECONNECT_ENABLED', true),
                    'max_reconnect_attempts' => env('HOMEASSISTANT_AUTO_RECONNECT_MAX_RECONNECT_ATTEMPTS', 3),
                    'delay_between_reconnect_attempts' => env('HOMEASSISTANT_AUTO_RECONNECT_DELAY_BETWEEN_RECONNECT_ATTEMPTS', 0),
                ],

            ],

        ],
    ],

];
