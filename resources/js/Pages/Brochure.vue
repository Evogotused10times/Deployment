<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'

/* =========================================================================
   Canonical product images (case-sensitive on most servers)
   ========================================================================= */
const IMAGE_BY_KEY = {
  mausoleum:         '/images/mausoleum.JPG',
  garden:            '/images/gardenlot.JPG',
  lawn:              '/images/mound.JPG',
  cv:                '/images/CV.JPG',            // Community Vaults
  vault:             '/images/CV.JPG',            // alias
  'community vault': '/images/CV.JPG',            // alias
}

/* Infer the correct image from key/title/tag if none was provided */
function imageFallbackFor(s = '') {
  const k = String(s).toLowerCase()
  if (k.includes('mausoleum'))        return IMAGE_BY_KEY.mausoleum
  if (k.includes('garden'))           return IMAGE_BY_KEY.garden
  if (k.includes('lawn'))             return IMAGE_BY_KEY.lawn
  if (k.includes('community vault') ||
      k.includes('vault') ||
      k.includes('cv'))               return IMAGE_BY_KEY.cv
  return null
}

const props = defineProps({
  products: { type: Array, default: () => [] }
})

/* Navigation + formatting */
function goApply(url) { if (url) router.visit(url) }
function formatPrice(p) {
  if (p == null) return null
  const n = Number(p); if (Number.isNaN(n)) return null
  return `₱${n.toLocaleString()}`
}

/* Tasteful accent per product */
function accentColorFor(key) {
  if (/mausoleum/i.test(key))  return 'from-amber-200/60  to-amber-100/20  border-amber-200  text-amber-900'
  if (/lawn/i.test(key))       return 'from-green-200/60  to-green-100/20  border-green-200  text-green-900'
  if (/(vault|cv)/i.test(key)) return 'from-sky-200/60    to-sky-100/20    border-sky-200    text-sky-900'
  if (/garden/i.test(key))     return 'from-emerald-200/60 to-emerald-100/20 border-emerald-200 text-emerald-900'
  return 'from-gray-200/60     to-gray-100/20  border-gray-200  text-gray-900'
}

/* Normalized, image-aware items */
const items = computed(() =>
  props.products.map((p, i) => {
    const guess = imageFallbackFor(`${p.key ?? ''} ${p.title ?? ''} ${p.tag ?? ''}`)
    const img = p.image || guess
    return {
      key: p.key || `prod-${i}`,
      title: p.title || 'Product',
      summary: p.summary || '',
      bullets: Array.isArray(p.bullets) ? p.bullets : [],
      cta: p.cta || null,
      image: img || null,
      imageAlt: p.imageAlt || p.title || 'MemoraBeth option',
      icon: p.icon || '🕊',
      price_from: p.price_from ?? null,
      tag: p.tag || 'Available',
      accent: p.accent || accentColorFor(p.key || '')
    }
  })
)

/* Optional quick filter by category (derives from key/title) */
const activeFilter = ref('all')
const categories = computed(() => {
  const set = new Set()
  items.value.forEach(i => {
    const t = (i.key || i.title || '').toLowerCase()
    if (t.match(/mausoleum/)) set.add('Mausoleum')
    else if (t.match(/garden/)) set.add('Garden')
    else if (t.match(/lawn/)) set.add('Lawn')
    else if (t.match(/(vault|cv)/)) set.add('Community Vaults')
  })
  return ['All', ...Array.from(set)]
})
const filtered = computed(() => {
  if (activeFilter.value.toLowerCase() === 'all') return items.value
  const f = activeFilter.value.toLowerCase()
  return items.value.filter(i => {
    const t = `${i.key} ${i.title}`.toLowerCase()
    if (f === 'mausoleum')        return t.includes('mausoleum')
    if (f === 'garden')           return t.includes('garden')
    if (f === 'lawn')             return t.includes('lawn')
    if (f === 'community vaults') return t.includes('vault') || t.includes('cv')
    return true
  })
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-green-50 via-white to-white">
    <!-- HERO -->
    <section class="relative overflow-hidden">
      <!-- soft blobs -->
      <div class="pointer-events-none absolute inset-0">
        <div class="absolute -top-28 -right-24 w-[36rem] h-[36rem] rounded-full bg-green-200/30 blur-3xl"></div>
        <div class="absolute -bottom-28 -left-24 w-[30rem] h-[30rem] rounded-full bg-emerald-200/30 blur-3xl"></div>
      </div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
        <div class="mx-auto max-w-3xl text-center">
          <span
            class="inline-flex items-center gap-2 text-[11px] md:text-[12px] uppercase tracking-wider text-green-700 bg-white/70 border border-green-200 px-3 py-1 rounded-full shadow-sm"
          >
            <span>MemoraBeth</span> • <span>Guided Choices</span>
          </span>
          <h1 class="mt-4 text-4xl md:text-5xl font-extrabold leading-tight text-green-900">
            Burial Options &amp; Brochure
          </h1>
          <p class="mt-3 md:mt-4 text-gray-600 md:text-lg">
            Explore dignified offerings at a glance. Apply online or have our staff guide you with care.
          </p>

          <!-- filters -->
          <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
            <button
              v-for="cat in categories" :key="cat"
              class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm bg-white hover:bg-gray-50"
              :class="activeFilter.toLowerCase() === cat.toLowerCase()
                ? 'border-green-500 text-green-800 shadow-[0_0_0_3px_rgba(16,185,129,.15)]'
                : 'border-gray-200 text-gray-700'"
              @click="activeFilter = cat.toLowerCase()"
            >
              {{ cat }}
            </button>
          </div>

          <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
            <a href="/" class="px-4 py-2.5 bg-white border border-gray-200 rounded-lg text-gray-800 hover:bg-gray-50 text-sm">
              ← Back to Home
            </a>
            <a href="#products" class="px-4 py-2.5 bg-green-700 text-white rounded-lg hover:bg-green-800 text-sm">
              Browse Products
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- PRODUCTS GRID -->
    <section id="products" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">
      <div class="grid gap-6 sm:gap-7 md:gap-8 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="p in filtered" :key="p.key"
          class="group relative rounded-2xl border bg-white/90 backdrop-blur-sm shadow-sm hover:shadow-lg hover:-translate-y-[2px] transition-all duration-200 overflow-hidden"
          tabindex="0"
        >
          <!-- diagonal ribbon -->
          <div
            class="pointer-events-none absolute -right-20 -top-16 w-60 h-40 rotate-45 bg-gradient-to-br opacity-90"
            :class="p.accent.replace('border-','bg-')"
            aria-hidden="true"
          ></div>

          <!-- image -->
          <div class="relative">
            <div class="aspect-[16/10] bg-gradient-to-br from-white to-green-50 overflow-hidden">
              <img
                v-if="p.image"
                :src="p.image"
                :alt="p.imageAlt"
                class="w-full h-full object-cover transition duration-500 group-hover:scale-[1.02]"
                loading="lazy"
                decoding="async"
                :sizes="`(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw`"
              />
              <div v-else class="w-full h-full flex items-center justify-center text-5xl select-none"> {{ p.icon }} </div>
            </div>

            <!-- tag chip -->
            <div
              class="absolute left-4 top-4 inline-flex items-center gap-2 text-[11px] font-medium px-2.5 py-1 rounded-full border bg-white/85 backdrop-blur-sm shadow-sm"
              :class="p.accent"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-current/70"></span>
              <span>{{ p.tag }}</span>
            </div>

            <!-- subtle gradient overlay -->
            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-white/85 to-transparent"></div>
          </div>

          <!-- body -->
          <div class="p-5">
            <div class="flex items-start justify-between gap-3">
              <h2 class="text-lg font-bold text-gray-900 leading-snug">{{ p.title }}</h2>
              <div v-if="formatPrice(p.price_from)" class="shrink-0 text-right">
                <div class="text-[10px] uppercase tracking-wider text-gray-500">From</div>
                <div class="text-sm font-semibold text-green-700">
                  {{ formatPrice(p.price_from) }}
                </div>
              </div>
            </div>

            <p class="mt-2 text-sm text-gray-600">
              {{ p.summary }}
            </p>

            <!-- bullets -->
            <ul class="mt-3 space-y-1.5">
              <li v-for="b in p.bullets" :key="b" class="flex items-start gap-2 text-[13px] text-gray-700">
                <svg class="mt-[2px] w-4 h-4 flex-none" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M20 7L10 17L4 11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>{{ b }}</span>
              </li>
            </ul>

            <!-- actions -->
            <div class="mt-4 flex flex-wrap items-center gap-2">
              <button
                :disabled="!p.cta"
                @click="goApply(p.cta)"
                class="px-3.5 py-2 rounded-lg text-sm font-medium text-white bg-green-700 hover:bg-green-800 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2 focus:ring-offset-white"
              >
                Apply for {{ p.title }}
              </button>
              <a
                v-if="p.cta"
                :href="p.cta"
                class="text-sm text-green-700 hover:text-green-800 underline underline-offset-2"
              >
                I want staff to help me
              </a>
            </div>
          </div>

          <!-- hover ring -->
          <div class="pointer-events-none absolute inset-0 rounded-2xl ring-1 ring-transparent group-hover:ring-green-300/50 transition"></div>
        </article>
      </div>
    </section>

    <!-- Sticky CTA -->
    <div
      class="sticky bottom-4 mx-4 sm:mx-6 md:mx-auto md:max-w-3xl bg-white/95 backdrop-blur-md border border-green-200 rounded-2xl shadow-lg px-4 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-2"
    >
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-green-100 text-green-700 flex items-center justify-center">ℹ️</div>
        <p class="text-sm text-gray-700">
          Not sure which option fits your family? Our staff can walk you through choices on site.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <a
          :href="items[0]?.cta || '#'"
          class="px-3 py-2 rounded-lg bg-green-700 text-white text-sm hover:bg-green-800"
        >
          Talk to Us
        </a>
        <a href="/Public/Apply" class="px-3 py-2 rounded-lg bg-white border text-sm hover:bg-gray-50">
          Start Application
        </a>
      </div>
    </div>

    <!-- Spacer -->
    <div class="h-8"></div>
  </div>
</template>

<style scoped>
/* polish: reduce layout shift on images with fixed aspect ratio */
.aspect-\[16\/10\] { aspect-ratio: 16 / 10; }

/* focus-visible outline for keyboard users (complements Tailwind ring) */
:focus-visible { outline: 2px solid rgba(16,185,129,.6); outline-offset: 2px; }

/* small screens: tighten paddings */
@media (max-width: 640px) {
  section > .max-w-7xl,
  #products { padding-left: 1rem; padding-right: 1rem; }
}
</style>
