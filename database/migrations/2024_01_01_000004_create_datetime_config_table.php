<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('datetime_config')) {
        Schema::create('datetime_config', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->default(1)->primary();
            $table->enum('mode', ['automatic', 'manual'])->default('automatic');
            $table->date('start_date')->nullable();
            $table->time('start_time')->nullable();
            $table->date('end_date')->nullable();
            $table->time('end_time')->nullable();
            $table->dateTime('last_triggered_at')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        } // end if !hasTable

        // Ensure the single config row always exists
        DB::table('datetime_config')->insertOrIgnore([
            'id'   => 1,
            'mode' => 'automatic',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('datetime_config');
    }
};
