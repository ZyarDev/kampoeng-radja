<script setup>
import InternalDashboardLayout from "@/Layouts/InternalDashboardLayout.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { useConfirmation } from "@/composables/useConfirmation";
import DiningPlaceManager from "@/Components/Internal/CMS/Wahana/DiningPlaceManager.vue";
import { CMS_UPLOAD_LIMITS, validateUploadFiles } from "@/utils/cmsUploadValidation";

const props = defineProps({
    user: { type: Object, required: true },
    items: { type: Array, required: true },
    labels: { type: Array, required: true },
    featuredLimit: { type: Number, required: true },
    featuredCount: { type: Number, required: true },
    diningItems: { type: Array, required: true },
    diningCategories: { type: Array, required: true },
    initialTab: { type: String, default: "wahana" },
});

const page = usePage();
const { confirm } = useConfirmation();
const modal = ref(null);
const deleteTarget = ref(null);
const existingPhotos = ref([]);
const newPhotoItems = ref([]);
const localPhotoError = ref(null);
const nameInput = ref(null);
const fileInputKey = ref(0);
const activeTab = ref(props.initialTab);
const form = useForm({
    nama_wahana: "",
    deskripsi_singkat: "",
    fotos: [],
    existing_photo_order: [],
    label: [],
    is_active: true,
    is_unggulan: false,
    urutan_tampil: 0,
});

const pageError = computed(() => page.props.errors?.is_unggulan);
const photoError = computed(() => {
    if (localPhotoError.value) return localPhotoError.value;
    if (form.errors.fotos) return form.errors.fotos;

    return (
        Object.entries(form.errors).find(([key]) =>
            key.startsWith("fotos."),
        )?.[1] || null
    );
});

const revokeNewPhotoPreviews = () => {
    newPhotoItems.value.forEach((photo) => URL.revokeObjectURL(photo.url));
    newPhotoItems.value = [];
};

const openModal = async (item = null) => {
    localPhotoError.value = null;
    revokeNewPhotoPreviews();
    form.clearErrors();
    form.reset();
    fileInputKey.value += 1;
    modal.value = { item };
    existingPhotos.value = item
        ? item.photos.map((photo) => ({ ...photo }))
        : [];

    if (item) {
        form.nama_wahana = item.nama_wahana;
        form.deskripsi_singkat = item.deskripsi_singkat;
        form.label = [...item.labels];
        form.is_active = item.is_active;
        form.is_unggulan = item.is_unggulan;
        form.urutan_tampil = item.urutan_tampil;
    }

    await nextTick();
    nameInput.value?.focus();
};

const closeModal = () => {
    localPhotoError.value = null;
    modal.value = null;
    existingPhotos.value = [];
    revokeNewPhotoPreviews();
    form.reset();
    form.clearErrors();
};

const selectPhotos = (event) => {
    const files = Array.from(event.target.files || []);
    const combined = [...newPhotoItems.value.map((photo) => photo.file), ...files];
    localPhotoError.value = validateUploadFiles(combined, CMS_UPLOAD_LIMITS.wahana, 'foto Wahana');
    if (localPhotoError.value) { event.target.value = ''; return; }

    newPhotoItems.value.push(
        ...files.map((file, index) => ({
            id: `${Date.now()}-${index}-${file.name}`,
            file,
            url: URL.createObjectURL(file),
        })),
    );
    form.fotos = newPhotoItems.value.map((photo) => photo.file);
    event.target.value = "";
};

const movePhoto = (collection, index, direction) => {
    const target = index + direction;
    if (target < 0 || target >= collection.length) return;

    const [photo] = collection.splice(index, 1);
    collection.splice(target, 0, photo);
};

const removeExistingPhoto = (index) => {
    existingPhotos.value.splice(index, 1);
};

const removeNewPhoto = (index) => {
    const [photo] = newPhotoItems.value.splice(index, 1);
    if (photo) URL.revokeObjectURL(photo.url);
    form.fotos = newPhotoItems.value.map((item) => item.file);
    localPhotoError.value = validateUploadFiles(form.fotos, CMS_UPLOAD_LIMITS.wahana, 'foto Wahana');
};

const submit = async () => {
    const item = modal.value?.item;
    const confirmed = await confirm({
        type: item ? "edit" : "save",
        title: item ? "Edit Wahana" : "Simpan Wahana",
        message: "Apakah Anda yakin ingin menyimpan data Wahana ini?",
        confirmText: "Ya, Simpan",
    });
    if (!confirmed) return;
    const routeName = item
        ? "dashboard.cms.wahana.update"
        : "dashboard.cms.wahana.store";

    form.transform((data) => ({
        ...data,
        fotos: newPhotoItems.value.map((photo) => photo.file),
        existing_photo_order: existingPhotos.value.map((photo) => photo.key),
        is_active: data.is_active ? 1 : 0,
        is_unggulan: data.is_unggulan ? 1 : 0,
        urutan_tampil: Number(data.urutan_tampil || 0),
        ...(item ? { _method: "patch" } : {}),
    })).post(route(routeName, item?.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const toggleStatus = async (item) => {
    const confirmed = await confirm({
        type: item.is_active ? "warning" : "edit",
        title: item.is_active ? "Nonaktifkan Wahana" : "Aktifkan Wahana",
        message: `Apakah Anda yakin ingin ${item.is_active ? "menonaktifkan" : "mengaktifkan"} Wahana ini?`,
        confirmText: `Ya, ${item.is_active ? "Nonaktifkan" : "Aktifkan"}`,
    });
    if (!confirmed) return;
    router.patch(
        route("dashboard.cms.wahana.status", item.id),
        {},
        { preserveScroll: true },
    );
};

const confirmDelete = async (item) => {
    const confirmed = await confirm({
        type: "delete",
        title: "Hapus Wahana",
        message: `Apakah Anda yakin ingin menghapus Wahana “${item.nama_wahana}”?`,
        description: "Data dan seluruh foto terkait akan dihapus dari website.",
        confirmText: "Ya, Hapus",
    });
    if (confirmed)
        router.delete(route("dashboard.cms.wahana.destroy", item.id), {
            preserveScroll: true,
        });
};

const closeTopModal = () => {
    if (deleteTarget.value) {
        deleteTarget.value = null;
        return;
    }

    if (modal.value) closeModal();
};

const handleKeydown = (event) => {
    if (event.key === "Escape") closeTopModal();
};

watch([modal, deleteTarget], ([formModal, deleteModal]) => {
    document.body.style.overflow = formModal || deleteModal ? "hidden" : "";
});

onBeforeUnmount(() => {
    document.body.style.overflow = "";
    revokeNewPhotoPreviews();
});
</script>

<template>
    <Head title="Kelola Wahana" />
    <InternalDashboardLayout :user="user" title="CMS / Wahana">
        <div
            class="mx-auto max-w-[1380px] px-4 py-5 sm:px-6 lg:px-7"
            @keydown.window="handleKeydown"
        >
            <nav
                class="mb-5 flex w-fit rounded-xl border border-slate-200 bg-white p-1 shadow-sm"
                aria-label="Jenis konten Wahana"
            >
                <button
                    type="button"
                    :aria-current="activeTab === 'wahana' ? 'page' : undefined"
                    :class="
                        activeTab === 'wahana'
                            ? 'bg-[#1769e0] text-white'
                            : 'text-slate-600 hover:bg-slate-50'
                    "
                    class="rounded-lg px-5 py-2.5 text-xs font-bold"
                    @click="activeTab = 'wahana'"
                >
                    Wahana
                </button>
                <button
                    type="button"
                    :aria-current="
                        activeTab === 'tempat-makan' ? 'page' : undefined
                    "
                    :class="
                        activeTab === 'tempat-makan'
                            ? 'bg-[#1769e0] text-white'
                            : 'text-slate-600 hover:bg-slate-50'
                    "
                    class="rounded-lg px-5 py-2.5 text-xs font-bold"
                    @click="activeTab = 'tempat-makan'"
                >
                    Tempat Makan
                </button>
            </nav>

            <template v-if="activeTab === 'wahana'">
                <header
                    class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 pb-4"
                >
                    <div>
                        <p
                            class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#1769e0]"
                        >
                            Content Management System
                        </p>
                        <h2
                            class="mt-1 text-[22px] font-bold leading-tight text-[#172554]"
                        >
                            Kelola Wahana
                        </h2>
                        <p
                            class="mt-1 max-w-2xl text-xs leading-5 text-slate-500"
                        >
                            Kelola wahana yang ditampilkan pada website Kampoeng
                            Radja.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#1769e0] px-4 text-xs font-bold text-white shadow-sm transition hover:bg-[#0756ba] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200"
                        @click="openModal()"
                    >
                        <span class="text-base leading-none">+</span> Tambah
                        Wahana
                    </button>
                </header>

                <div
                    v-if="pageError"
                    class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                    role="alert"
                >
                    {{ pageError }}
                </div>

                <section
                    class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4"
                    >
                        <div>
                            <h3 class="text-sm font-bold text-[#172554]">
                                Daftar Wahana
                            </h3>
                            <p class="mt-1 text-xs text-slate-500">
                                Wahana aktif tampil pada website publik sesuai
                                urutan.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-[11px] font-bold">
                            <span
                                class="rounded-full bg-blue-50 px-3 py-1.5 text-[#0756ba]"
                                >{{ items.length }} Wahana</span
                            >
                            <span
                                class="rounded-full bg-amber-50 px-3 py-1.5 text-amber-700"
                                >{{ featuredCount }}/{{
                                    featuredLimit
                                }}
                                Unggulan Aktif</span
                            >
                        </div>
                    </div>

                    <div v-if="items.length" class="mt-4 space-y-3">
                        <article
                            v-for="item in items"
                            :key="item.id"
                            class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-3 sm:grid-cols-[112px_minmax(0,1fr)_auto] sm:items-center sm:p-4"
                        >
                            <img
                                :src="item.foto_url"
                                :alt="item.nama_wahana"
                                class="h-24 w-full rounded-lg border border-slate-200 bg-white object-cover sm:w-28"
                            />
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4
                                        class="text-sm font-bold text-[#172554]"
                                    >
                                        {{ item.nama_wahana }}
                                    </h4>
                                    <span
                                        :class="
                                            item.is_active
                                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                                : 'bg-slate-100 text-slate-600 ring-slate-200'
                                        "
                                        class="rounded-full px-2.5 py-1 text-[9px] font-extrabold uppercase tracking-wide ring-1 ring-inset"
                                        >{{
                                            item.is_active
                                                ? "Aktif"
                                                : "Nonaktif"
                                        }}</span
                                    >
                                    <span
                                        v-if="item.is_unggulan"
                                        class="rounded-full bg-amber-50 px-2.5 py-1 text-[9px] font-extrabold uppercase tracking-wide text-amber-700 ring-1 ring-inset ring-amber-200"
                                        >★ Wahana Unggulan</span
                                    >
                                </div>
                                <p
                                    class="mt-1.5 line-clamp-2 text-xs leading-5 text-slate-500"
                                >
                                    {{ item.deskripsi_singkat }}
                                </p>
                                <div
                                    class="mt-2 flex flex-wrap items-center gap-1.5"
                                >
                                    <span
                                        v-for="label in item.labels"
                                        :key="label"
                                        class="rounded-full bg-blue-50 px-2 py-1 text-[9px] font-bold text-[#0756ba]"
                                        >{{ label }}</span
                                    >
                                    <span
                                        v-if="!item.labels.length"
                                        class="text-[10px] font-medium text-slate-400"
                                        >Tanpa label</span
                                    >
                                    <span
                                        class="ml-1 text-[10px] font-semibold text-slate-400"
                                        >Urutan {{ item.urutan_tampil }}</span
                                    >
                                    <span
                                        class="text-[10px] font-semibold text-slate-400"
                                        >· {{ item.photos.length }} foto</span
                                    >
                                </div>
                            </div>
                            <div
                                class="flex flex-wrap items-center gap-2 sm:max-w-[250px] sm:justify-end"
                            >
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
                                    {{
                                        item.is_active
                                            ? "Nonaktifkan"
                                            : "Aktifkan"
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-red-200 bg-white px-3 py-2 text-[11px] font-bold text-red-600 hover:bg-red-50"
                                    @click="confirmDelete(item)"
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
                        <h4 class="text-sm font-bold text-slate-700">
                            Belum ada Wahana
                        </h4>
                        <p class="mt-1 text-xs text-slate-500">
                            Tambahkan wahana pertama untuk mulai mengisi halaman
                            publik.
                        </p>
                    </div>
                </section>

                <div
                    v-if="modal"
                    class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 p-3 sm:p-5"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="modal.item ? 'Edit Wahana' : 'Tambah Wahana'"
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
                                    {{
                                        modal.item
                                            ? "Edit Wahana"
                                            : "Tambah Wahana"
                                    }}
                                </h3>
                                <p class="mt-1 text-xs text-slate-500">
                                    Lengkapi informasi, label, foto, dan
                                    pengaturan publikasi Wahana.
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
                            class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_280px]"
                        >
                            <div class="space-y-4">
                                <label
                                    class="block text-xs font-bold text-slate-700"
                                    >Nama Wahana *<input
                                        ref="nameInput"
                                        v-model="form.nama_wahana"
                                        type="text"
                                        maxlength="150"
                                        class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                                /></label>
                                <p
                                    v-if="form.errors.nama_wahana"
                                    class="-mt-2 text-xs text-red-600"
                                >
                                    {{ form.errors.nama_wahana }}
                                </p>

                                <label
                                    class="block text-xs font-bold text-slate-700"
                                    >Deskripsi Singkat *<textarea
                                        v-model="form.deskripsi_singkat"
                                        rows="4"
                                        maxlength="255"
                                        class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                                    ></textarea
                                    ><span
                                        class="mt-1 block text-right text-[10px] font-medium text-slate-400"
                                        >{{
                                            form.deskripsi_singkat.length
                                        }}/255</span
                                    ></label
                                >
                                <p
                                    v-if="form.errors.deskripsi_singkat"
                                    class="-mt-2 text-xs text-red-600"
                                >
                                    {{ form.errors.deskripsi_singkat }}
                                </p>

                                <fieldset>
                                    <legend
                                        class="text-xs font-bold text-slate-700"
                                    >
                                        Label
                                    </legend>
                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                        <label
                                            v-for="label in labels"
                                            :key="label"
                                            class="flex min-h-10 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700"
                                        >
                                            <input
                                                v-model="form.label"
                                                type="checkbox"
                                                :value="label"
                                                class="rounded border-slate-300 text-[#1769e0] focus:ring-[#1769e0]"
                                            />
                                            {{ label }}
                                        </label>
                                    </div>
                                    <p
                                        v-if="form.errors.label"
                                        class="mt-2 text-xs text-red-600"
                                    >
                                        {{ form.errors.label }}
                                    </p>
                                </fieldset>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label
                                        class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700"
                                        ><input
                                            v-model="form.is_active"
                                            type="checkbox"
                                            class="rounded border-slate-300 text-[#1769e0] focus:ring-[#1769e0]"
                                        />
                                        Status Aktif</label
                                    >
                                    <label
                                        class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700"
                                        ><input
                                            v-model="form.is_unggulan"
                                            type="checkbox"
                                            class="rounded border-slate-300 text-[#1769e0] focus:ring-[#1769e0]"
                                        />
                                        Wahana Unggulan</label
                                    >
                                </div>
                                <p
                                    v-if="
                                        form.errors.is_active ||
                                        form.errors.is_unggulan
                                    "
                                    class="text-xs text-red-600"
                                >
                                    {{
                                        form.errors.is_active ||
                                        form.errors.is_unggulan
                                    }}
                                </p>

                                <label
                                    class="block max-w-xs text-xs font-bold text-slate-700"
                                    >Urutan Tampil *<input
                                        v-model.number="form.urutan_tampil"
                                        type="number"
                                        min="0"
                                        class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                                /></label>
                                <p
                                    v-if="form.errors.urutan_tampil"
                                    class="-mt-2 text-xs text-red-600"
                                >
                                    {{ form.errors.urutan_tampil }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-700"
                                    >Foto Wahana {{ modal.item ? "" : "*" }}
                                    <input
                                        :key="fileInputKey"
                                        type="file"
                                        multiple
                                        accept="image/jpeg,image/png,image/webp"
                                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-bold file:text-[#0756d8]"
                                        :required="
                                            !modal.item &&
                                            newPhotoItems.length === 0
                                        "
                                        @change="selectPhotos"
                                    />
                                </label>
                                <p
                                    class="mt-2 text-[10px] leading-4 text-slate-500"
                                >
                                    JPG, JPEG, PNG, atau WebP • Maks. 10 MB per foto • Maks. total 30 MB • Disarankan 1200 × 800 px (3:2). Foto paling atas menjadi cover.
                                </p>
                                <p
                                    v-if="photoError"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{ photoError }}
                                </p>

                                <div
                                    v-if="existingPhotos.length"
                                    class="mt-4 space-y-2"
                                >
                                    <p
                                        class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-400"
                                    >
                                        Foto tersimpan
                                    </p>
                                    <article
                                        v-for="(photo, index) in existingPhotos"
                                        :key="photo.key"
                                        class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-2"
                                    >
                                        <img
                                            :src="photo.url"
                                            :alt="`Foto ${index + 1} ${form.nama_wahana}`"
                                            class="h-16 w-20 shrink-0 rounded-lg object-cover"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="text-xs font-bold text-slate-700"
                                            >
                                                Foto {{ index + 1 }}
                                            </p>
                                            <p
                                                v-if="index === 0"
                                                class="mt-1 text-[9px] font-bold uppercase tracking-wide text-[#1769e0]"
                                            >
                                                Cover
                                            </p>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <div class="flex gap-1">
                                                <button
                                                    type="button"
                                                    :disabled="index === 0"
                                                    class="rounded border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-600 disabled:opacity-35"
                                                    aria-label="Naikkan urutan foto"
                                                    @click="
                                                        movePhoto(
                                                            existingPhotos,
                                                            index,
                                                            -1,
                                                        )
                                                    "
                                                >
                                                    ↑</button
                                                ><button
                                                    type="button"
                                                    :disabled="
                                                        index ===
                                                        existingPhotos.length -
                                                            1
                                                    "
                                                    class="rounded border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-600 disabled:opacity-35"
                                                    aria-label="Turunkan urutan foto"
                                                    @click="
                                                        movePhoto(
                                                            existingPhotos,
                                                            index,
                                                            1,
                                                        )
                                                    "
                                                >
                                                    ↓
                                                </button>
                                            </div>
                                            <button
                                                type="button"
                                                class="rounded border border-red-200 bg-white px-2 py-1 text-[10px] font-bold text-red-600 hover:bg-red-50"
                                                @click="
                                                    removeExistingPhoto(index)
                                                "
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </article>
                                </div>

                                <div
                                    v-if="newPhotoItems.length"
                                    class="mt-4 space-y-2"
                                >
                                    <p
                                        class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-400"
                                    >
                                        Foto baru
                                    </p>
                                    <article
                                        v-for="(photo, index) in newPhotoItems"
                                        :key="photo.id"
                                        class="flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50/50 p-2"
                                    >
                                        <img
                                            :src="photo.url"
                                            :alt="`Preview foto baru ${index + 1}`"
                                            class="h-16 w-20 shrink-0 rounded-lg object-cover"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate text-xs font-bold text-slate-700"
                                            >
                                                {{ photo.file.name }}
                                            </p>
                                            <p
                                                class="mt-1 text-[9px] font-semibold text-slate-400"
                                            >
                                                Urutan setelah foto tersimpan
                                            </p>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <div class="flex gap-1">
                                                <button
                                                    type="button"
                                                    :disabled="index === 0"
                                                    class="rounded border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-600 disabled:opacity-35"
                                                    aria-label="Naikkan urutan foto baru"
                                                    @click="
                                                        movePhoto(
                                                            newPhotoItems,
                                                            index,
                                                            -1,
                                                        )
                                                    "
                                                >
                                                    ↑</button
                                                ><button
                                                    type="button"
                                                    :disabled="
                                                        index ===
                                                        newPhotoItems.length - 1
                                                    "
                                                    class="rounded border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-600 disabled:opacity-35"
                                                    aria-label="Turunkan urutan foto baru"
                                                    @click="
                                                        movePhoto(
                                                            newPhotoItems,
                                                            index,
                                                            1,
                                                        )
                                                    "
                                                >
                                                    ↓
                                                </button>
                                            </div>
                                            <button
                                                type="button"
                                                class="rounded border border-red-200 bg-white px-2 py-1 text-[10px] font-bold text-red-600 hover:bg-red-50"
                                                @click="removeNewPhoto(index)"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </article>
                                </div>

                                <div
                                    v-if="
                                        !existingPhotos.length &&
                                        !newPhotoItems.length
                                    "
                                    class="mt-4 grid min-h-28 place-items-center rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 text-center text-xs text-slate-400"
                                >
                                    Belum ada foto. Minimal satu foto wajib
                                    tersedia.
                                </div>
                                <div
                                    class="mt-4 rounded-lg bg-amber-50 px-3 py-3 text-[10px] leading-4 text-amber-700"
                                >
                                    Maksimal {{ featuredLimit }} Wahana aktif
                                    dapat ditampilkan sebagai Wahana Unggulan di
                                    Beranda.
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
                                {{
                                    form.processing
                                        ? "Menyimpan..."
                                        : "Simpan Wahana"
                                }}
                            </button>
                        </footer>
                    </form>
                </div>
            </template>

            <DiningPlaceManager
                v-else
                :items="diningItems"
                :categories="diningCategories"
            />

            <div
                v-if="deleteTarget"
                class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4"
                role="dialog"
                aria-modal="true"
                aria-label="Konfirmasi hapus Wahana"
                @click.self="deleteTarget = null"
            >
                <div
                    class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl sm:p-6"
                >
                    <div
                        class="grid h-11 w-11 place-items-center rounded-full bg-red-50 text-red-600"
                        aria-hidden="true"
                    >
                        !
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-[#172554]">
                        Hapus Wahana {{ deleteTarget.nama_wahana }}?
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Data dan foto Wahana akan dihapus dari CMS serta tidak
                        lagi tampil pada website.
                    </p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50"
                            @click="deleteTarget = null"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-red-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-red-700"
                            @click="confirmDelete"
                        >
                            Hapus Wahana
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </InternalDashboardLayout>
</template>
