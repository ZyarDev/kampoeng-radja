<?php

namespace App\Http\Controllers\ClosingEvent;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClosingEvent\ClosingEventMasterRequest;
use App\Models\JenisEvent;
use App\Models\Lokasi;
use App\Models\Pic;
use App\Support\ClosingEventAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClosingEventMasterController extends Controller
{
    public function index(Request $request, ClosingEventAccess $access): Response
    {
        $permissions = $access->for($request->user());
        abort_unless($permissions['canViewMaster'], 403);

        return Inertia::render('Internal/ClosingEvent/Masters', [
            'pic' => Pic::query()->withCount('closingEvents')->orderBy('nama_pic')->paginate(5, ['*'], 'pic_page')->withQueryString(),
            'jenisEvent' => JenisEvent::query()->withCount('closingEvents')->orderBy('jenis_event')->paginate(5, ['*'], 'event_page')->withQueryString(),
            'lokasi' => Lokasi::query()->withCount('closingEvents')->orderBy('nama_lokasi')->paginate(5, ['*'], 'lokasi_page')->withQueryString(),
            'permissions' => $permissions,
            'user' => $this->userPayload($request),
        ]);
    }

    public function storePic(ClosingEventMasterRequest $request, ClosingEventAccess $access): RedirectResponse
    {
        $this->authorizeMaster($request, $access, 'canCreateMaster');
        Pic::create($request->validated());

        return back()->with('success', 'PIC berhasil ditambahkan.');
    }

    public function updatePic(ClosingEventMasterRequest $request, Pic $pic, ClosingEventAccess $access): RedirectResponse
    {
        $this->authorizeMaster($request, $access, 'canUpdateMaster');
        $pic->update($request->validated());

        return back()->with('success', 'PIC berhasil diperbarui.');
    }

    public function destroyPic(Request $request, Pic $pic, ClosingEventAccess $access): RedirectResponse
    {
        $this->authorizeMaster($request, $access, 'canDeleteMaster');

        return $this->destroyMaster($pic, 'PIC');
    }

    public function storeJenisEvent(ClosingEventMasterRequest $request, ClosingEventAccess $access): RedirectResponse
    {
        $this->authorizeMaster($request, $access, 'canCreateMaster');
        JenisEvent::create($request->validated());

        return back()->with('success', 'Jenis Event berhasil ditambahkan.');
    }

    public function updateJenisEvent(ClosingEventMasterRequest $request, JenisEvent $jenisEvent, ClosingEventAccess $access): RedirectResponse
    {
        $this->authorizeMaster($request, $access, 'canUpdateMaster');
        $jenisEvent->update($request->validated());

        return back()->with('success', 'Jenis Event berhasil diperbarui.');
    }

    public function destroyJenisEvent(Request $request, JenisEvent $jenisEvent, ClosingEventAccess $access): RedirectResponse
    {
        $this->authorizeMaster($request, $access, 'canDeleteMaster');

        return $this->destroyMaster($jenisEvent, 'Jenis Event');
    }

    public function storeLokasi(ClosingEventMasterRequest $request, ClosingEventAccess $access): RedirectResponse
    {
        $this->authorizeMaster($request, $access, 'canCreateMaster');
        Lokasi::create($request->validated());

        return back()->with('success', 'Lokasi berhasil ditambahkan.');
    }

    public function updateLokasi(ClosingEventMasterRequest $request, Lokasi $lokasi, ClosingEventAccess $access): RedirectResponse
    {
        $this->authorizeMaster($request, $access, 'canUpdateMaster');
        $lokasi->update($request->validated());

        return back()->with('success', 'Lokasi berhasil diperbarui.');
    }

    public function destroyLokasi(Request $request, Lokasi $lokasi, ClosingEventAccess $access): RedirectResponse
    {
        $this->authorizeMaster($request, $access, 'canDeleteMaster');

        return $this->destroyMaster($lokasi, 'Lokasi');
    }

    private function authorizeMaster(Request $request, ClosingEventAccess $access, string $capability): void
    {
        abort_unless($access->for($request->user())[$capability] ?? false, 403);
    }

    private function destroyMaster(Pic|JenisEvent|Lokasi $model, string $label): RedirectResponse
    {
        if ($model->closingEvents()->exists()) {
            return back()->with('error', 'Data tidak dapat dihapus karena masih digunakan pada Closing Event.');
        }

        $model->delete();

        return back()->with('success', $label.' berhasil dihapus.');
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
