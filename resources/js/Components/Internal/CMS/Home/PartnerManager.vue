<script setup>
import { router, useForm } from "@inertiajs/vue3";
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { useConfirmation } from "@/composables/useConfirmation";
import { CMS_UPLOAD_LIMITS, validateUploadFile } from "@/utils/cmsUploadValidation";

defineProps({ partners: { type: Array, required: true } });
const modal = ref(null);
const nameInput = ref(null);
const localPreview = ref(null);
const fileKey = ref(0);
const form = useForm({
    nama_brand: "",
    logo: null,
    is_active: true,
    urutan_tampil: 0,
});
const preview = computed(
    () => localPreview.value || modal.value?.item?.logo_url || null,
);
const revoke = () => {
    if (localPreview.value) URL.revokeObjectURL(localPreview.value);
    localPreview.value = null;
};
const openModal = async (item = null) => {
    revoke();
    form.reset();
    form.clearErrors();
    fileKey.value += 1;
    modal.value = { item };
    if (item)
        Object.assign(form, {
            nama_brand: item.nama_brand,
            is_active: item.is_active,
            urutan_tampil: item.urutan_tampil,
            logo: null,
        });
    await nextTick();
    nameInput.value?.focus();
};
const closeModal = () => {
    modal.value = null;
    revoke();
    form.reset();
    form.clearErrors();
};
const selectLogo = (event) => {
    const [file] = event.target.files;
    form.clearErrors('logo');
    const error = validateUploadFile(file, CMS_UPLOAD_LIMITS.partner.file, 'logo Mitra');
    if (error) { event.target.value = ''; form.logo = null; form.setError('logo', error); return; }
    revoke();
    form.logo = file || null;
    localPreview.value = file ? URL.createObjectURL(file) : null;
};
const { confirm } = useConfirmation();
const submit = async () => {
    const item = modal.value?.item;
    const ok = await confirm({
        type: item ? "edit" : "save",
        title: item ? "Edit Mitra" : "Simpan Mitra",
        message: "Apakah Anda yakin ingin menyimpan data Mitra ini?",
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
                ? "dashboard.cms.home.partners.update"
                : "dashboard.cms.home.partners.store",
            item?.id,
        ),
        { forceFormData: true, preserveScroll: true, onSuccess: closeModal },
    );
};
const confirmDelete = async (item) => {
    const ok = await confirm({
        type: "delete",
        title: "Hapus Mitra",
        message: `Apakah Anda yakin ingin menghapus Mitra “${item.nama_brand}”?`,
        description: "Logo Mitra tidak akan tampil lagi pada Beranda.",
        confirmText: "Ya, Hapus",
    });
    if (ok)
        router.delete(route("dashboard.cms.home.partners.destroy", item.id), {
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
                <h3 class="text-sm font-bold text-[#172554]">Mitra</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Kelola logo Mitra yang ditampilkan pada Beranda.
                </p>
            </div>
            <button
                type="button"
                class="rounded-lg bg-[#1769e0] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#0756ba]"
                @click="openModal()"
            >
                + Tambah Mitra
            </button>
        </div>
        <div v-if="partners.length" class="mt-4 grid gap-3 md:grid-cols-2">
            <article
                v-for="item in partners"
                :key="item.id"
                class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-3 sm:grid-cols-[88px_minmax(0,1fr)]"
            >
                <div
                    class="grid h-20 w-[88px] place-items-center rounded-lg border border-slate-200 bg-white p-2"
                >
                    <img
                        :src="item.logo_url"
                        :alt="item.nama_brand"
                        class="max-h-full max-w-full object-contain"
                    />
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h4 class="truncate text-sm font-bold text-[#172554]">
                            {{ item.nama_brand }}
                        </h4>
                        <span
                            :class="
                                item.is_active
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-slate-200 text-slate-600'
                            "
                            class="rounded-full px-2 py-1 text-[9px] font-extrabold uppercase"
                            >{{ item.is_active ? "Aktif" : "Nonaktif" }}</span
                        >
                    </div>
                    <p class="mt-1 text-[10px] font-semibold text-slate-400">
                        Urutan tampil: {{ item.urutan_tampil }}
                    </p>
                    <div class="mt-3 flex gap-2">
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
                </div>
            </article>
        </div>
        <div
            v-else
            class="mt-4 rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center"
        >
            <h4 class="text-sm font-bold text-slate-700">Belum ada Mitra</h4>
            <p class="mt-1 text-xs text-slate-500">
                Tambahkan logo Mitra pertama untuk Beranda.
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
                class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl"
                @submit.prevent="submit"
            >
                <header
                    class="flex justify-between border-b border-slate-200 px-5 py-4"
                >
                    <div>
                        <h3 class="text-lg font-bold text-[#172554]">
                            {{ modal.item ? "Edit Mitra" : "Tambah Mitra" }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Lengkapi identitas brand dan urutan tampil.
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
                    class="grid gap-5 p-5 sm:p-6 sm:grid-cols-[minmax(0,1fr)_220px]"
                >
                    <div class="space-y-4">
                        <label class="block text-xs font-bold text-slate-700"
                            >Nama Brand *<input
                                ref="nameInput"
                                v-model="form.nama_brand"
                                maxlength="100"
                                required
                                class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm focus:border-[#1769e0] focus:ring-[#1769e0]"
                            /><span
                                class="mt-1 block text-right text-[10px] text-slate-400"
                                >{{ form.nama_brand.length }} / 100</span
                            ></label
                        >
                        <p
                            v-if="form.errors.nama_brand"
                            class="-mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.nama_brand }}
                        </p>
                        <label class="block text-xs font-bold text-slate-700"
                            >Urutan Tampil *<input
                                v-model.number="form.urutan_tampil"
                                type="number"
                                min="0"
                                max="999"
                                required
                                class="mt-2 h-10 w-full rounded-lg border-slate-300 text-sm"
                        /></label>
                        <p
                            v-if="form.errors.urutan_tampil"
                            class="-mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.urutan_tampil }}
                        </p>
                        <label
                            class="flex h-10 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700"
                            ><input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-slate-300 text-[#1769e0]"
                            />
                            Status Aktif</label
                        >
                        <p
                            v-if="form.errors.is_active"
                            class="-mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.is_active }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700"
                            >Logo {{ modal.item ? "" : "*"
                            }}<input
                                :key="fileKey"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                :required="!modal.item"
                                class="mt-2 block w-full rounded-lg border border-slate-300 p-2 text-xs file:mr-2 file:rounded file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-bold file:text-[#0756d8]"
                                @change="selectLogo"
                        /></label>
                        <p class="mt-2 text-[10px] text-slate-500">
                            JPG, JPEG, PNG, atau WebP • Maks. 5 MB per logo • Disarankan sekitar 800 × 800 px atau lebih, pertahankan rasio asli. PNG/WebP transparan didukung.
                        </p>
                        <p
                            v-if="form.errors.logo"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.logo }}
                        </p>
                        <div
                            class="mt-4 grid aspect-[4/3] place-items-center rounded-xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <img
                                v-if="preview"
                                :src="preview"
                                alt="Preview logo Mitra"
                                class="max-h-full max-w-full object-contain"
                            /><span v-else class="text-xs text-slate-400"
                                >Preview logo</span
                            >
                        </div>
                    </div>
                </div>
                <footer
                    class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4"
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
                        {{ form.processing ? "Menyimpan..." : "Simpan Mitra" }}
                    </button>
                </footer>
            </form>
        </div>
    </div>
</template>
