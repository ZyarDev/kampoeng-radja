<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departemen', function (Blueprint $table) {
            $table->id();
            $table->string('nama_departemen', 100)->unique();
        });

        Schema::create('role', function (Blueprint $table) {
            $table->id();
            $table->string('nama_role', 20)->unique();
        });

        Schema::create('jabatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jabatan', 100)->unique();
            $table->foreignId('role_id')->nullable()->constrained('role')->nullOnDelete();
        });

        Schema::create('penempatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_penempatan', 100)->unique();
            $table->timestamps();
        });

        Schema::create('karyawan', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 20)->unique();
            $table->string('nama', 100);
            $table->date('tanggal_lahir');
            $table->string('tempat_lahir', 100)->nullable();
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->text('alamat')->nullable();
            $table->enum('agama', ['islam', 'kristen', 'katolik', 'hindu', 'buddha', 'konghucu']);
            $table->enum('status_perkawinan', ['belum kawin', 'kawin', 'cerai hidup', 'cerai mati'])->nullable();
            $table->enum('pendidikan', ['SD', 'SMP', 'SMA', 'MAN', 'SMK', 'D3', 'D4', 'S1', 'S2', 'S3'])->nullable();
            $table->foreignId('jabatan_id')->constrained('jabatan')->restrictOnDelete();
            $table->foreignId('departemen_id')->nullable()->constrained('departemen')->restrictOnDelete();
            $table->foreignId('penempatan_id')->nullable()->constrained('penempatan')->nullOnDelete();
            $table->foreignId('atasan_langsung_id')->nullable()->constrained('karyawan')->nullOnDelete();
            $table->enum('status_keaktifan', ['aktif', 'nonaktif']);
            $table->enum('status_kerja', ['kontrak', 'magang', 'buruh', 'freelance'])->nullable();
            $table->date('tanggal_masuk')->nullable();
            $table->date('tanggal_keluar')->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->string('foto_ktp', 255)->nullable();
            $table->string('foto_tanda_tangan', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan');
        Schema::dropIfExists('jabatan');
        Schema::dropIfExists('role');
        Schema::dropIfExists('penempatan');
        Schema::dropIfExists('departemen');
    }
};
