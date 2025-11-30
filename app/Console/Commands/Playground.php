<?php

namespace App\Console\Commands;

use App\Http\Clients\PetKitClient;
use App\Models\Device;
use DateTime;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;
use Symfony\Component\Yaml\Yaml;

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

//        $date = new DateTime('now', new DateTimeZone('Europe/Berlin'));
//
//        echo $date->format('I');
//
//        if ($date->format('I') == 1) {
//            echo "Sommerzeit (MESZ)";
//        } else {
//            echo "Winterzeit (MEZ)";
//        }

        $data = file_get_contents(public_path('petkit/D4H/go2rtc.yml'));

        dd(Yaml::parse($data));




    }

}
