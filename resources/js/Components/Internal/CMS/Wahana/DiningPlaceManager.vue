<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { useConfirmation } from '@/composables/useConfirmation';
import { CMS_UPLOAD_LIMITS, validateUploadFiles } from '@/utils/cmsUploadValidation';

const props = defineProps({
    items: { type: Array, required: true },
    categories: { type: Array, required: true },
});

const { confirm } = useConfirmation();
const modal = ref(null);
const nameInput = ref(null);
const existingPhotos = ref([]);
const newPhotos = ref([]);
const localPhotoError = ref(null);
const fileInputKey = ref(0);
const form = useForm({
    nama: '',
    kategori: '',
    tagline: '',
    deskripsi: '',
    jam_buka: '',
    jam_tutup: '',
    kapasitas: '',
    lokasi: '',
    jenis_menu: '',
    is_recommended: false,
    is_active: true,
    urutan_tampil: 0,
    fotos: [],
    existing_photo_order: [],
    menu_highlights: [''],
});

const photoError = computed(() => localPhotoError.value || form.errors.fotos
    || Object.entries(form.errors).find(([key]) => key.startsWith('fotos.'))?.[1]
    || form.errors.existing_photo_order);

const revokeNewPhotos = () => {
    newPhotos.value.forEach((photo) => URL.revokeObjectURL(photo.url));
    newPhotos.value = [];
};

const openModal = async (item = null) => {
    localPhotoError.value = null;
    revokeNewPhotos();
    form.reset();
    form.clearErrors();
    fileInputKey.value += 1;
    existingPhotos.value = item ? item.photos.map((photo) => ({ ...photo })) : [];
    modal.value = { item };

    if (item) {
        Object.assign(form, {
            nama: item.nama,
            kategori: item.kategori,
            tagline: item.tagline || '',
            deskripsi: item.deskripsi,
            jam_buka: item.jam_buka || '',
            jam_tutup: item.jam_tutup || '',
            kapasitas: item.kapasitas ?? '',
            lokasi: item.lokasi || '',
            jenis_menu: item.jenis_menu || '',
            is_recommended: item.is_recommended,
            is_active: item.is_active,
            urutan_tampil: item.urutan_tampil,
            menu_highlights: item.menu_highlights.length ? [...item.menu_highlights] : [''],
        });
    } else {
        form.kategori = props.categories[0] || '';
        form.menu_highlights = [''];
    }

    await nextTick();
    nameInput.value?.focus();
};

const closeModal = () => {
    localPhotoError.value = null;
    modal.value = null;
    existingPhotos.value = [];
    revokeNewPhotos();
    form.reset();
    form.clearErrors();
};

const selectPhotos = (event) => {
    const files = Array.from(event.target.files || []);
    const combined = [...newPhotos.value.map((photo) => photo.file), ...files];
    localPhotoError.value = validateUploadFiles(combined, CMS_UPLOAD_LIMITS.dining, 'foto Tempat Makan');
    if (localPhotoError.value) { event.target.value = ''; return; }
    newPhotos.value.push(...files.map((file, index) => ({
        key: `${Date.now()}-${index}-${file.name}`,
        file,
        url: URL.createObjectURL(file),
    })));
    form.fotos = newPhotos.value.map((photo) => photo.file);
    event.target.value = '';
};

const move = (items, index, direction) => {
    const destination = index + direction;
    if (destination < 0 || destination >= items.length) return;
    const [item] = items.splice(index, 1);
    items.splice(destination, 0, item);
};

const removeExistingPhoto = (index) => existingPhotos.value.splice(index, 1);
const removeNewPhoto = (index) => {
    const [photo] = newPhotos.value.splice(index, 1);
    if (photo) URL.revokeObjectURL(photo.url);
    form.fotos = newPhotos.value.map((item) => item.file);
    localPhotoError.value = validateUploadFiles(form.fotos, CMS_UPLOAD_LIMITS.dining, 'foto Tempat Makan');
};
const addMenuHighlight = () => form.menu_highlights.push('');
const removeMenuHighlight = (index) => {
    form.menu_highlights.splice(index, 1);
    if (!form.menu_highlights.length) form.menu_highlights.push('');
};

const submit = async () => {
    const item = modal.value?.item;
    const confirmed = await confirm({
        type: item ? 'edit' : 'save',
        title: item ? 'Edit Tempat Makan' : 'Simpan Tempat Makan',
        message: `Apakah Anda yakin ingin menyimpan data ${form.nama || 'Tempat Makan'}?`,
        confirmText: 'Ya, Simpan',
    });
    if (!confirmed) return;

    form.transform((data) => ({
        ...data,
        fotos: newPhotos.value.map((photo) => photo.file),
        existing_photo_order: existingPhotos.value.map((photo) => photo.id),
        menu_highlights: data.menu_highlights.map((value) => value.trim()).filter(Boolean),
        kapasitas: data.kapasitas === '' ? null : Number(data.kapasitas),
        is_recommended: data.is_recommended ? 1 : 0,
        is_active: data.is_active ? 1 : 0,
        urutan_tampil: Number(data.urutan_tampil || 0),
        ...(item ? { _method: 'patch' } : {}),
    })).post(route(item ? 'dashboard.cms.wahana.dining.update' : 'dashboard.cms.wahana.dining.store', item?.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const destroy = async (item) => {
    const confirmed = await confirm({
        type: 'delete',
        title: 'Hapus Tempat Makan',
        message: `Apakah Anda yakin ingin menghapus ${item.nama}?`,
        description: 'Tindakan ini tidak dapat dibatalkan.',
        confirmText: 'Ya, Hapus',
    });
    if (confirmed) router.delete(route('dashboard.cms.wahana.dining.destroy', item.id), { preserveScroll: true });
};

onBeforeUnmount(revokeNewPhotos);
</script>

<template>
    <div>
        <header class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 pb-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#1769e0]">Content Management System</p>
                <h2 class="mt-1 text-[22px] font-bold leading-tight text-[#172554]">Kelola Tempat Makan</h2>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500">Kelola informasi, galeri, dan status Tempat Makan pada halaman publik Wahana.</p>
            </div>
            <button type="button" class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#1769e0] px-4 text-xs font-bold text-white shadow-sm hover:bg-[#0756ba]" @click="openModal()"><span class="text-base">+</span> Tambah Tempat Makan</button>
        </header>

        <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <div><h3 class="text-sm font-bold text-[#172554]">Daftar Tempat Makan</h3><p class="mt-1 text-xs text-slate-500">Data aktif tampil sesuai urutan pada tab Tempat Makan.</p></div>
                <span class="rounded-full bg-blue-50 px-3 py-1.5 text-[11px] font-bold text-[#0756ba]">{{ items.length }} Tempat Makan</span>
            </div>

            <div v-if="items.length" class="mt-4 space-y-3">
                <article v-for="item in items" :key="item.id" class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-3 md:grid-cols-[112px_minmax(0,1fr)_auto] md:items-center sm:p-4">
                    <div class="h-24 overflow-hidden rounded-lg border border-slate-200 bg-white">
                        <img v-if="item.cover_url" :src="item.cover_url" :alt="item.nama" class="h-full w-full object-cover" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-sm font-bold text-[#172554]">{{ item.nama }}</h4>
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[9px] font-bold text-[#0756ba]">{{ item.kategori }}</span>
                            <span :class="item.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'" class="rounded-full px-2.5 py-1 text-[9px] font-bold">{{ item.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            <span v-if="item.is_recommended" class="rounded-full bg-orange-50 px-2.5 py-1 text-[9px] font-bold text-orange-700">Rekomendasi</span>
                        </div>
                        <p class="mt-1.5 line-clamp-2 text-xs leading-5 text-slate-500">{{ item.deskripsi }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[10px] font-semibold text-slate-400"><span>{{ item.jam_buka && item.jam_tutup ? `${item.jam_buka}–${item.jam_tutup}` : 'Jam belum diatur' }}</span><span>{{ item.lokasi || 'Lokasi belum diatur' }}</span><span>Urutan {{ item.urutan_tampil }}</span><span>{{ item.photos.length }} foto</span></div>
                    </div>
                    <div class="flex gap-2 md:justify-end"><button type="button" class="rounded-lg border border-blue-200 bg-white px-3 py-2 text-[11px] font-bold text-[#0756d8] hover:bg-blue-50" @click="openModal(item)">Lihat/Edit</button><button type="button" class="rounded-lg border border-red-200 bg-white px-3 py-2 text-[11px] font-bold text-red-600 hover:bg-red-50" @click="destroy(item)">Hapus</button></div>
                </article>
            </div>
            <div v-else class="mt-4 rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center"><h4 class="text-sm font-bold text-slate-700">Belum ada Tempat Makan</h4><p class="mt-1 text-xs text-slate-500">Tambahkan data pertama untuk ditampilkan pada halaman publik.</p></div>
        </section>

        <div v-if="modal" class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 p-3 sm:p-5" role="dialog" aria-modal="true" :aria-label="modal.item ? 'Edit Tempat Makan' : 'Tambah Tempat Makan'" @click.self="closeModal">
            <form class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" @submit.prevent="submit">
                <header class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6"><div><h3 class="text-lg font-bold text-[#172554]">{{ modal.item ? 'Edit Tempat Makan' : 'Tambah Tempat Makan' }}</h3><p class="mt-1 text-xs text-slate-500">Lengkapi informasi, Menu Highlight, dan galeri foto.</p></div><button type="button" class="grid h-9 w-9 place-items-center rounded-lg text-xl text-slate-500 hover:bg-slate-100" aria-label="Tutup modal" @click="closeModal">×</button></header>

                <div class="min-h-0 flex-1 overflow-y-auto p-5 sm:p-6">
                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="text-xs font-bold text-slate-700">Nama Tempat Makan *<input ref="nameInput" v-model="form.nama" maxlength="150" class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm" /></label>
                                <label class="text-xs font-bold text-slate-700">Kategori *<select v-model="form.kategori" class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm"><option v-for="category in categories" :key="category" :value="category">{{ category }}</option></select></label>
                            </div>
                            <p v-if="form.errors.nama || form.errors.kategori" class="text-xs text-red-600">{{ form.errors.nama || form.errors.kategori }}</p>
                            <label class="block text-xs font-bold text-slate-700">Tagline<input v-model="form.tagline" maxlength="200" class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm" /><span class="mt-1 block text-right text-[10px] text-slate-400">{{ form.tagline.length }}/200</span></label>
                            <p v-if="form.errors.tagline" class="-mt-2 text-xs text-red-600">{{ form.errors.tagline }}</p>
                            <label class="block text-xs font-bold text-slate-700">Deskripsi *<textarea v-model="form.deskripsi" rows="5" maxlength="2000" class="mt-2 w-full rounded-lg border-slate-300 text-sm"></textarea><span class="mt-1 block text-right text-[10px] text-slate-400">{{ form.deskripsi.length }}/2000</span></label>
                            <p v-if="form.errors.deskripsi" class="-mt-2 text-xs text-red-600">{{ form.errors.deskripsi }}</p>
                            <div class="grid gap-4 sm:grid-cols-2"><label class="text-xs font-bold text-slate-700">Jam Buka<input v-model="form.jam_buka" type="time" class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm" /></label><label class="text-xs font-bold text-slate-700">Jam Tutup<input v-model="form.jam_tutup" type="time" class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm" /></label></div>
                            <p v-if="form.errors.jam_buka || form.errors.jam_tutup" class="text-xs text-red-600">{{ form.errors.jam_buka || form.errors.jam_tutup }}</p>
                            <div class="grid gap-4 sm:grid-cols-2"><label class="text-xs font-bold text-slate-700">Kapasitas<input v-model="form.kapasitas" type="number" min="1" class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm" /></label><label class="text-xs font-bold text-slate-700">Urutan Tampil<input v-model="form.urutan_tampil" type="number" min="0" max="999" class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm" /></label></div>
                            <p v-if="form.errors.kapasitas || form.errors.urutan_tampil" class="text-xs text-red-600">{{ form.errors.kapasitas || form.errors.urutan_tampil }}</p>
                            <div class="grid gap-4 sm:grid-cols-2"><label class="text-xs font-bold text-slate-700">Lokasi Area<input v-model="form.lokasi" maxlength="150" class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm" /></label><label class="text-xs font-bold text-slate-700">Jenis Menu<input v-model="form.jenis_menu" maxlength="150" class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm" /></label></div>
                            <p v-if="form.errors.lokasi || form.errors.jenis_menu" class="text-xs text-red-600">{{ form.errors.lokasi || form.errors.jenis_menu }}</p>
                            <div class="grid gap-3 sm:grid-cols-2"><label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700"><input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-[#1769e0]" /> Status Aktif</label><label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700"><input v-model="form.is_recommended" type="checkbox" class="rounded border-slate-300 text-[#1769e0]" /> Rekomendasi</label></div>

                            <fieldset class="rounded-xl border border-slate-200 p-4"><div class="flex items-center justify-between gap-3"><legend class="text-xs font-bold text-slate-700">Menu Highlight</legend><button type="button" class="text-xs font-bold text-[#1769e0]" @click="addMenuHighlight">+ Tambah Menu</button></div><div class="mt-3 space-y-2"><div v-for="(menu, index) in form.menu_highlights" :key="index" class="flex gap-2"><input v-model="form.menu_highlights[index]" maxlength="100" :placeholder="`Menu ${index + 1}`" class="h-10 min-w-0 flex-1 rounded-lg border-slate-300 text-sm" /><button type="button" class="rounded-lg border border-red-200 px-3 text-xs font-bold text-red-600" @click="removeMenuHighlight(index)">Hapus</button></div></div><p v-if="form.errors.menu_highlights" class="mt-2 text-xs text-red-600">{{ form.errors.menu_highlights }}</p></fieldset>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700">Galeri Foto {{ modal.item ? '' : '*' }}<input :key="fileInputKey" type="file" multiple accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 text-xs file:mr-2 file:rounded file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-bold file:text-[#0756d8]" :required="!modal.item && !newPhotos.length" @change="selectPhotos" /></label>
                            <p class="mt-2 text-[10px] leading-4 text-slate-500">JPG, JPEG, PNG, atau WebP • Maks. 10 MB per foto • Maks. total 30 MB • Disarankan 1200 × 800 px (3:2). Foto teratas menjadi cover.</p><p v-if="photoError" class="mt-2 text-xs text-red-600">{{ photoError }}</p>
                            <div v-if="existingPhotos.length" class="mt-4 space-y-2"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Foto tersimpan</p><article v-for="(photo, index) in existingPhotos" :key="photo.id" class="flex items-center gap-2 rounded-lg border border-slate-200 p-2"><img :src="photo.url" alt="" class="h-14 w-20 rounded-md object-cover" /><span class="min-w-0 flex-1 text-xs font-bold text-slate-600">Foto {{ index + 1 }}</span><div class="flex gap-1"><button type="button" :disabled="index === 0" class="rounded border px-2 py-1 text-xs disabled:opacity-30" @click="move(existingPhotos, index, -1)">↑</button><button type="button" :disabled="index === existingPhotos.length - 1" class="rounded border px-2 py-1 text-xs disabled:opacity-30" @click="move(existingPhotos, index, 1)">↓</button><button type="button" class="rounded border border-red-200 px-2 py-1 text-xs text-red-600" @click="removeExistingPhoto(index)">×</button></div></article></div>
                            <div v-if="newPhotos.length" class="mt-4 space-y-2"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Foto baru</p><article v-for="(photo, index) in newPhotos" :key="photo.key" class="flex items-center gap-2 rounded-lg border border-blue-100 bg-blue-50/40 p-2"><img :src="photo.url" alt="" class="h-14 w-20 rounded-md object-cover" /><span class="min-w-0 flex-1 truncate text-xs font-bold text-slate-600">{{ photo.file.name }}</span><div class="flex gap-1"><button type="button" :disabled="index === 0" class="rounded border px-2 py-1 text-xs disabled:opacity-30" @click="move(newPhotos, index, -1)">↑</button><button type="button" :disabled="index === newPhotos.length - 1" class="rounded border px-2 py-1 text-xs disabled:opacity-30" @click="move(newPhotos, index, 1)">↓</button><button type="button" class="rounded border border-red-200 px-2 py-1 text-xs text-red-600" @click="removeNewPhoto(index)">×</button></div></article></div>
                            <div v-if="!existingPhotos.length && !newPhotos.length" class="mt-4 grid min-h-28 place-items-center rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center text-xs text-slate-400">Minimal satu foto wajib tersedia.</div>
                        </div>
                    </div>
                </div>
                <footer class="flex shrink-0 justify-end gap-2 border-t border-slate-200 px-5 py-4 sm:px-6"><button type="button" class="rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-600" @click="closeModal">Batal</button><button type="submit" :disabled="form.processing" class="rounded-lg bg-[#1769e0] px-5 py-2.5 text-xs font-bold text-white disabled:opacity-60">{{ form.processing ? 'Menyimpan...' : 'Simpan Tempat Makan' }}</button></footer>
            </form>
        </div>
    </div>
</template>
