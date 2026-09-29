<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pic', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_pic', 100)->unique();
        });

        Schema::create('event', function (Blueprint $table): void {
            $table->id();
            $table->string('jenis_event', 150)->unique();
        });

        Schema::create('lokasi', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_lokasi', 150)->unique();
        });

        Schema::create('closing_event', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pic_id')->constrained('pic')->restrictOnDelete();
            $table->foreignId('event_id')->constrained('event')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal')->index();
            $table->date('tanggal_selesai')->nullable()->index();
            $table->enum('status_event', ['aktif', 'dibatalkan'])->default('aktif')->index();
            $table->string('konsumen', 150);
            $table->string('kontak', 20);
            $table->time('jam_kedatangan');
            $table->text('additional')->nullable();
            $table->boolean('konsumsi');
            $table->integer('jumlah_pengunjung');
            $table->decimal('harga_total', 15, 2);
            $table->text('panitia')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('closing_event_lokasi', function (Blueprint $table): void {
            $table->foreignId('closing_event_id')->constrained('closing_event')->cascadeOnDelete();
            $table->foreignId('lokasi_id')->constrained('lokasi')->restrictOnDelete();
            $table->primary(['closing_event_id', 'lokasi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_event_lokasi');
        Schema::dropIfExists('closing_event');
        Schema::dropIfExists('lokasi');
        Schema::dropIfExists('event');
        Schema::dropIfExists('pic');
    }
};
