<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { Link } from '@inertiajs/vue3'
import {
  ArrowLeft,
  Info,
  Landmark,
  Target,
  Users,
  Leaf,
  CalendarClock,
  Code2,
  BookOpenCheck
} from 'lucide-vue-next'

/* ----------------------- Accordion state ----------------------- */
const open = ref({
  tagline: true,
  history: false,
  vmv: false,
  staffFacilities: false,
  community: false,
  timeline: false,
  devs: false,
})
const toggle = (k) => (open.value[k] = !open.value[k])

/* ----------------------- Keys for a11y ------------------------- */
const onKey = (e, k) => {
  if (e.key === 'Enter' || e.key === ' ') {
    e.preventDefault()
    toggle(k)
  }
}

/* ----------------------- Back behavior ------------------------- */
function goBack(e) {
  e?.preventDefault?.()
  if (window.history.length > 1) window.history.back()
  else window.location.href = '/'
}

/* ----------------------- Scroll hint (desktop) ----------------- */
const showShadow = ref(false)
const onScroll = () => (showShadow.value = window.scrollY > 6)
onMounted(() => window.addEventListener('scroll', onScroll))
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))

/* ----------------------- Section map (for TOC) ----------------- */
const sections = [
  { id: 'tagline',  title: 'Tagline',               icon: Info },
  { id: 'history',  title: 'History',               icon: Landmark },
  { id: 'vmv',      title: 'Vision, Mission & Values', icon: Target },
  { id: 'staff',    title: 'Staff & Facilities',    icon: Users },
  { id: 'community',title: 'Community & Stewardship', icon: Leaf },
  { id: 'timeline', title: 'Company Timeline',      icon: CalendarClock },
  { id: 'devs',     title: 'Developers & MemoraBeth', icon: Code2 },
]
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-white to-[#FAFAF7] text-[#1C1C1C] relative">
    <!-- Brand composition ribbon: 50/20/20/10 -->
    <div class="w-full h-2 flex">
      <div class="basis-[50%] bg-[#F5D146]" aria-hidden="true"></div>
      <div class="basis-[20%] bg-[#4FA07F]" aria-hidden="true"></div>
      <div class="basis-[20%] bg-[#6E63A6]" aria-hidden="true"></div>
      <div class="basis-[10%] bg-[#7A5B43]" aria-hidden="true"></div>
    </div>

    <!-- Top ambient shapes (subtle, performance-friendly) -->
    <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
      <div class="absolute -top-24 -left-24 h-72 w-72 rounded-full bg-[#4FA07F]/10 blur-3xl"></div>
      <div class="absolute -bottom-16 -right-20 h-80 w-80 rounded-full bg-[#6E63A6]/10 blur-3xl"></div>
      <div class="absolute top-1/3 left-1/2 -translate-x-1/2 h-72 w-72 rounded-full bg-[#F5D146]/10 blur-3xl"></div>
    </div>

    <!-- Sticky nav -->
    <nav
      class="sticky top-0 z-20 bg-white/80 backdrop-blur border-b border-black/10 transition-shadow"
      :class="showShadow ? 'shadow-[0_6px_24px_rgba(0,0,0,0.06)]' : ''"
    >
      <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 h-12 flex items-center justify-between">
        <button
          @click="goBack"
          class="inline-flex items-center gap-2 text-[#4FA07F] hover:text-[#3A7C63] hover:bg-[#EFFEFA] px-3 py-1.5 rounded-md text-sm font-medium transition"
          aria-label="Go back"
        >
          <ArrowLeft class="w-4 h-4" />
          Back
        </button>

        <!-- tiny brand crumb -->
        <span class="hidden sm:inline-flex items-center gap-2 text-xs text-gray-600">
           <img
                src="/images/Bethany.png"
                alt="Bethany Memorial Logo"
                loading="lazy"
                class="h-10 w-10 rounded-lg object-contain border border-[#4FA07F]/20 bg-white p-1 shadow-sm"
              />
              About
        </span>
      </div>
    </nav>

    <section class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8 lg:py-10 grid lg:grid-cols-[minmax(0,1fr)_280px] gap-8">
      <!-- Main column -->
      <div>
        <!-- Header / Title card -->
        <div
          class="rounded-2xl border border-black/10 bg-white/80 backdrop-blur-sm shadow-[0_10px_30px_rgba(0,0,0,0.06)] p-6 md:p-8 transition-all duration-300 ease-[cubic-bezier(.2,.8,.2,1)]"
        >
          <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
              <h1 class="text-3xl md:text-4xl font-semibold tracking-tight">
                About Bethany Memorial Park & Cemeteries
              </h1>
              <p class="mt-2 text-sm md:text-base text-black/70">
                <strong>Established:</strong> 2017 &nbsp;•&nbsp;
                <strong>Type:</strong> Garden-style memorial park
              </p>
            </div>

            <!-- Logos slot using images -->
            <div class="flex items-center gap-3">
              <img
                src="/images/MDC logo.png"
                alt="MDC Logo"
                loading="lazy"
                class="h-10 w-10 rounded-lg object-contain border border-[#6E63A6]/20 bg-white p-1 shadow-sm"
              />
              <img
                src="/images/Bethany.png"
                alt="Bethany Memorial Logo"
                loading="lazy"
                class="h-10 w-10 rounded-lg object-contain border border-[#4FA07F]/20 bg-white p-1 shadow-sm"
              />
            </div>
          </div>

          <!-- reading notice -->
          <div class="mt-6 rounded-xl border border-black/10 bg-[#F5D146]/10 p-4 text-sm text-black/80 flex items-start gap-3">
            <BookOpenCheck class="w-5 h-5 text-[#B38D00] mt-0.5" />
            <p>
              Each section below can be expanded as needed. Please read responsibly; the company isn’t liable for
              misunderstandings caused by not reading.
            </p>
          </div>
        </div>

        <!-- Accordions -->
        <div class="mt-8 space-y-4">
          <!-- Tagline -->
          <section id="tagline" class="rounded-2xl border border-black/10 bg-white shadow-sm overflow-hidden">
            <div class="h-1.5 w-full bg-gradient-to-r from-[#4FA07F] via-[#F5D146] to-[#6E63A6]"></div>
            <button
              class="w-full flex items-center justify-between px-4 md:px-6 py-4 md:py-5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#6E63A6]"
              :aria-expanded="open.tagline"
              aria-controls="panel-tagline"
              @click="toggle('tagline')"
              @keydown="onKey($event, 'tagline')"
            >
              <div class="flex items-center gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-[#4FA07F]/15 text-[#4FA07F]">
                  <Info class="w-4 h-4" />
                </span>
                <h2 class="text-lg md:text-xl font-semibold">Tagline</h2>
              </div>
              <Chevron :open="open.tagline" />
            </button>
            <Transition
              enter-active-class="transition-all duration-300 ease-[cubic-bezier(.2,.8,.2,1)]"
              enter-from-class="opacity-0 -translate-y-1"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition-all duration-200 ease-[cubic-bezier(.2,.8,.2,1)]"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-1"
            >
              <div v-show="open.tagline" id="panel-tagline" class="px-6 pb-6 prose prose-slate max-w-none">
                <blockquote>
                  “A place to remember. A place to rest. A Caring and Responsible Eternal Service (CARES)”
                </blockquote>
              </div>
            </Transition>
          </section>

          <!-- History -->
          <section id="history" class="rounded-2xl border border-black/10 bg-white shadow-sm overflow-hidden">
            <div class="h-1.5 w-full bg-gradient-to-r from-[#6E63A6] via-[#4FA07F] to-[#F5D146]"></div>
            <button
              class="w-full flex items-center justify-between px-4 md:px-6 py-4 md:py-5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#6E63A6]"
              :aria-expanded="open.history"
              aria-controls="panel-history"
              @click="toggle('history')"
              @keydown="onKey($event, 'history')"
            >
              <div class="flex items-center gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-[#6E63A6]/15 text-[#6E63A6]">
                  <Landmark class="w-4 h-4" />
                </span>
                <h2 class="text-lg md:text-xl font-semibold">History</h2>
              </div>
              <Chevron :open="open.history" />
            </button>
            <Transition
              enter-active-class="transition-all duration-300 ease-[cubic-bezier(.2,.8,.2,1)]"
              enter-from-class="opacity-0 -translate-y-1"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition-all duration-200 ease-[cubic-bezier(.2,.8,.2,1)]"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-1"
            >
              <div v-show="open.history" id="panel-history" class="px-6 pb-6 prose prose-slate max-w-none">
                <p>
                  Bethany Memorial Park & Cemeteries opened in 2017 in response to the growing local demand
                  for a serene, professionally managed resting place that blends contemporary memorial options
                  with traditional values. Designed by landscape and memorial planners, the park offers
                  accessible, low-maintenance plots and flexible memorial choices while preserving native
                  greenery and encouraging visitation and reflection.
                </p>
              </div>
            </Transition>
          </section>

          <!-- Vision, Mission & Core Values -->
          <section id="vmv" class="rounded-2xl border border-black/10 bg-white shadow-sm overflow-hidden">
            <div class="h-1.5 w-full bg-gradient-to-r from-[#F5D146] via-[#6E63A6] to-[#4FA07F]"></div>
            <button
              class="w-full flex items-center justify-between px-4 md:px-6 py-4 md:py-5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#6E63A6]"
              :aria-expanded="open.vmv"
              aria-controls="panel-vmv"
              @click="toggle('vmv')"
              @keydown="onKey($event, 'vmv')"
            >
              <div class="flex items-center gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-[#F5D146]/20 text-[#B38D00]">
                  <Target class="w-4 h-4" />
                </span>
                <h2 class="text-lg md:text-xl font-semibold">Vision, Mission & Core Values</h2>
              </div>
              <Chevron :open="open.vmv" />
            </button>
            <Transition
              enter-active-class="transition-all duration-300 ease-[cubic-bezier(.2,.8,.2,1)]"
              enter-from-class="opacity-0 -translate-y-1"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition-all duration-200 ease-[cubic-bezier(.2,.8,.2,1)]"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-1"
            >
              <div v-show="open.vmv" id="panel-vmv" class="px-6 pb-6 prose prose-slate max-w-none">
                <p><strong>Vision:</strong> To be a serene sanctuary where love, memory, and legacy are honored for generations to come.</p>
                <p><strong>Mission:</strong> To provide dignified, affordable, and respectful memorial services and permanent resting places that honor individuals and support families and the community during times of loss.</p>
                <p><strong>Core values:</strong> Compassion, dignity, integrity, stewardship, and community.</p>
              </div>
            </Transition>
          </section>

          <!-- Staff & Facilities -->
          <section id="staff" class="rounded-2xl border border-black/10 bg-white shadow-sm overflow-hidden">
            <div class="h-1.5 w-full bg-gradient-to-r from-[#7A5B43] via-[#F5D146] to-[#4FA07F]"></div>
            <button
              class="w-full flex items-center justify-between px-4 md:px-6 py-4 md:py-5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#6E63A6]"
              :aria-expanded="open.staffFacilities"
              aria-controls="panel-staffFacilities"
              @click="toggle('staffFacilities')"
              @keydown="onKey($event, 'staffFacilities')"
            >
              <div class="flex items-center gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-[#7A5B43]/15 text-[#7A5B43]">
                  <Users class="w-4 h-4" />
                </span>
                <h2 class="text-lg md:text-xl font-semibold">Staff & Facilities</h2>
              </div>
              <Chevron :open="open.staffFacilities" />
            </button>
            <Transition
              enter-active-class="transition-all duration-300 ease-[cubic-bezier(.2,.8,.2,1)]"
              enter-from-class="opacity-0 -translate-y-1"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition-all duration-200 ease-[cubic-bezier(.2,.8,.2,1)]"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-1"
            >
              <div v-show="open.staffFacilities" id="panel-staffFacilities" class="px-6 pb-6 prose prose-slate max-w-none">
                <ul class="grid sm:grid-cols-2 gap-x-8">
                  <li>Park-like landscaping: resilient trees, walking paths, and curated lawns.</li>
                  <li>Multiple burial options: traditional gravesites, family lots, cremation gardens, columbarium niches, and mausoleum spaces.</li>
                  <li>Chapel & visitation rooms: non-denominational chapel; special-use charges may apply for medium-large gatherings.</li>
                  <li>Administration & care: on-site offices, groundskeeping team, and a 24/7 maintenance schedule.</li>
                </ul>
              </div>
            </Transition>
          </section>

          <!-- Community & Stewardship -->
          <section id="community" class="rounded-2xl border border-black/10 bg-white shadow-sm overflow-hidden">
            <div class="h-1.5 w-full bg-gradient-to-r from-[#4FA07F] via-[#6E63A6] to-[#F5D146]"></div>
            <button
              class="w-full flex items-center justify-between px-4 md:px-6 py-4 md:py-5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#6E63A6]"
              :aria-expanded="open.community"
              aria-controls="panel-community"
              @click="toggle('community')"
              @keydown="onKey($event, 'community')"
            >
              <div class="flex items-center gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-[#4FA07F]/15 text-[#4FA07F]">
                  <Leaf class="w-4 h-4" />
                </span>
                <h2 class="text-lg md:text-xl font-semibold">Community & Stewardship</h2>
              </div>
              <Chevron :open="open.community" />
            </button>
            <Transition
              enter-active-class="transition-all duration-300 ease-[cubic-bezier(.2,.8,.2,1)]"
              enter-from-class="opacity-0 -translate-y-1"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition-all duration-200 ease-[cubic-bezier(.2,.8,.2,1)]"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-1"
            >
              <div v-show="open.community" id="panel-community" class="px-6 pb-6 prose prose-slate max-w-none">
                <p>
                  We host remembrance services, seasonal memorial ceremonies, and educational outreach on end-of-life
                  planning. We also support mental-health awareness by providing safe, quiet spaces for reflection and
                  by listening with patience and compassion.
                </p>
                <blockquote>
                  “Fear not, for I am with you… I will uphold you with my righteous right hand.” — Isaiah 41:10 (ESV)
                </blockquote>
              </div>
            </Transition>
          </section>

          <!-- Company Timeline -->
          <section id="timeline" class="rounded-2xl border border-black/10 bg-white shadow-sm overflow-hidden">
            <div class="h-1.5 w-full bg-gradient-to-r from-[#F5D146] via-[#4FA07F] to-[#6E63A6]"></div>
            <button
              class="w-full flex items-center justify-between px-4 md:px-6 py-4 md:py-5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#6E63A6]"
              :aria-expanded="open.timeline"
              aria-controls="panel-timeline"
              @click="toggle('timeline')"
              @keydown="onKey($event, 'timeline')"
            >
              <div class="flex items-center gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-[#F5D146]/20 text-[#B38D00]">
                  <CalendarClock class="w-4 h-4" />
                </span>
                <h2 class="text-lg md:text-xl font-semibold">Company Timeline</h2>
              </div>
              <Chevron :open="open.timeline" />
            </button>
            <Transition
              enter-active-class="transition-all duration-300 ease-[cubic-bezier(.2,.8,.2,1)]"
              enter-from-class="opacity-0 -translate-y-1"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition-all duration-200 ease-[cubic-bezier(.2,.8,.2,1)]"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-1"
            >
              <div v-show="open.timeline" id="panel-timeline" class="px-6 pb-6 prose prose-slate max-w-none">
                <ul class="space-y-1.5">
                  <li><strong>2016:</strong> Planning and land acquisition; community consultations, benchmarking, design and purpose.</li>
                  <li><strong>2017:</strong> Official opening; first interment and inaugural dedication ceremony.</li>
                  <li><strong>2018–2020:</strong> Expansion of blocks for sale gardens; building customer networks.</li>
                  <li><strong>2021–present:</strong> Strengthening community programs and regular maintenance, sustaining leadership in the town.</li>
                </ul>
              </div>
            </Transition>
          </section>

          <!-- Developers & MemoraBeth -->
          <section id="devs" class="rounded-2xl border border-black/10 bg-white shadow-sm overflow-hidden">
            <div class="h-1.5 w-full bg-gradient-to-r from-[#6E63A6] via-[#F5D146] to-[#4FA07F]"></div>
            <button
              class="w-full flex items-center justify-between px-4 md:px-6 py-4 md:py-5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#6E63A6]"
              :aria-expanded="open.devs"
              aria-controls="panel-devs"
              @click="toggle('devs')"
              @keydown="onKey($event, 'devs')"
            >
              <div class="flex items-center gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-black/5 text-black/70">
                  <Code2 class="w-4 h-4" />
                </span>
                <h2 class="text-lg md:text-xl font-semibold">Developers & MemoraBeth</h2>
              </div>
              <Chevron :open="open.devs" />
            </button>
            <Transition
              enter-active-class="transition-all duration-300 ease-[cubic-bezier(.2,.8,.2,1)]"
              enter-from-class="opacity-0 -translate-y-1"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition-all duration-200 ease-[cubic-bezier(.2,.8,.2,1)]"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-1"
            >
              <div v-show="open.devs" id="panel-devs" class="px-6 pb-6 prose prose-slate max-w-none">
                <p>
                  Include your school name, full names, roles, contact information, and photos. Share your story—the hows,
                  whys, constraints, struggles, and resolutions that made MemoraBeth possible. Acknowledge your professors,
                  parents, and loved ones, and give thanks to God for guidance throughout.
                </p>
              </div>
            </Transition>
          </section>
        </div>
      </div>

      <!-- Right rail: sticky mini-TOC (desktop) -->
      <aside class="hidden lg:block">
        <div class="sticky top-20 rounded-2xl border border-[#4FA07F]/20 bg-white/80 backdrop-blur p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wide text-[#4FA07F] mb-2">
            On this page
          </div>
          <nav class="space-y-1.5 text-sm">
            <a
              v-for="s in sections" :key="s.id" :href="'#' + s.id"
              class="flex items-center gap-2 px-2 py-1 rounded hover:bg-[#EFFEFA] text-slate-700"
            >
              <component :is="s.icon" class="w-4 h-4 text-[#6E63A6]" />
              <span>{{ s.title }}</span>
            </a>
          </nav>
        </div>
      </aside>
    </section>
  </div>
</template>

<!-- Inline utility component (chevron) -->
<script>
export default {
  components: {
    Chevron: {
      props: { open: { type: Boolean, default: false } },
      template: `
        <svg :class="['h-5 w-5 text-slate-700 transition-transform duration-200', open ? 'rotate-180' : 'rotate-0']"
             viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.08 1.04l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
        </svg>
      `
    }
  }
}
</script>

<style>
/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
  .transition-all, .transition-transform, .duration-200, .duration-300 {
    transition: none !important;
  }
}

/* Optional: smooth anchor scrolling for TOC */
html:has(body) {
  scroll-behavior: smooth;
}
</style>
