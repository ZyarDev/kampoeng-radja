<script setup>
import { Link } from "@inertiajs/vue3";
</script>

<template>
    <footer class="bg-[#06478d] text-white">
        <div
            class="relative mx-auto grid min-h-[252px] max-w-[1120px] gap-10 px-6 py-16 md:grid-cols-[1.35fr_.8fr_1fr] lg:px-0"
        >
            <div>
                <img
                    src="/assets/temporary/footer-logo-white.png"
                    alt="Taman Wisata Kampoeng Radja"
                    class="h-[56px] w-auto"
                />
                <p
                    class="mt-7 max-w-[300px] text-[15px] leading-5 text-[#bbd0ff]"
                >
                    Destinasi wisata keluarga terbaik di Jambi dengan berbagai
                    wahana edukatif dan rekreatif.
                </p>
                <div class="mt-5 flex gap-3">
                    <a
                        href="#"
                        aria-label="[FIGMA SEMENTARA] Instagram"
                        class="grid h-8 w-8 place-items-center rounded-full bg-white/10"
                        >◎</a
                    >
                    <a
                        href="#"
                        aria-label="[FIGMA SEMENTARA] YouTube"
                        class="grid h-8 w-8 place-items-center rounded-full bg-white/10"
                        >▶</a
                    >
                    <a
                        href="#"
                        aria-label="[FIGMA SEMENTARA] Email"
                        class="grid h-8 w-8 place-items-center rounded-full bg-white/10"
                        >@</a
                    >
                </div>
            </div>
            <div>
                <h2 class="text-[15px] font-medium">Tautan Cepat</h2>
                <div class="mt-4 grid gap-2 text-[15px] text-[#bbd0ff]">
                    <Link :href="route('home')">Beranda</Link
                    ><Link :href="route('tentang-kami')">Tentang Kami</Link
                    ><Link :href="route('wahana')">Wahana</Link
                    ><Link :href="route('galeri-event')">Galeri Event</Link>
                </div>
            </div>
            <div>
                <h2 class="text-[15px] font-medium">Kontak</h2>
                <div
                    class="mt-4 grid gap-3 text-[14px] leading-5 text-[#bbd0ff]"
                >
                    <p class="flex gap-3">
                        <span aria-hidden="true">⌖</span
                        ><span
                            >Jln. Lkr. Barat 3 No.108, Kenali Besar,<br />Kec.
                            Kota Baru, Kota Jambi,<br />Jambi 36361</span
                        >
                    </p>
                    <p class="flex gap-3">
                        <span aria-hidden="true">⌕</span
                        ><span>081289909180</span>
                    </p>
                    <p class="flex gap-3">
                        <span aria-hidden="true">✉</span
                        ><span>info@kampoengradja.com</span>
                    </p>
                </div>
            </div>
            <Link
                :href="route('login')"
                class="absolute right-6 top-8 rounded-[10px] border border-white px-3 py-1 text-xs lg:right-0"
                >Login Staff</Link
            >
        </div>
        <div
            class="border-t border-white/10 py-4 text-center text-[10px] text-[#79a6dd]"
        >
            Wisata Kampoeng Radja. Kesenangan Tiada Akhir.
        </div>
    </footer>
</template>
