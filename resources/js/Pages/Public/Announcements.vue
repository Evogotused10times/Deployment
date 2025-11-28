<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Megaphone, Calendar, Pin, ExternalLink, X, ArrowLeft } from 'lucide-vue-next'

const props = defineProps({
  items: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

/* ---------------------- URL <-> state sync ---------------------- */
const initialUrl = typeof window !== 'undefined' ? new URL(window.location.href) : null
const q = ref((initialUrl?.searchParams.get('q') || '').trim())
const tab = ref((initialUrl?.searchParams.get('tab') || 'all').toLowerCase()) // 'all' | 'pinned' | 'news' | 'event' | 'notice'

function syncUrl() {
  if (typeof window === 'undefined') return
  const u = new URL(window.location.href)
  if (q.value) u.searchParams.set('q', q.value); else u.searchParams.delete('q')
  if (tab.value && tab.value !== 'all') u.searchParams.set('tab', tab.value); else u.searchParams.delete('tab')
  window.history.replaceState({}, '', u.toString())
}
watch([q, tab], syncUrl, { immediate: true })

/* ---------------------- keyboard helpers ---------------------- */
const searchRef = ref(null)
const onKey = (e) => {
  if (e.key === '/' && !e.metaKey && !e.ctrlKey && !e.altKey) {
    e.preventDefault()
    searchRef.value?.focus()
  }
  if (e.key === 'Escape' && document.activeElement === searchRef.value) {
    q.value = ''
  }
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))

/* ---------------------- data shaping ---------------------- */
const normalized = computed(() =>
  props.items.map(a => ({
    ...a,
    dateObj: a?.date ? new Date(a.date) : null,
    category: (a?.category || 'news').toLowerCase(),
    href: a?.href || null,
  }))
)

const filtered = computed(() => {
  const term = q.value.trim().toLowerCase()
  return normalized.value
    .filter(a =>
      tab.value === 'all'
        ? true
        : tab.value === 'pinned'
          ? !!a.pinned
          : a.category === tab.value
    )
    .filter(a =>
      !term ||
      (a.title?.toLowerCase().includes(term)) ||
      (a.body?.toLowerCase().includes(term))
    )
    .sort((a, b) =>
      Number(b.pinned) - Number(a.pinned) ||
      (b.dateObj?.getTime() || 0) - (a.dateObj?.getTime() || 0)
    )
})

/* counts for tab badges */
const tabCounts = computed(() => {
  const base = { all: normalized.value.length, pinned: 0, news: 0, event: 0, notice: 0 }
  normalized.value.forEach(a => {
    if (a.pinned) base.pinned++
    if (base[a.category] != null) base[a.category]++
  })
  return base
})

/* ---------------------- UI helpers ---------------------- */
const chip = (c) => ({
  news:   'bg-[#F5D146]/15 text-[#7A5B43] ring-1 ring-[#F5D146]/30',
  event:  'bg-[#EFFEFA] text-[#23785E] ring-1 ring-[#4FA07F]/30',
  notice: 'bg-[#6E63A6]/10 text-[#453B7C] ring-1 ring-[#6E63A6]/25',
}[c] || 'bg-gray-50 text-gray-700 ring-1 ring-gray-200')

const fmt = new Intl.DateTimeFormat(undefined, { year:'numeric', month:'short', day:'2-digit' })
const prettyDate = (d) => (d instanceof Date && !isNaN(d)) ? fmt.format(d) : ''

function goBack(e) {
  e?.preventDefault?.()
  if (typeof window !== 'undefined' && window.history.length > 1) window.history.back()
  else window.location.href = '/'
}

const tabs = [
  { key: 'all',    label: 'All' },
  { key: 'pinned', label: 'Pinned' },
  { key: 'news',   label: 'News' },
  { key: 'event',  label: 'Events' },
  { key: 'notice', label: 'Notices' },
]
</script>

<template>
  <section class="relative">
    <!-- soft background -->
    <div class="absolute inset-0 -z-10 bg-gradient-to-b from-[#F7FCF9] via-white to-[#F3F4F6]"></div>

    <!-- top nav bar -->
    <nav class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-black/5">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-11 flex items-center justify-between">
        <button
          @click="goBack"
          class="inline-flex items-center gap-2 text-[#4FA07F] hover:text-[#3A7C63] hover:bg-[#EFFEFA] px-3 py-1.5 rounded-md text-xs sm:text-sm font-medium transition"
          aria-label="Go back"
        >
          <ArrowLeft class="w-4 h-4" />
          Back
        </button>

        <div class="flex items-center gap-2">
          <span class="hidden sm:inline text-xs text-gray-500">Public announcements</span>
          <Link
            v-if="canManage"
            href="/admin/announcements"
            class="hidden sm:inline-flex items-center gap-1.5 rounded-full border border-[#6E63A6]/25 bg-[#6E63A6]/5 px-3 py-1 text-[11px] font-medium text-[#453B7C] hover:bg-[#6E63A6]/10"
          >
            <Megaphone class="w-3.5 h-3.5" />
            Admin manage
          </Link>
        </div>
      </div>
    </nav>

    <!-- header + search + tabs -->
    <header class="sticky top-11 z-20 backdrop-blur bg-white/85 border-b border-[#4FA07F]/10">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 space-y-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p class="inline-flex items-center gap-2 rounded-full bg-[#EFFEFA] px-3 py-1 text-[11px] font-medium text-[#23785E] border border-[#4FA07F]/30">
              <Megaphone class="w-3.5 h-3.5" />
              Campus / memorial park updates
            </p>
            <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-gray-900">
              Announcements
            </h1>
            <p class="mt-1 text-sm text-gray-600">
              Browse news, events, and important notices about our memorial park.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <div class="relative">
              <input
                ref="searchRef"
                v-model="q"
                type="search"
                inputmode="search"
                placeholder="Search announcements…  (press /)"
                class="w-72 max-w-[75vw] rounded-lg border border-[rgba(79,160,127,0.28)] bg-white/95 px-3 py-2 text-sm
                       focus:outline-none focus:ring-2 focus:ring-[#4FA07F] focus:border-[#4FA07F] placeholder:text-gray-400 shadow-sm"
                aria-label="Search announcements"
              />
              <button
                v-if="q"
                @click="q=''"
                class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600"
                aria-label="Clear search"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>

        <!-- filter tabs -->
        <div class="flex flex-wrap gap-2 text-xs sm:text-sm" role="tablist" aria-label="Filter announcements by type">
          <button
            v-for="t in tabs"
            :key="t.key"
            @click="tab = t.key"
            :aria-selected="tab === t.key"
            role="tab"
            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 ring-1 transition"
            :class="[
              tab === t.key
                ? 'bg-[#EFFEFA] text-[#23785E] ring-[#4FA07F]/50 shadow-sm'
                : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50'
            ]"
          >
            <span>{{ t.label }}</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-black/5 text-gray-500">
              {{ tabCounts[t.key] ?? 0 }}
            </span>
          </button>
        </div>
      </div>
    </header>

    <!-- main content -->
    <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
      <!-- loading skeletons -->
      <div v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="i in 6"
          :key="i"
          class="h-40 rounded-2xl bg-gray-100/80 animate-pulse"
        ></div>
      </div>

      <!-- cards -->
      <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="a in filtered"
          :key="a.id || a.title"
          class="group rounded-2xl border border-[rgba(79,160,127,0.18)] bg-white/95 backdrop-blur p-5 shadow-sm hover:shadow-md hover:-translate-y-[1px] transition h-full"
          :class="a.pinned ? 'ring-1 ring-[#4FA07F]/40' : ''"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="flex flex-col gap-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span
                  v-if="a.pinned"
                  class="inline-flex items-center gap-1 text-[#4FA07F] text-[11px] font-semibold"
                >
                  <Pin class="w-3.5 h-3.5" /> Pinned
                </span>
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                  :class="chip(a.category)"
                >
                  {{ a.category || 'news' }}
                </span>
              </div>
            </div>

            <time
              v-if="a.dateObj"
              class="text-[11px] text-gray-500 inline-flex items-center gap-1 whitespace-nowrap"
            >
              <Calendar class="w-3.5 h-3.5" />
              {{ prettyDate(a.dateObj) }}
            </time>
          </div>

          <h2 class="mt-3 text-lg font-semibold leading-snug line-clamp-2">
            <template v-if="a.href">
              <a
                :href="a.href"
                class="hover:underline decoration-[#6E63A6]/40 decoration-2 underline-offset-2"
              >
                {{ a.title }}
              </a>
            </template>
            <template v-else>{{ a.title }}</template>
          </h2>

          <p class="mt-1.5 text-sm text-gray-600 line-clamp-3">
            {{ a.body }}
          </p>

          <div class="mt-4 flex items-center justify-between">
            <a
              v-if="a.href"
              :href="a.href"
              class="inline-flex items-center gap-1.5 text-[#6E63A6] text-sm font-medium group/link"
            >
              Read more
              <ExternalLink class="w-4 h-4 group-hover/link:translate-x-0.5 transition-transform" />
            </a>
            <span
              v-else
              class="inline-flex items-center gap-1.5 text-gray-400 text-xs"
            >
              No external link
            </span>

            <span v-if="a.is_published === false" class="text-[11px] px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
              Draft
            </span>
          </div>
        </article>
      </div>

      <!-- empty states -->
      <div v-if="!loading && filtered.length === 0" class="mt-10">
        <div class="mx-auto max-w-3xl">
          <div class="rounded-2xl border border-dashed border-[rgba(79,160,127,0.35)] bg-white/80 p-10 text-center">
            <Megaphone class="w-7 h-7 mx-auto text-[#4FA07F]" />
            <h3 class="mt-3 text-lg font-semibold text-gray-900">
              {{ q ? 'No announcements match your search' : 'No announcements yet' }}
            </h3>
            <p class="text-sm text-gray-600 mt-1">
              {{ q ? 'Try a different search term or clear your filters.' : 'Check back soon for updates and news.' }}
            </p>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
              <button
                v-if="q || tab !== 'all'"
                @click="q=''; tab='all'"
                class="px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white hover:bg-gray-50"
              >
                Clear search & filters
              </button>
              <Link
                v-if="canManage"
                href="/admin/announcements"
                class="px-3 py-2 text-sm rounded-lg border border-[#4FA07F] text-[#4FA07F] bg-[#EFFEFA] hover:bg-[#D9F7EC] inline-flex items-center gap-2"
              >
                <Megaphone class="w-4 h-4" />
                Add the first announcement
              </Link>
            </div>
          </div>
        </div>
      </div>
    </main>
  </section>
</template>

<style scoped>
.line-clamp-2,
.line-clamp-3 {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.line-clamp-2 { -webkit-line-clamp: 2; }
.line-clamp-3 { -webkit-line-clamp: 3; }
</style>
