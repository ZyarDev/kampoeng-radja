<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
  products: { type: Array, default: () => [] },
});

const products = computed(() => props.products);

const activeIndex = ref(0);
const sectionRoot = ref(null);
const mediaReady = ref(false);
let sectionObserver = null;
const transitionDirection = ref(1);
const thumbnailTrack = ref(null);
const activeProduct = computed(() => props.products[activeIndex.value] ?? null);
const activeHeroImage = computed(() => mediaReady.value ? (activeProduct.value?.heroImage || activeProduct.value?.thumbnail || '') : '');
const transitionName = computed(() => transitionDirection.value > 0 ? 'product-next' : 'product-previous');

const revealThumbnail = async (index) => {
  await nextTick();
  const target = thumbnailTrack.value?.querySelector(`[data-product-index="${index}"]`);
  target?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
};

const selectProduct = (index, direction = null) => {
  if (index === activeIndex.value) return;

  transitionDirection.value = direction ?? (index > activeIndex.value ? 1 : -1);
  activeIndex.value = index;
  revealThumbnail(index);
};

const moveProduct = (direction) => {
  if (props.products.length <= 1) return;
  const nextIndex = (activeIndex.value + direction + props.products.length) % props.products.length;
  selectProduct(nextIndex, direction);
};

const moveThumbnails = (direction) => {
  const track = thumbnailTrack.value;
  if (!track) return;

  track.scrollBy({
    left: direction * Math.max(track.clientWidth * 0.72, 240),
    behavior: 'smooth',
  });
};

onMounted(() => {
  if (!('IntersectionObserver' in window)) {
    mediaReady.value = true;
    return;
  }

  sectionObserver = new IntersectionObserver(([entry]) => {
    if (!entry.isIntersecting) return;
    mediaReady.value = true;
    sectionObserver?.disconnect();
  }, { rootMargin: '350px 0px' });
  sectionObserver.observe(sectionRoot.value);
});

onBeforeUnmount(() => sectionObserver?.disconnect());
</script>

<template>
  <div
    ref="sectionRoot"
    class="product-showcase overflow-hidden bg-[linear-gradient(180deg,#ffffff_0%,#f8fbff_78%,#eef5ff_100%)] px-5 pb-9 pt-16 sm:pb-10 sm:pt-20 lg:px-0 lg:pb-10 lg:pt-24"
    role="region"
    aria-labelledby="product-showcase-title"
  >
    <div class="mx-auto max-w-[1120px]">
      <header class="mx-auto max-w-[720px] text-center">
        <h2 id="product-showcase-title" class="font-heading text-[38px] font-extrabold leading-none text-[#062a59] sm:text-[48px] lg:text-[56px]">Produk</h2>
        <p class="mx-auto mt-4 max-w-[620px] text-sm leading-6 text-[#596273] sm:text-base">
          Beragam paket, fasilitas, dan aktivitas terbaik dari Kampoeng Radja<br class="hidden sm:block" />
          untuk pengalaman belajar, bermain, dan berkesan.
        </p>
      </header>

      <div v-if="activeProduct" class="relative mt-10 lg:mt-12">
        <div class="relative aspect-[4/3] overflow-hidden rounded-[22px] border-[6px] border-white bg-[radial-gradient(circle_at_center,#f7fbff_0%,#e4f2ff_100%)] shadow-[0_18px_44px_rgba(13,64,125,.16)] sm:aspect-[16/8] lg:aspect-[16/7]">
          <Transition :name="transitionName" mode="out-in">
            <div
              :key="activeProduct.id"
              class="absolute inset-0 h-full w-full overflow-hidden"
            >
              <img
                v-if="activeHeroImage"
                :src="activeHeroImage"
                :alt="`Produk ${activeProduct.name}`"
                class="relative z-10 h-full w-full object-contain"
                decoding="async"
              />
            </div>
          </Transition>
          <span class="sr-only" aria-live="polite">Produk aktif: {{ activeProduct.name }}</span>
        </div>

      </div>

      <div v-if="products.length" class="relative mt-7 px-10 sm:px-12">
        <button
          type="button"
          class="absolute left-0 top-[72px] z-10 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full border border-[#d8e2ef] bg-white font-bold text-[#075ac2] shadow-md transition hover:border-[#075ac2] hover:bg-[#075ac2] hover:text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8cc7ff]/60"
          aria-label="Geser thumbnail produk ke kiri"
          @click="moveThumbnails(-1)"
        >
          ←
        </button>

        <div ref="thumbnailTrack" class="product-thumbnails flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2 sm:gap-4">
          <button
            v-for="(product, index) in products"
            :key="product.id"
            type="button"
            :data-product-index="index"
            class="group w-[112px] shrink-0 snap-start rounded-[16px] border-2 bg-white p-1.5 text-center transition duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8cc7ff]/60 sm:w-[124px] lg:w-[128px]"
            :class="index === activeIndex ? 'border-[#0868d5] shadow-[0_8px_22px_rgba(8,104,213,.16)]' : 'border-transparent shadow-[0_4px_14px_rgba(26,57,92,.08)] hover:border-[#b8d6f5]'"
            :aria-pressed="index === activeIndex"
            :aria-label="`Tampilkan produk ${product.name}`"
            @click="selectProduct(index)"
          >
            <img :src="mediaReady ? product.thumbnail : undefined" alt="" class="aspect-[4/5] w-full rounded-[11px] bg-slate-100 object-cover" loading="lazy" decoding="async" />
            <span class="mt-2 block truncate px-1 text-[11px] font-semibold leading-4 text-[#46556a] group-hover:text-[#075ac2]">{{ product.name }}</span>
          </button>
        </div>

        <button
          type="button"
          class="absolute right-0 top-[72px] z-10 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full border border-[#d8e2ef] bg-white font-bold text-[#075ac2] shadow-md transition hover:border-[#075ac2] hover:bg-[#075ac2] hover:text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#8cc7ff]/60"
          aria-label="Geser thumbnail produk ke kanan"
          @click="moveThumbnails(1)"
        >
          →
        </button>
      </div>

      <div v-else class="mt-10 rounded-2xl border border-dashed border-[#cddcf0] bg-white/70 px-6 py-14 text-center text-sm text-[#596273]">
        Belum ada Produk yang ditampilkan.
      </div>

    </div>
  </div>
</template>

<style scoped>
.product-thumbnails {
  scrollbar-width: none;
}

.product-thumbnails::-webkit-scrollbar {
  display: none;
}

.product-next-enter-active,
.product-next-leave-active,
.product-previous-enter-active,
.product-previous-leave-active {
  transition: opacity 320ms ease, transform 320ms ease;
  will-change: opacity, transform;
}

.product-next-enter-from,
.product-previous-leave-to {
  opacity: 0;
  transform: translate3d(18px, 0, 0);
}

.product-next-leave-to,
.product-previous-enter-from {
  opacity: 0;
  transform: translate3d(-18px, 0, 0);
}

@media (prefers-reduced-motion: reduce) {
  .product-next-enter-active,
  .product-next-leave-active,
  .product-previous-enter-active,
  .product-previous-leave-active {
    transition-duration: 1ms;
  }

  .product-thumbnails {
    scroll-behavior: auto;
  }
}
</style>
