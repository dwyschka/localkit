<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pet_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('pets')
            ->whereNotNull('images')
            ->orderBy('id')
            ->select('id', 'images')
            ->each(function (object $pet) {
                $images = json_decode((string) $pet->images, true) ?? [];

                foreach (array_values($images) as $index => $path) {
                    DB::table('pet_images')->insert([
                        'pet_id' => $pet->id,
                        'path' => $path,
                        'sort_order' => $index,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->json('images')->nullable();
        });

        DB::table('pets')->orderBy('id')->select('id')->each(function (object $pet) {
            $images = DB::table('pet_images')
                ->where('pet_id', $pet->id)
                ->orderBy('sort_order')
                ->pluck('path')
                ->all();

            DB::table('pets')->where('id', $pet->id)->update([
                'images' => json_encode($images),
            ]);
        });

        Schema::dropIfExists('pet_images');
    }
};
