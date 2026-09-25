<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('jabatan', 'role_id')) {
            Schema::table('jabatan', function (Blueprint $table): void {
                $table->foreignId('role_id')
                    ->nullable()
                    ->after('nama_jabatan')
                    ->constrained('role')
                    ->nullOnDelete();
            });
        }

        $roleIds = DB::table('role')
            ->pluck('id', 'nama_role')
            ->mapWithKeys(fn ($id, $name): array => [mb_strtolower((string) $name) => $id]);

        DB::table('jabatan')
            ->whereNull('role_id')
            ->orderBy('id')
            ->get(['id', 'nama_jabatan'])
            ->each(function (object $jabatan) use ($roleIds): void {
                $normalized = mb_strtolower(trim((string) $jabatan->nama_jabatan));
                $tokens = preg_split('/\s+/', preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?: '') ?: [];

                $roleName = match (true) {
                    (bool) array_intersect($tokens, ['dirut', 'direktur', 'manajer', 'manager']) => 'super_admin',
                    (bool) array_intersect($tokens, ['spv', 'supervisor']) => 'admin',
                    (bool) array_intersect($tokens, [
                        'marketing', 'marcom', 'markom', 'it', 'finance', 'kasir',
                        'operasional', 'general', 'facility',
                    ]) => 'user',
                    default => null,
                };

                if ($roleName !== null && isset($roleIds[$roleName])) {
                    DB::table('jabatan')
                        ->where('id', $jabatan->id)
                        ->update(['role_id' => $roleIds[$roleName]]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('jabatan', 'role_id')) {
            Schema::table('jabatan', function (Blueprint $table): void {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            });
        }
    }
};
