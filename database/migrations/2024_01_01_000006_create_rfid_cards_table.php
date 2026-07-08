<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rfid_cards')) return;

        Schema::create('rfid_cards', function (Blueprint $table) {
            $table->id();
            $table->string('id_number', 50)->unique()->comment('School ID — this is the RFID identifier');
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('middle_initial', 5)->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->timestamp('registered_at')->useCurrent();

            $table->index('id_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_cards');
    }
};
