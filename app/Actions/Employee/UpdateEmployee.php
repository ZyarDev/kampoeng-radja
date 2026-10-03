<?php

namespace App\Actions\Employee;

use App\Models\Karyawan;
use App\Models\Jabatan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateEmployee
{
    public function handle(Karyawan $employee, array $data, ?UploadedFile $photo, ?UploadedFile $signature): Karyawan
    {
        $oldPath = $employee->foto_ktp;
        $oldSignaturePath = $employee->foto_tanda_tangan;
        $newPath = $photo?->store('employee-ktp', 'local');
        $newSignaturePath = $signature?->store('karyawan/tanda-tangan', 'local');
        try {
            DB::transaction(function () use ($employee, $data, $newPath, $newSignaturePath): void {
                $jabatanChanged = array_key_exists('jabatan_id', $data)
                    && (int) $employee->jabatan_id !== (int) $data['jabatan_id'];
                $newJabatan = null;
                if ($jabatanChanged) {
                    $newJabatan = Jabatan::query()->findOrFail($data['jabatan_id']);
                    if ($employee->user()->exists() && ! $newJabatan->role_id) {
                        throw ValidationException::withMessages([
                            'jabatan_id' => 'Jabatan yang dipilih belum memiliki role akun yang valid.',
                        ]);
                    }
                }

                unset($data['foto_ktp'], $data['foto_tanda_tangan']);
                if ($newPath) {
                    $data['foto_ktp'] = $newPath;
                }
                if ($newSignaturePath) {
                    $data['foto_tanda_tangan'] = $newSignaturePath;
                }
                $employee->update($data);

                if ($jabatanChanged && $employee->user()->exists()) {
                    $employee->user()->update(['role_id' => $newJabatan->role_id]);
                }

                if ($employee->status_keaktifan === 'nonaktif') {
                    $employee->user()->update(['is_active' => false]);
                }
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            if ($newSignaturePath) {
                Storage::disk('local')->delete($newSignaturePath);
            }

            throw $exception;
        }

        if ($newPath && $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }
        if ($newSignaturePath && $oldSignaturePath) {
            Storage::disk('local')->delete($oldSignaturePath);
        }

        return $employee->refresh();
    }
}
