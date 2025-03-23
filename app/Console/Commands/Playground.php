<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\MQTT;

class Playground extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:playground';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {


//        $topic = '/sys/a54dw3gx0nb/d_t4_20240413L12893/thing/service/start';
//        $json = '{"method":"thing.service.start","id":"386251703","params":{"start_action":0},"version":"1.0.0"}';
//
//        MQTT::publish($topic, $json);


        $topic = '/sys/a54dw3gx0nb/d_t4_20240413L12893/thing/service/property/set';
        $json = '{"method":"thing.service.property.set","id":"677220430","params":{"language":"en_US"},"version":"1.0.0"}';

        MQTT::publish($topic, $json);


    }
}
