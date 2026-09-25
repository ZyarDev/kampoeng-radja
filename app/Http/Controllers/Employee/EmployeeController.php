<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Employee\CreateEmployee;
use App\Actions\Employee\DeleteEmployee;
use App\Actions\Employee\ProcessEmployeeExit;
use App\Actions\Employee\UpdateEmployee;
use App\Exports\Employee\EmployeesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\ExitEmployeeRequest;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Penempatan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        [$roleName] = $this->accessContext($request);
        $query = Karyawan::query();

        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search, $roleName): void {
                $builder->where('nama', 'like', "%{$search}%");
                if ($roleName === 'super_admin') {
                    $builder->orWhere('nik', 'like', "%{$search}%");
                }
            });
        }

        foreach (['jabatan_id', 'departemen_id', 'penempatan_id', 'status_keaktifan', 'status_kerja'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        $employees = $query
            ->with([
                'jabatan:id,nama_jabatan',
                'departemen:id,nama_departemen',
                'penempatan:id,nama_penempatan',
                'atasanLangsung:id,nama',
                'user:id,karyawan_id',
            ])
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Karyawan $employee): array => $this->employeeListPayload($employee, $roleName));

        return Inertia::render('Internal/Employee/Index', [
            'employees' => $employees,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'jabatan_id' => $request->input('jabatan_id'),
                'departemen_id' => $request->input('departemen_id'),
                'penempatan_id' => $request->input('penempatan_id'),
                'status_keaktifan' => $request->input('status_keaktifan'),
                'status_kerja' => $request->input('status_kerja'),
            ],
            'masterData' => [
                'jabatan' => Jabatan::query()->orderBy('nama_jabatan')->get(['id', 'nama_jabatan']),
                'departemen' => Departemen::query()->orderBy('nama_departemen')->get(['id', 'nama_departemen']),
                'penempatan' => Penempatan::query()->orderBy('nama_penempatan')->get(['id', 'nama_penempatan']),
            ],
            'permissions' => [
                'roleName' => $roleName,
                'canManage' => $roleName === 'super_admin',
                'canExport' => $roleName === 'super_admin',
                'canSearch' => true,
                'canManageMasters' => $roleName === 'super_admin',
            ],
            'user' => $this->userPayload($request),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Internal/Employee/Create', [
            'masterData' => $this->formMasterData(),
            'user' => $this->userPayload($request),
        ]);
    }

    public function export(Request $request): BinaryFileResponse|RedirectResponse
    {
        abort_unless($request->user()?->role()->value('nama_role') === 'super_admin', 403);

        $validated = $request->validate([
            'status_keaktifan' => ['required', 'in:aktif,nonaktif'],
        ]);
        $activeStatus = $validated['status_keaktifan'];
        $employees = Karyawan::query()
            ->select([
                'id',
                'nama',
                'nik',
                'jenis_kelamin',
                'agama',
                'tempat_lahir',
                'tanggal_lahir',
                'alamat',
                'status_perkawinan',
                'pendidikan',
                'jabatan_id',
                'departemen_id',
                'penempatan_id',
                'atasan_langsung_id',
                'status_kerja',
                'status_keaktifan',
                'tanggal_masuk',
                'tanggal_keluar',
                'no_hp',
            ])
            ->with([
                'jabatan:id,nama_jabatan',
                'departemen:id,nama_departemen',
                'penempatan:id,nama_penempatan',
                'atasanLangsung:id,nama',
            ])
            ->where('status_keaktifan', $activeStatus)
            ->orderBy('nama')
            ->orderBy('id')
            ->get();

        if ($employees->isEmpty()) {
            return to_route('dashboard.karyawan.index')->with(
                'error',
                'Tidak ada data karyawan dengan status keaktifan yang dipilih.',
            );
        }

        return Excel::download(
            new EmployeesExport($employees, $activeStatus),
            "data-karyawan-{$activeStatus}.xlsx",
        );
    }

    public function store(StoreEmployeeRequest $request, CreateEmployee $action): RedirectResponse
    {
        $employee = $action->handle(
            $request->validated(),
            $request->file('foto_ktp'),
            $request->file('foto_tanda_tangan'),
        );

        return to_route('dashboard.karyawan.show', $employee)->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function show(Request $request, Karyawan $karyawan): Response
    {
        [$roleName] = $this->accessContext($request);
        $employee = Karyawan::query()
            ->with([
                'jabatan:id,nama_jabatan,role_id',
                'jabatan.role:id,nama_role',
                'departemen:id,nama_departemen',
                'penempatan:id,nama_penempatan',
                'atasanLangsung:id,nama',
                'user.role:id,nama_role',
            ])
            ->findOrFail($karyawan->id);

        return Inertia::render('Internal/Employee/Show', [
            'employee' => $this->employeeDetailPayload($employee, $roleName),
            'permissions' => [
                'roleName' => $roleName,
                'canManage' => $roleName === 'super_admin',
                'canDelete' => $roleName === 'super_admin' && ! $employee->user()->exists() && ! $employee->absensi()->exists(),
                'canManageMasters' => $roleName === 'super_admin',
                'canManageAccount' => $roleName === 'super_admin',
            ],
            'user' => $this->userPayload($request),
        ]);
    }

    public function edit(Request $request, Karyawan $karyawan): Response
    {
        $karyawan->load([
            'jabatan:id,nama_jabatan',
            'departemen:id,nama_departemen',
            'penempatan:id,nama_penempatan',
            'atasanLangsung:id,nama',
        ]);

        return Inertia::render('Internal/Employee/Edit', [
            'employee' => $this->employeeDetailPayload($karyawan, 'super_admin'),
            'masterData' => $this->formMasterData($karyawan->id),
            'user' => $this->userPayload($request),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Karyawan $karyawan, UpdateEmployee $action): RedirectResponse
    {
        $action->handle(
            $karyawan,
            $request->validated(),
            $request->file('foto_ktp'),
            $request->file('foto_tanda_tangan'),
        );

        return to_route('dashboard.karyawan.show', $karyawan)->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function processExit(ExitEmployeeRequest $request, Karyawan $karyawan, ProcessEmployeeExit $action): RedirectResponse
    {
        $action->handle($karyawan, $request->validated('tanggal_keluar'));

        return back()->with('success', 'Karyawan dan akun terkait berhasil dinonaktifkan.');
    }

    public function destroy(Karyawan $karyawan, DeleteEmployee $action): RedirectResponse
    {
        $action->handle($karyawan);

        return to_route('dashboard.karyawan.index')->with('success', 'Data karyawan berhasil dihapus permanen.');
    }

    public function photo(Request $request, Karyawan $karyawan): StreamedResponse
    {
        abort_unless($request->user()?->role()->value('nama_role') === 'super_admin', 403);
        abort_unless($karyawan->foto_ktp && Storage::disk('local')->exists($karyawan->foto_ktp), 404);

        return Storage::disk('local')->response($karyawan->foto_ktp);
    }

    /** @return array{0:string,1:Karyawan} */
    private function accessContext(Request $request): array
    {
        $user = $request->user()->loadMissing(['role:id,nama_role', 'karyawan']);
        abort_unless($user->role && $user->karyawan, 403);
        $roleName = mb_strtolower($user->role->nama_role);
        abort_unless(in_array($roleName, ['super_admin', 'admin', 'user'], true), 403);

        return [$roleName, $user->karyawan];
    }

    private function employeeListPayload(Karyawan $employee, string $roleName): array
    {
        $payload = $this->employeeCommonPayload($employee);

        if ($roleName === 'super_admin') {
            $payload['nik'] = $employee->nik;
        }

        return $payload;
    }

    private function employeeDetailPayload(
        Karyawan $employee,
        string $roleName,
    ): array {
        $payload = $this->employeeCommonPayload($employee);

        if ($roleName === 'super_admin') {
            $account = $employee->user;
            $suggestedRole = $account ? null : mb_strtolower($employee->jabatan?->role?->nama_role ?? '');
            $suggestedRole = $suggestedRole !== '' ? $suggestedRole : null;

            $payload += [
                'positionId' => $employee->jabatan_id,
                'departmentId' => $employee->departemen_id,
                'placementId' => $employee->penempatan_id,
                'supervisorId' => $employee->atasan_langsung_id,
                'nik' => $employee->nik,
                'address' => $employee->alamat,
                'maritalStatus' => $employee->status_perkawinan,
                'phone' => $employee->no_hp,
                'hasKtpPhoto' => filled($employee->foto_ktp),
                'ktpPhotoUrl' => filled($employee->foto_ktp) ? route('dashboard.karyawan.photo', $employee) : null,
                'hasSignaturePhoto' => filled($employee->foto_tanda_tangan),
                'signaturePhotoUrl' => filled($employee->foto_tanda_tangan)
                    ? Storage::disk('public')->url($employee->foto_tanda_tangan)
                    : null,
                'account' => $account
                    ? [
                        'username' => $account->username,
                        'roleName' => mb_strtolower($account->role?->nama_role ?? ''),
                        'roleLabel' => str($account->role?->nama_role ?? 'Tanpa role')->replace('_', ' ')->title()->toString(),
                        'isActive' => (bool) $account->is_active,
                        'mustChangePin' => (bool) $account->must_change_pin,
                    ]
                    : null,
                'accountRole' => $suggestedRole,
                'accountRoleLabel' => $suggestedRole
                    ? str($suggestedRole)->replace('_', ' ')->title()->toString()
                    : null,
            ];
        }

        return $payload;
    }

    private function employeeCommonPayload(Karyawan $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->nama,
            'gender' => $employee->jenis_kelamin,
            'religion' => $employee->agama,
            'birthDate' => $employee->tanggal_lahir?->toDateString(),
            'birthPlace' => $employee->tempat_lahir,
            'education' => $employee->pendidikan,
            'position' => $employee->jabatan?->nama_jabatan,
            'department' => $employee->departemen?->nama_departemen,
            'placement' => $employee->penempatan?->nama_penempatan,
            'supervisor' => $employee->atasanLangsung?->nama,
            'phone' => $employee->no_hp,
            'hasAccount' => $employee->relationLoaded('user') ? $employee->user !== null : $employee->user()->exists(),
            'employmentStatus' => $employee->status_kerja,
            'activeStatus' => $employee->status_keaktifan,
            'joinedAt' => $employee->tanggal_masuk?->toDateString(),
            'leftAt' => $employee->tanggal_keluar?->toDateString(),
        ];
    }

    private function formMasterData(?int $excludeEmployeeId = null): array
    {
        return [
            'jabatan' => Jabatan::query()->orderBy('nama_jabatan')->get(['id', 'nama_jabatan']),
            'departemen' => Departemen::query()->orderBy('nama_departemen')->get(['id', 'nama_departemen']),
            'penempatan' => Penempatan::query()->orderBy('nama_penempatan')->get(['id', 'nama_penempatan']),
            'karyawan' => Karyawan::query()
                ->when($excludeEmployeeId, fn (Builder $query) => $query->where('id', '!=', $excludeEmployeeId))
                ->orderBy('nama')
                ->get(['id', 'nama', 'nik']),
        ];
    }

    private function userPayload(Request $request): array
    {
        $user = $request->user()->loadMissing(['role:id,nama_role', 'karyawan:id,nama']);
        $name = $user->karyawan?->nama ?? $user->username;
        $roleName = mb_strtolower($user->role?->nama_role ?? '');

        return [
            'name' => $name,
            'initials' => collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode(''),
            'roleName' => $roleName,
            'roleLabel' => str($roleName)->replace('_', ' ')->title()->toString(),
        ];
    }
}
