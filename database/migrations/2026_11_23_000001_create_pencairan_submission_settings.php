<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pencairan_submission_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('is_open');
            $table->timestamps();
        });
        Schema::create('pencairan_submission_setting_histories', function (Blueprint $table) {
            $table->id();
            $table->boolean('from_open');
            $table->boolean('to_open');
            $table->string('actor_username');
            $table->timestamp('created_at');
        });
        DB::table('pencairan_submission_settings')->insert([
            'id' => 1, 'is_open' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pencairan_submission_setting_histories');
        Schema::dropIfExists('pencairan_submission_settings');
    }
};
