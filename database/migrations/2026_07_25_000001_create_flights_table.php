<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->string('tanggal');
            $table->string('maskapai');
            $table->string('penerbangan');
            $table->string('rute')->nullable();
            $table->string('asal')->nullable();
            $table->string('tujuan')->nullable();
            $table->string('waktu')->nullable();
            $table->unsignedInteger('manifested')->default(0);
            $table->unsignedInteger('boarded')->nullable();
            $table->unsignedInteger('no_show')->nullable();
            $table->timestamps();

            $table->unique(['tanggal', 'penerbangan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
