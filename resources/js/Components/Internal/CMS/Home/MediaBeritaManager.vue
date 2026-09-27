<script setup>
import { router, useForm } from "@inertiajs/vue3";
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { useConfirmation } from "@/composables/useConfirmation";
import { CMS_UPLOAD_LIMITS, validateUploadFile } from "@/utils/cmsUploadValidation";

defineProps({ items: { type: Array, required: true } });

const modal = ref(null);
const { confirm } = useConfirmation();
const localPreview = ref(null);
const fileKey = ref(0);
const titleInput = ref(null);
const form = useForm({
    judul: "",
    tanggal_publish: "",
    foto: null,
    deskripsi: "",
});
const preview = computed(
    () => localPreview.value || modal.value?.item?.foto_url || null,
);
const formatDate = (value) =>
    value
        ? new Intl.DateTimeFormat("id-ID", {
              dateStyle: "long",
              timeStyle: "short",
              timeZone: "Asia/Jakarta",
          }).format(new Date(value))
        : "-";

const revokePreview = () => {
    if (localPreview.value) URL.revokeObjectURL(localPreview.value);
    localPreview.value = null;
};
const openModal = async (item = null) => {
    revokePreview();
    form.reset();
    form.clearErrors();
    fileKey.value += 1;
    modal.value = { item };
    if (item)
        Object.assign(form, {
            judul: item.judul,
            tanggal_publish: item.tanggal_publish,
            deskripsi: item.deskripsi,
            foto: null,
        });
    await nextTick();
    titleInput.value?.focus();
};
const closeModal = () => {
    modal.value = null;
    revokePreview();
    form.reset();
    form.clearErrors();
};
const selectPhoto = (event) => {
    const [file] = event.target.files;
    form.clearErrors('foto');
    const error = validateUploadFile(file, CMS_UPLOAD_LIMITS.media.file, 'gambar Media & Berita');
    if (error) { event.target.value = ''; form.foto = null; form.setError('foto', error); return; }
    revokePreview();
    form.foto = file || null;
    localPreview.value = file ? URL.createObjectURL(file) : null;
};
const submit = async () => {
    const item = modal.value?.item;
    const confirmed = await confirm({
        type: item ? "edit" : "save",
        title: item ? "Edit Berita" : "Simpan Berita",
        message: item
            ? "Apakah Anda yakin ingin menyimpan perubahan berita ini?"
            : "Apakah Anda yakin ingin menyimpan berita ini?",
        confirmText: "Ya, Simpan",
    });
    if (!confirmed) return;
    form.transform((data) => ({
        ...data,
        ...(item ? { _method: "patch" } : {}),
    })).post(
        route(
            item
                ? "dashboard.cms.home.media.update"
                : "dashboard.cms.home.media.store",
            item?.id,
        ),
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: closeModal,
        },
    );
};
const confirmDelete = async (item) => {
    const confirmed = await confirm({
        type: "delete",
        title: "Hapus Berita",
        message: `Apakah Anda yakin ingin menghapus berita “${item.judul}”?`,
        description: "Berita tidak akan tampil lagi pada website.",
        confirmText: "Ya, Hapus",
    });
    if (confirmed)
        router.delete(route("dashboard.cms.home.media.destroy", item.id), {
            preserveScroll: true,
        });
};
watch(modal, (edit) => {
    document.body.style.overflow = edit ? "hidden" : "";
});
onBeforeUnmount(() => {
    document.body.style.overflow = "";
    revokePreview();
});
</script>

<template>
    <div class="p-4 sm:p-5">
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4"
        >
            <div>
                <h3 class="text-sm font-bold text-[#172554]">
                    Media &amp; Berita
                </h3>
                <p class="mt-1 text-xs text-slate-500">
                    Kelola berita dari satu sumber untuk Beranda dan halaman
                    Media &amp; Berita.
                </p>
            </div>
            <button
                type="button"
                class="rounded-lg bg-[#1769e0] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#0756ba]"
                @click="openModal()"
            >
                + Tambah Berita
            </button>
        </div>
        <div v-if="items.length" class="mt-4 space-y-3">
            <article
                v-for="item in items"
                :key="item.id"
                class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-3 sm:grid-cols-[112px_minmax(0,1fr)_auto] sm:items-center"
            >
                <img
                    :src="item.foto_url"
                    :alt="item.judul"
                    class="aspect-[16/10] w-full rounded-lg border border-slate-200 object-cover sm:w-28"
                />
                <div class="min-w-0">
                    <h4 class="truncate text-sm font-bold text-[#172554]">
                        {{ item.judul }}
                    </h4>
                    <p class="mt-1 text-xs font-semibold text-[#0756ba]">
                        {{ formatDate(item.tanggal_publish_iso) }}
                    </p>
                    <p
                        class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500"
                    >
                        {{ item.deskripsi }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-blue-200 bg-white px-3 py-2 text-[11px] font-bold text-[#0756d8]"
                        @click="openModal(item)"
                    >
                        Edit</button
                    ><button
                        type="button"
                        class="rounded-lg border border-red-200 bg-white px-3 py-2 text-[11px] font-bold text-red-600"
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
                Belum ada Media &amp; Berita
            </h4>
            <p class="mt-1 text-xs text-slate-500">
                Tambahkan berita pertama untuk ditampilkan di website.
            </p>
        </div>

        <div
            v-if="modal"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 p-3"
            role="dialog"
            aria-modal="true"
            @click.self="closeModal"
        >
            <form
                class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl"
                @submit.prevent="submit"
            >
                <header
                    class="sticky top-0 z-10 flex justify-between border-b border-slate-200 bg-white px-5 py-4"
                >
                    <div>
                        <h3 class="text-lg font-bold text-[#172554]">
                            {{ modal.item ? "Edit Berita" : "Tambah Berita" }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Lengkapi informasi berita yang akan ditampilkan.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="h-9 w-9 rounded-lg text-xl text-slate-500 hover:bg-slate-100"
                        @click="closeModal"
                    >
                        ×
                    </button>
                </header>
                <div
                    class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_240px]"
                >
                    <div class="space-y-4">
                        <label class="block text-xs font-bold text-slate-700"
                            >Judul Berita *<input
                                ref="titleInput"
                                v-model="form.judul"
                                maxlength="150"
                                required
                                class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                            /><span
                                class="mt-1 block text-right text-[10px] text-slate-400"
                                >{{ form.judul.length }} / 150</span
                            ></label
                        >
                        <p
                            v-if="form.errors.judul"
                            class="-mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.judul }}
                        </p>
                        <label class="block text-xs font-bold text-slate-700"
                            >Tanggal Publish *<input
                                v-model="form.tanggal_publish"
                                type="datetime-local"
                                required
                                class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                        /></label>
                        <p
                            v-if="form.errors.tanggal_publish"
                            class="-mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.tanggal_publish }}
                        </p>
                        <label class="block text-xs font-bold text-slate-700"
                            >Deskripsi *<textarea
                                v-model="form.deskripsi"
                                maxlength="250"
                                rows="5"
                                required
                                class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                            ></textarea
                            ><span
                                class="mt-1 block text-right text-[10px] text-slate-400"
                                >{{ form.deskripsi.length }} / 250</span
                            ></label
                        >
                        <p
                            v-if="form.errors.deskripsi"
                            class="-mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.deskripsi }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700"
                            >Foto {{ modal.item ? "" : "*"
                            }}<input
                                :key="fileKey"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                :required="!modal.item"
                                class="mt-2 block w-full rounded-lg border border-slate-300 p-2 text-xs file:mr-2 file:rounded file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-bold file:text-[#0756d8]"
                                @change="selectPhoto"
                        /></label>
                        <p class="mt-2 text-[10px] text-slate-500">
                            JPG, JPEG, PNG, atau WebP • Maks. ukuran file: 20 MB • Disarankan 1600 × 1000 px (16:10).
                        </p>
                        <p
                            v-if="form.errors.foto"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.foto }}
                        </p>
                        <div
                            class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-100"
                        >
                            <img
                                v-if="preview"
                                :src="preview"
                                alt="Preview foto berita"
                                class="aspect-[16/10] w-full object-cover"
                            />
                            <div
                                v-else
                                class="grid aspect-[16/10] place-items-center text-xs text-slate-400"
                            >
                                Preview foto
                            </div>
                        </div>
                    </div>
                </div>
                <footer
                    class="sticky bottom-0 flex justify-end gap-2 border-t border-slate-200 bg-white px-5 py-4"
                >
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-600"
                        @click="closeModal"
                    >
                        Batal</button
                    ><button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-[#1769e0] px-5 py-2.5 text-xs font-bold text-white disabled:opacity-60"
                    >
                        {{ form.processing ? "Menyimpan..." : "Simpan Berita" }}
                    </button>
                </footer>
            </form>
        </div>
    </div>
</template>
