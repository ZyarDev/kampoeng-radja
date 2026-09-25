<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Departemen;
use App\Models\Jabatan;
use App\Models\Penempatan;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeMasterController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $name = $request->user()->karyawan()->value('nama') ?? $request->user()->username;

        return Inertia::render('Internal/Employee/Masters', [
            'jabatan' => Jabatan::query()
                ->with(['role:id,nama_role'])
                ->withCount('karyawan')
                ->orderBy('nama_jabatan')
                ->get(['id', 'nama_jabatan', 'role_id']),
            'roles' => Role::query()->orderBy('id')->get(['id', 'nama_role']),
            'departemen' => Departemen::query()->withCount('karyawan')->orderBy('nama_departemen')->get(['id', 'nama_departemen']),
            'penempatan' => Penempatan::query()->withCount('karyawan')->orderBy('nama_penempatan')->get(['id', 'nama_penempatan']),
            'user' => [
                'name' => $name,
                'initials' => collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode(''),
                'roleName' => 'super_admin',
                'roleLabel' => 'Super Admin',
            ],
        ]);
    }
}
