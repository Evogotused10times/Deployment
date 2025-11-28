<script setup>
import { useForm, Link, router, usePage } from '@inertiajs/vue3'
import { onMounted, onBeforeUnmount, ref, reactive, computed, watch } from 'vue'

/* =========================
   BRAND + MEDIA
   ========================= */
const LOGO = '/images/Bethany.png'
const HERO_IMG = '/images/entrance.JPG' // fallback if no slides

/* =========================
   HERO SLIDESHOW
   ========================= */
const slides = [
  {
    src: '/images/entrance.JPG',
    title: 'Main Entrance',
    desc: 'Morning light at Bethany Memorial Park.',
  },
  {
    src: '/images/chapel.JPG',
    title: 'Chapel & Rites',
    desc: 'A calm space for services and gatherings.',
  },
  {
    src: '/images/mound.JPG',
    title: 'Garden Section',
    desc: 'Green spaces reserved for garden lots and memorials.',
  },
  {
    src: '/images/tree.JPG',
    title: 'Shaded Walks',
    desc: 'Tree-lined paths for visiting loved ones.',
  },
]

const activeSlide = ref(0)
let slideTimer = null

function nextSlide() {
  if (!slides.length) return
  activeSlide.value = (activeSlide.value + 1) % slides.length
}
function prevSlide() {
  if (!slides.length) return
  activeSlide.value = (activeSlide.value - 1 + slides.length) % slides.length
}
function goSlide(i) {
  if (!slides.length) return
  activeSlide.value = i
}

/* =========================
   THEME TOKENS
   ========================= */
const C = {
  yellow: '#F5D146',
  green:  '#4FA07F',
  purple: '#6E63A6',
  brown:  '#7A5B43',
  ink:    '#1C1C1C',
}

/* =========================
   SAFE ROUTE HELPER
   ========================= */
function r(name, params = {}, fallback = '#') {
  try {
    if (typeof route === 'function' && route().has && route().has(name)) {
      return route(name, params)
    }
  } catch (_) {}
  const defaults = {
    'admin.dashboard': '/admin',
    'admin.applications.index': '/admin/applications',
    'admin.reservations.index': '/admin/reservations',
    'admin.interments.index': '/admin/interments',
    'admin.plots.index': '/admin/plots',
    'admin.plots.create': '/admin/plots/create',
    'admin.map': '/admin/map',
    'admin.map.gl': '/admin/map-gl',
    'admin.announcements.index': '/admin/announcements',
  }
  return fallback !== '#'
    ? fallback
    : (defaults[name] ?? '#')
}

/* =========================
   PROPS (SERVER DATA)
   ========================= */
const props = defineProps({
  appsCounts:          { type: Object, default: () => ({}) },
  resCounts:           { type: Object, default: () => ({}) },
  plotCounts:          { type: Object, default: () => ({}) },
  intermentCounts:     { type: Object, default: () => ({}) },
  recentApplications:  { type: Array,  default: () => [] },
  recentReservations:  { type: Array,  default: () => [] },
})

/* =========================
   COMMAND PALETTE (SIMPLE)
   ========================= */
const query = ref('')
function go() {
  const q = query.value.trim().toLowerCase()
  if (!q) return
  if (q.startsWith('app'))     return router.visit(r('admin.applications.index'))
  if (q.startsWith('res'))     return router.visit(r('admin.reservations.index'))
  if (q.startsWith('inter'))   return router.visit(r('admin.interments.index'))
  if (q.startsWith('map gl'))  return router.visit(r('admin.map.gl'))
  if (q.startsWith('map'))     return router.visit(r('admin.map'))
  if (q.startsWith('plots'))   return router.visit(r('admin.plots.index'))
  return router.visit(r('admin.applications.index'))
}

/* =========================
   HEADER NAV
   ========================= */
const showNav = ref(false)
const navRef = ref(null)
function toggleNav() { showNav.value = !showNav.value }
function closeNav()  { showNav.value = false }
function goTo(href)  { closeNav(); router.visit(href) }
function logout()    { closeNav(); router.post('/logout', {}, { onSuccess: () => router.visit('/') }) }

function onDocClick(e) {
  if (navRef.value && !navRef.value.contains(e.target)) closeNav()
}

/* =========================
   HELPERS
   ========================= */
const clamp = (n) => Math.max(0, Number.isFinite(+n) ? +n : 0)
function pct(part, total) {
  const t = clamp(total); const p = clamp(part)
  return t > 0 ? Math.round((p / t) * 100) : 0
}

/* =========================
   KPI CARDS (WITH RATIOS)
   ========================= */
const kpiApps = computed(() => {
  const t = clamp(props.appsCounts.total)
  const pending  = clamp(props.appsCounts.pending)
  const approved = clamp(props.appsCounts.approved)
  const denied   = clamp(props.appsCounts.denied)
  return {
    title: 'Applications', total: t,
    parts: [
      { label:'Pending',  v: pending,  color:'bg-amber-400',  txt:'text-amber-900' },
      { label:'Approved', v: approved, color:'bg-violet-600', txt:'text-white' },
      { label:'Denied',   v: denied,   color:'bg-rose-500',   txt:'text-white' },
    ],
    cta: { text: 'Open', to: r('admin.applications.index') },
    icon: '📝', accent: C.purple
  }
})

const kpiRes = computed(() => {
  const t = clamp(props.resCounts.total)
  const pending   = clamp(props.resCounts.pending)
  const reserved  = clamp(props.resCounts.reserved)
  const confirmed = clamp(props.resCounts.confirmed)
  return {
    title: 'Reservations', total: t,
    parts: [
      { label:'Pending',   v: pending,   color:'bg-slate-300',  txt:'text-slate-900' },
      { label:'Reserved',  v: reserved,  color:'bg-amber-400',  txt:'text-amber-900' },
      { label:'Confirmed', v: confirmed, color:'bg-emerald-600',txt:'text-white' },
    ],
    cta: { text: 'Open', to: r('admin.reservations.index') },
    icon: '📌', accent: C.brown
  }
})

const kpiInter = computed(() => {
  const t = clamp(props.intermentCounts.total)
  const upcoming = clamp(props.intermentCounts.upcoming)
  const week     = clamp(props.intermentCounts.week)
  const done     = clamp(props.intermentCounts.done)
  return {
    title: 'Interments', total: t,
    parts: [
      { label:'Upcoming',  v: upcoming, color:'bg-sky-500',    txt:'text-white' },
      { label:'This Week', v: week,     color:'bg-indigo-500', txt:'text-white' },
      { label:'Completed', v: done,     color:'bg-emerald-600',txt:'text-white' },
    ],
    cta: { text: 'Open', to: r('admin.interments.index') },
    icon: '🕯️', accent: C.yellow
  }
})

const kpiPlots = computed(() => {
  const t = clamp(props.plotCounts.total)
  const vacant   = clamp(props.plotCounts.vacant)
  const reserved = clamp(props.plotCounts.reserved)
  const occupied = clamp(props.plotCounts.occupied)
  const unmapped = clamp(props.plotCounts.unmapped)
  return {
    title: 'Plots', total: t,
    parts: [
      { label:'Vacant',   v: vacant,   color:'bg-emerald-600', txt:'text-white' },
      { label:'Reserved', v: reserved, color:'bg-amber-400',   txt:'text-amber-900' },
      { label:'Occupied', v: occupied, color:'bg-rose-500',    txt:'text-white' },
    ],
    foot: { label:'Unmapped', v: unmapped, color:'bg-slate-300', txt:'text-slate-900' },
    cta: { text: 'Manage', to: r('admin.plots.index') },
    icon: '🗺️', accent: C.purple
  }
})

const cards = computed(() => [kpiApps.value, kpiRes.value, kpiInter.value])

/* ===============================
   ANNOUNCEMENTS: quick composer
   =============================== */
const showAnn = ref(false)
function openAnnModal() { showAnn.value = true }
function closeAnnModal() { showAnn.value = false }

const annForm = useForm({
  title: '',
  category: 'news',
  date: new Date().toISOString().slice(0,10),
  pinned: false,
  href: '',
  body: '',
})
const annCats = [
  { value: 'news',   label: 'News'   },
  { value: 'event',  label: 'Event'  },
  { value: 'notice', label: 'Notice' },
]
const bodyChars = computed(() => annForm.body?.length || 0)
const titleChars = computed(() => annForm.title?.length || 0)
const titleMax = 120
const bodyMax  = 2000

function submitAnnouncement() {
  annForm.post(r('admin.announcements.store'), {
    preserveScroll: true,
    onSuccess: () => {
      annForm.reset('title','body','href','pinned')
      showAnn.value = false
      if (!(getFlash()?.success)) {
        Object.assign(toast, { show:true, text:'Announcement posted.', kind:'success' })
        setTimeout(() => (toast.show = false), 2500)
      }
    },
  })
}

/* =========================
   TOAST (FLASH)
   ========================= */
const toast = reactive({ show:false, text:'', kind:'success' })
const page = usePage()
const getFlash = () => {
  const p = page?.props?.value ?? page?.props ?? {}
  return p.flash ?? { success: null, error: null }
}

watch(() => getFlash().success, (msg) => {
  if (!msg) return
  Object.assign(toast, { show:true, text: msg, kind:'success' })
  setTimeout(() => (toast.show = false), 2500)
},{ immediate:true })

watch(() => getFlash().error, (msg) => {
  if (!msg) return
  Object.assign(toast, { show:true, text: msg, kind:'error' })
  setTimeout(() => (toast.show = false), 3000)
},{ immediate:true })

/* =========================
   LIFECYCLE HOOKS
   ========================= */
onMounted(() => {
  document.addEventListener('click', onDocClick)
  // autoplay hero slideshow
  if (slides.length) {
    slideTimer = setInterval(nextSlide, 7000)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  if (slideTimer) clearInterval(slideTimer)
})
</script>

<template>
  <div class="min-h-screen text-[color:var(--ink)] bg-gradient-to-b from-white via-[#F8FAF9] to-white" style="--ink:#1C1C1C">
    <!-- =========================
         HERO / TOP BAR (with slideshow)
         ========================= -->
    <div
      class="relative w-full bg-center bg-cover h-[320px] md:h-[360px] lg:h-[420px] rounded-b-[2rem] shadow-[0_10px_40px_rgba(79,160,127,.18)]"
      :style="{
        backgroundImage: slides.length
          ? `linear-gradient(180deg, rgba(0,0,0,.55), rgba(0,0,0,.18)), url('${slides[activeSlide].src}')`
          : `linear-gradient(180deg, rgba(0,0,0,.55), rgba(0,0,0,.18)), url('${HERO_IMG}')`,
        backgroundPosition: 'center 75%'
      }"
      role="img"
      :aria-label="slides.length ? slides[activeSlide].title : 'Administrative area background'"
    >
      <!-- soft brand glaze -->
      <div class="absolute inset-0 pointer-events-none mix-blend-soft-light"
           :style="{ background: `linear-gradient(90deg, ${C.green}66, ${C.yellow}55)` }"></div>

      <!-- Hero content -->
      <div class="max-w-7xl mx-auto h-full flex flex-col relative z-10">
        <!-- Top bar -->
        <div class="flex items-center justify-between gap-3 px-6 pt-4">
          <div class="flex items-center gap-2">
            <img :src="LOGO" alt="Bethany Memorial Park"
                 class="h-9 w-9 rounded-lg object-contain bg-white ring-2 ring-white/60 shadow-sm" />
            <div class="text-white/95 font-semibold tracking-tight">Bethany Memorial • Admin</div>
          </div>

          <!-- Right: search + burger -->
          <div class="ml-auto flex items-center gap-2">
            <!-- Command input -->
            <div class="relative hidden md:flex items-center">
              <span class="cmdk-icon absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm10 2-6-6"
                        stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>

              <input
                id="cmdk"
                v-model="query"
                @keyup.enter="go"
                placeholder="Jump to: apps, reservations, interments…"
                class="w-80 max-w-full pl-9 pr-3 py-2 rounded-lg text-sm bg-white border border-white/70
                      focus:outline-none focus:ring-2 focus:ring-[#6E63A6] focus:border-[#6E63A6]" />
            </div>

            <button
              class="px-3 py-2 rounded-lg text-sm font-medium text-[color:var(--ink)]
                    bg-[#F5D146] hover:brightness-95"
              @click="go">
              Go
            </button>

            <!-- Burger -->
            <div class="relative" ref="navRef">
              <button
                type="button" @click.stop="toggleNav"
                class="h-10 w-10 rounded-xl bg-white border border-white/70 grid place-items-center
                      focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#6E63A6]"
                :aria-expanded="showNav ? 'true' : 'false'" aria-haspopup="menu"
                aria-label="Open navigation"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-800" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M3 12h18M3 18h18"/>
                </svg>
              </button>

              <!-- SOLID dropdown (no glass, no backdrop-blur) -->
              <div
                v-show="showNav"
                class="absolute right-0 mt-2 w-64 bg-white border border-gray-200 rounded-2xl shadow-2xl overflow-hidden z-50"
                role="menu"
              >
                <div class="px-3 py-2 text-[11px] uppercase tracking-wide text-gray-500 bg-gray-50">Navigate</div>
                <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(r('admin.applications.index'))">📝 Applications</button>
                <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(r('admin.reservations.index'))">📌 Reservations</button>
                <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(r('admin.interments.index'))">🕯️ Interments</button>
                <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(r('admin.plots.index'))">📦 Plots</button>
                <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(r('admin.announcements.index'))">📜 Announcements</button>
                <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(r('admin.map.gl'))">🗺️ Map (GL)</button>
                <div class="h-px bg-gray-200 my-1"></div>
                <div class="px-3 py-2 text-[11px] uppercase tracking-wide text-gray-500 bg-gray-50">Session</div>
                <button class="w-full text-left px-3 py-2 hover:bg-red-50 text-red-600" @click="logout">🚪 Logout</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Hero Title + slide caption -->
        <div class="flex-1 flex items-end px-6 pb-9">
          <div class="text-white drop-shadow max-w-xl">
            <div class="text-3xl md:text-5xl font-extrabold tracking-tight">Admin Dashboard</div>
            <div class="mt-2 text-sm md:text-base opacity-90">
              Quick navigation, live counters, and recent activity.
            </div>
            <div v-if="slides.length" class="mt-3 text-xs md:text-sm text-white/80">
              <span class="font-semibold">{{ slides[activeSlide].title }} · </span>
              <span>{{ slides[activeSlide].desc }}</span>
            </div>
          </div>
        </div>

        <!-- Hero slideshow controls (bottom-right) -->
        <div
          v-if="slides.length"
          class="absolute bottom-4 right-4 flex items-center gap-3 text-xs text-white/80 z-20"
        >
          <div class="flex items-center gap-1">
            <button
              type="button"
              class="px-2 py-1 rounded-full border border-white/50 bg-black/30 hover:bg-black/45"
              @click.stop="prevSlide"
            >◀</button>
            <button
              type="button"
              class="px-2 py-1 rounded-full border border-white/50 bg-black/30 hover:bg-black/45"
              @click.stop="nextSlide"
            >▶</button>
          </div>
          <div class="flex items-center gap-1">
            <button
              v-for="(s, i) in slides"
              :key="i"
              type="button"
              class="w-2 h-2 rounded-full"
              :class="i === activeSlide ? 'bg-[#F5D146]' : 'bg-white/60'"
              @click.stop="goSlide(i)"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- =========================
         CONTENT
         ========================= -->
    <div class="max-w-7xl mx-auto px-6 py-6">
      <!-- KPI ROW -->
      <section class="grid gap-6 lg:grid-cols-3">
        <!-- Generic KPI Card -->
        <div
          v-for="c in cards" :key="c.title"
          class="group rounded-2xl border border-[rgba(17,24,39,0.08)] bg-white/80 backdrop-blur-sm shadow-sm p-5 transition
                hover:shadow-lg hover:-translate-y-[1px] focus-within:ring-2 focus-within:ring-[color:var(--accent)]/30"
          :style="{ '--accent': c.accent }"
          tabindex="0"
        >
          <!-- Header -->
          <div class="flex items-start justify-between">
            <div>
              <div class="text-xs font-medium tracking-wide text-gray-600 uppercase">{{ c.title }}</div>
              <div class="mt-1 text-3xl font-extrabold text-gray-900">{{ c.total }}</div>
            </div>
            <div class="text-3xl select-none" aria-hidden="true">{{ c.icon }}</div>
          </div>

          <!-- Stacked Ratio Bar -->
          <div class="mt-4">
            <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden ring-1 ring-black/5">
              <div v-for="(p) in c.parts" :key="p.label"
                  class="h-full inline-block"
                  :class="p.color"
                  :style="{ width: (c.total ? (p.v / c.total) * 100 : 0) + '%' }"></div>
            </div>
            <div class="mt-2 grid grid-cols-3 gap-1.5 text-[11px]">
              <div v-for="p in c.parts" :key="p.label"
                  class="rounded-md px-1.5 py-1 text-center font-medium ring-1 ring-black/5"
                  :class="[p.color, p.txt]">
                {{ p.label }}: {{ p.v }} ({{ pct(p.v, c.total) }}%)
              </div>
            </div>
          </div>

          <!-- CTA -->
          <div class="mt-4 flex items-center gap-2">
            <Link
              :href="c.cta.to"
              class="px-3 py-2 rounded-lg text-sm font-semibold text-white
                    bg-[color:var(--accent)] hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[color:var(--accent)]/40"
            >{{ c.cta.text }}</Link>
          </div>
        </div>

        <!-- Plots Card -->
        <div
          class="rounded-2xl border border-[rgba(17,24,39,0.08)] bg-white/80 backdrop-blur-sm shadow-sm p-5 transition
                hover:shadow-lg hover:-translate-y-[1px]"
          :style="{ '--accent': kpiPlots.accent }"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="text-xs font-medium tracking-wide text-gray-600 uppercase">{{ kpiPlots.title }}</div>
              <div class="mt-1 text-3xl font-extrabold text-gray-900">{{ kpiPlots.total }}</div>
            </div>
            <div class="text-3xl select-none" aria-hidden="true">{{ kpiPlots.icon }}</div>
          </div>

          <div class="mt-4">
            <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden ring-1 ring-black/5">
              <div v-for="p in kpiPlots.parts" :key="p.label" class="h-full inline-block"
                  :class="p.color" :style="{ width: (kpiPlots.total ? (p.v / kpiPlots.total) * 100 : 0) + '%' }"></div>
            </div>
            <div class="mt-2 grid grid-cols-3 gap-1.5 text-[11px]">
              <div v-for="p in kpiPlots.parts" :key="p.label"
                  class="rounded-md px-1.5 py-1 text-center font-medium ring-1 ring-black/5"
                  :class="[p.color, p.txt]">
                {{ p.label }}: {{ p.v }} ({{ pct(p.v, kpiPlots.total) }}%)
              </div>
            </div>
            <div class="mt-2 text-[12px] inline-flex items-center gap-1 px-2 py-1 rounded-md"
                :class="[kpiPlots.foot.color, kpiPlots.foot.txt]">
              <span class="font-medium">{{ kpiPlots.foot.label }}:</span> {{ kpiPlots.foot.v }}
            </div>
          </div>

          <div class="mt-4 flex flex-wrap items-center gap-2">
            <Link :href="kpiPlots.cta.to"
                  class="px-3 py-2 rounded-lg text-sm font-semibold text-white bg-[color:var(--accent)] hover:brightness-110">
              {{ kpiPlots.cta.text }}
            </Link>
            <Link :href="r('admin.map.gl')" class="px-3 py-2 rounded-lg bg-white border text-sm hover:bg-gray-50">
              Map (GL)
            </Link>
          </div>
        </div>
      </section>

      <!-- QUICK ACTIONS + RECENTS -->
      <section class="mt-8 grid gap-6 md:grid-cols-3">
        <!-- Quick Actions -->
        <div class="rounded-2xl border border-[rgba(17,24,39,0.08)] bg-white/80 backdrop-blur-sm shadow-sm p-5">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-[#6E63A6]">Quick Actions</h3>
          </div>
          <div class="grid gap-2">
            <Link :href="r('admin.announcements.index')" class="px-3 py-2 rounded-lg border bg-white text-sm hover:bg-gray-50 text-left">
              📜 Manage Announcements
            </Link>
            <Link :href="r('admin.applications.index')" class="px-3 py-2 rounded-lg border bg-white text-sm hover:bg-gray-50 text-left">
              📝 View Applications
            </Link>
            <Link :href="r('admin.reservations.index')" class="px-3 py-2 rounded-lg border bg-white text-sm hover:bg-gray-50 text-left">
              📌 Manage Reservations
            </Link>
            <Link :href="r('admin.interments.index')" class="px-3 py-2 rounded-lg border bg-white text-sm hover:bg-gray-50 text-left">
              🕯️ Interment Bookings
            </Link>
            <Link :href="r('admin.map.gl')" class="px-3 py-2 rounded-lg border bg-white text-sm hover:bg-gray-50 text-left">
              🗺️ Open Map (GL)
            </Link>
            <Link :href="r('admin.plots.index')" class="px-3 py-2 rounded-lg border bg-white text-sm hover:bg-gray-50 text-left">
              📦 Plots Inventory
            </Link>
          </div>
          <button
            type="button"
            class="mt-3 inline-flex sm:hidden items-center gap-1 text-xs px-3 py-1.5 rounded-full bg-[#4FA07F]/10 text-[#4FA07F] hover:bg-[#4FA07F]/20"
            @click="openAnnModal"
          >
            ✏️ New Announcement
          </button>
        </div>

        <!-- Recent Applications -->
        <div class="rounded-2xl border border-[rgba(17,24,39,0.08)] bg-white/80 backdrop-blur-sm shadow-sm p-5">
          <h3 class="font-semibold text-[#6E63A6] mb-3">Recent Applications</h3>
          <ul class="divide-y">
            <li v-for="a in recentApplications" :key="a.id" class="py-2.5 text-sm">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 min-w-0">
                  <div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-800 grid place-items-center text-xs font-bold">
                    {{ (a.applicant_name || '?').slice(0,1).toUpperCase() }}
                  </div>
                  <div class="truncate">
                    <span class="font-medium text-gray-900">{{ a.applicant_name }}</span>
                    <span class="text-gray-500"> • {{ a.service_type }}</span>
                  </div>
                </div>
                <span
                  class="ml-3 px-2 py-0.5 rounded-full text-[11px] font-medium"
                  :class="{
                    'bg-amber-100 text-amber-900': a.status === 'pending',
                    'bg-violet-600 text-white': a.status === 'approved',
                    'bg-rose-500 text-white': a.status === 'denied'
                  }"
                >
                  {{ a.status }}
                </span>
              </div>
              <div class="text-[11px] text-gray-500 mt-0.5">
                #{{ a.id }} • {{ a.created_at?.slice(0,10) }}
                <span v-if="a.assigned_plot_id" class="ml-1">• Plot {{ a.assigned_plot_id }}</span>
              </div>
            </li>
            <li v-if="!recentApplications.length" class="text-sm text-gray-500 py-6 text-center">
              No recent applications.
            </li>
          </ul>
        </div>

        <!-- Recent Reservations -->
        <div class="rounded-2xl border border-[rgba(17,24,39,0.08)] bg-white/80 backdrop-blur-sm shadow-sm p-5">
          <h3 class="font-semibold text-[#6E63A6] mb-3">Recent Reservations</h3>
          <ul class="divide-y">
            <li v-for="rsv in recentReservations" :key="rsv.id" class="py-2.5 text-sm">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 min-w-0">
                  <div class="w-7 h-7 rounded-full bg-amber-100 text-amber-900 grid place-items-center text-xs font-bold">
                    {{ (rsv.reserved_by_name || '?').slice(0,1).toUpperCase() }}
                  </div>
                  <div class="truncate">
                    <span class="font-medium text-gray-900">{{ rsv.reserved_by_name }}</span>
                    <span class="text-gray-500">
                      • Plot {{ rsv.plot?.lot_number ?? rsv.plot_id }}
                    </span>
                  </div>
                </div>
                <span
                  class="ml-3 px-2 py-0.5 rounded-full text-[11px] font-medium"
                  :class="{
                    'bg-slate-200 text-slate-800': rsv.status === 'pending',
                    'bg-amber-100 text-amber-900': rsv.status === 'reserved',
                    'bg-emerald-600 text-white': rsv.status === 'confirmed',
                    'bg-rose-500 text-white': rsv.status === 'cancelled' || rsv.status === 'expired',
                  }"
                >
                  {{ rsv.status }}
                </span>
              </div>
              <div class="text-[11px] text-gray-500 mt-0.5">
                #{{ rsv.id }} • {{ rsv.start_date || '—' }} → {{ rsv.expires_at || '—' }}
              </div>
            </li>
            <li v-if="!recentReservations.length" class="text-sm text-gray-500 py-6 text-center">
              No recent reservations.
            </li>
          </ul>
        </div>
      </section>
    </div>

    <!-- =========================
         ANNOUNCEMENTS MODAL
         ========================= -->
    <transition enter-active-class="duration-150 ease-out" enter-from-class="opacity-0" leave-active-class="duration-150 ease-in" leave-to-class="opacity-0">
      <div v-if="showAnn" class="fixed inset-0 z-50 bg-black/30 flex items-end sm:items-center justify-center p-4">
        <div class="w-full max-w-3xl rounded-2xl bg-white/98 backdrop-blur shadow-2xl border border-[rgba(17,24,39,0.08)] overflow-hidden">
          <div class="h-1.5 w-full" :style="{ background:`linear-gradient(90deg, ${C.green}, ${C.yellow})` }"></div>

          <div class="p-5 grid gap-5 md:grid-cols-2">
            <!-- Editor -->
            <div>
              <div class="flex items-start justify-between mb-2">
                <div>
                  <h3 class="text-xl font-semibold">New Announcement</h3>
                  <p class="text-sm text-gray-600">Publish a news item, event, or notice.</p>
                </div>
                <button class="p-2 rounded-lg hover:bg-gray-100" @click="closeAnnModal" aria-label="Close">✕</button>
              </div>

              <form @submit.prevent="submitAnnouncement" class="grid gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                  <input v-model="annForm.title" :maxlength="titleMax" type="text"
                        class="w-full rounded-lg border px-3 py-2 text-sm border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#4FA07F] focus:border-[#4FA07F]" />
                  <div class="mt-1 text-[11px] text-gray-500">{{ titleChars }} / {{ titleMax }}</div>
                  <p v-if="annForm.errors.title" class="text-sm text-red-600 mt-1">{{ annForm.errors.title }}</p>
                </div>

                <div class="grid sm:grid-cols-3 gap-3">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select v-model="annForm.category" class="w-full rounded-lg border px-3 py-2 text-sm border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#4FA07F] focus:border-[#4FA07F]">
                      <option v-for="c in annCats" :key="c.value" :value="c.value">{{ c.label }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input v-model="annForm.date" type="date" class="w-full rounded-lg border px-3 py-2 text-sm border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#4FA07F] focus:border-[#4FA07F]" />
                  </div>
                  <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm">
                      <input type="checkbox" v-model="annForm.pinned" class="rounded border-gray-300 text-[#4FA07F] focus:ring-[#4FA07F]" />
                      Pin to top
                    </label>
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Optional Link (URL)</label>
                  <input v-model="annForm.href" type="url" placeholder="https://example.com/details"
                        class="w-full rounded-lg border px-3 py-2 text-sm border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#4FA07F] focus:border-[#4FA07F]" />
                  <p v-if="annForm.errors.href" class="text-sm text-red-600 mt-1">{{ annForm.errors.href }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Body</label>
                  <textarea v-model="annForm.body" :maxlength="bodyMax" rows="7"
                          class="w-full rounded-lg border px-3 py-2 text-sm border-gray-300 focus:outline-none focus:ring-2 focus:ring-[#4FA07F] focus:border-[#4FA07F]"
                          placeholder="Write the announcement…"></textarea>
                  <div class="mt-1 text-[11px] text-gray-500">{{ bodyChars }} / {{ bodyMax }}</div>
                  <p v-if="annForm.errors.body" class="text-sm text-red-600 mt-1">{{ annForm.errors.body }}</p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                  <button type="button" @click="closeAnnModal" class="px-3 py-2 rounded-lg border hover:bg-gray-50">Cancel</button>
                  <button type="submit" :disabled="annForm.processing"
                          class="px-4 py-2 rounded-lg text-white font-semibold bg-[#4FA07F] hover:brightness-110 disabled:opacity-60">
                    {{ annForm.processing ? 'Publishing…' : 'Publish' }}
                  </button>
                </div>
              </form>
            </div>

            <!-- Live Preview -->
            <div class="hidden md:block">
              <div class="text-sm font-medium text-gray-700 mb-2">Preview</div>
              <article class="rounded-2xl border border-gray-200 bg-white/80 backdrop-blur p-4 shadow-sm">
                <div class="flex items-center justify-between">
                  <div class="text-xs uppercase tracking-wide text-gray-500">
                    {{ (annCats.find(c=>c.value===annForm.category)?.label) || 'News' }} • {{ annForm.date || '—' }}
                  </div>
                  <div v-if="annForm.pinned" class="text-[11px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 ring-1 ring-black/5">Pinned</div>
                </div>
                <h4 class="mt-2 font-semibold text-gray-900 truncate">{{ annForm.title || 'Untitled announcement' }}</h4>
                <p class="mt-1 text-sm text-gray-600 line-clamp-5 whitespace-pre-line">{{ annForm.body || 'Start writing to see a live preview…' }}</p>
                <a v-if="annForm.href" :href="annForm.href" target="_blank" class="mt-3 inline-flex items-center gap-1 text-[13px] text-[#4FA07F] hover:underline">
                  Open link
                  <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"><path d="M7 17L17 7M17 7H9M17 7v8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </a>
              </article>
              <p class="mt-2 text-[11px] text-gray-500">This is an approximation of how it will look in the list.</p>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Toast -->
    <transition enter-active-class="duration-150 ease-out"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2">
      <div v-if="toast.show"
            class="fixed bottom-4 right-4 z-50 rounded-xl px-4 py-3 text-sm shadow-lg border
                  bg-white"
            :class="toast.kind==='success' ? 'border-emerald-200 text-emerald-800'
                                          : 'border-rose-200 text-rose-700'"
            role="status" aria-live="polite">
        <span v-if="toast.kind==='success'">✅</span>
        <span v-else>⚠️</span>
        <span class="ml-2">{{ toast.text }}</span>
      </div>
    </transition>
  </div>
</template>

<style scoped>
* {
  transition: background-color .18s ease, color .18s ease,
              box-shadow .18s ease, border-color .18s ease,
              transform .18s ease;
}
@media (prefers-reduced-motion: reduce) { * { transition: none !important; } }

/* Hero rounding on small screens */
@media (max-width: 640px) {
  .rounded-b-\[2rem\] {
    border-bottom-left-radius: 1.25rem;
    border-bottom-right-radius: 1.25rem;
  }
}

/* Clamp helper for preview text */
.line-clamp-5 {
  display: -webkit-box;
  -webkit-line-clamp: 5;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Focus ring for keyboard nav */
:focus-visible {
  outline: 2px solid rgba(110, 99, 166, .45);
  outline-offset: 2px;
}

svg { width: auto; height: auto; }
.cmdk-icon svg { width: 1rem; height: 1rem; }
.cmdk-icon { pointer-events: none; }
#cmdk { position: relative; z-index: 1; }
</style>
