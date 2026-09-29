<?php

namespace Tests\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

abstract class ActivityTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'UTC',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'petkit.bypass_auth' => false,
        ]);
        DB::purge('sqlite');

        foreach (['pets', 'devices'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
            });
        }
        Schema::create('history', function (Blueprint $table): void {
            $table->id();
            $table->string('messageId');
            $table->integer('pet_id')->nullable();
            $table->integer('device_id')->nullable();
            $table->string('type')->nullable();
            $table->json('parameters')->nullable();
            $table->timestamps();
        });
        Schema::create('media_files', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id');
            $table->string('file_id');
            $table->string('file_type')->nullable();
            $table->string('module_type')->nullable();
        });

        Filament::setCurrentPanel(Filament::getPanel('petkit'));
    }

    protected function event(int $id, array $overrides = []): void
    {
        DB::table('history')->insert(array_replace([
            'id' => $id, 'messageId' => 'event-'.$id, 'type' => 'DETECT',
            'created_at' => '2026-09-01 12:00:00', 'updated_at' => '2026-09-01 12:00:00',
        ], $overrides));
    }
}
