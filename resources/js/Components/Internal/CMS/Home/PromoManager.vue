<script setup>
import { router, useForm } from "@inertiajs/vue3";
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { useConfirmation } from "@/composables/useConfirmation";
import { CMS_UPLOAD_LIMITS, validateUploadFile } from "@/utils/cmsUploadValidation";

const props = defineProps({
    promotions: { type: Array, required: true },
    summary: { type: Object, required: true },
});
const { confirm } = useConfirmation();

const modal = ref(null);
const localPreviewUrl = ref(null);
const titleInput = ref(null);
const posterInputKey = ref(0);
const form = useForm({
    judul: "",
    poster: null,
    tanggal_mulai: "",
    tanggal_selesai: "",
    deskripsi_singkat: "",
    deskripsi_lengkap: "",
    link_wa: "",
    urutan_tampil: 0,
    is_active: true,
});

const currentPosterUrl = computed(
    () => localPreviewUrl.value || modal.value?.item?.poster_url || null,
);
const statusLabels = {
    aktif: "Aktif",
    akan_datang: "Akan Datang",
    berakhir: "Berakhir",
    nonaktif: "Nonaktif",
};
const statusClasses = {
    aktif: "bg-emerald-50 text-emerald-700 ring-emerald-200",
    akan_datang: "bg-blue-50 text-blue-700 ring-blue-200",
    berakhir: "bg-slate-100 text-slate-600 ring-slate-200",
    nonaktif: "bg-orange-50 text-orange-700 ring-orange-200",
};

const revokePreview = () => {
    if (localPreviewUrl.value) URL.revokeObjectURL(localPreviewUrl.value);
    localPreviewUrl.value = null;
};

const openModal = async (item = null) => {
    revokePreview();
    form.clearErrors();
    form.reset();
    posterInputKey.value += 1;
    modal.value = { item };

    if (item) {
        form.judul = item.judul;
        form.tanggal_mulai = item.tanggal_mulai || "";
        form.tanggal_selesai = item.tanggal_selesai || "";
        form.deskripsi_singkat = item.deskripsi_singkat;
        form.deskripsi_lengkap = item.deskripsi_lengkap || "";
        form.link_wa = item.link_wa || "";
        form.urutan_tampil = item.urutan_tampil;
        form.is_active = item.is_active;
    }

    await nextTick();
    titleInput.value?.focus();
};

const closeModal = () => {
    modal.value = null;
    revokePreview();
    form.reset();
    form.clearErrors();
};

const selectPoster = (event) => {
    const [file] = event.target.files;
    form.clearErrors('poster');
    const error = validateUploadFile(file, CMS_UPLOAD_LIMITS.promo.file, 'gambar Promo');
    if (error) { event.target.value = ''; form.poster = null; form.setError('poster', error); return; }
    revokePreview();
    form.poster = file || null;
    localPreviewUrl.value = file ? URL.createObjectURL(file) : null;
};

const submit = async () => {
    const item = modal.value?.item;
    const confirmed = await confirm({
        type: item ? "edit" : "save",
        title: item ? "Edit Promo" : "Simpan Promo",
        message: "Apakah Anda yakin ingin menyimpan data Promo ini?",
        confirmText: "Ya, Simpan",
    });
    if (!confirmed) return;
    const routeName = item
        ? "dashboard.cms.home.promo.update"
        : "dashboard.cms.home.promo.store";
    const routeParams = item ? item.id : undefined;

    form.transform((data) => ({
        ...data,
        is_active: data.is_active ? 1 : 0,
        urutan_tampil: Number(data.urutan_tampil || 0),
        ...(item ? { _method: "patch" } : {}),
    })).post(route(routeName, routeParams), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const toggleStatus = async (item) => {
    const confirmed = await confirm({
        type: item.is_active ? "warning" : "edit",
        title: item.is_active ? "Nonaktifkan Promo" : "Aktifkan Promo",
        message: `Apakah Anda yakin ingin ${item.is_active ? "menonaktifkan" : "mengaktifkan"} Promo ini?`,
        confirmText: `Ya, ${item.is_active ? "Nonaktifkan" : "Aktifkan"}`,
    });
    if (!confirmed) return;
    router.patch(
        route("dashboard.cms.home.promo.status", item.id),
        {},
        { preserveScroll: true },
    );
};

const destroyPromo = async (item) => {
    const confirmed = await confirm({
        type: "delete",
        title: "Hapus Promo",
        message: `Apakah Anda yakin ingin menghapus promo “${item.judul}”?`,
        description: "Tindakan ini tidak dapat dibatalkan.",
        confirmText: "Ya, Hapus",
    });
    if (!confirmed) return;
    router.delete(route("dashboard.cms.home.promo.destroy", item.id), {
        preserveScroll: true,
    });
};

const handleKeydown = (event) => {
    if (event.key === "Escape" && modal.value) closeModal();
};

watch(modal, (value) => {
    document.body.style.overflow = value ? "hidden" : "";
});

onBeforeUnmount(() => {
    document.body.style.overflow = "";
    revokePreview();
});
</script>

<template>
    <div class="p-4 sm:p-5" @keydown.window="handleKeydown">
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4"
        >
            <div>
                <h3 class="text-sm font-bold text-[#172554]">Promo</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Kelola promo yang tampil di halaman Beranda.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span
                    class="rounded-full bg-emerald-50 px-3 py-1.5 text-[11px] font-bold text-emerald-700"
                    >{{ summary.active_count }} Promo Aktif</span
                >
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#1769e0] px-4 text-xs font-bold text-white shadow-sm transition hover:bg-[#0756ba] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200"
                    @click="openModal()"
                >
                    <span class="text-base leading-none">+</span> Tambah Promo
                </button>
            </div>
        </div>

        <div v-if="promotions.length" class="mt-4 space-y-3">
            <article
                v-for="item in promotions"
                :key="item.id"
                class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-3 sm:grid-cols-[76px_minmax(0,1fr)_auto] sm:items-center sm:p-4"
            >
                <img
                    :src="item.poster_url"
                    :alt="`Poster ${item.judul}`"
                    class="h-[92px] w-[76px] rounded-lg border border-slate-200 bg-white object-cover"
                />
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h4 class="truncate text-sm font-bold text-[#172554]">
                            {{ item.judul }}
                        </h4>
                        <span
                            :class="statusClasses[item.status]"
                            class="inline-flex rounded-full px-2.5 py-1 text-[9px] font-extrabold uppercase tracking-wide ring-1 ring-inset"
                            >{{ statusLabels[item.status] }}</span
                        >
                    </div>
                    <p class="mt-1.5 text-xs font-semibold text-[#0756ba]">
                        {{ item.periode }}
                    </p>
                    <p
                        class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500"
                    >
                        {{ item.deskripsi_singkat }}
                    </p>
                    <p class="mt-1 text-[10px] font-semibold text-slate-400">
                        Urutan tampil: {{ item.urutan_tampil }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                    <button
                        type="button"
                        class="rounded-lg border border-blue-200 bg-white px-3 py-2 text-[11px] font-bold text-[#0756d8] hover:bg-blue-50"
                        @click="openModal(item)"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        :class="
                            item.is_active
                                ? 'border-orange-200 text-orange-700 hover:bg-orange-50'
                                : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'
                        "
                        class="rounded-lg border bg-white px-3 py-2 text-[11px] font-bold"
                        @click="toggleStatus(item)"
                    >
                        {{ item.is_active ? "Nonaktifkan" : "Aktifkan" }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-red-200 bg-white px-3 py-2 text-[11px] font-bold text-red-600 hover:bg-red-50"
                        @click="destroyPromo(item)"
                    >
                        Hapus
                    </button>
                </div>
            </article>
        </div>

        <div
            v-else
            class="mt-4 rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center"
        >
            <h4 class="text-sm font-bold text-slate-700">Belum ada Promo</h4>
            <p class="mt-1 text-xs text-slate-500">
                Tambahkan promo pertama untuk mulai mengisi carousel Beranda.
            </p>
        </div>

        <div
            v-if="modal"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 p-3 sm:p-5"
            role="dialog"
            aria-modal="true"
            :aria-label="modal.item ? 'Edit Promo' : 'Tambah Promo'"
            @click.self="closeModal"
        >
            <form
                class="max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl"
                @submit.prevent="submit"
            >
                <header
                    class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-200 bg-white px-5 py-4 sm:px-6"
                >
                    <div>
                        <h3 class="text-lg font-bold text-[#172554]">
                            {{ modal.item ? "Edit Promo" : "Tambah Promo" }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Lengkapi konten preview, detail, periode, dan
                            pengaturan tayang.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="grid h-9 w-9 place-items-center rounded-lg text-xl text-slate-500 hover:bg-slate-100"
                        aria-label="Tutup modal"
                        @click="closeModal"
                    >
                        ×
                    </button>
                </header>

                <div
                    class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_260px]"
                >
                    <div class="space-y-4">
                        <label class="block text-xs font-bold text-slate-700"
                            >Judul Promo *<input
                                ref="titleInput"
                                v-model="form.judul"
                                type="text"
                                maxlength="150"
                                class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                        /></label>
                        <p
                            v-if="form.errors.judul"
                            class="-mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.judul }}
                        </p>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label
                                class="block text-xs font-bold text-slate-700"
                                >Tanggal Mulai *<input
                                    v-model="form.tanggal_mulai"
                                    type="date"
                                    class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                            /></label>
                            <label
                                class="block text-xs font-bold text-slate-700"
                                >Tanggal Selesai *<input
                                    v-model="form.tanggal_selesai"
                                    type="date"
                                    class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                            /></label>
                        </div>
                        <div class="grid gap-1 sm:grid-cols-2">
                            <p class="text-xs text-red-600">
                                {{ form.errors.tanggal_mulai }}
                            </p>
                            <p class="text-xs text-red-600">
                                {{ form.errors.tanggal_selesai }}
                            </p>
                        </div>

                        <label class="block text-xs font-bold text-slate-700"
                            >Deskripsi Singkat *<textarea
                                v-model="form.deskripsi_singkat"
                                rows="3"
                                maxlength="255"
                                class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                            ></textarea
                            ><span
                                class="mt-1 block text-right text-[10px] font-medium text-slate-400"
                                >{{ form.deskripsi_singkat.length }}/255</span
                            ></label
                        >
                        <p
                            v-if="form.errors.deskripsi_singkat"
                            class="-mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.deskripsi_singkat }}
                        </p>

                        <label class="block text-xs font-bold text-slate-700"
                            >Deskripsi Lengkap *<textarea
                                v-model="form.deskripsi_lengkap"
                                rows="7"
                                class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                            ></textarea>
                        </label>
                        <p
                            v-if="form.errors.deskripsi_lengkap"
                            class="-mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.deskripsi_lengkap }}
                        </p>

                        <label class="block text-xs font-bold text-slate-700"
                            >Nomor WhatsApp<input
                                v-model="form.link_wa"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                                maxlength="24"
                                placeholder="081234567890"
                                class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                            /><span
                                class="mt-1.5 block text-[10px] font-medium leading-4 text-slate-500"
                                >Masukkan nomor WhatsApp yang dapat dihubungi
                                untuk promo ini.</span
                            ></label
                        >
                        <p
                            v-if="form.errors.link_wa"
                            class="-mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.link_wa }}
                        </p>

                        <div class="grid gap-4 sm:grid-cols-2 sm:items-end">
                            <label
                                class="block text-xs font-bold text-slate-700"
                                >Urutan Tampil *<input
                                    v-model.number="form.urutan_tampil"
                                    type="number"
                                    min="0"
                                    class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                            /></label>
                            <label
                                class="flex h-10 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700"
                                ><input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-[#1769e0] focus:ring-[#1769e0]"
                                />
                                Status Aktif</label
                            >
                        </div>
                        <p
                            v-if="
                                form.errors.urutan_tampil ||
                                form.errors.is_active
                            "
                            class="text-xs text-red-600"
                        >
                            {{
                                form.errors.urutan_tampil ||
                                form.errors.is_active
                            }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700"
                            >Poster Promo {{ modal.item ? "" : "*" }}
                            <input
                                :key="posterInputKey"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-bold file:text-[#0756d8]"
                                :required="!modal.item"
                                @change="selectPoster"
                            />
                        </label>
                        <p class="mt-2 text-[10px] leading-4 text-slate-500">
                            JPG, JPEG, PNG, atau WebP • Maks. ukuran file: 20 MB • Disarankan 1200 × 1500 px (4:5). Poster lama
                            tetap dipakai jika tidak diganti.
                        </p>
                        <p
                            v-if="form.errors.poster"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.poster }}
                        </p>
                        <div
                            class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-100"
                        >
                            <img
                                v-if="currentPosterUrl"
                                :src="currentPosterUrl"
                                alt="Preview poster Promo"
                                class="aspect-[4/5] w-full object-contain"
                            />
                            <div
                                v-else
                                class="grid aspect-[4/5] place-items-center px-5 text-center text-xs text-slate-400"
                            >
                                Preview poster akan tampil di sini.
                            </div>
                        </div>
                    </div>
                </div>

                <footer
                    class="sticky bottom-0 flex justify-end gap-2 border-t border-slate-200 bg-white px-5 py-4 sm:px-6"
                >
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50"
                        @click="closeModal"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-[#1769e0] px-5 py-2.5 text-xs font-bold text-white hover:bg-[#0756ba] disabled:cursor-wait disabled:opacity-60"
                    >
                        {{ form.processing ? "Menyimpan..." : "Simpan Promo" }}
                    </button>
                </footer>
            </form>
        </div>
    </div>
</template>
