<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->restrictOnDelete();
            $table->date('tanggal_absensi');
            $table->enum('status_kehadiran', ['H', 'I', 'A']);
            $table->time('jam_masuk')->nullable();
            $table->time('jam_keluar')->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            $table->unique(['karyawan_id', 'tanggal_absensi']);
            $table->index('tanggal_absensi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
