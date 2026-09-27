<script setup>
import { useForm } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, ref } from "vue";
import { useConfirmation } from "@/composables/useConfirmation";
import { CMS_UPLOAD_LIMITS, validateUploadFile } from "@/utils/cmsUploadValidation";

const props = defineProps({
    hero: { type: Object, default: null },
});
const { confirm } = useConfirmation();

const videoPreview = ref(null);
const videoInput = ref(null);

const form = useForm({
    video: null,
});

const activeVideoUrl = computed(
    () => videoPreview.value ?? props.hero?.video_url ?? null,
);

const revokePreview = (preview) => {
    if (preview.value?.startsWith("blob:")) URL.revokeObjectURL(preview.value);
    preview.value = null;
};

const selectVideo = (event) => {
    form.clearErrors('video');
    const file = event.target.files?.[0] ?? null;
    const error = validateUploadFile(file, CMS_UPLOAD_LIMITS.hero.file, 'video Hero');
    if (error) {
        event.target.value = '';
        form.video = null;
        form.setError('video', error);
        return;
    }
    revokePreview(videoPreview);
    form.video = file;
    if (form.video) videoPreview.value = URL.createObjectURL(form.video);
};

const submit = async () => {
    const confirmed = await confirm({
        type: "save",
        title: "Simpan Hero Beranda",
        message: "Apakah Anda yakin ingin menyimpan perubahan video Hero?",
        confirmText: "Ya, Simpan",
    });
    if (!confirmed) return;
    form.transform((data) => ({ ...data, _method: "patch" })).post(
        route("dashboard.cms.home.hero.update"),
        {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                revokePreview(videoPreview);
                form.video = null;
                if (videoInput.value) videoInput.value.value = "";
            },
            onFinish: () => form.transform((data) => data),
        },
    );
};

onBeforeUnmount(() => {
    revokePreview(videoPreview);
});
</script>

<template>
    <form class="space-y-6 p-4 sm:p-5" @submit.prevent="submit">
        <div>
            <h3 class="text-sm font-bold text-[#172554]">Hero Beranda</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Kelola video utama pada halaman Beranda.
            </p>
        </div>

        <div class="max-w-3xl">
            <section
                class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
            >
                <div class="border-b border-slate-200 bg-white px-4 py-3">
                    <p
                        class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#1769e0]"
                    >
                        Video Hero
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                            MP4 atau WebM • Maks. ukuran file: 100 MB • Disarankan video landscape 1920 × 1080 px (16:9).
                    </p>
                </div>
                <div class="p-4">
                    <div
                        class="aspect-video overflow-hidden rounded-lg border border-slate-200 bg-slate-900"
                    >
                        <video
                            v-if="activeVideoUrl"
                            :key="activeVideoUrl"
                            :src="activeVideoUrl"
                            controls
                            preload="metadata"
                            class="h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="grid h-full place-items-center px-5 text-center text-xs leading-5 text-slate-300"
                        >
                            Belum ada video Hero.
                        </div>
                    </div>
                    <label
                        class="mt-3 inline-flex h-9 cursor-pointer items-center justify-center rounded-lg border border-blue-200 bg-white px-4 text-xs font-bold text-[#0756d8] transition hover:bg-blue-50 focus-within:ring-2 focus-within:ring-blue-200"
                    >
                        {{ activeVideoUrl ? "Ganti Video" : "Pilih Video" }}
                        <input
                            ref="videoInput"
                            class="sr-only"
                            type="file"
                            accept="video/mp4,video/webm"
                            @change="selectVideo"
                        />
                    </label>
                    <p
                        v-if="form.video"
                        class="mt-2 truncate text-xs text-slate-500"
                    >
                        Dipilih: {{ form.video.name }}
                    </p>
                    <p
                        v-if="form.errors.video"
                        class="mt-2 text-xs font-medium text-red-600"
                    >
                        {{ form.errors.video }}
                    </p>
                </div>
            </section>
        </div>

        <div class="flex justify-end border-t border-slate-200 pt-5">
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex h-10 items-center justify-center rounded-lg bg-[#0756d8] px-5 text-sm font-bold text-white transition hover:bg-[#0648b5] disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{ form.processing ? "Menyimpan..." : "Simpan Perubahan" }}
            </button>
        </div>
    </form>
</template>
