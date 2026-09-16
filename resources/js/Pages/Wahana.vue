<script setup>
import { computed, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import PublicLayout from "../Layouts/PublicLayout.vue";
import BaseModal from "../Components/Base/BaseModal.vue";
import LazyImage from "../Components/Base/LazyImage.vue";
import DiningPlaceShowcase from "../Components/Wahana/DiningPlaceShowcase.vue";

const props = defineProps({
    categories: Array,
    photos: Array,
    diningPlaces: { type: Array, default: () => [] },
    wahanaFallbackEnabled: Boolean,
});
const activeMode = ref("wahana");
const selected = ref([]);
const applied = ref([]);
const selectedDiningCategory = ref("semua");
const appliedDiningCategory = ref("semua");
const preview = ref(null);
const imageIndexes = ref({});
const diningImageIndexes = ref({});

const diningCategories = [
    { label: "Semua", value: "semua" },
    { label: "Resto", value: "resto" },
    { label: "Café", value: "cafe" },
    { label: "Saung", value: "saung" },
    { label: "Minuman", value: "minuman" },
    { label: "Camilan", value: "camilan" },
];

const fallbackPhotos = [
    {
        id: "waterpark",
        title: "Waterpark",
        description:
            "Nikmati keseruan bermain air bersama keluarga di kolam luas dengan berbagai perosotan seru.",
        photos: [
            { id: "waterpark-1", url: "/assets/temporary/ride-waterpark.png" },
        ],
        labels: [
            { id: "air", name: "Air", slug: "air" },
            { id: "anak", name: "Anak-anak", slug: "anak-anak" },
        ],
    },
    {
        id: "flying-fox",
        title: "Flying Fox",
        description:
            "Rasakan sensasi meluncur dari ketinggian melintasi pepohonan hijau yang menyegarkan.",
        photos: [
            {
                id: "flying-fox-1",
                url: "/assets/temporary/ride-flying-fox.png",
            },
        ],
        labels: [
            { id: "darat", name: "Darat", slug: "darat" },
            { id: "adrenaline", name: "Adrenaline", slug: "adrenaline" },
        ],
    },
    {
        id: "go-kart",
        title: "Go Kart",
        description:
            "Uji adrenalin dan kemampuan mengemudi Anda di sirkuit Go Kart menantang kami.",
        photos: [
            { id: "go-kart-1", url: "/assets/temporary/ride-go-kart.png" },
        ],
        labels: [
            { id: "darat2", name: "Darat", slug: "darat" },
            { id: "dewasa", name: "Dewasa", slug: "dewasa" },
        ],
    },
    {
        id: "perahu",
        title: "Perahu Bebek",
        description:
            "Bersantai mengelilingi danau buatan dengan perahu bebek kayuh bersama pasangan atau teman.",
        photos: [
            { id: "perahu-1", url: "/assets/temporary/ride-perahu-bebek.png" },
        ],
        labels: [
            { id: "air2", name: "Air", slug: "air" },
            { id: "dewasa2", name: "Dewasa", slug: "dewasa" },
        ],
    },
    {
        id: "carousel",
        title: "Carousel",
        description:
            "Wahana klasik komidi putar yang selalu menjadi favorit anak-anak dengan iringan musik ceria.",
        photos: [
            { id: "carousel-1", url: "/assets/temporary/ride-carousel.png" },
        ],
        labels: [
            { id: "darat3", name: "Darat", slug: "darat" },
            { id: "anak2", name: "Anak-anak", slug: "anak-anak" },
        ],
    },
];

const allPhotos = computed(() =>
    props.photos?.length
        ? props.photos
        : props.wahanaFallbackEnabled
          ? fallbackPhotos
          : [],
);
const availableLabels = computed(() => {
    const labels =
        props.categories?.flatMap((category) => category.labels || []) || [];
    return labels.length
        ? labels
        : [
              { id: "anak", name: "Anak-anak", slug: "anak-anak" },
              { id: "dewasa", name: "Dewasa", slug: "dewasa" },
              { id: "air", name: "Air", slug: "air" },
              { id: "darat", name: "Darat", slug: "darat" },
              { id: "adrenaline", name: "Adrenaline", slug: "adrenaline" },
              { id: "santai", name: "Santai", slug: "santai" },
          ];
});
const toggle = (slug) => {
    selected.value = selected.value.includes(slug)
        ? selected.value.filter((item) => item !== slug)
        : [...selected.value, slug];
};
const results = computed(() =>
    allPhotos.value.filter((photo) =>
        applied.value.every((slug) =>
            photo.labels.some((label) => label.slug === slug),
        ),
    ),
);
const apply = () => {
    applied.value = [...selected.value];
};
const reset = () => {
    selected.value = [];
    applied.value = [];
};
const diningResults = computed(() =>
    props.diningPlaces.filter((place) => {
        if (appliedDiningCategory.value === "semua") return true;
        return (
            String(place.kategori || "")
                .toLowerCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "") === appliedDiningCategory.value
        );
    }),
);
const applyDiningFilter = () => {
    appliedDiningCategory.value = selectedDiningCategory.value;
};
const resetDiningFilter = () => {
    selectedDiningCategory.value = "semua";
    appliedDiningCategory.value = "semua";
};
const diningActiveIndex = (place) => {
    const total = place?.photos?.length || 1;
    return (diningImageIndexes.value[place?.id] || 0) % total;
};
const selectDiningPhoto = (place, index) => {
    diningImageIndexes.value = {
        ...diningImageIndexes.value,
        [place.id]: index,
    };
};
const changeDiningPhoto = (place, direction) => {
    const total = place?.photos?.length || 0;
    if (total <= 1) return;
    selectDiningPhoto(
        place,
        (diningActiveIndex(place) + direction + total) % total,
    );
};
const badgeTone = (name) =>
    name === "Air"
        ? "bg-[#e0f2fe] text-[#0369a1]"
        : name === "Adrenaline"
          ? "bg-[#fce7f3] text-[#be185d]"
          : name === "Darat"
            ? "bg-[#dcfce7] text-[#15803d]"
            : "bg-[#fef9c3] text-[#a16207]";
const activeImageIndex = (photo) => {
    const total = photo?.photos?.length || 1;
    return (imageIndexes.value[photo?.id] || 0) % total;
};
const activePhoto = (photo) => photo?.photos?.[activeImageIndex(photo)] || null;
const changePhoto = (photo, direction) => {
    const total = photo.photos?.length || 0;
    if (total <= 1) return;

    imageIndexes.value = {
        ...imageIndexes.value,
        [photo.id]: (activeImageIndex(photo) + direction + total) % total,
    };
};
</script>

<template>
    <Head title="Wahana"
        ><meta
            name="description"
            content="[PLACEHOLDER: Meta description Wahana]"
    /></Head>
    <PublicLayout>
        <main class="bg-[#f7f8fa] px-5 py-16 lg:px-0">
            <div class="mx-auto max-w-[1120px]">
                <header class="text-center">
                    <h1
                        class="font-heading text-4xl font-extrabold tracking-tight text-[#0754c7] lg:text-[48px]"
                    >
                        {{
                            activeMode === "wahana"
                                ? "Wahana Seru Kampoeng Radja"
                                : "Tempat Makan Kampoeng Radja"
                        }}
                    </h1>
                    <p
                        class="mx-auto mt-3 max-w-[520px] text-sm leading-5 text-[#434655]"
                    >
                        {{
                            activeMode === "wahana"
                                ? "Kesenangan Tiada Akhir menanti Anda. Temukan berbagai wahana menarik untuk semua usia."
                                : "Nikmati sajian lezat dalam suasana alami dan nyaman di Kampoeng Radja."
                        }}
                    </p>
                </header>
                <section
                    class="mt-9 rounded-2xl border border-[#e0e3e5] bg-white p-4 shadow-[0_8px_30px_rgba(0,0,0,.04)]"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-4"
                    >
                        <div class="flex rounded-md bg-[#f2f4f6] p-1">
                            <button
                                type="button"
                                :aria-pressed="activeMode === 'wahana'"
                                :class="
                                    activeMode === 'wahana'
                                        ? 'bg-[#0878de] text-white shadow-sm'
                                        : 'text-[#434655]'
                                "
                                class="rounded-md px-5 py-2 text-[10px] font-bold"
                                @click="activeMode = 'wahana'"
                            >
                                Wahana
                            </button>
                            <button
                                type="button"
                                :aria-pressed="activeMode === 'tempat-makan'"
                                :class="
                                    activeMode === 'tempat-makan'
                                        ? 'bg-[#0878de] text-white shadow-sm'
                                        : 'text-[#434655]'
                                "
                                class="rounded-md px-5 py-2 text-[10px] font-bold"
                                @click="activeMode = 'tempat-makan'"
                            >
                                Tempat Makan
                            </button>
                        </div>
                        <div class="flex gap-3">
                            <button
                                type="button"
                                class="rounded-full bg-[#0754c7] px-5 py-2 text-[10px] font-bold text-white"
                                @click="
                                    activeMode === 'wahana'
                                        ? apply()
                                        : applyDiningFilter()
                                "
                            >
                                ⌕ Cari</button
                            ><button
                                type="button"
                                class="rounded-full border border-[#c3c6d7] px-5 py-2 text-[10px] text-[#434655]"
                                @click="
                                    activeMode === 'wahana'
                                        ? reset()
                                        : resetDiningFilter()
                                "
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                    <div
                        v-if="activeMode === 'wahana'"
                        class="mt-4 flex flex-wrap gap-2"
                    >
                        <button
                            v-for="label in availableLabels"
                            :key="label.id"
                            type="button"
                            :aria-pressed="selected.includes(label.slug)"
                            :class="
                                selected.includes(label.slug)
                                    ? 'border-[#0754c7] bg-[#2563eb] text-white'
                                    : 'border-[#c3c6d7] bg-white text-[#434655]'
                            "
                            class="rounded-full border px-4 py-1.5 text-[10px] font-medium"
                            @click="toggle(label.slug)"
                        >
                            {{ label.name }}
                        </button>
                    </div>
                    <div v-else class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="category in diningCategories"
                            :key="category.value"
                            type="button"
                            :aria-pressed="
                                selectedDiningCategory === category.value
                            "
                            :class="
                                selectedDiningCategory === category.value
                                    ? 'border-[#ff9d42] bg-[#ff9d42] text-white'
                                    : 'border-[#c3c6d7] bg-white text-[#434655]'
                            "
                            class="rounded-full border px-4 py-1.5 text-[10px] font-medium"
                            @click="selectedDiningCategory = category.value"
                        >
                            {{ category.label }}
                        </button>
                    </div>
                </section>
                <p
                    v-if="activeMode === 'wahana'"
                    class="mt-4 text-xs text-[#737686]"
                >
                    Filter menerapkan semua label terpilih sekaligus (AND).
                </p>
                <section
                    v-if="activeMode === 'wahana' && results.length"
                    class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <article
                        v-for="photo in results"
                        :key="photo.id"
                        class="overflow-hidden rounded-2xl border border-[#e0e3e5] bg-white text-left shadow-[0_1px_2px_rgba(0,0,0,.05)]"
                    >
                        <div
                            class="relative h-[194px] overflow-hidden bg-slate-100"
                        >
                            <button
                                type="button"
                                class="block h-full w-full"
                                :aria-label="`Lihat detail ${photo.title}`"
                                @click="preview = photo"
                            >
                                <Transition name="ride-photo" mode="out-in">
                                    <LazyImage
                                        :key="activePhoto(photo)?.id"
                                        :src="activePhoto(photo)?.url"
                                        :alt="photo.alt_text || photo.title"
                                        class-name="h-[194px] w-full object-cover"
                                    />
                                </Transition>
                            </button>
                            <template v-if="photo.photos?.length > 1">
                                <button
                                    type="button"
                                    class="ride-photo-nav left-3"
                                    :aria-label="`Foto sebelumnya untuk ${photo.title}`"
                                    @click="changePhoto(photo, -1)"
                                >
                                    ‹
                                </button>
                                <button
                                    type="button"
                                    class="ride-photo-nav right-3"
                                    :aria-label="`Foto berikutnya untuk ${photo.title}`"
                                    @click="changePhoto(photo, 1)"
                                >
                                    ›
                                </button>
                                <span
                                    class="absolute bottom-3 right-3 rounded-full bg-slate-950/65 px-2 py-1 text-[9px] font-bold text-white"
                                    >{{ activeImageIndex(photo) + 1 }} /
                                    {{ photo.photos.length }}</span
                                >
                            </template>
                        </div>
                        <button
                            type="button"
                            class="block w-full p-4 text-left"
                            @click="preview = photo"
                        >
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="label in photo.labels"
                                    :key="label.id"
                                    :class="badgeTone(label.name)"
                                    class="rounded-full px-2 py-1 text-[9px] font-bold"
                                    >{{ label.name }}</span
                                >
                            </div>
                            <h2
                                class="mt-3 font-heading text-xl font-bold text-[#191c1e]"
                            >
                                {{ photo.title }}
                            </h2>
                            <p class="mt-2 text-xs leading-4 text-[#434655]">
                                {{ photo.description }}
                            </p>
                        </button>
                    </article>
                </section>
                <section
                    v-else-if="activeMode === 'wahana'"
                    class="mt-8 rounded-2xl border border-[#e0e3e5] bg-white p-10 text-center"
                >
                    <h2 class="font-heading text-2xl">
                        Belum ada wahana dengan kombinasi filter ini
                    </h2>
                    <p class="mt-2 text-sm text-[#434655]">
                        Coba hapus salah satu label atau tekan Reset.
                    </p>
                </section>

                <section
                    v-else-if="diningResults.length"
                    class="mt-8 space-y-8 lg:space-y-10"
                >
                    <DiningPlaceShowcase
                        v-for="place in diningResults"
                        :key="place.id"
                        :place="place"
                        :active-index="diningActiveIndex(place)"
                        @select-photo="selectDiningPhoto(place, $event)"
                        @previous-photo="changeDiningPhoto(place, -1)"
                        @next-photo="changeDiningPhoto(place, 1)"
                    />
                </section>
                <section
                    v-else
                    class="mt-8 rounded-[24px] border border-[#d7e2ef] bg-white p-10 text-center shadow-[0_12px_30px_rgba(4,69,133,.08)]"
                >
                    <h2 class="font-heading text-2xl font-bold text-[#063b76]">
                        Belum ada tempat makan yang tersedia
                    </h2>
                    <p
                        class="mx-auto mt-2 max-w-[520px] text-sm leading-6 text-[#596273]"
                    >
                        Silakan pilih kategori lain atau kembali lagi setelah
                        informasi tempat makan diperbarui.
                    </p>
                </section>
            </div>
        </main>
        <BaseModal
            :open="Boolean(preview)"
            :title="preview?.title || 'Detail wahana'"
            panel-class="ride-modal-panel max-w-5xl"
            @close="preview = null"
        >
            <div class="ride-modal-grid">
                <div class="ride-modal-media">
                    <LazyImage
                        :src="activePhoto(preview)?.url"
                        :alt="preview?.alt_text || preview?.title || ''"
                        class-name="ride-modal-image"
                    />
                </div>
                <div class="ride-modal-detail">
                    <p class="ride-modal-eyebrow">Detail Wahana</p>
                    <h3 class="ride-modal-title">{{ preview?.title }}</h3>
                    <p class="ride-modal-copy">{{ preview?.description }}</p>
                    <div class="ride-modal-badges">
                        <span
                            v-for="label in preview?.labels || []"
                            :key="label.id"
                            :class="badgeTone(label.name)"
                            class="rounded-full px-3 py-1.5 text-[10px] font-bold"
                            >{{ label.name }}</span
                        >
                    </div>
                </div>
            </div>
        </BaseModal>
    </PublicLayout>
</template>

<style scoped>
.ride-photo-nav {
    position: absolute;
    top: 50%;
    z-index: 2;
    display: grid;
    height: 34px;
    width: 34px;
    transform: translateY(-50%);
    place-items: center;
    border: 1px solid rgba(255, 255, 255, 0.72);
    border-radius: 999px;
    color: #fff;
    background: rgba(3, 43, 84, 0.7);
    font-size: 24px;
    line-height: 1;
    backdrop-filter: blur(4px);
    transition:
        background-color 0.2s ease,
        transform 0.2s ease;
}
.ride-photo-nav:hover {
    background: rgba(7, 84, 199, 0.92);
    transform: translateY(-50%) scale(1.04);
}
.ride-photo-enter-active,
.ride-photo-leave-active {
    transition: opacity 350ms ease;
}
.ride-photo-enter-from,
.ride-photo-leave-to {
    opacity: 0;
}
main {
    min-height: 100vh;
    background:
        radial-gradient(
            circle at 8% 5%,
            rgba(255, 157, 66, 0.16) 0 110px,
            transparent 111px
        ),
        radial-gradient(
            circle at 94% 20%,
            rgba(45, 169, 234, 0.16) 0 150px,
            transparent 151px
        ),
        linear-gradient(180deg, #edf8ff, #fff8ef) !important;
}
main header h1 {
    color: #063b76;
    text-shadow: 0 3px 12px rgba(7, 120, 222, 0.12);
}
main header + section {
    border: 2px solid #fff;
    border-top: 6px solid #ff9d42;
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 18px 38px rgba(4, 69, 133, 0.13);
}
main header + section > div:first-child > div:first-child {
    background: #eaf6ff;
}
main header + section > div:first-child > div:first-child span:first-child {
    color: #fff;
    background: #0878de;
    border-radius: 6px;
}
main header + section > div:first-child > div:last-child button:first-child {
    background: linear-gradient(90deg, #0878de, #2da9ea);
    box-shadow: 0 7px 16px rgba(8, 120, 222, 0.22);
}
main header + section > div:first-child > div:last-child button:last-child {
    border: 2px solid #ff9d42;
    color: #c95600;
    background: #fff8ef;
}
main header + section > div:last-child button {
    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease;
}
main header + section > div:last-child button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(4, 69, 133, 0.12);
}
main > div > section:nth-of-type(2) > article {
    border: 2px solid #fff;
    border-top: 5px solid #2da9ea;
    border-radius: 24px;
    box-shadow: 0 12px 28px rgba(4, 69, 133, 0.12);
    transition:
        transform 0.22s ease,
        box-shadow 0.22s ease;
}
main > div > section:nth-of-type(2) > article:nth-child(even) {
    border-top-color: #ff9d42;
}
main > div > section:nth-of-type(2) > article:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 36px rgba(4, 69, 133, 0.2);
}
main > div > section:nth-of-type(2) > article h2 {
    color: #063b76;
}
@media (max-width: 767px) {
    main {
        padding-top: 44px;
    }
    main > div > section:nth-of-type(2) > article:hover,
    main header + section > div:last-child button:hover {
        transform: none;
    }
}
</style>

<style>
.ride-modal-panel {
    position: relative;
    border: 3px solid #fff;
    border-top: 7px solid #ff9d42;
    background: linear-gradient(145deg, #fff 0%, #f4fbff 64%, #fff4e6 100%);
    box-shadow: 0 30px 80px rgba(0, 49, 98, 0.32);
}
.ride-modal-panel::before {
    position: absolute;
    right: 82px;
    top: 26px;
    width: 58px;
    height: 10px;
    content: "";
    border-radius: 999px;
    background: linear-gradient(90deg, #2da9ea 0 62%, #ff9d42 62%);
}
.ride-modal-panel > div:first-child {
    margin-bottom: 18px;
    padding: 0 2px 14px;
    border-bottom: 1px solid rgba(8, 120, 222, 0.14);
}
.ride-modal-panel > div:first-child h2 {
    color: #063b76;
}
.ride-modal-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.08fr) minmax(280px, 0.92fr);
    gap: 28px;
    align-items: stretch;
}
.ride-modal-media {
    position: relative;
    min-height: 390px;
    overflow: hidden;
    border: 4px solid #fff;
    border-radius: 24px;
    background: #dff3ff;
    box-shadow: 0 16px 32px rgba(4, 69, 133, 0.16);
}
.ride-modal-media::after {
    position: absolute;
    inset: auto 18px 18px auto;
    width: 52px;
    height: 52px;
    content: "★";
    display: grid;
    place-items: center;
    border-radius: 50%;
    color: #a84300;
    background: #ffca58;
    box-shadow: 0 8px 20px rgba(255, 157, 66, 0.32);
}
.ride-modal-image {
    height: 100%;
    min-height: 390px;
    width: 100%;
    object-fit: cover;
}
.ride-modal-detail {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 18px 10px 18px 0;
}
.ride-modal-eyebrow {
    width: max-content;
    border-radius: 999px;
    padding: 7px 13px;
    color: #07549a;
    background: #dff3ff;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}
.ride-modal-title {
    margin-top: 18px;
    color: #063b76;
    font-family: Poppins, sans-serif;
    font-size: 30px;
    font-weight: 800;
    line-height: 1.15;
}
.ride-modal-copy {
    margin-top: 14px;
    color: #434655;
    font-size: 14px;
    line-height: 1.8;
}
.ride-modal-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 24px;
}
.ride-modal-badges span {
    border: 1px solid rgba(8, 120, 222, 0.15);
    box-shadow: 0 5px 12px rgba(4, 69, 133, 0.07);
}
@media (max-width: 767px) {
    .ride-modal-panel {
        padding: 16px;
        border-radius: 24px;
    }
    .ride-modal-panel::before {
        display: none;
    }
    .ride-modal-grid {
        grid-template-columns: 1fr;
        gap: 18px;
    }
    .ride-modal-media,
    .ride-modal-image {
        min-height: 0;
        height: 230px;
    }
    .ride-modal-media {
        border-radius: 18px;
    }
    .ride-modal-media::after {
        width: 42px;
        height: 42px;
        right: 12px;
        bottom: 12px;
    }
    .ride-modal-detail {
        padding: 0 2px 4px;
    }
    .ride-modal-title {
        margin-top: 12px;
        font-size: 25px;
    }
    .ride-modal-copy {
        margin-top: 10px;
        line-height: 1.65;
    }
    .ride-modal-badges {
        margin-top: 16px;
    }
}
</style>
