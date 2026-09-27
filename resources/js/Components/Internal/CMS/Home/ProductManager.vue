<script setup>
import { router, useForm } from "@inertiajs/vue3";
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { useConfirmation } from "@/composables/useConfirmation";
import { CMS_UPLOAD_LIMITS, validateUploadFile } from "@/utils/cmsUploadValidation";

defineProps({ products: { type: Array, required: true } });
const modal = ref(null);
const titleInput = ref(null);
const thumbnailPreview = ref(null);
const heroPreview = ref(null);
const fileKey = ref(0);
const form = useForm({
    nama: "",
    deskripsi_singkat: "",
    deskripsi_lengkap: "",
    thumbnail: null,
    hero_image: null,
    is_active: true,
    urutan_tampil: 0,
});
const currentThumbnail = computed(
    () => thumbnailPreview.value || modal.value?.item?.thumbnail_url || null,
);
const currentHero = computed(
    () => heroPreview.value || modal.value?.item?.hero_image_url || null,
);
const revoke = () => {
    if (thumbnailPreview.value) URL.revokeObjectURL(thumbnailPreview.value);
    if (heroPreview.value) URL.revokeObjectURL(heroPreview.value);
    thumbnailPreview.value = null;
    heroPreview.value = null;
};
const openModal = async (item = null) => {
    revoke();
    form.reset();
    form.clearErrors();
    fileKey.value += 1;
    modal.value = { item };
    if (item)
        Object.assign(form, {
            nama: item.nama,
            deskripsi_singkat: item.deskripsi_singkat,
            deskripsi_lengkap: item.deskripsi_lengkap || "",
            is_active: item.is_active,
            urutan_tampil: item.urutan_tampil,
            thumbnail: null,
            hero_image: null,
        });
    await nextTick();
    titleInput.value?.focus();
};
const closeModal = () => {
    modal.value = null;
    revoke();
    form.reset();
    form.clearErrors();
};
const selectImage = (field, event) => {
    const [file] = event.target.files;
    form.clearErrors(field);
    const error = validateUploadFile(file, CMS_UPLOAD_LIMITS.product.file, 'gambar Produk');
    if (error) { event.target.value = ''; form[field] = null; form.setError(field, error); return; }
    const target = field === "thumbnail" ? thumbnailPreview : heroPreview;
    if (target.value) URL.revokeObjectURL(target.value);
    form[field] = file || null;
    target.value = file ? URL.createObjectURL(file) : null;
};
const { confirm } = useConfirmation();
const submit = async () => {
    const item = modal.value?.item;
    const ok = await confirm({
        type: item ? "edit" : "save",
        title: item ? "Edit Produk" : "Simpan Produk",
        message: "Apakah Anda yakin ingin menyimpan data Produk ini?",
        confirmText: "Ya, Simpan",
    });
    if (!ok) return;
    form.transform((data) => ({
        ...data,
        is_active: data.is_active ? 1 : 0,
        urutan_tampil: Number(data.urutan_tampil),
        ...(item ? { _method: "patch" } : {}),
    })).post(
        route(
            item
                ? "dashboard.cms.home.products.update"
                : "dashboard.cms.home.products.store",
            item?.id,
        ),
        { forceFormData: true, preserveScroll: true, onSuccess: closeModal },
    );
};
const confirmDelete = async (item) => {
    const ok = await confirm({
        type: "delete",
        title: "Hapus Produk",
        message: `Apakah Anda yakin ingin menghapus Produk “${item.nama}”?`,
        description: "Produk tidak akan tampil lagi pada Beranda.",
        confirmText: "Ya, Hapus",
    });
    if (ok)
        router.delete(route("dashboard.cms.home.products.destroy", item.id), {
            preserveScroll: true,
        });
};
watch(modal, (edit) => {
    document.body.style.overflow = edit ? "hidden" : "";
});
onBeforeUnmount(() => {
    document.body.style.overflow = "";
    revoke();
});
</script>

<template>
    <div class="p-4 sm:p-5">
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4"
        >
            <div>
                <h3 class="text-sm font-bold text-[#172554]">Produk</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Kelola visual showcase Produk pada Beranda.
                </p>
            </div>
            <button
                type="button"
                class="rounded-lg bg-[#1769e0] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#0756ba]"
                @click="openModal()"
            >
                + Tambah Produk
            </button>
        </div>
        <div v-if="products.length" class="mt-4 space-y-3">
            <article
                v-for="item in products"
                :key="item.id"
                class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/50 p-3 sm:grid-cols-[76px_minmax(0,1fr)_auto] sm:items-center"
            >
                <img
                    :src="item.thumbnail_url"
                    :alt="item.nama"
                    class="aspect-[4/5] w-[76px] rounded-lg border border-slate-200 bg-white object-cover"
                />
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h4 class="truncate text-sm font-bold text-[#172554]">
                            {{ item.nama }}
                        </h4>
                        <span
                            :class="
                                item.is_active
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-slate-200 text-slate-600'
                            "
                            class="rounded-full px-2.5 py-1 text-[9px] font-extrabold uppercase"
                            >{{ item.is_active ? "Aktif" : "Nonaktif" }}</span
                        >
                    </div>
                    <p
                        class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500"
                    >
                        {{ item.deskripsi_singkat }}
                    </p>
                    <p class="mt-1 text-[10px] font-semibold text-slate-400">
                        Urutan tampil: {{ item.urutan_tampil }}
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
            <h4 class="text-sm font-bold text-slate-700">Belum ada Produk</h4>
            <p class="mt-1 text-xs text-slate-500">
                Tambahkan Produk pertama untuk mengisi showcase Beranda.
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
                class="max-h-[94vh] w-full max-w-5xl overflow-y-auto rounded-2xl bg-white shadow-2xl"
                @submit.prevent="submit"
            >
                <header
                    class="sticky top-0 z-10 flex justify-between border-b border-slate-200 bg-white px-5 py-4"
                >
                    <div>
                        <h3 class="text-lg font-bold text-[#172554]">
                            {{ modal.item ? "Edit Produk" : "Tambah Produk" }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Gunakan thumbnail portrait dan Hero Image khusus
                            area showcase.
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
                    class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_360px]"
                >
                    <div class="space-y-4">
                        <label class="block text-xs font-bold text-slate-700"
                            >Nama Produk *<input
                                ref="titleInput"
                                v-model="form.nama"
                                maxlength="150"
                                required
                                class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                            /><span
                                class="mt-1 block text-right text-[10px] text-slate-400"
                                >{{ form.nama.length }} / 150</span
                            ></label
                        >
                        <p
                            v-if="form.errors.nama"
                            class="-mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.nama }}
                        </p>
                        <label class="block text-xs font-bold text-slate-700"
                            >Deskripsi Singkat *<textarea
                                v-model="form.deskripsi_singkat"
                                maxlength="250"
                                rows="4"
                                required
                                class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                            ></textarea
                            ><span
                                class="mt-1 block text-right text-[10px] text-slate-400"
                                >{{ form.deskripsi_singkat.length }} / 250</span
                            ></label
                        >
                        <p
                            v-if="form.errors.deskripsi_singkat"
                            class="-mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.deskripsi_singkat }}
                        </p>
                        <label class="block text-xs font-bold text-slate-700"
                            >Deskripsi Lengkap<textarea
                                v-model="form.deskripsi_lengkap"
                                maxlength="2000"
                                rows="6"
                                class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                            ></textarea
                            ><span
                                class="mt-1 block text-right text-[10px] text-slate-400"
                                >{{ form.deskripsi_lengkap.length }} /
                                2000</span
                            ></label
                        >
                        <p
                            v-if="form.errors.deskripsi_lengkap"
                            class="-mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.deskripsi_lengkap }}
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2 sm:items-end">
                            <label
                                class="block text-xs font-bold text-slate-700"
                                >Urutan Tampil *<input
                                    v-model.number="form.urutan_tampil"
                                    type="number"
                                    min="0"
                                    max="999"
                                    required
                                    class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm" /></label
                            ><label
                                class="flex h-10 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700"
                                ><input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-[#1769e0]"
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
                    <div class="space-y-5">
                        <div>
                            <label
                                class="block text-xs font-bold text-slate-700"
                                >Thumbnail {{ modal.item ? "" : "*"
                                }}<input
                                    :key="`thumb-${fileKey}`"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    :required="!modal.item"
                                    class="mt-2 block w-full rounded-lg border border-slate-300 p-2 text-xs file:mr-2 file:rounded file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-bold file:text-[#0756d8]"
                                    @change="selectImage('thumbnail', $event)"
                            /></label>
                            <p
                                v-if="form.errors.thumbnail"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.thumbnail }}
                            </p>
                            <img
                                v-if="currentThumbnail"
                                :src="currentThumbnail"
                                alt="Preview thumbnail Produk"
                                class="mx-auto mt-3 aspect-[4/5] w-32 rounded-lg border border-slate-200 object-cover"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-slate-700"
                                >Hero Image {{ modal.item ? "" : "*"
                                }}<input
                                    :key="`hero-${fileKey}`"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    :required="!modal.item"
                                    class="mt-2 block w-full rounded-lg border border-slate-300 p-2 text-xs file:mr-2 file:rounded file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-bold file:text-[#0756d8]"
                                    @change="selectImage('hero_image', $event)"
                            /></label>
                            <p
                                v-if="form.errors.hero_image"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.hero_image }}
                            </p>
                            <img
                                v-if="currentHero"
                                :src="currentHero"
                                alt="Preview Hero Image Produk"
                                class="mt-3 aspect-[16/7] w-full rounded-lg border border-slate-200 object-contain"
                            />
                        </div>
                        <p class="text-[10px] leading-4 text-slate-500">
                            JPG, JPEG, PNG, atau WebP • Maks. 20 MB per file • Thumbnail 1200 × 1500 px (4:5), Hero Image 1600 × 700 px (16:7).
                        </p>
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
                        {{ form.processing ? "Menyimpan..." : "Simpan Produk" }}
                    </button>
                </footer>
            </form>
        </div>
    </div>
</template>
