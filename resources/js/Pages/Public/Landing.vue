<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import {
  ArrowRight, LogIn, ShieldCheck, CalendarCheck, FileText,
  Flower2, Landmark, Trees, Bone, Info
} from 'lucide-vue-next' // MapPin removed

const props = defineProps({
  stats: { type: Object, default: () => ({ appsThisWeek: 0, reservationsActive: 0 }) }
})

/* ---------------- STATS (FIXED) ---------------- */
const defaultStats = { appsThisWeek: 0, reservationsActive: 0 }

// what the template actually uses
const metrics = ref({ ...defaultStats, ...props.stats })

// keep in sync if Inertia props change
watch(
  () => props.stats,
  (val) => {
    metrics.value = { ...defaultStats, ...(val || {}) }
  },
  { deep: true, immediate: true }
)

// safe number cast
function toNum(v) {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

// show real stats only when both look numeric
const hasStats = computed(() => {
  const s = metrics.value
  return Number.isFinite(toNum(s.appsThisWeek)) && Number.isFinite(toNum(s.reservationsActive))
})

let timer = null

/* ---------------- UI STATE ---------------- */
const navOpen = ref(false)
const toggleNav = () => (navOpen.value = !navOpen.value)
const closeNav = () => (navOpen.value = false)

// Header shrink on scroll
const headerShrink = ref(false)
const handleScroll = () => { headerShrink.value = window.scrollY > 16 }

const bgImages = ref([
  '/images/entrance.JPG','/images/chapel.JPG','/images/office.JPG',
  '/images/mound.JPG','/images/facade.JPG','/images/tree.JPG',
])

const LOGO = '/images/Bethany.png'

onMounted(() => {
  // stats polling – keeps metrics “real-time-ish”
  const pull = async () => {
    try {
      const { data } = await axios.get('/api/public/stats', {
        headers: { Accept: 'application/json' }
      })
      metrics.value = { ...defaultStats, ...(data || {}) }
    } catch (e) {
      console.error('Failed to pull public stats', e)
    }
  }

  pull()
  timer = setInterval(pull, 30_000)

  // header shrink + perf
  window.addEventListener('scroll', handleScroll, { passive: true })
  handleScroll()

  // lazy the hero images on small screens to cut TBT
  if (window.matchMedia('(max-width: 640px)').matches) {
    document.querySelectorAll('.bg-item img').forEach(img => img.setAttribute('loading','lazy'))
  }
})

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
  window.removeEventListener('scroll', handleScroll)
})
</script>

<template>
  <div class="relative min-h-screen bg-white text-[#1C1C1C] overflow-x-hidden pb-[env(safe-area-inset-bottom)]">
    <!-- Site-wide soft mint wash -->
    <div class="fixed inset-0 -z-20" style="background:linear-gradient(180deg,#F7FCF9 0%,#FFFFFF 42%,#F7FCF9 100%)"></div>

    <!-- INLINE BRAND BACKDROP -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
      <div class="absolute inset-0 opacity-20"
           style="background:conic-gradient(from 0deg,#F5D146 0 180deg,#4FA07F 180deg 252deg,#6E63A6 252deg 324deg,#7A5B43 324deg 360deg);filter:saturate(1.02) contrast(1.02)" />
      <div class="absolute inset-0"
           style="background:radial-gradient(60% 55% at 50% 40%,rgba(255,255,255,.9) 0%,rgba(255,255,255,.65) 46%,rgba(255,255,255,.15) 78%,rgba(255,255,255,0) 100%)" />
      <div class="absolute inset-0 opacity-[0.04] mix-blend-multiply" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
          <filter id="mb-grain" x="-20%" y="-20%" width="140%" height="140%"><feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="2" stitchTiles="stitch"/><feColorMatrix type="saturate" values="0"/><feComponentTransfer><feFuncA type="table" tableValues="0 0 0 .07 .12 .07 0 0"/></feComponentTransfer></filter>
          <rect width="100%" height="100%" filter="url(#mb-grain)"/>
        </svg>
      </div>
    </div>

    <!-- Top accent bars -->
    <div class="pointer-events-none fixed top-0 left-0 right-0 -z-10">
      <div class="w-full h-1.5 flex">
        <div class="basis-[50%]" style="background:#F5D146"></div>
        <div class="basis-[20%]" style="background:#4FA07F"></div>
        <div class="basis-[20%]" style="background:#6E63A6"></div>
        <div class="basis-[10%]" style="background:#7A5B43"></div>
      </div>
    </div>

    <!-- NAV -->
    <header
      class="sticky top-0 z-30 backdrop-blur bg-white/85 border-b border-[rgba(79,160,127,0.18)]
             transition-all duration-200 will-change-[height,box-shadow,background-color]
             data-[shrink=true]:bg-white/92 data-[shrink=true]:shadow-[0_6px_24px_rgba(2,6,23,.06)]"
      :data-shrink="headerShrink"
    >
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button
            class="md:hidden inline-flex items-center justify-center rounded-md p-2 hover:bg-[#EFFEFA]
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4FA07F] focus-visible:ring-offset-2"
            @click="navOpen = !navOpen" aria-label="Toggle navigation">
            <svg v-if="!navOpen" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
          <img
            :src="LOGO"
            alt="MemoraBeth"
            class="h-9 w-9 rounded-lg ring-2 ring-[#4FA07F]/40 shadow-sm object-contain bg-white"
            width="36" height="36" loading="eager" decoding="async"
          />
          <span class="text-xl font-extrabold tracking-tight">
            <span class="text-[#6E63A6]">Memora</span><span class="mint-gradient">Beth</span>
          </span>
        </div>

        <nav class="hidden md:flex items-center gap-6 text-sm">
          <a href="#features" class="hover:text-[#4FA07F] focus-visible:ring-2 focus-visible:ring-[#4FA07F] rounded-md px-1">Features</a>
          <a href="#brochure" class="hover:text-[#4FA07F] focus-visible:ring-2 focus-visible:ring-[#4FA07F] rounded-md px-1">Brochure</a>
          <a href="#how" class="hover:text-[#4FA07F] focus-visible:ring-2 focus-visible:ring-[#4FA07F] rounded-md px-1">How it works</a>
          <a href="#contact" class="hover:text-[#4FA07F] focus-visible:ring-2 focus-visible:ring-[#4FA07F] rounded-md px-1">Contact</a>
          <a href="/map" class="hover:text-[#4FA07F] focus-visible:ring-2 focus-visible:ring-[#4FA07F] rounded-md px-1">Map</a>

        </nav>

        <div class="hidden sm:flex items-center gap-2">
          <Link :href="route('login')"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-[#6E63A6] text-[#6E63A6] font-medium hover:bg-[#EFFEFA] transition
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E63A6] focus-visible:ring-offset-2">
            <LogIn class="w-4 h-4" /> Admin
          </Link>
          <Link :href="route('apply.create')"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#F5D146] text-[#1C1C1C] font-semibold hover:brightness-95 transition cta-glow
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E63A6] focus-visible:ring-offset-2">
            Apply Now <ArrowRight class="w-4 h-4" />
          </Link>
        </div>
      </div>

      <!-- Mobile drawer -->
      <transition enter-active-class="duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2"
                  enter-to-class="opacity-100 translate-y-0" leave-active-class="duration-150 ease-in"
                  leave-from-class="opacity-100" leave-to-class="opacity-0 -translate-y-2">
        <div v-if="navOpen" class="md:hidden border-t bg-white/95 backdrop-blur">
          <div class="mx-auto max-w-7xl px-4 sm:px-6 py-3">
            <div class="text-[11px] uppercase tracking-wider text-slate-500 mb-2">Navigate</div>
            <div class="grid gap-1.5 text-[15px]">
              <a href="#features" class="py-2 rounded-lg px-2 hover:bg-[#EFFEFA] focus-visible:ring-2 focus-visible:ring-[#4FA07F]" @click="closeNav">Features</a>
              <a href="#brochure" class="py-2 rounded-lg px-2 hover:bg-[#EFFEFA]" @click="closeNav">Brochure</a>
              <a href="#how" class="py-2 rounded-lg px-2 hover:bg-[#EFFEFA]" @click="closeNav">How it works</a>
              <a href="#contact" class="py-2 rounded-lg px-2 hover:bg-[#EFFEFA]" @click="closeNav">Contact</a>
              <a href="/map" class="hover:text-[#4FA07F] focus-visible:ring-2 focus-visible:ring-[#4FA07F] rounded-md px-1">Map</a>

            </div>
            <div class="flex gap-2 pt-3">
              <Link :href="route('login')" class="flex-1 px-3 py-2.5 rounded-lg bg-[#F7FCF9] text-center hover:bg-[#EFFEFA]" @click="closeNav">Admin</Link>
              <Link :href="route('apply.create')" class="flex-1 px-3 py-2.5 rounded-lg bg-[#F5D146] text-[#1C1C1C] text-center hover:brightness-95" @click="closeNav">Apply Now</Link>
            </div>
          </div>
        </div>
      </transition>
    </header>

    <!-- HERO -->
    <section class="relative">
      <!-- Background (one scrolling row) -->
      <div class="absolute inset-0 overflow-hidden" aria-hidden="true">
        <div class="bg-row bg-row-hero">
          <div class="bg-track">
            <figure v-for="(src, i) in bgImages" :key="'r1a'+i" class="bg-item">
              <img :src="src" alt="" />
            </figure>
            <figure v-for="(src, i) in bgImages" :key="'r1b'+i" class="bg-item">
              <img :src="src" alt="" />
            </figure>
          </div>
        </div>

        <div class="absolute inset-0 pointer-events-none"
          style="background:
            radial-gradient(60% 50% at 30% 42%, rgba(255,255,255,.78) 0%, rgba(255,255,255,.52) 48%, rgba(255,255,255,.12) 78%, rgba(255,255,255,.08) 100%),
            linear-gradient(90deg, rgba(79,160,127,.12), rgba(79,160,127,.10));">
        </div>

        <div class="accent-slice slice-1" aria-hidden="true"></div>
        <div class="accent-slice slice-2" aria-hidden="true"></div>
      </div>

      <!-- Foreground content -->
      <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-14 lg:py-10 grid lg:grid-cols-2 gap-8 lg:gap-10 items-center">
        <div class="rounded-2xl bg-white/75 sm:bg-white/65 backdrop-blur-sm border border-[rgba(79,160,127,0.18)] p-4 sm:p-6 shadow-[0_1px_0_rgba(79,160,127,.06)]">
          <div class="flex items-center gap-3 mb-4">
            <img :src="LOGO" alt="Bethany Memorial Park" class="h-12 w-12 object-contain" width="48" height="48" />
            <div class="leading-tight">
              <div class="text-2xl font-semibold text-[#6E63A6]">Bethany</div>
              <div class="text-sm font-medium text-[#1C1C1C] -mt-1">Memorial Park</div>
            </div>
          </div>

          <h1 class="font-extrabold tracking-tight"
              style="font-size:clamp(1.75rem, 2.5vw + 1.1rem, 3.25rem); line-height:1.08;">
            <span class="text-[#6E63A6]">Caring, organized,</span>
            <span class="block mint-gradient">and dignified</span>
          </h1>
          <p class="mt-4 text-base sm:text-lg text-gray-800 leading-relaxed">
            A modern platform for managing <span class="font-semibold text-[#4FA07F]">burial services</span> at
            <span class="font-medium">Bethany Memorial Park, Tubigon, Bohol</span>.
            Reserve lots, submit applications, and receive timely updates — all in one place.
          </p>

          <div class="mt-6 sm:mt-8 flex flex-col sm:flex-row gap-3">
            <Link :href="route('apply.create')"
                  class="inline-flex items-center justify-center gap-2 px-5 sm:px-6 py-2.5 sm:py-3 bg-[#F5D146] text-[#1C1C1C] text-base font-semibold rounded-xl shadow hover:brightness-95 transition cta-glow
                         focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E63A6] focus-visible:ring-offset-2">
              Apply for Burial Services
              <ArrowRight class="w-5 h-5" />
            </Link>
          </div>

          <p class="mt-4 text-sm text-gray-700 flex items-center gap-2">
            <Info class="w-4 h-4 text-[#4FA07F]" />
            Need help? Call <a href="tel:+639000000000" class="text-[#4FA07F] font-medium hover:underline">+63 900 000 0000</a>
          </p>
        </div>

        <!-- Visual / Stats -->
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="rounded-2xl bg-white/92 backdrop-blur shadow-sm border border-[rgba(79,160,127,0.28)] p-5 card-accent mint-soft ring-1 ring-transparent hover:ring-[#4FA07F]/15 transition hero-glass">
            <div class="flex items-center gap-3">
              <ShieldCheck class="w-6 h-6 text-[#4FA07F]" />
              <h3 class="font-semibold">Secure & Reliable</h3>
            </div>
            <p class="mt-2 text-sm text-gray-700">
              Your information is handled with care. Staff-only admin ensures accuracy and privacy.
            </p>
          </div>
          <div class="rounded-2xl bg-white/92 backdrop-blur shadow-sm border border-[rgba(79,160,127,0.28)] p-5 card-accent mint-soft ring-1 ring-transparent hover:ring-[#4FA07F]/15 transition hero-glass">
            <div class="flex items-center gap-3">
              <CalendarCheck class="w-6 h-6 text-[#7A5B43]" />
              <h3 class="font-semibold">Guided Scheduling</h3>
            </div>
            <p class="mt-2 text-sm text-gray-700">
              Coordinate interments and rites with our team for smooth arrangements.
            </p>
          </div>

          <!-- Stats with skeleton (FIXED) -->
          <div class="col-span-2 rounded-2xl bg-gradient-to-r from-[#F5D146] via-[#EFFEFA] to-[#6E63A6] text-[#1C1C1C] p-6 card-accent tilt--1">
            <template v-if="hasStats">
              <p class="text-sm/none opacity-90">This week</p>
              <p class="mt-1 text-3xl font-extrabold">
                {{ toNum(metrics.appsThisWeek).toLocaleString() }} applications
              </p>
              <p class="opacity-90">
                and {{ toNum(metrics.reservationsActive).toLocaleString() }} reservations in progress
              </p>
            </template>
            <template v-else>
              <div class="animate-pulse space-y-2">
                <div class="h-3 w-20 bg-white/60 rounded"></div>
                <div class="h-7 w-56 bg-white/70 rounded"></div>
                <div class="h-4 w-64 bg-white/60 rounded"></div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </section>

    <!-- FOI ACTIONS -->
    <section class="py-8 -mt-6">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-[rgba(79,160,127,0.20)] bg-[#F7FCF9]/90 backdrop-blur p-5 shadow-[0_1px_0_0_rgba(79,160,127,.06)]">
          <p class="text-xs text-gray-600">
            Each item is an active button users can open or ignore (FOI). Please read responsibly.
          </p>
          <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <Link href="/about" class="group rounded-2xl border border-[rgba(79,160,127,0.28)] bg-white p-4 hover:bg-[#EFFEFA] transition shadow-sm hover:shadow-md hover:-translate-y-[1px] will-change-transform">
              <div class="font-semibold">About Us</div>
              <div class="text-xs text-[#4FA07F] group-hover:underline">Open</div>
            </Link>
            <Link href="/legal" class="group rounded-2xl border border-[rgba(79,160,127,0.28)] bg-white p-4 hover:bg-[#EFFEFA] transition shadow-sm hover:shadow-md hover:-translate-y-[1px] will-change-transform">
              <div class="font-semibold">Philippine Law</div>
              <div class="text-xs text-[#4FA07F] group-hover:underline">Open</div>
            </Link>
            <Link href="/announcements" class="group rounded-2xl border border-[rgba(79,160,127,0.28)] bg-white p-4 hover:bg-[#EFFEFA] transition shadow-sm hover:shadow-md hover:-translate-y-[1px] will-change-transform">
              <div class="font-semibold">Announcements</div>
              <div class="text-xs text-[#4FA07F] group-hover:underline">Open</div>
            </Link>
            <Link href="/careers" class="group rounded-2xl border border-[rgba(79,160,127,0.28)] bg-white p-4 hover:bg-[#EFFEFA] transition shadow-sm hover:shadow-md hover:-translate-y-[1px] will-change-transform">
              <div class="font-semibold">Careers</div>
              <div class="text-xs text-[#4FA07F] group-hover:underline">Open</div>
            </Link>
          </div>
        </div>
      </div>
    </section>

    <!-- FEATURES -->
    <section id="features" class="py-14 border-y border-[rgba(79,160,127,0.22)] bg-[#F7FCF9]">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-[#4FA07F]">What you can do</h2>
        <div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="p-5 rounded-2xl border border-[rgba(79,160,127,0.28)] bg-white hover:bg-[#EFFEFA] transition shadow-sm hover:shadow-md hover:-translate-y-[1px] will-change-transform">
            <FileText class="w-6 h-6 text-[#4FA07F]" />
            <h3 class="mt-3 font-semibold">Customer Applications</h3>
            <p class="text-sm text-gray-700 mt-1">Submit details and receive confirmation via email.</p>
          </div>
          <div class="p-5 rounded-2xl border border-[rgba(79,160,127,0.28)] bg-white hover:bg-[#EFFEFA] transition shadow-sm hover:shadow-md hover:-translate-y-[1px] will-change-transform">
            <CalendarCheck class="w-6 h-6 text-[#7A5B43]" />
            <h3 class="mt-3 font-semibold">Interment Bookings</h3>
            <p class="text-sm text-gray-700 mt-1">Coordinate schedules and rites with our staff.</p>
          </div>
          <div class="p-5 rounded-2xl border border-[rgba(79,160,127,0.28)] bg-white hover:bg-[#EFFEFA] transition shadow-sm hover:shadow-md hover:-translate-y-[1px] will-change-transform">
            <ShieldCheck class="w-6 h-6 text-[#4FA07F]" />
            <h3 class="mt-3 font-semibold">Admin Workflow</h3>
            <p class="text-sm text-gray-700 mt-1">Staff-only approvals keep records accurate.</p>
          </div>
          <div class="p-5 rounded-2xl border border-[rgba(79,160,127,0.28)] bg-white hover:bg-[#EFFEFA] transition shadow-sm hover:shadow-md hover:-translate-y-[1px] will-change-transform">
            <Flower2 class="w-6 h-6 text-[#4FA07F]" />
            <h3 class="mt-3 font-semibold">Notifications</h3>
            <p class="text-sm text-gray-700 mt-1">Email updates for approvals and schedules.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- BROCHURE -->
    <section id="brochure" class="py-14 bg-gradient-to-b from-[#F7FCF9] to-[#EFFEFA]">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between">
          <h2 class="text-2xl font-bold text-[#4FA07F]">Brochure</h2>
          <a href="/brochure" class="text-sm text-[#4FA07F] hover:underline">View full brochure →</a>
        </div>

        <div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <article class="group rounded-2xl border border-[rgba(79,160,127,0.24)] bg-white p-5 hover:bg-[#EFFEFA] transition shadow-sm hover:shadow-md hover:-translate-y-[1px] will-change-transform">
            <div class="h-28 rounded-xl bg-gradient-to-br from-[#EFFEFA] to-white flex items-center justify-center">
              <Landmark class="w-10 h-10 text-[#4FA07F]" />
            </div>
            <h3 class="mt-4 font-semibold">Mausoleum</h3>
            <p class="text-sm text-gray-700 mt-1">Family mausoleums with premium locations.</p>
            <a href="/brochure?type=mausoleum" class="mt-3 inline-flex items-center gap-2 text-[#4FA07F] text-sm hover:underline">
              Learn more <ArrowRight class="w-4 h-4" />
            </a>
          </article>

          <article class="group rounded-2xl border border-[rgba(79,160,127,0.24)] bg-white p-5 hover:bg-[#EFFEFA] transition shadow-sm hover:shadow-md hover:-translate-y-[1px] will-change-transform">
            <div class="h-28 rounded-xl bg-gradient-to-br from-[#F7FCF9] to-white flex items-center justify-center">
              <Trees class="w-10 h-10 text-[#7A5B43]" />
            </div>
            <h3 class="mt-4 font-semibold">Garden Lot</h3>
            <p class="text-sm text-gray-700 mt-1">Serene, landscaped areas near walks and trees.</p>
            <a href="/brochure?type=garden" class="mt-3 inline-flex items-center gap-2 text-[#4FA07F] text-sm hover:underline">
              Learn more <ArrowRight class="w-4 h-4" />
            </a>
          </article>

          <article class="group rounded-2xl border border-[rgba(79,160,127,0.24)] bg-white p-5 hover:bg-[#EFFEFA] transition shadow-sm hover:shadow-md hover:-translate-y-[1px] will-change-transform">
            <div class="h-28 rounded-xl bg-gradient-to-br from-[#EFFEFA] to-white flex items-center justify-center">
              <Flower2 class="w-10 h-10 text-[#4FA07F]" />
            </div>
            <h3 class="mt-4 font-semibold">Lawn Lot</h3>
            <p class="text-sm text-gray-700 mt-1">Simple, dignified, staff-assigned placement.</p>
            <a href="/brochure?type=lawn" class="mt-3 inline-flex items-center gap-2 text-[#4FA07F] text-sm hover:underline">
              Learn more <ArrowRight class="w-4 h-4" />
            </a>
          </article>

          <article class="group rounded-2xl border border-[rgba(79,160,127,0.24)] bg-white p-5 hover:bg-[#EFFEFA] transition shadow-sm hover:shadow-md hover:-translate-y-[1px] will-change-transform">
            <div class="h-28 rounded-xl bg-gradient-to-br from-[#F7FCF9] to-white flex items-center justify-center">
              <Bone class="w-10 h-10 text-[#4FA07F]" />
            </div>
            <h3 class="mt-4 font-semibold">Ossuary</h3>
            <p class="text-sm text-gray-700 mt-1">Compact memorial options for families.</p>
            <a href="/brochure?type=ossuary" class="mt-3 inline-flex items-center gap-2 text-[#4FA07F] text-sm hover:underline">
              Learn more <ArrowRight class="w-4 h-4" />
            </a>
          </article>
        </div>
      </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="how" class="py-14 bg-[#F7FCF9]">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-[#4FA07F]">How it works</h2>
        <ol class="mt-8 grid gap-6 sm:grid-cols-3">
          <li class="p-5 rounded-2xl border border-[rgba(79,160,127,0.24)] bg-white shadow-sm hover:shadow-md transition">
            <span class="text-xs font-semibold text-[#4FA07F]">STEP 1</span>
            <h3 class="mt-1 font-semibold">Submit application</h3>
            <p class="text-sm text-gray-700 mt-1">Fill out the online form with contact and service details.</p>
          </li>
          <li class="p-5 rounded-2xl border border-[rgba(79,160,127,0.24)] bg-white shadow-sm hover:shadow-md transition">
            <span class="text-xs font-semibold text-[#4FA07F]">STEP 2</span>
            <h3 class="mt-1 font-semibold">Staff review & guidance</h3>
            <p class="text-sm text-gray-700 mt-1">We’ll confirm details and guide you on rites and scheduling.</p>
          </li>
          <li class="p-5 rounded-2xl border border-[rgba(79,160,127,0.24)] bg-white shadow-sm hover:shadow-md transition">
            <span class="text-xs font-semibold text-[#4FA07F]">STEP 3</span>
            <h3 class="mt-1 font-semibold">Approval & updates</h3>
            <p class="text-sm text-gray-700 mt-1">Receive status updates and confirmations via email.</p>
          </li>
        </ol>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-14">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="rounded-2xl bg-gradient-to-r from-[#F5D146] via-[#EFFEFA] to-[#6E63A6] p-8 text-[#1C1C1C] flex flex-col md:flex-row items-center justify-between gap-4">
          <div>
            <h3 class="text-2xl font-extrabold">Start your application today</h3>
            <p class="opacity-90">Fast, guided, and respectful — we’re here to help.</p>
          </div>
          <Link :href="route('apply.create')"
                class="inline-flex items-center gap-2 bg-white text-[#4FA07F] font-semibold px-5 py-3 rounded-xl border border-[#4FA07F] hover:bg-[#EFFEFA] transition
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4FA07F]">
            Apply Now <ArrowRight class="w-5 h-5" />
          </Link>
        </div>
      </div>
    </section>

    <!-- FOOTER -->
    <footer id="contact" class="border-t border-[rgba(79,160,127,0.20)] bg-white">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-8 text-sm">
        <div>
          <div class="flex items-center gap-2 mb-2">
            <img :src="LOGO" alt="MemoraBeth" class="h-8 w-8 rounded-lg object-contain bg-white ring-1 ring-[#4FA07F]/30" width="32" height="32" />
            <span class="font-bold text-[#6E63A6]">MemoraBeth</span>
          </div>
          <p class="text-gray-700">Bethany Memorial Park, Tubigon, Bohol</p>
          <p class="mt-2">
            <a href="tel:+639000000000" class="text-[#4FA07F] hover:underline">+63 900 000 0000</a><br/>
            <a href="mailto:info@memorabeth.ph" class="text-[#4FA07F] hover:underline">info@memorabeth.ph</a>
          </p>
        </div>

        <div>
          <h4 class="font-semibold text-[#4FA07F]">Hours</h4>
          <ul class="mt-2 text-gray-700">
            <li>Mon–Sat: 8:00 AM – 5:00 PM</li>
            <li>Sun & Holidays: By appointment</li>
          </ul>
        </div>

        <div>
          <h4 class="font-semibold text-[#4FA07F]">Quick links</h4>
          <ul class="mt-2 space-y-1.5">
            <li><Link :href="route('apply.create')" class="inline-block py-1 hover:underline text-[#4FA07F]">Apply for Services</Link></li>
            <li><a href="/brochure" class="inline-block py-1 hover:underline text-[#4FA07F]">Brochure</a></li>
          </ul>
        </div>

        <div>
          <h4 class="font-semibold text-[#4FA07F]">Admin</h4>
          <p class="mt-2 text-gray-700">Staff access only</p>
          <Link :href="route('login')" class="inline-flex items-center gap-2 mt-2 px-3 py-2 rounded-lg border border-[#4FA07F] text-[#4FA07F] hover:bg-[#EFFEFA] transition">
            <LogIn class="w-4 h-4" /> Login
          </Link>
        </div>
      </div>

      <div class="border-t border-[rgba(79,160,127,0.20)] py-4 text-center text-xs text-gray-600">
        © {{ new Date().getFullYear() }} MemoraBeth · All rights reserved · <a href="/privacy" class="hover:underline">Privacy</a> · <a href="/terms" class="hover:underline">Terms</a>
      </div>
    </footer>
  </div>
</template>

<style scoped>
@media (prefers-color-scheme: dark) { .opacity-20 { opacity: .16 !important; } }

/* ONE row, bigger, clearer */
.bg-row { position:absolute; left:0; right:0; display:flex; align-items:center; pointer-events:none; opacity:.82; filter:saturate(1.08) contrast(1.05) brightness(1.04); }
.bg-row-hero { top:6%; height:88%; }

/* Track scrolls continuously; duplicate content for a seamless loop */
.bg-track { --speed:44s; display:flex; gap:2rem; min-width:200%; animation:scroll-x var(--speed) linear infinite; will-change:transform; }
@media (hover:hover) { .bg-track:hover { animation-play-state: paused; } }

/* Each item is a responsive block; images cover fully */
.bg-item { position:relative; flex:0 0 auto; width:46vw; height:100%; border-radius:1rem; overflow:hidden; box-shadow:0 14px 28px rgba(0,0,0,.10); isolation:isolate;
  -webkit-mask-image: linear-gradient(to right, transparent 0%, black 6%, black 94%, transparent 100%);
          mask-image: linear-gradient(to right, transparent 0%, black 6%, black 94%, transparent 100%);
}
.bg-item::after { content:""; position:absolute; inset:0; background:
  radial-gradient(120% 80% at 10% 50%, rgba(79,160,127,.10), transparent 55%),
  radial-gradient(120% 80% at 90% 50%, rgba(79,160,127,.08), transparent 60%),
  linear-gradient(180deg, rgba(239,254,250,.12), rgba(239,254,250,0));
  pointer-events:none;
}
.bg-item img { width:100%; height:100%; object-fit:cover; object-position:center; display:block; transform:translateZ(0) scale(1.03); image-rendering:auto; vertical-align:middle; }

/* Keyframes */
@keyframes scroll-x { from { transform: translateX(0) } to { transform: translateX(-50%) } }

/* Reduced motion & mobile perf */
@media (prefers-reduced-motion: reduce) {
  .bg-track { animation: none; transform: translateX(-25%) }
  .bg-row   { opacity: .50 }
}

/* Responsive tuning (mobile-first) */
@media (max-width: 1280px) { .bg-item { width:52vw } }
@media (max-width: 1024px) { .bg-item { width:60vw } .bg-row-hero { height:78% } }
@media (max-width: 640px)  {
  .bg-item { width:88vw; border-radius:.875rem }
  .bg-row-hero { height:72%; top:6% }
  section > .max-w-7xl { padding-left:1rem; padding-right:1rem; }
}

/* Angled mint slices across hero */
.accent-slice { position:absolute; inset:-10% -10% auto -10%; height:38%; pointer-events:none; mix-blend-mode:screen; opacity:.28;
  background: linear-gradient(to right, rgba(239,254,250,0), rgba(239,254,250,.85), rgba(239,254,250,0)); filter: blur(2px) saturate(1.05);
}
.slice-1 { top:10%; transform: rotate(10deg); }
.slice-2 { top:54%; transform: rotate(-7deg); opacity:.22; }

/* Card accent & soft mint fill */
.card-accent { position:relative; }
.card-accent::before{
  content:""; position:absolute; top:-1px; left:14px; right:14px; height:2px;
  background: linear-gradient(90deg, rgba(79,160,127,0), rgba(79,160,127,.55), rgba(79,160,127,0));
  border-radius:999px; transform: rotate(.4deg);
}
.mint-soft { background-image: linear-gradient(180deg,#FFFFFF 0%,#F7FCF9 100%); }

/* Gentle tilt utilities */
.tilt--1 { transform: rotate(-.35deg); }
.tilt-1  { transform: rotate(.35deg); }

/* CTA glow */
.cta-glow { box-shadow:0 0 0 0 rgba(79,160,127,0); transition: box-shadow .2s ease, filter .2s ease; }
.cta-glow:hover { box-shadow: 0 12px 28px rgba(79,160,127,.18); filter:saturate(1.05); }

/* Brand text mint gradient */
.mint-gradient { background: linear-gradient(90deg,#4FA07F 0%, #73C5A2 60%, #6E63A6 100%); -webkit-background-clip:text; background-clip:text; color:transparent; }

/* Sharper contrast for small text */
:where(p, li, small){ text-rendering: optimizeLegibility; }

/* Better focus rings globally */
:focus-visible { outline: none; box-shadow: 0 0 0 2px rgba(110,99,166,.75), 0 0 0 4px rgba(255,255,255,.9); border-radius: .625rem; }

/* Subtle header shrink animation */
header[data-shrink="true"] .h-16 { height: 3.5rem; }

/* Make buttons feel “clickable” on mobile */
button, a { -webkit-tap-highlight-color: transparent; }

/* Softer base shadows */
.shadow-sm { box-shadow: 0 6px 18px rgba(2,6,23,.06); }
.hover\:shadow-md:hover { box-shadow: 0 10px 28px rgba(2,6,23,.08); }

.hero-glass {
  background: radial-gradient(circle at top left,
              rgba(255, 255, 255, 0.85) 0%,
              rgba(255, 255, 255, 0.68) 40%,
              rgba(247, 252, 249, 0.52) 70%,
              rgba(247, 252, 249, 0.30) 100%);
  backdrop-filter: blur(18px) saturate(1.35);
  -webkit-backdrop-filter: blur(18px) saturate(1.35);
  border-color: rgba(79, 160, 127, 0.26);
  box-shadow:
    0 18px 45px rgba(15, 23, 42, 0.14),
    0 0 0 1px rgba(255, 255, 255, 0.5) inset;
}

.hero-glass:hover {
  background: radial-gradient(circle at top left,
              rgba(255, 255, 255, 0.9) 0%,
              rgba(255, 255, 255, 0.75) 40%,
              rgba(247, 252, 249, 0.6) 70%,
              rgba(247, 252, 249, 0.38) 100%);
  box-shadow:
    0 22px 55px rgba(15, 23, 42, 0.16),
    0 0 0 1px rgba(255, 255, 255, 0.6) inset;
}

</style>
