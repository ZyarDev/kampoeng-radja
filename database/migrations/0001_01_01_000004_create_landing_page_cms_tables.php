<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_berita', function (Blueprint $table) {
            $table->id();
            $this->addAuditForeignKeys($table);
            $table->string('judul', 150);
            $table->text('deskripsi');
            $table->string('foto', 255);
            $table->dateTime('tanggal_publish');
            $table->timestamps();
        });

        Schema::create('event_promo', function (Blueprint $table) {
            $table->id();
            $this->addAuditForeignKeys($table);
            $table->string('judul', 150);
            $table->string('deskripsi_singkat', 255);
            $table->text('deskripsi_lengkap')->nullable();
            $table->string('poster', 255);
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('link_wa', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('urutan_tampil')->default(0);
            $table->timestamps();
        });

        Schema::create('wahana', function (Blueprint $table) {
            $table->id();
            $this->addAuditForeignKeys($table);
            $table->string('nama_wahana', 150);
            $table->string('deskripsi_singkat', 255);
            $table->string('foto', 255);
            $table->string('label', 50)->nullable();
            $table->boolean('is_unggulan');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('urutan_tampil')->default(0);
            $table->timestamps();
        });

        Schema::create('mitra', function (Blueprint $table) {
            $table->id();
            $this->addAuditForeignKeys($table);
            $table->string('nama_brand', 150);
            $table->string('logo', 255);
            $table->boolean('is_active');
            $table->unsignedInteger('urutan_tampil')->default(0);
            $table->timestamps();
        });

        Schema::create('galeri_event', function (Blueprint $table) {
            $table->id();
            $this->addAuditForeignKeys($table);
            $table->string('nama_event', 150);
            $table->text('deskripsi');
            $table->date('tanggal_event')->nullable();
            $table->timestamps();
        });

        Schema::create('galeri_event_foto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('galeri_event_id')->constrained('galeri_event')->cascadeOnDelete();
            $this->addAuditForeignKeys($table);
            $table->string('foto', 255);
            $table->string('caption', 255)->nullable();
            $table->integer('urutan')->nullable();
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('galeri_event_foto');
        Schema::dropIfExists('galeri_event');
        Schema::dropIfExists('mitra');
        Schema::dropIfExists('wahana');
        Schema::dropIfExists('event_promo');
        Schema::dropIfExists('media_berita');
    }

    private function addAuditForeignKeys(Blueprint $table): void
    {
        $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
        $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    }
};
