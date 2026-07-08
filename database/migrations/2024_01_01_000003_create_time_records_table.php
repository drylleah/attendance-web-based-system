<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('time_records')) return;

        Schema::create('time_records', function (Blueprint $table) {
            $table->id();
            $table->string('id_number', 50);
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('middle_initial', 5)->nullable();
            $table->dateTime('time_in')->nullable();
            $table->dateTime('time_out')->nullable();
            $table->date('date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('saved_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_records');
    }
};
