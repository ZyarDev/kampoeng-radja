<script setup>
import InternalDashboardLayout from "@/Layouts/InternalDashboardLayout.vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { useConfirmation } from "@/composables/useConfirmation";
import { CMS_UPLOAD_LIMITS, validateUploadFiles } from "@/utils/cmsUploadValidation";

const props = defineProps({
    user: { type: Object, required: true },
    items: { type: Array, required: true },
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
const form = useForm({
    nama_event: "",
    tanggal_event: "",
    deskripsi: "",
    fotos: [],
    new_photo_captions: [],
    existing_photos: [],
});

const pageError = computed(() => page.props.errors?.existing_photos);
const photoError = computed(() => {
    if (localPhotoError.value) return localPhotoError.value;
    if (form.errors.fotos) return form.errors.fotos;

    return (
        Object.entries(form.errors).find(([key]) =>
            key.startsWith("fotos."),
        )?.[1] || null
    );
});

const formatDate = (value) => {
    if (!value) return "Tanggal belum tersedia";

    return new Intl.DateTimeFormat("id-ID", {
        day: "numeric",
        month: "long",
        year: "numeric",
        timeZone: "Asia/Jakarta",
    }).format(new Date(`${value}T00:00:00+07:00`));
};

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
        ? item.photos.map((photo) => ({
              ...photo,
              caption: photo.caption || "",
          }))
        : [];

    if (item) {
        form.nama_event = item.nama_event;
        form.tanggal_event = item.tanggal_event || "";
        form.deskripsi = item.deskripsi;
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
    localPhotoError.value = validateUploadFiles(combined, CMS_UPLOAD_LIMITS.gallery, 'foto Galeri Event');
    if (localPhotoError.value) { event.target.value = ''; return; }

    newPhotoItems.value.push(
        ...files.map((file, index) => ({
            id: `${Date.now()}-${index}-${file.name}`,
            file,
            url: URL.createObjectURL(file),
            caption: "",
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
    localPhotoError.value = validateUploadFiles(form.fotos, CMS_UPLOAD_LIMITS.gallery, 'foto Galeri Event');
};

const submit = async () => {
    const item = modal.value?.item;
    const confirmed = await confirm({
        type: item ? "edit" : "save",
        title: item ? "Edit Galeri Event" : "Simpan Galeri Event",
        message: "Apakah Anda yakin ingin menyimpan album Galeri Event ini?",
        confirmText: "Ya, Simpan",
    });
    if (!confirmed) return;
    const routeName = item
        ? "dashboard.cms.gallery.update"
        : "dashboard.cms.gallery.store";

    form.transform((data) => ({
        ...data,
        fotos: newPhotoItems.value.map((photo) => photo.file),
        new_photo_captions: newPhotoItems.value.map(
            (photo) => photo.caption || "",
        ),
        existing_photos: existingPhotos.value.map((photo) => ({
            id: photo.id,
            caption: photo.caption || "",
        })),
        ...(item ? { _method: "patch" } : {}),
    })).post(route(routeName, item?.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: closeModal,
    });
};

const confirmDelete = async (item) => {
    const confirmed = await confirm({
        type: "delete",
        title: "Hapus Galeri Event",
        message: `Apakah Anda yakin ingin menghapus album “${item.nama_event}”?`,
        description: `Album dan seluruh ${item.photo_count} foto di dalamnya akan dihapus.`,
        confirmText: "Ya, Hapus",
    });
    if (confirmed)
        router.delete(route("dashboard.cms.gallery.destroy", item.id), {
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
    <Head title="Kelola Galeri Event" />

    <InternalDashboardLayout :user="user" title="CMS / Galeri Event">
        <div
            class="mx-auto max-w-[1380px] px-4 py-5 sm:px-6 lg:px-7"
            @keydown.window="handleKeydown"
        >
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
                        Kelola Galeri Event
                    </h2>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500">
                        Kelola album dan dokumentasi event yang tampil pada
                        website Kampoeng Radja.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#1769e0] px-4 text-xs font-bold text-white shadow-sm transition hover:bg-[#0756ba] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200"
                    @click="openModal()"
                >
                    <span class="text-base leading-none">+</span> Tambah Event
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
                            Album Event
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Foto dengan urutan pertama otomatis menjadi cover
                            dan featured photo publik.
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-blue-50 px-3 py-1.5 text-[11px] font-bold text-[#0756ba]"
                        >{{ items.length }} Album</span
                    >
                </div>

                <div v-if="items.length" class="mt-4 space-y-3">
                    <article
                        v-for="item in items"
                        :key="item.id"
                        class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-3 sm:grid-cols-[128px_minmax(0,1fr)_auto] sm:items-center sm:p-4"
                    >
                        <img
                            v-if="item.cover_url"
                            :src="item.cover_url"
                            :alt="`Cover ${item.nama_event}`"
                            class="h-24 w-full rounded-lg border border-slate-200 bg-white object-cover sm:w-32"
                        />
                        <div
                            v-else
                            class="grid h-24 w-full place-items-center rounded-lg border border-dashed border-slate-300 bg-white px-3 text-center text-[10px] font-semibold text-slate-400 sm:w-32"
                        >
                            Belum ada foto
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-sm font-bold text-[#172554]">
                                {{ item.nama_event }}
                            </h4>
                            <div
                                class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] font-semibold text-slate-500"
                            >
                                <span>{{
                                    formatDate(item.tanggal_event)
                                }}</span>
                                <span
                                    class="rounded-full bg-blue-50 px-2.5 py-1 text-[#0756ba]"
                                    >{{ item.photo_count }} Foto</span
                                >
                            </div>
                            <p
                                class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500"
                            >
                                {{ item.deskripsi }}
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-2 sm:justify-end"
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
                        Tidak ada Galeri Event.
                    </h4>
                    <p class="mt-1 text-xs text-slate-500">
                        Tambahkan album pertama untuk menampilkan dokumentasi
                        event di website.
                    </p>
                    <button
                        type="button"
                        class="mt-4 rounded-lg bg-[#1769e0] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#0756ba]"
                        @click="openModal()"
                    >
                        + Tambah Event
                    </button>
                </div>
            </section>

            <div
                v-if="modal"
                class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 p-3 sm:p-5"
                role="dialog"
                aria-modal="true"
                :aria-label="
                    modal.item ? 'Edit Galeri Event' : 'Tambah Galeri Event'
                "
                @click.self="closeModal"
            >
                <form
                    class="max-h-[94vh] w-full max-w-6xl overflow-y-auto rounded-2xl bg-white shadow-2xl"
                    @submit.prevent="submit"
                >
                    <header
                        class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-200 bg-white px-5 py-4 sm:px-6"
                    >
                        <div>
                            <h3 class="text-lg font-bold text-[#172554]">
                                {{
                                    modal.item
                                        ? "Edit Galeri Event"
                                        : "Tambah Galeri Event"
                                }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-500">
                                Satu event merupakan satu album. Atur detail,
                                caption, dan urutan seluruh fotonya.
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
                        class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[minmax(280px,0.8fr)_minmax(0,1.4fr)]"
                    >
                        <div class="space-y-4">
                            <label
                                class="block text-xs font-bold text-slate-700"
                                >Nama Event *
                                <input
                                    ref="nameInput"
                                    v-model="form.nama_event"
                                    type="text"
                                    maxlength="150"
                                    class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                                />
                            </label>
                            <p
                                v-if="form.errors.nama_event"
                                class="-mt-2 text-xs text-red-600"
                            >
                                {{ form.errors.nama_event }}
                            </p>

                            <label
                                class="block text-xs font-bold text-slate-700"
                                >Tanggal Event *
                                <input
                                    v-model="form.tanggal_event"
                                    type="date"
                                    class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                                />
                            </label>
                            <p
                                v-if="form.errors.tanggal_event"
                                class="-mt-2 text-xs text-red-600"
                            >
                                {{ form.errors.tanggal_event }}
                            </p>

                            <label
                                class="block text-xs font-bold text-slate-700"
                                >Deskripsi Event *
                                <textarea
                                    v-model="form.deskripsi"
                                    rows="8"
                                    class="mt-2 w-full rounded-lg border-slate-300 text-sm leading-6 focus:border-[#1769e0] focus:ring-[#1769e0]"
                                ></textarea>
                            </label>
                            <p
                                v-if="form.errors.deskripsi"
                                class="-mt-2 text-xs text-red-600"
                            >
                                {{ form.errors.deskripsi }}
                            </p>

                            <div
                                class="rounded-xl border border-blue-100 bg-blue-50/60 p-4 text-[11px] leading-5 text-[#0756ba]"
                            >
                                Foto pertama menjadi cover CMS dan featured
                                photo default di halaman publik. Gunakan tombol
                                Naik/Turun untuk menyimpan urutan secara
                                permanen.
                            </div>
                        </div>

                        <div>
                            <label
                                class="block text-xs font-bold text-slate-700"
                                >Foto Event {{ modal.item ? "" : "*" }}
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
                                JPG, JPEG, PNG, atau WebP • Maks. 10 MB per foto • Maks. total 50 MB • Disarankan sisi terpanjang minimal 1600–1920 px; pertahankan rasio asli.
                            </p>
                            <p
                                v-if="photoError"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ photoError }}
                            </p>

                            <div v-if="existingPhotos.length" class="mt-4">
                                <p
                                    class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-400"
                                >
                                    Foto tersimpan
                                </p>
                                <div
                                    class="mt-2 grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                                >
                                    <article
                                        v-for="(photo, index) in existingPhotos"
                                        :key="photo.id"
                                        class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
                                    >
                                        <div
                                            class="relative aspect-video bg-slate-100"
                                        >
                                            <img
                                                :src="photo.url"
                                                :alt="`Foto ${index + 1} ${form.nama_event}`"
                                                class="h-full w-full object-cover"
                                            />
                                            <span
                                                v-if="index === 0"
                                                class="absolute left-2 top-2 rounded-full bg-[#1769e0] px-2 py-1 text-[8px] font-extrabold uppercase tracking-wide text-white"
                                                >Cover</span
                                            >
                                        </div>
                                        <div class="space-y-2 p-3">
                                            <label
                                                class="block text-[10px] font-bold text-slate-600"
                                                >Caption (opsional)
                                                <input
                                                    v-model="photo.caption"
                                                    type="text"
                                                    maxlength="255"
                                                    placeholder="Caption foto"
                                                    class="mt-1.5 h-9 w-full rounded-lg border-slate-300 text-xs focus:border-[#1769e0] focus:ring-[#1769e0]"
                                                />
                                            </label>
                                            <div
                                                class="flex items-center justify-between gap-2"
                                            >
                                                <span
                                                    class="text-[10px] font-bold text-slate-400"
                                                    >Urutan
                                                    {{ index + 1 }}</span
                                                >
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
                                                        ↑
                                                    </button>
                                                    <button
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
                                                    <button
                                                        type="button"
                                                        class="rounded border border-red-200 bg-white px-2 py-1 text-[10px] font-bold text-red-600 hover:bg-red-50"
                                                        @click="
                                                            removeExistingPhoto(
                                                                index,
                                                            )
                                                        "
                                                    >
                                                        Hapus
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </div>

                            <div v-if="newPhotoItems.length" class="mt-4">
                                <p
                                    class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-400"
                                >
                                    Foto baru
                                </p>
                                <div
                                    class="mt-2 grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                                >
                                    <article
                                        v-for="(photo, index) in newPhotoItems"
                                        :key="photo.id"
                                        class="overflow-hidden rounded-xl border border-blue-100 bg-blue-50/40"
                                    >
                                        <div class="aspect-video bg-slate-100">
                                            <img
                                                :src="photo.url"
                                                :alt="`Preview foto baru ${index + 1}`"
                                                class="h-full w-full object-cover"
                                            />
                                        </div>
                                        <div class="space-y-2 p-3">
                                            <label
                                                class="block text-[10px] font-bold text-slate-600"
                                                >Caption (opsional)
                                                <input
                                                    v-model="photo.caption"
                                                    type="text"
                                                    maxlength="255"
                                                    placeholder="Caption foto"
                                                    class="mt-1.5 h-9 w-full rounded-lg border-slate-300 bg-white text-xs focus:border-[#1769e0] focus:ring-[#1769e0]"
                                                />
                                            </label>
                                            <div
                                                class="flex items-center justify-between gap-2"
                                            >
                                                <span
                                                    class="text-[10px] font-bold text-slate-400"
                                                    >Baru {{ index + 1 }}</span
                                                >
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
                                                        ↑
                                                    </button>
                                                    <button
                                                        type="button"
                                                        :disabled="
                                                            index ===
                                                            newPhotoItems.length -
                                                                1
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
                                                    <button
                                                        type="button"
                                                        class="rounded border border-red-200 bg-white px-2 py-1 text-[10px] font-bold text-red-600 hover:bg-red-50"
                                                        @click="
                                                            removeNewPhoto(
                                                                index,
                                                            )
                                                        "
                                                    >
                                                        Hapus
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </div>

                            <div
                                v-if="
                                    !existingPhotos.length &&
                                    !newPhotoItems.length
                                "
                                class="mt-4 grid min-h-32 place-items-center rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 text-center text-xs text-slate-400"
                            >
                                {{
                                    modal.item
                                        ? "Album ini belum memiliki foto. Tambahkan foto jika diperlukan."
                                        : "Belum ada foto. Minimal satu foto wajib untuk event baru."
                                }}
                            </div>
                            <p
                                v-if="form.errors.existing_photos"
                                class="mt-2 text-xs text-red-600"
                            >
                                {{ form.errors.existing_photos }}
                            </p>
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
                                    : "Simpan Event"
                            }}
                        </button>
                    </footer>
                </form>
            </div>

            <div
                v-if="deleteTarget"
                class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4"
                role="dialog"
                aria-modal="true"
                aria-label="Konfirmasi hapus Galeri Event"
                @click.self="deleteTarget = null"
            >
                <div
                    class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl sm:p-6"
                >
                    <div
                        class="grid h-11 w-11 place-items-center rounded-full bg-red-50 font-bold text-red-600"
                        aria-hidden="true"
                    >
                        !
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-[#172554]">
                        Hapus Galeri Event {{ deleteTarget.nama_event }}?
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
                        Album dan seluruh {{ deleteTarget.photo_count }} foto di
                        dalamnya akan dihapus dari CMS serta website.
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
                            Hapus Event
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </InternalDashboardLayout>
</template>
