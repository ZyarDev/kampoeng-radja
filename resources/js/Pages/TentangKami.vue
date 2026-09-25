<script setup>
import { Head } from "@inertiajs/vue3";
import PublicLayout from "../Layouts/PublicLayout.vue";
const stories = [
    {
        section: "BAGIAN 1",
        title: "Awal Mula — 2006",
        image: "/assets/temporary/about-foundation.png?v=20260922-2",
        paragraphs: [
            "Berawal dari gagasan Ir. Petrie Ramli untuk menghadirkan ruang rekreasi keluarga di Jambi, Kampoeng Radja mulai membuka pintunya untuk masyarakat pada 1 April 2006. Berdiri di atas kawasan seluas sekitar 8 hektare, Kampoeng Radja hadir sebagai tempat di mana anak-anak, remaja, orang dewasa hingga keluarga dapat menikmati waktu bersama dalam satu kawasan rekreasi.",
            "Sejak hari-hari pertamanya, Kampoeng Radja tidak hanya dibangun sebagai tempat bermain, tetapi sebagai ruang untuk menciptakan pengalaman dan kenangan. Dari sinilah perjalanan panjang Kampoeng Radja dimulai.",
        ],
    },
    {
        section: "BAGIAN 2",
        title: "Transformasi dan Komitmen — 2007–2009",
        image: "/assets/temporary/about-transformation.jpeg?v=20260922",
        paragraphs: [
            "Seiring meningkatnya antusiasme masyarakat, Kampoeng Radja terus berkembang. Pada 2007, pembangunan Jasmine Water Park dimulai dan kemudian dibuka untuk umum pada 2008. Perjalanan tersebut dilanjutkan dengan penambahan fasilitas water slide pada Juni 2009, menghadirkan pengalaman rekreasi air yang semakin lengkap.",
            "Pada tahun yang sama, Kampoeng Radja kembali memperluas pilihan rekreasinya melalui kehadiran Go Kart dan Kereta Api Mini, serta pembangunan fasilitas parkir yang lebih representatif. Salah satunya adalah area parkir kendaraan roda empat dengan konsep berundak atau terasering, yang pada masa itu menjadi sesuatu yang unik di Jambi.",
            "Perubahan demi perubahan tersebut menjadi bagian dari komitmen Kampoeng Radja untuk terus beradaptasi dan memberikan pengalaman rekreasi yang semakin baik bagi setiap generasi pengunjung.",
        ],
    },
    {
        section: "BAGIAN 3",
        title: "Bertumbuh Hingga Hari Ini",
        image: "/assets/temporary/about-growth.png?v=20260923",
        paragraphs: [
            "Dari sebuah taman rekreasi keluarga yang mulai dibuka pada 2006, Kampoeng Radja terus berkembang mengikuti perjalanan waktu. Berbagai wahana dan fasilitas kini melengkapi kawasan rekreasi, mulai dari Kereta Api, Flying Pirate, Mobil Golf, Jasmine Water Park, Sepeda Air, Paint Ball, area Outbound dan Flying Fox, Ombang-Ambing dan Muter, hingga area Pemancingan.",
            "Perjalanan tersebut terus berlanjut dengan hadirnya berbagai pembaruan dan pengalaman baru bagi pengunjung. Salah satu yang menjadi unggulan terbaru adalah Rainbow Slide, menghadirkan warna baru dalam perjalanan Kampoeng Radja sekaligus menjadi bagian dari transformasinya sebagai destinasi rekreasi keluarga yang terus berkembang.",
            "Hampir dua dekade sejak pertama kali dibuka, wajah Kampoeng Radja mungkin terus berubah, wahana terus bertambah, dan generasi pengunjung terus berganti. Namun semangatnya tetap sama: menjadi tempat bagi keluarga, sahabat, dan berbagai generasi untuk bertemu, bermain, serta menciptakan kenangan bersama di Jambi.",
        ],
    },
];
const vision =
    "Menjadi tempat wisata terbaik di Provinsi Jambi yang berwawasan lingkungan.";
const missions = [
    "Sebagai Tempat Wisata yang memberikan pengalaman berkesan.",
    "Sebagai tempat pengembangan sumber daya manusia secara internal maupun eksternal.",
    "Sebagai tempat wisata yang terbaik / rapi dan menerapkan prinsip-prinsip ramah lingkungan.",
    "Sebagai tempat wisata yang kinerja-nya terukur.",
];
</script>
<template>
    <Head title="Tentang Kami">
        <meta
            name="description"
            content="[PLACEHOLDER: Deskripsi Tentang Kami]"
        />
    </Head>
    <PublicLayout>
        <div class="about-page">
            <section
                class="relative grid h-[640px] place-items-center overflow-hidden bg-[#173e59] text-center text-white"
            >
                <img
                    src="/assets/temporary/tentang-kami-hero.jpeg"
                    alt="[FIGMA SEMENTARA] Tampak aerial Kampoeng Radja"
                    class="absolute inset-0 h-full w-full object-cover"
                />
                <div class="absolute inset-0 bg-[#002147]/50"></div>
                <div class="relative">
                    <h1
                        class="font-heading text-4xl font-extrabold lg:text-[48px]"
                    >
                        Kampoeng Radja
                    </h1>
                    <p class="mt-5 text-xl font-bold text-white/90">
                        "Kesenangan Tiada Akhir di Kampoeng Radja"
                    </p>
                </div>
            </section>
            <section class="bg-white px-5 py-20 lg:px-0">
                <div class="mx-auto max-w-[1120px] space-y-12">
                    <article
                        v-for="(story, index) in stories"
                        :key="story.section"
                        :class="index % 2 ? 'lg:flex-row-reverse' : ''"
                        class="flex flex-col items-center gap-8 lg:flex-row"
                    >
                        <div class="flex-1">
                            <span
                                class="rounded-full bg-[#fce7f3] px-3 py-1 font-mono text-[10px] font-semibold tracking-[.15em] text-[#be185d]"
                                >{{ story.section }}</span
                            >
                            <h2
                                class="mt-4 font-heading text-2xl font-bold text-[#191c1e]"
                            >
                                {{ story.title }}
                            </h2>
                            <div
                                class="mt-3 h-[3px] w-10 rounded-full bg-[#0063c7]"
                            ></div>
                            <p
                                v-for="paragraph in story.paragraphs"
                                :key="paragraph"
                                class="mt-5 text-sm leading-5 text-[#434655]"
                            >
                                {{ paragraph }}
                            </p>
                        </div>
                        <img
                            :src="story.image"
                            :alt="`[FIGMA SEMENTARA] ${story.title}`"
                            class="h-[255px] w-full flex-1 rounded-xl object-cover shadow-sm"
                        />
                    </article>
                </div>
            </section>
            <section class="about-values bg-white px-5 py-16 sm:py-20 lg:px-0">
                <div class="mx-auto max-w-[1120px]">
                    <div class="text-center">
                        <span
                            class="rounded-full bg-[#fce7f3] px-3 py-1 font-mono text-[10px] font-semibold tracking-[.15em] text-[#be185d]"
                            >LANDASAN KAMI</span
                        >
                        <h2
                            class="mt-4 font-heading text-2xl font-bold text-[#191c1e] sm:text-[28px]"
                        >
                            Visi &amp; Misi
                        </h2>
                    </div>
                    <div
                        class="mx-auto mt-9 grid max-w-[920px] items-stretch gap-5 md:grid-cols-2 md:gap-6"
                    >
                        <article
                            class="value-card value-card--vision flex min-h-[280px] flex-col rounded-2xl border border-[#dbe7ff] bg-[linear-gradient(145deg,#fff,#f4f8ff)] p-6 sm:p-7"
                        >
                            <span
                                class="grid h-11 w-11 place-items-center rounded-xl bg-[#dce9ff] text-[#0756ba]"
                                aria-hidden="true"
                            >
                                <svg
                                    class="h-6 w-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        d="M2.5 12s3.5-6.5 9.5-6.5 9.5 6.5 9.5 6.5-3.5 6.5-9.5 6.5S2.5 12 2.5 12Z"
                                    />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </span>
                            <h3
                                class="mt-5 font-heading text-xl font-bold text-[#063b76]"
                            >
                                Visi
                            </h3>
                            <div
                                class="mt-3 h-0.5 w-10 rounded-full bg-[#2d8ee8]"
                            ></div>
                            <p
                                class="mt-6 text-[15px] font-medium leading-7 text-[#34445b]"
                            >
                                {{ vision }}
                            </p>
                        </article>
                        <article
                            class="value-card value-card--mission min-h-[280px] rounded-2xl border border-[#f5dcc7] bg-[linear-gradient(145deg,#fff,#fff7ef)] p-6 sm:p-7"
                        >
                            <span
                                class="grid h-11 w-11 place-items-center rounded-xl bg-[#ffe4cc] text-[#b45309]"
                                aria-hidden="true"
                            >
                                <svg
                                    class="h-6 w-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <circle cx="12" cy="12" r="8" />
                                    <circle cx="12" cy="12" r="4" />
                                    <path d="m15 9 5-5m-2 0h2v2" />
                                </svg>
                            </span>
                            <h3
                                class="mt-5 font-heading text-xl font-bold text-[#063b76]"
                            >
                                Misi
                            </h3>
                            <div
                                class="mt-3 h-0.5 w-10 rounded-full bg-[#f28b32]"
                            ></div>
                            <ol
                                class="mt-5 grid gap-3.5 text-[13px] leading-5 text-[#34445b]"
                            >
                                <li
                                    v-for="(item, index) in missions"
                                    :key="item"
                                    class="grid grid-cols-[28px_minmax(0,1fr)] items-start gap-3"
                                >
                                    <span
                                        class="grid h-7 w-7 place-items-center rounded-full bg-white text-[11px] font-bold text-[#b45309] shadow-sm ring-1 ring-[#f3cfaf]"
                                        aria-hidden="true"
                                        >{{ index + 1 }}</span
                                    >
                                    <span class="pt-0.5">{{ item }}</span>
                                </li>
                            </ol>
                        </article>
                    </div>
                </div>
            </section>
            <section
                class="overflow-hidden bg-[#f7f9fb] py-10 md:px-5 md:py-16 lg:px-0"
            >
                <div
                    class="mx-auto max-w-[1400px] overflow-x-auto md:rounded-2xl"
                >
                    <img
                        src="/assets/about/struktur-organisasi-kampoeng-radja.png"
                        alt="Struktur organisasi PT Anjungan Buana Wisata Kampoeng Radja"
                        class="block h-auto w-full min-w-[880px] object-contain"
                        loading="lazy"
                    />
                </div>
            </section>
        </div>
    </PublicLayout>
</template>

<style scoped>
.about-page > section:first-child > div.absolute {
    background: linear-gradient(
        120deg,
        rgba(2, 43, 91, 0.72),
        rgba(7, 120, 222, 0.38) 58%,
        rgba(255, 126, 30, 0.28)
    ) !important;
}
.about-page > section:first-child h1 {
    text-shadow: 0 8px 24px rgba(0, 26, 63, 0.38);
}
.about-page > section:first-child p {
    display: inline-block;
    padding: 10px 20px;
    border: 1px solid rgba(255, 255, 255, 0.55);
    border-radius: 999px;
    background: rgba(3, 62, 122, 0.48);
    backdrop-filter: blur(8px);
}
.about-page > section:nth-child(2) {
    background: linear-gradient(180deg, #f7fbff, #fff8ef) !important;
}
.about-page > section:nth-child(2) article {
    padding: 24px;
    border: 1px solid #d9eaf8;
    border-top: 5px solid #2da9ea;
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 14px 34px rgba(4, 69, 133, 0.1);
}
.about-page > section:nth-child(2) article:nth-child(even) {
    border-top-color: #ff9d42;
}
.about-page > section:nth-child(2) article > div > span,
.about-values > div > div:first-child > span {
    color: #b94e00;
    background: #fff0df;
}
.about-page h2 {
    color: #063b76;
}
.about-page > section:nth-child(2) img {
    border: 4px solid #fff;
    border-radius: 22px;
    box-shadow: 0 12px 28px rgba(3, 59, 118, 0.16);
}
.about-values {
    background: linear-gradient(135deg, #eaf7ff, #fff 48%, #fff3e5) !important;
}
.value-card {
    border-width: 1px;
    box-shadow: 0 12px 28px rgba(4, 69, 133, 0.09);
}
.value-card--vision {
    border-top: 4px solid #2d8ee8;
}
.value-card--mission {
    border-top: 4px solid #f28b32;
}
@media (max-width: 767px) {
    .about-page > section:nth-child(2) article {
        padding: 18px;
        border-radius: 22px;
    }
    .about-page > section:first-child p {
        margin-inline: 20px;
        font-size: 15px;
    }
}
</style>
