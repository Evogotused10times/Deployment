<script setup>
import { ref, reactive, watch, computed } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
  items:   { type: Array,  default: () => [] },
  filters: { type: Object, default: () => ({ q: '' }) },
  flash:   { type: Object, default: () => ({ success:null, error:null }) },
})

/* ---------- search ---------- */
const q = ref(props.filters.q || '')
function doSearch() {
  router.get(route('admin.announcements.index'), { q: q.value }, {
    preserveState: true,
    replace: true,
    preserveScroll: true,
  })
}

/* ---------- simple stats for header chips ---------- */
const stats = computed(() => {
  const total     = props.items.length
  const published = props.items.filter(a => a.is_published).length
  const pinned    = props.items.filter(a => a.pinned).length
  const drafts    = total - published
  return { total, published, drafts, pinned }
})

/* ---------- toast ---------- */
const page = usePage()
const toast = reactive({ show:false, text:'', kind:'success' })

const getFlash = () => {
  const p = page?.props?.value ?? page?.props ?? {}
  return p.flash ?? props.flash ?? { success:null, error:null }
}

watch(() => getFlash().success, (msg) => {
  if (!msg) return
  Object.assign(toast, { show:true, text: msg, kind:'success' })
  setTimeout(() => (toast.show = false), 2200)
}, { immediate: true })

watch(() => getFlash().error, (msg) => {
  if (!msg) return
  Object.assign(toast, { show:true, text: msg, kind:'error' })
  setTimeout(() => (toast.show = false), 2500)
}, { immediate: true })

/* ---------- create modal ---------- */
const showNew = ref(false)
const form = useForm({
  title: '',
  body: '',
  category: 'news',
  pinned: false,
  publish_now: true,
})
function submitNew() {
  form.post(route('admin.announcements.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('title','body','pinned')
      showNew.value = false
      Object.assign(toast, { show:true, text:'Announcement created.', kind:'success' })
      setTimeout(() => (toast.show = false), 1800)
    },
  })
}

/* ---------- inline actions ---------- */
function togglePublish(id) {
  router.post(route('admin.announcements.toggle-publish', id), {}, {
    preserveScroll: true,
    preserveState: true,
  })
}
function togglePin(id) {
  router.post(route('admin.announcements.toggle-pin', id), {}, {
    preserveScroll: true,
    preserveState: true,
  })
}
function destroyItem(id) {
  if (!confirm('Delete this announcement?')) return
  router.delete(route('admin.announcements.destroy', id), {
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

      <!-- Header card -->
      <section class="rounded-2xl border border-slate-200 bg-white/90 backdrop-blur shadow-sm px-4 sm:px-6 py-4 sm:py-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <nav class="flex items-center gap-1 text-xs text-slate-500 mb-1">
              <button
                type="button"
                class="hover:underline hover:text-slate-700"
                @click="router.visit(route('admin.dashboard'))"
              >
                Admin
              </button>
              <span>/</span>
              <span class="text-slate-700 font-medium">Announcements</span>
            </nav>

            <div class="flex flex-wrap items-center gap-3">
              <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">
                Announcements
              </h1>
              <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Live on public site
              </span>
            </div>

            <p class="mt-1 text-sm text-slate-600">
              Create news, events, and notices that appear on the public announcements page.
            </p>

            <!-- stats chips -->
            <div class="mt-3 flex flex-wrap gap-2 text-xs">
              <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-800 border border-slate-200 px-2 py-0.5">
                Total: <span class="ml-1 font-semibold">{{ stats.total }}</span>
              </span>
              <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5">
                Published: <span class="ml-1 font-semibold">{{ stats.published }}</span>
              </span>
              <span class="inline-flex items-center rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5">
                Drafts: <span class="ml-1 font-semibold">{{ stats.drafts }}</span>
              </span>
              <span class="inline-flex items-center rounded-full bg-violet-50 text-violet-700 border border-violet-200 px-2 py-0.5">
                Pinned: <span class="ml-1 font-semibold">{{ stats.pinned }}</span>
              </span>
            </div>
          </div>

          <!-- search / actions -->
          <!-- search / actions -->
<div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 mt-2 sm:mt-0">
  <!-- left: back + search -->
  <div class="flex items-center gap-2">
    <!-- Back to dashboard button -->
    <button
      type="button"
      @click="router.visit(route('admin.dashboard'))"
      class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs sm:text-sm font-medium text-slate-700 hover:bg-slate-50"
      title="Back to Admin Dashboard"
    >
      ← Dashboard
    </button>

    <div class="relative w-full sm:w-64">
      <span class="pointer-events-none absolute inset-y-0 left-2 flex items-center text-slate-400 text-xs">
        🔎
      </span>
      <input
        v-model="q"
        @keyup.enter="doSearch"
        type="search"
        placeholder="Search title or category…"
        class="w-full rounded-lg border border-slate-300 bg-white pl-7 pr-3 py-2 text-sm
               focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
      />
    </div>

    <button
      @click="doSearch"
      type="button"
      class="hidden sm:inline-flex items-center justify-center rounded-lg bg-violet-600 px-3 py-2 text-sm font-semibold text-white hover:brightness-110"
    >
      Search
    </button>
  </div>

  <!-- right: new announcement -->
  <div class="flex items-center gap-2 justify-end">
    <button
      @click="showNew = true"
      type="button"
      class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 sm:px-4 py-2 text-sm font-semibold text-white shadow-sm hover:brightness-110"
    >
      + New announcement
    </button>
  </div>
</div>

        </div>
      </section>

      <!-- Table card -->
      <main class="rounded-2xl border border-slate-200 bg-white/95 shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 px-4 sm:px-6 py-2.5 flex items-center justify-between text-xs text-slate-500">
          <span>Showing {{ props.items.length || 0 }} announcements</span>
          <span class="hidden sm:inline">Click “Publish” to toggle visibility on the public site.</span>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50/80">
              <tr class="text-left text-slate-600">
                <th class="px-4 sm:px-6 py-3 font-medium">Title</th>
                <th class="px-4 sm:px-6 py-3 font-medium">Category</th>
                <th class="px-4 sm:px-6 py-3 font-medium">Pinned</th>
                <th class="px-4 sm:px-6 py-3 font-medium">Status</th>
                <th class="px-4 sm:px-6 py-3 font-medium">Date</th>
                <th class="px-4 sm:px-6 py-3 font-medium text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(a, idx) in props.items"
                :key="a.id"
                class="border-t border-slate-100 hover:bg-slate-50/60 transition-colors"
                :class="idx % 2 ? 'bg-white' : 'bg-slate-50/20'"
              >
                <!-- title + slug -->
                <td class="px-4 sm:px-6 py-3 align-top">
                  <div class="flex flex-col gap-0.5">
                    <div class="font-medium text-slate-900 line-clamp-2">
                      {{ a.title }}
                    </div>
                    <div class="text-[11px] text-slate-500 line-clamp-1">
                      {{ a.href }}
                    </div>
                  </div>
                </td>

                <!-- category -->
                <td class="px-4 sm:px-6 py-3 align-top">
                  <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium capitalize"
                    :class="{
                      'bg-sky-50 text-sky-700 border border-sky-200': a.category === 'news',
                      'bg-amber-50 text-amber-700 border border-amber-200': a.category === 'event',
                      'bg-rose-50 text-rose-700 border border-rose-200': a.category === 'notice',
                    }"
                  >
                    {{ a.category || '—' }}
                  </span>
                </td>

                <!-- pinned -->
                <td class="px-4 sm:px-6 py-3 align-top">
                  <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium border"
                    :class="a.pinned
                      ? 'bg-violet-50 text-violet-700 border-violet-200'
                      : 'bg-slate-100 text-slate-700 border-slate-200'"
                  >
                    <span
                      class="mr-1 inline-block h-1.5 w-1.5 rounded-full"
                      :class="a.pinned ? 'bg-violet-500' : 'bg-slate-400'"
                    ></span>
                    {{ a.pinned ? 'Pinned' : 'Not pinned' }}
                  </span>
                </td>

                <!-- published / draft -->
                <td class="px-4 sm:px-6 py-3 align-top">
                  <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium border"
                    :class="a.is_published
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : 'bg-slate-100 text-slate-700 border-slate-200'"
                  >
                    <span
                      class="mr-1 inline-block h-1.5 w-1.5 rounded-full"
                      :class="a.is_published ? 'bg-emerald-500' : 'bg-slate-400'"
                    ></span>
                    {{ a.is_published ? 'Published' : 'Draft' }}
                  </span>
                </td>

                <!-- date -->
                <td class="px-4 sm:px-6 py-3 align-top whitespace-nowrap text-slate-700">
                  {{ a.date ? new Date(a.date).toLocaleDateString() : '—' }}
                </td>

                <!-- actions -->
                <td class="px-4 sm:px-6 py-3 align-top">
                  <div class="flex flex-wrap justify-end gap-1.5">
                    <a
                      :href="a.href"
                      target="_blank"
                      class="inline-flex items-center rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-50"
                    >
                      View
                    </a>

                    <button
                      type="button"
                      @click="togglePin(a.id)"
                      class="inline-flex items-center rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-50"
                    >
                      {{ a.pinned ? 'Unpin' : 'Pin' }}
                    </button>

                    <button
                      type="button"
                      @click="togglePublish(a.id)"
                      class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium text-white"
                      :class="a.is_published
                        ? 'bg-slate-700 hover:brightness-105'
                        : 'bg-emerald-600 hover:brightness-110'"
                    >
                      {{ a.is_published ? 'Unpublish' : 'Publish' }}
                    </button>

                    <!-- Quick save keeps minimal data; mostly stylistic -->
                    <Link
                      :href="route('admin.announcements.update', a.id)"
                      method="patch"
                      as="button"
                      :data="{ title: a.title, body:'', category:a.category, pinned:a.pinned }"
                      class="inline-flex items-center rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-50"
                    >
                      Quick Save
                    </Link>

                    <button
                      type="button"
                      @click="destroyItem(a.id)"
                      class="inline-flex items-center rounded-lg bg-rose-500 px-2.5 py-1 text-xs font-medium text-white hover:brightness-110"
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="!props.items.length">
                <td colspan="6" class="px-4 sm:px-6 py-10 text-center text-slate-500">
                  <div class="inline-flex flex-col items-center gap-2">
                    <div class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-xl">
                      📰
                    </div>
                    <p class="text-sm">No announcements yet.</p>
                    <button
                      type="button"
                      @click="showNew = true"
                      class="mt-1 inline-flex items-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:brightness-110"
                    >
                      + Create your first announcement
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </main>
    </div>

    <!-- Create modal -->
    <transition
      enter-active-class="duration-150 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="showNew"
        class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center p-4"
      >
        <div class="w-full max-w-2xl rounded-2xl bg-white border border-slate-200 shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
          <div class="h-1.5 bg-gradient-to-r from-emerald-500 via-violet-500 to-amber-400"></div>

          <div class="px-5 pt-4 pb-2 flex items-start justify-between gap-2">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">New Announcement</h3>
              <p class="text-xs sm:text-sm text-slate-600">
                Announcements appear on the public site. You can pin and publish them at any time.
              </p>
            </div>
            <button
              type="button"
              @click="showNew = false"
              class="p-2 rounded-full hover:bg-slate-100 text-slate-500"
              aria-label="Close"
            >
              ✕
            </button>
          </div>

          <form
            @submit.prevent="submitNew"
            class="px-5 pb-4 pt-1 space-y-4 overflow-y-auto"
          >
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Title
              </label>
              <input
                v-model="form.title"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
              />
              <p v-if="form.errors.title" class="text-xs text-rose-600 mt-1">
                {{ form.errors.title }}
              </p>
            </div>

            <div class="grid sm:grid-cols-3 gap-3">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                  Category
                </label>
                <select
                  v-model="form.category"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
                >
                  <option value="news">News</option>
                  <option value="event">Event</option>
                  <option value="notice">Notice</option>
                </select>
              </div>
              <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                  <input
                    type="checkbox"
                    v-model="form.pinned"
                    class="rounded border-slate-300 text-violet-600 focus:ring-violet-600"
                  >
                  Pin to top
                </label>
              </div>
              <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                  <input
                    type="checkbox"
                    v-model="form.publish_now"
                    class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-600"
                  >
                  Publish immediately
                </label>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Body
              </label>
              <textarea
                v-model="form.body"
                rows="6"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
                placeholder="Write the announcement…"
              ></textarea>
              <p v-if="form.errors.body" class="text-xs text-rose-600 mt-1">
                {{ form.errors.body }}
              </p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
              <button
                type="button"
                @click="showNew = false"
                class="px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm hover:bg-slate-50"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="form.processing"
                class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-emerald-600 hover:brightness-110 disabled:opacity-60"
              >
                {{ form.processing ? 'Saving…' : 'Save announcement' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </transition>

    <!-- Toast -->
    <transition
      enter-active-class="duration-150 ease-out"
      enter-from-class="opacity-0 translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-2"
    >
      <div
        v-if="toast.show"
        class="fixed bottom-4 right-4 z-50 rounded-xl px-4 py-3 text-sm shadow-lg border flex items-center"
        :class="toast.kind==='success'
          ? 'bg-white border-emerald-200 text-emerald-800'
          : 'bg-white border-rose-200 text-rose-700'"
      >
        <span v-if="toast.kind==='success'">✅</span>
        <span v-else>⚠️</span>
        <span class="ml-2">{{ toast.text }}</span>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.line-clamp-1 {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  overflow: hidden;
  -webkit-line-clamp: 1;
}
</style>
