<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tentang_kami', function (Blueprint $table): void {
            $table->id();
            $table->string('hero_judul')->nullable();
            $table->text('hero_subjudul')->nullable();
            $table->string('hero_foto')->nullable();
            $table->string('struktur_organisasi_foto')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('tentang_kami_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tentang_kami_id')->constrained('tentang_kami')->cascadeOnDelete();
            $table->unsignedTinyInteger('section_number');
            $table->string('judul')->nullable();
            $table->text('deskripsi')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
            $table->unique(['tentang_kami_id', 'section_number']);
        });
        Schema::create('tentang_kami_section_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')->constrained('tentang_kami_sections')->cascadeOnDelete();
            $table->string('foto');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tentang_kami_section_photos');
        Schema::dropIfExists('tentang_kami_sections');
        Schema::dropIfExists('tentang_kami');
    }
};
