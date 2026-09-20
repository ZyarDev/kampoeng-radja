<script setup>
import { Head, Link } from "@inertiajs/vue3";
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import PublicLayout from "../Layouts/PublicLayout.vue";
import LazyImage from "../Components/Base/LazyImage.vue";
import ProductShowcase from "../Components/Home/ProductShowcase.vue";

const props = defineProps({
    hero: Object,
    news: Array,
    promotions: Array,
    promotionFallbackEnabled: Boolean,
    products: Array,
    partners: Array,
    featuredRides: Array,
    featuredRideFallbackEnabled: Boolean,
});

const heroVideoUrl = computed(() => props.hero?.video_url ?? null);
const heroPosterUrl = computed(() => props.hero?.poster_url ?? null);
const deferredHeroVideoUrl = ref(null);
const heroVideo = ref(null);
const heroVideoVisible = ref(false);
let heroIdleHandle = null;
let heroFallbackTimer = null;
let mounted = false;

const cancelHeroSchedule = () => {
    if (heroIdleHandle !== null && "cancelIdleCallback" in window)
        window.cancelIdleCallback(heroIdleHandle);
    if (heroFallbackTimer !== null) window.clearTimeout(heroFallbackTimer);
    heroIdleHandle = null;
    heroFallbackTimer = null;
};
const activateHeroVideo = async () => {
    if (!heroVideoUrl.value || heroVideoFailed.value) return;
    deferredHeroVideoUrl.value = heroVideoUrl.value;
    await nextTick();
    heroVideo.value?.load();
};
const scheduleHeroVideo = () => {
    cancelHeroSchedule();
    if (!heroVideoUrl.value) return;
    if ("requestIdleCallback" in window) {
        heroIdleHandle = window.requestIdleCallback(activateHeroVideo, {
            timeout: 1200,
        });
    } else {
        heroFallbackTimer = window.setTimeout(activateHeroVideo, 350);
    }
};

const heroVideoFailed = ref(false);
const onHeroVideoError = () => {
    heroVideoFailed.value = true;
    heroVideoVisible.value = false;
};
const onHeroVideoCanPlay = () => {
    heroVideoVisible.value = true;
    heroVideo.value?.play().catch(() => {});
};
watch(
    () => props.hero?.video_url,
    () => {
        deferredHeroVideoUrl.value = null;
        heroVideoFailed.value = false;
        heroVideoVisible.value = false;
        if (mounted) scheduleHeroVideo();
    },
);
const newsSection = ref(null);
const promoSection = ref(null);
const ridesSection = ref(null);
const partnersSection = ref(null);
const mapSection = ref(null);
const newsMediaReady = ref(false);
const promoMediaReady = ref(false);
const ridesMediaReady = ref(false);
const partnersMediaReady = ref(false);
const mapReady = ref(false);
const sectionObservers = [];
const observeSection = (element, ready) => {
    if (!element || ready.value) return;
    if (!("IntersectionObserver" in window)) {
        ready.value = true;
        return;
    }
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (!entry.isIntersecting) return;
            ready.value = true;
            observer.disconnect();
        },
        { rootMargin: "350px 0px" },
    );
    observer.observe(element);
    sectionObservers.push(observer);
};
const expandedNews = ref(null);
const selectedPromo = ref(null);
const newsCards = computed(() =>
    (props.news ?? []).map((item) => ({
        foto_url: item.foto_url,
        category: "Berita",
        categoryClass: "bg-[#0052a5]",
        title: item.title,
        excerpt:
            item.description.length > 150
                ? `${item.description.slice(0, 147)}...`
                : item.description,
        detail: item.description,
    })),
);
const fallbackPromoCards = [
    {
        poster_url: "/assets/promotions/promo-berempat.jpeg",
        title: "Promo Main Ber-4",
        period: "18–31 Agustus 2026",
        summary:
            "Promo Main Ber-4 cuma Rp100.000 untuk 4 orang. Sudah termasuk 4 wahana: Flying Pirate, Kiddy Land, Flying Fox, dan Outbound.",
        detail: "Promo Main Ber-4 cuma Rp100.000 untuk 4 orang. Sudah termasuk Flying Pirate, Kiddy Land, Flying Fox, dan Outbound.",
        link_wa: null,
    },
    {
        poster_url: "/assets/promotions/promo-agus.jpeg",
        title: "Promo Spesial Lahir Bulan Agustus",
        period: "Agustus 2026",
        summary:
            "Khusus kamu yang lahir di bulan Agustus, cukup Rp20.000 untuk menikmati Outbound dan Ombang Ambing.",
        detail: "Khusus pengunjung yang lahir pada bulan Agustus. Tunjukkan identitas saat pembelian tiket dan ikuti media sosial Kampoeng Radja.",
        link_wa: null,
    },
    {
        poster_url: "/assets/promotions/promo-honda.jpeg",
        title: "Promo Pengguna Motor Honda",
        period: "Hingga 31 Agustus 2026",
        summary:
            "Datang menggunakan motor Honda dan follow media sosial Kampoeng Radja untuk menikmati promo spesial bermain hingga 31 Agustus 2026.",
        detail: "Datang menggunakan motor Honda dan ikuti media sosial Kampoeng Radja untuk menikmati promo spesial selama periode yang berlaku.",
        link_wa: null,
    },
];
const currentPromoIndex = ref(0);
const promoViewport = ref(null);
const promoTrack = ref(null);
const promoStep = ref(0);
const promoTranslateX = ref(0);
const promoDirection = ref(0);
const isPromoAnimating = ref(false);
const isPromoTransitioning = ref(false);
const isPromoReady = ref(false);
let promoResizeFrame = null;
const promoCards = computed(() => {
    const cmsPromoCards = (props.promotions ?? []).map((promo) => ({
        id: `cms-${promo.id}`,
        poster_url: promo.poster_url,
        title: promo.title,
        period: promo.period ?? "",
        summary: promo.description ?? "",
        detail: promo.detail ?? promo.description ?? "",
        link_wa: promo.link_wa,
    }));

    if (cmsPromoCards.length) return cmsPromoCards;

    return props.promotionFallbackEnabled ? fallbackPromoCards : [];
});
const visualPromoCards = computed(() => {
    const cards = promoCards.value;

    if (!cards.length) return [];
    if (cards.length === 1) return [{ ...cards[0], visualKey: "promo-slot-0" }];

    return Array.from(
        { length: Math.min(3, cards.length) + 2 },
        (_, slotIndex) => {
            const cardIndex =
                (currentPromoIndex.value + slotIndex - 1 + cards.length) %
                cards.length;

            return {
                ...cards[cardIndex],
                visualKey: `promo-slot-${slotIndex}`,
            };
        },
    );
});
const promoTrackStyle = computed(() => ({
    transform: `translate3d(${promoTranslateX.value}px, 0, 0)`,
}));
const measurePromoStep = async () => {
    await nextTick();

    const slides = promoTrack.value?.children;

    if (!slides?.length || promoCards.value.length <= 1) {
        promoStep.value = 0;
        promoTranslateX.value = 0;
        isPromoReady.value = true;
        return;
    }

    promoStep.value =
        slides.length > 1
            ? slides[1].offsetLeft - slides[0].offsetLeft
            : slides[0].getBoundingClientRect().width;
    promoTranslateX.value = -promoStep.value;
    isPromoReady.value = true;
};
const movePromo = async (direction) => {
    const total = promoCards.value.length;

    if (total <= 1 || isPromoAnimating.value) return;

    if (!promoStep.value) await measurePromoStep();

    promoDirection.value = direction;
    isPromoAnimating.value = true;
    isPromoTransitioning.value = true;

    await nextTick();

    window.requestAnimationFrame(() => {
        promoTranslateX.value = direction > 0 ? -promoStep.value * 2 : 0;
    });
};
const finishPromoTransition = async (event) => {
    if (
        !isPromoAnimating.value ||
        event.propertyName !== "transform" ||
        event.target !== promoTrack.value
    )
        return;

    const total = promoCards.value.length;
    isPromoTransitioning.value = false;
    currentPromoIndex.value =
        (currentPromoIndex.value + promoDirection.value + total) % total;
    promoTranslateX.value = -promoStep.value;

    await nextTick();

    promoDirection.value = 0;
    isPromoAnimating.value = false;
};
const handlePromoResize = () => {
    if (isPromoAnimating.value) return;

    if (promoResizeFrame) window.cancelAnimationFrame(promoResizeFrame);

    promoResizeFrame = window.requestAnimationFrame(() => {
        measurePromoStep();
    });
};
const openPromoDetail = (promo) => {
    selectedPromo.value = promo;
};
const closePromoDetail = () => {
    selectedPromo.value = null;
};
const handlePromoModalKeydown = (event) => {
    if (event.key === "Escape" && selectedPromo.value) closePromoDetail();
};

watch(selectedPromo, (promo) => {
    document.body.style.overflow = promo ? "hidden" : "";
});

onMounted(() => {
    mounted = true;
    scheduleHeroVideo();
    observeSection(newsSection.value, newsMediaReady);
    observeSection(promoSection.value, promoMediaReady);
    observeSection(ridesSection.value, ridesMediaReady);
    observeSection(partnersSection.value, partnersMediaReady);
    observeSection(mapSection.value, mapReady);
    measurePromoStep();
    window.addEventListener("resize", handlePromoResize);
    window.addEventListener("keydown", handlePromoModalKeydown);
});

onBeforeUnmount(() => {
    mounted = false;
    cancelHeroSchedule();
    sectionObservers.forEach((observer) => observer.disconnect());
    window.removeEventListener("resize", handlePromoResize);
    window.removeEventListener("keydown", handlePromoModalKeydown);
    document.body.style.overflow = "";
    if (promoResizeFrame) window.cancelAnimationFrame(promoResizeFrame);
});
const featuredFallback = [
    {
        title: "Waterpark",
        description:
            "Kolam renang luas dengan berbagai jenis perosotan seru untuk anak-anak dan dewasa.",
        image: "/assets/temporary/hero-waterpark-v2.png",
        label: "Keluarga",
        tone: "bg-[#fce7f3] text-[#be185d]",
    },
    {
        title: "Flying Fox",
        description:
            "Rasakan sensasi meluncur dari ketinggian melintasi area taman yang hijau dan asri.",
        image: "/assets/figma/figma-news-1.png",
        label: "Adrenalin",
        tone: "bg-[#fee2e2] text-[#b91c1c]",
    },
    {
        title: "Go Kart",
        description:
            "Pacu kecepatanmu di sirkuit mini yang dirancang aman untuk pengalaman balapan yang seru.",
        image: "/assets/figma/figma-news-3.png",
        label: "Populer",
        tone: "bg-[#fef9c3] text-[#854d0e]",
    },
];
const featuredCards = computed(() =>
    props.featuredRides?.length
        ? props.featuredRides.map((ride) => ({
              title: ride.title || "[PERLU KONTEN RESMI: Wahana]",
              description:
                  ride.description || "[PERLU KONTEN RESMI: Deskripsi wahana]",
              image: ride.cover_url,
              label: ride.labels?.[0]?.name || "Wahana",
              tone: "bg-[#e0f2fe] text-[#0369a1]",
          }))
        : props.featuredRideFallbackEnabled
          ? featuredFallback
          : [],
);
</script>

<template>
    <Head title="Kampoeng Radja"
        ><meta
            name="description"
            content="[PLACEHOLDER: Deskripsi resmi Kampoeng Radja]"
    /></Head>
    <PublicLayout>
        <section
            class="home-hero relative z-10 min-h-[620px] overflow-hidden bg-[#304755] sm:min-h-[680px] lg:h-[720px]"
        >
            <img
                v-if="heroPosterUrl"
                :src="heroPosterUrl"
                alt=""
                aria-hidden="true"
                class="absolute inset-0 h-full w-full object-cover"
                decoding="async"
                fetchpriority="high"
            />
            <video
                v-if="deferredHeroVideoUrl && !heroVideoFailed"
                ref="heroVideo"
                :key="deferredHeroVideoUrl"
                :src="deferredHeroVideoUrl"
                autoplay
                muted
                loop
                playsinline
                preload="none"
                :class="heroVideoVisible ? 'opacity-100' : 'opacity-0'"
                class="absolute inset-0 h-full w-full object-cover transition-opacity duration-300"
                @canplay="onHeroVideoCanPlay"
                @error="onHeroVideoError"
            />
        </section>

        <section
            ref="newsSection"
            class="bg-[#f8f9fa] px-5 pb-20 pt-32 lg:px-0"
        >
            <div class="mx-auto max-w-[1120px]">
                <h2 class="font-heading text-[28px] font-bold text-[#062a59]">
                    Media & Berita
                </h2>
                <p class="mt-2 text-sm text-[#4b5563]">
                    Informasi terbaru seputar Kampoeng Radja.
                </p>
                <div
                    v-if="newsCards.length"
                    class="mt-5 grid items-start gap-5 md:grid-cols-3"
                >
                    <article
                        v-for="(newsItem, index) in newsCards"
                        :key="newsItem.title"
                        class="overflow-hidden rounded-xl border border-[#e1e2eb] bg-white"
                    >
                        <div class="relative">
                            <LazyImage
                                :src="newsItem.foto_url"
                                :active="newsMediaReady"
                                :alt="newsItem.title"
                                class-name="h-[198px] w-full object-cover"
                            /><span
                                :class="newsItem.categoryClass"
                                class="absolute left-3 top-3 rounded-full px-3 py-1 text-[10px] text-white"
                                >{{ newsItem.category }}</span
                            >
                        </div>
                        <div class="p-[18px]">
                            <h3
                                class="font-heading text-xl font-bold leading-6 text-[#062a59]"
                            >
                                {{ newsItem.title }}
                            </h3>
                            <p class="mt-3 text-sm leading-5 text-[#4b5563]">
                                {{ newsItem.excerpt }}
                            </p>
                            <p
                                v-if="expandedNews === index"
                                class="mt-4 border-t border-[#e1e2eb] pt-4 text-sm leading-6 text-[#4b5563]"
                            >
                                {{ newsItem.detail }}
                            </p>
                            <button
                                type="button"
                                class="mt-4 h-9 w-full rounded-md border border-[#0063c7] text-xs font-medium text-[#0063c7]"
                                :aria-expanded="expandedNews === index"
                                @click="
                                    expandedNews =
                                        expandedNews === index ? null : index
                                "
                            >
                                {{
                                    expandedNews === index
                                        ? "Tutup Detail"
                                        : "Lihat Detail"
                                }}
                            </button>
                        </div>
                    </article>
                </div>
                <p
                    v-else
                    class="mt-8 rounded-xl border border-dashed border-[#d6dce6] bg-white px-6 py-10 text-center text-sm text-[#667085]"
                >
                    Belum ada Media &amp; Berita terbaru.
                </p>
            </div>
        </section>
        <section
            ref="promoSection"
            class="home-promo-section relative overflow-hidden px-4 py-16 text-white sm:px-6 sm:py-20 lg:px-8 lg:py-24"
        >
            <div class="mx-auto max-w-[1280px]">
                <header class="mx-auto max-w-[720px] text-center">
                    <h2
                        class="font-heading text-[42px] font-extrabold leading-none sm:text-[52px] lg:text-[60px]"
                    >
                        Promo
                    </h2>
                    <p
                        class="mx-auto mt-4 max-w-[650px] text-sm leading-6 text-[#dcecff] sm:text-base sm:leading-7"
                    >
                        Nikmati berbagai penawaran menarik di Kampoeng Radja.
                        Waktu terbatas, jangan sampai ketinggalan!
                    </p>
                </header>

                <div
                    class="promo-carousel-shell relative mt-9 rounded-[28px] border border-white/15 bg-white/[0.08] p-4 shadow-[0_24px_60px_rgba(0,30,73,.24)] backdrop-blur-[2px] sm:p-5 lg:mt-12 lg:p-6"
                    :class="
                        promoCards.length === 1
                            ? 'mx-auto max-w-[500px]'
                            : promoCards.length === 2
                              ? 'lg:mx-auto lg:max-w-[1120px]'
                              : ''
                    "
                >
                    <div
                        class="mb-4 flex items-center justify-between gap-4 sm:mb-5"
                    >
                        <div>
                            <span
                                class="block text-[10px] font-bold uppercase tracking-[0.16em] text-[#9bcfff]"
                                >Promo aktif</span
                            >
                            <strong
                                class="mt-1 block text-sm font-semibold text-white"
                                >{{ promoCards.length }} penawaran
                                pilihan</strong
                            >
                        </div>

                        <span
                            v-if="promoCards.length > 1"
                            class="text-xs font-semibold tabular-nums text-[#c9e3ff]"
                            >{{ currentPromoIndex + 1 }} /
                            {{ promoCards.length }}</span
                        >
                    </div>

                    <div
                        v-if="!promoCards.length"
                        class="rounded-2xl border border-dashed border-white/25 bg-white/[0.06] px-6 py-12 text-center"
                    >
                        <h3 class="text-base font-bold text-white">
                            Belum ada promo aktif saat ini.
                        </h3>
                        <p class="mt-2 text-sm text-[#c9e3ff]">
                            Promo baru akan tampil otomatis sesuai periode yang
                            dikelola melalui CMS.
                        </p>
                    </div>
                    <div v-else class="relative">
                        <button
                            v-if="promoCards.length > 1"
                            type="button"
                            aria-label="Promo sebelumnya"
                            :disabled="isPromoAnimating"
                            class="promo-navigation absolute left-0 top-1/2 z-20 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full border border-white/30 bg-[#073e7c]/90 text-lg font-bold text-white shadow-[0_8px_22px_rgba(0,25,62,.28)] backdrop-blur-sm focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#ffca58]/60 disabled:cursor-wait disabled:opacity-50 sm:left-1 lg:left-2 lg:h-11 lg:w-11"
                            @click="movePromo(-1)"
                        >
                            ←
                        </button>
                        <div
                            ref="promoViewport"
                            class="overflow-hidden"
                            :class="[
                                isPromoReady ? 'visible' : 'invisible',
                                promoCards.length > 1
                                    ? 'mx-12 sm:mx-14 lg:mx-16'
                                    : '',
                            ]"
                        >
                            <div
                                ref="promoTrack"
                                class="promo-track flex gap-5"
                                :class="[
                                    isPromoTransitioning
                                        ? 'promo-track--animating'
                                        : '',
                                    promoCards.length === 1
                                        ? 'promo-track--single'
                                        : '',
                                    promoCards.length === 2
                                        ? 'promo-track--double'
                                        : '',
                                ]"
                                :style="promoTrackStyle"
                                @transitionend="finishPromoTransition"
                            >
                                <article
                                    v-for="promo in visualPromoCards"
                                    :key="promo.visualKey"
                                    class="promo-slide flex h-full shrink-0 flex-col overflow-hidden rounded-[24px] bg-white text-[#062a59]"
                                >
                                    <img
                                        :src="
                                            promoMediaReady
                                                ? promo.poster_url
                                                : undefined
                                        "
                                        :alt="`Poster ${promo.title}`"
                                        class="aspect-[4/5] w-full bg-slate-100 object-cover"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                    <div
                                        class="flex flex-1 flex-col p-5 lg:p-6"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <span
                                                class="w-fit rounded-full bg-[#fff0e4] px-3 py-1 text-[10px] font-extrabold tracking-[0.13em] text-[#c95700]"
                                                >PROMO</span
                                            >
                                            <span
                                                v-if="promo.period"
                                                class="text-right text-[11px] font-bold text-[#d65f00]"
                                                >{{ promo.period }}</span
                                            >
                                        </div>
                                        <h3
                                            class="mt-4 min-h-[52px] font-heading text-[21px] font-bold leading-[1.28] text-[#062a59] lg:text-[22px]"
                                        >
                                            {{ promo.title }}
                                        </h3>
                                        <p
                                            class="mt-3 line-clamp-2 min-h-[44px] flex-1 text-sm leading-[22px] text-[#596273]"
                                        >
                                            {{ promo.summary }}
                                        </p>
                                        <button
                                            type="button"
                                            :aria-label="`Lihat detail ${promo.title}`"
                                            class="mt-5 flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#f47a1f] text-center text-sm font-bold text-white"
                                            @click="openPromoDetail(promo)"
                                        >
                                            Lihat Detail
                                            <span aria-hidden="true">→</span>
                                        </button>
                                    </div>
                                </article>
                            </div>
                        </div>
                        <button
                            v-if="promoCards.length > 1"
                            type="button"
                            aria-label="Promo berikutnya"
                            :disabled="isPromoAnimating"
                            class="promo-navigation absolute right-0 top-1/2 z-20 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full border border-white/30 bg-[#073e7c]/90 text-lg font-bold text-white shadow-[0_8px_22px_rgba(0,25,62,.28)] backdrop-blur-sm focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#ffca58]/60 disabled:cursor-wait disabled:opacity-50 sm:right-1 lg:right-2 lg:h-11 lg:w-11"
                            @click="movePromo(1)"
                        >
                            →
                        </button>
                    </div>
                </div>
            </div>
        </section>
        <div
            v-if="selectedPromo"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/65 p-3 sm:p-6"
            role="dialog"
            aria-modal="true"
            :aria-label="`Detail ${selectedPromo.title}`"
            @click.self="closePromoDetail"
        >
            <article
                class="relative grid max-h-[92vh] w-full max-w-[920px] overflow-y-auto rounded-[24px] bg-white shadow-2xl md:grid-cols-[minmax(280px,0.85fr)_minmax(0,1.15fr)]"
            >
                <button
                    type="button"
                    class="absolute right-3 top-3 z-10 grid h-10 w-10 place-items-center rounded-full bg-white/95 text-2xl leading-none text-slate-600 shadow-md transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-200"
                    aria-label="Tutup detail Promo"
                    @click="closePromoDetail"
                >
                    ×
                </button>
                <div
                    class="grid min-h-[300px] place-items-center bg-slate-100 p-4 sm:p-6 md:min-h-[560px]"
                >
                    <img
                        :src="selectedPromo.poster_url"
                        :alt="`Poster ${selectedPromo.title}`"
                        class="max-h-[520px] h-auto w-full object-contain"
                        decoding="async"
                    />
                </div>
                <div class="flex flex-col p-5 sm:p-8 md:p-10">
                    <span
                        class="w-fit rounded-full bg-[#fff0e4] px-3 py-1 text-[10px] font-extrabold tracking-[0.13em] text-[#c95700]"
                        >PROMO</span
                    >
                    <h2
                        class="mt-4 pr-9 font-heading text-2xl font-extrabold leading-tight text-[#062a59] sm:text-3xl"
                    >
                        {{ selectedPromo.title }}
                    </h2>
                    <p
                        v-if="selectedPromo.period"
                        class="mt-3 text-sm font-bold text-[#d65f00]"
                    >
                        {{ selectedPromo.period }}
                    </p>
                    <div
                        class="mt-6 whitespace-pre-line text-sm leading-7 text-[#4b5563]"
                    >
                        {{ selectedPromo.detail || selectedPromo.summary }}
                    </div>
                    <a
                        v-if="selectedPromo.link_wa"
                        :href="selectedPromo.link_wa"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-8 inline-flex h-12 w-full items-center justify-center rounded-xl bg-[#f47a1f] px-5 text-sm font-bold text-white transition hover:bg-[#d96008] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-orange-200 sm:w-auto"
                        >Tanya Lebih Lanjut</a
                    >
                </div>
            </article>
        </div>
        <ProductShowcase :products="products" />
        <section ref="ridesSection" class="bg-[#f1f3f5] px-5 py-20 lg:px-0">
            <div class="mx-auto max-w-[1120px]">
                <div class="flex flex-wrap items-end justify-between gap-5">
                    <div>
                        <h2
                            class="font-heading text-[28px] font-bold text-[#191c1e]"
                        >
                            Wahana Unggulan
                        </h2>
                        <p class="mt-2 text-sm text-[#434655]">
                            Jelajahi berbagai wahana seru yang siap menguji
                            adrenalin dan memberikan keceriaan.
                        </p>
                    </div>
                    <Link
                        :href="route('wahana')"
                        class="text-sm font-bold text-[#005cc8]"
                        >Lihat Semua Wahana →</Link
                    >
                </div>
                <div class="mt-8 grid gap-6 md:grid-cols-3">
                    <article
                        v-for="ride in featuredCards"
                        :key="ride.title"
                        class="overflow-hidden rounded-[24px] border border-[#e0e3e5] bg-white shadow-[0_1px_2px_rgba(0,0,0,.05)]"
                    >
                        <div class="relative">
                            <LazyImage
                                :src="ride.image"
                                :active="ridesMediaReady"
                                :alt="ride.title"
                                class-name="h-[137px] w-full object-cover"
                            /><span
                                :class="ride.tone"
                                class="absolute left-3 top-3 rounded-full px-3 py-1 text-[10px] font-bold"
                                >{{ ride.label }}</span
                            >
                        </div>
                        <div class="p-[18px]">
                            <h3
                                class="font-heading text-lg font-bold text-[#191c1e]"
                            >
                                {{ ride.title }}
                            </h3>
                            <p
                                class="mt-2 min-h-10 text-xs leading-4 text-[#434655]"
                            >
                                {{ ride.description }}
                            </p>
                            <Link
                                :href="route('wahana')"
                                class="mt-4 block w-full rounded-md border border-[#0063c7] py-2 text-center text-xs font-medium text-[#0063c7]"
                                >Detail Wahana</Link
                            >
                        </div>
                    </article>
                </div>
            </div>
        </section>
        <section
            ref="partnersSection"
            class="overflow-hidden border-t-[3px] border-[#1685ed] bg-[#dedede] px-5 py-10 lg:px-0"
        >
            <div class="mx-auto max-w-[1120px]">
                <h2
                    class="text-center font-heading text-xl font-bold text-[#191c1e]"
                >
                    Mitra
                </h2>
                <div v-if="partners?.length" class="mt-8 overflow-hidden">
                    <div
                        class="partners-marquee flex w-max will-change-transform"
                    >
                        <div class="flex shrink-0 items-center gap-16 pr-16">
                            <div
                                v-for="item in partners"
                                :key="item.id"
                                class="grid h-12 w-28 shrink-0 place-items-center"
                            >
                                <LazyImage
                                    :src="item.logo"
                                    :active="partnersMediaReady"
                                    :alt="item.name"
                                    class-name="max-h-10 max-w-24 object-contain"
                                />
                            </div>
                        </div>
                        <div
                            aria-hidden="true"
                            class="flex shrink-0 items-center gap-16 pr-16"
                        >
                            <div
                                v-for="item in partners"
                                :key="`duplicate-${item.id}`"
                                class="grid h-12 w-28 shrink-0 place-items-center"
                            >
                                <LazyImage
                                    :src="item.logo"
                                    :active="partnersMediaReady"
                                    alt=""
                                    class-name="max-h-10 max-w-24 object-contain"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="mt-6 text-center text-sm text-[#5e6470]">
                    Belum ada Mitra yang ditampilkan.
                </p>
            </div>
        </section>
        <section ref="mapSection" class="bg-[#f9f9ff] px-5 py-[120px] lg:px-0">
            <div
                class="mx-auto h-[450px] max-w-[1120px] overflow-hidden rounded-2xl border-2 border-[#e1e2eb] bg-slate-100 shadow-[0_1px_2px_rgba(0,0,0,.05)]"
            >
                <iframe
                    v-if="mapReady"
                    title="Lokasi Kampoeng Radja"
                    class="h-[446px] w-full border-0"
                    src="https://www.google.com/maps?q=kampoeng+radja&amp;z=14&amp;t=m&amp;hl=en&amp;output=embed"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                ></iframe>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
.partners-marquee {
    animation: partners-marquee 34s linear infinite;
}

@keyframes partners-marquee {
    from {
        transform: translate3d(0, 0, 0);
    }

    to {
        transform: translate3d(-50%, 0, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .partners-marquee {
        animation: none;
        transform: translate3d(0, 0, 0);
    }
}

section:nth-of-type(2) {
    background:
        radial-gradient(
            circle at 4% 12%,
            rgba(255, 157, 66, 0.12) 0 72px,
            transparent 73px
        ),
        radial-gradient(
            circle at 96% 88%,
            rgba(45, 169, 234, 0.13) 0 128px,
            transparent 129px
        ),
        #f7fbff !important;
}

section:nth-of-type(2) h2 {
    padding-left: 14px;
    text-align: center;
    color: #063b76;
}

section:nth-of-type(2) article {
    border-color: #d4e7f8;
    border-radius: 20px;
    box-shadow: 0 12px 30px rgba(4, 69, 133, 0.1);
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
}

section:nth-of-type(2) article:nth-child(2) {
    border-top: 4px solid #ff9d42;
}

section:nth-of-type(2) article:nth-child(1),
section:nth-of-type(2) article:nth-child(3) {
    border-top: 4px solid #2da9ea;
}

section:nth-of-type(2) article:hover {
    transform: translateY(-5px);
    box-shadow: 0 18px 36px rgba(4, 69, 133, 0.16);
}

section:nth-of-type(2) article button {
    border-width: 2px;
    border-color: #ff8a1f;
    border-radius: 10px;
    color: #d65f00;
    transition:
        color 0.2s ease,
        background-color 0.2s ease;
}

section:nth-of-type(2) article button:hover {
    color: #fff;
    background: #ff8a1f;
}

.home-promo-section {
    background: linear-gradient(
        145deg,
        #052f65 0%,
        #0756a6 52%,
        #0878de 100%
    ) !important;
    border-top: 6px solid #ff9d42;
}

.home-promo-section h2 {
    text-shadow: 0 5px 18px rgba(0, 31, 70, 0.28);
}

.home-promo-section .promo-carousel-shell {
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.12),
        0 24px 60px rgba(0, 25, 62, 0.24);
}

.home-promo-section article {
    border: 1px solid rgba(210, 225, 240, 0.95);
    box-shadow: 0 14px 32px rgba(0, 25, 62, 0.22);
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
}

.home-promo-section article a {
    background: #f47a1f;
    transition:
        background-color 0.2s ease,
        transform 0.2s ease;
}

.home-promo-section article:hover {
    box-shadow: 0 20px 42px rgba(0, 25, 62, 0.3);
    transform: translateY(-4px);
}

.home-promo-section article a:hover {
    background: #d96008;
    transform: translateY(-1px);
}

.home-promo-section .promo-track {
    will-change: transform;
}

.home-promo-section .promo-track--animating {
    transition: transform 380ms ease-in-out;
}

.home-promo-section .promo-slide {
    flex-basis: 100%;
}

.home-promo-section .promo-track--single .promo-slide {
    flex-basis: 100%;
}

.home-promo-section .promo-navigation {
    transition:
        color 0.2s ease,
        background-color 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease;
}

.home-promo-section .promo-navigation:hover {
    color: #fff;
    background: #f47a1f;
    transform: translateY(-50%) scale(1.04);
}

@media (min-width: 768px) {
    .home-promo-section .promo-slide {
        flex-basis: calc((100% - 1.25rem) / 2);
    }
}

@media (min-width: 1024px) {
    .home-promo-section .promo-slide {
        flex-basis: calc((100% - 2.5rem) / 3);
    }

    .home-promo-section .promo-track--double .promo-slide {
        flex-basis: calc((100% - 1.25rem) / 2);
    }
}

@media (prefers-reduced-motion: reduce) {
    .home-promo-section .promo-track--animating {
        transition-duration: 1ms;
    }
}

section:nth-of-type(4) {
    background: linear-gradient(
        135deg,
        #fff8ef 0%,
        #eef8ff 58%,
        #dff2ff 100%
    ) !important;
    border-top: 1px solid #cde9fb;
}

section:nth-of-type(4) h2 {
    color: #063b76;
}

section:nth-of-type(4) > div > div:first-child > a {
    color: #d65f00;
}

section:nth-of-type(4) article {
    overflow: hidden;
    border: 2px solid #fff;
    border-radius: 26px;
    box-shadow: 0 14px 28px rgba(4, 69, 133, 0.13);
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
}

section:nth-of-type(4) article:nth-child(2) {
    box-shadow: 0 14px 28px rgba(219, 105, 12, 0.14);
}

section:nth-of-type(4) article:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 38px rgba(4, 69, 133, 0.2);
}

section:nth-of-type(4) article a {
    border-width: 2px;
    border-color: #0878de;
    border-radius: 10px;
    font-weight: 700;
    transition:
        color 0.2s ease,
        background-color 0.2s ease;
}

section:nth-of-type(4) article a:hover {
    color: #fff;
    background: #0878de;
}

section:nth-of-type(5) {
    border-top-color: #ff8a1f !important;
    background: linear-gradient(90deg, #e7f5ff, #fff5e8, #e7f5ff) !important;
}

section:nth-of-type(5) h2 {
    color: #063b76;
}

section:nth-of-type(6) {
    background: linear-gradient(180deg, #e9f6ff 0%, #f8fbff 100%) !important;
}

section:nth-of-type(6) > div {
    border-color: #ff9d42;
    border-width: 4px;
    box-shadow: 0 20px 45px rgba(4, 69, 133, 0.18);
}

/* Stakeholder-requested curved transition: one wide, shallow CSS ellipse. */
section:nth-of-type(2) {
    position: relative;
    z-index: 1;
}

section:nth-of-type(2)::after {
    position: absolute;
    z-index: 1;
    bottom: -58px;
    left: 50%;
    width: 160%;
    height: 128px;
    content: "";
    pointer-events: none;
    background: #f7fbff;
    border-radius: 0 0 50% 50% / 0 0 100% 100%;
    transform: translateX(-50%);
}

.home-promo-section {
    position: relative;
    z-index: 0;
}

.home-promo-section > div {
    position: relative;
    z-index: 2;
}

section:nth-of-type(2) > div > h2,
section:nth-of-type(2) > div > p {
    text-align: center;
}

@media (max-width: 1023px) {
    section:nth-of-type(2) article:hover,
    .home-promo-section article:hover,
    section:nth-of-type(4) article:hover {
        transform: none;
    }

    section:nth-of-type(2)::after {
        bottom: -34px;
        width: 180%;
        height: 84px;
    }
}
</style>
