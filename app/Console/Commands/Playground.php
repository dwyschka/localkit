<?php

namespace App\Console\Commands;

use App\Http\Clients\PetKitClient;
use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

        $str = "{\"wifi\":{\"ssid\":\"No Signal\",\"rsq\":-60,\"bssid\":\"7eacb921d240\"},\"hardware\":1,\"firmware\":\"810\",\"locale\":\"Europe/Berlin\",\"ir\":0,\"batV\":4151,\"DCV\":5992,\"runtime\":20111,\"mem\":0,\"cpu\":0,\"ubat\":0,\"cameraStatus\":1,\"door\":1,\"food\":0,\"bowl\":-1,\"feeding\":0,\"eating\":0,\"ota\":0,\"ultra_sta\":0,\"ready\":[0,0,0,0,0],\"err\":{\"DC\":0,\"sys\":0,\"rtc_c\":0,\"moto\":0,\"blk_f\":0,\"blk_d\":0,\"camera\":0,\"serial\":0},\"sensor\":{\"left_hall\":0,\"home_hall\":0,\"right_hall\":0,\"left_sub_hall\":0},\"other\":\"PowerSRC:1,CloudUseAcceDomain:0,DnsList:NotGot,Ip:10.10.46.30,feed_recoed[0-0-0-0-0]\"}";

        $match = json_decode($str, false);



    }

}
