<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

const PLOTS_BASE = '/admin/plots'

const props = defineProps({
  sections: { type: Array, required: true },
  plots:    { type: Object, required: true },
})

const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
if (csrf) axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf

/* -------------------- Filters / Search -------------------- */
const filters = reactive({
  status: '',
  section_id: '',
  q: '',
})

const initialized = ref(false)
const paramsFromUrl = new URLSearchParams(window.location.search)

filters.status     = paramsFromUrl.get('status')     || ''
filters.section_id = paramsFromUrl.get('section_id') || ''
filters.q          = paramsFromUrl.get('q')          || ''

initialized.value = true

function applyFilters(pageUrl = null) {
  const params = {}

  if (filters.status)     params.status     = filters.status
  if (filters.section_id) params.section_id = filters.section_id
  if (filters.q)          params.q          = filters.q

  const url = pageUrl || '/admin/plots'

  router.visit(url, {
    method: 'get',
    data: params,
    preserveState: true,
    preserveScroll: true,
  })
}

watch(
  () => filters.status,
  () => {
    if (!initialized.value) return
    applyFilters()
  }
)

watch(
  () => filters.section_id,
  () => {
    if (!initialized.value) return
    applyFilters()
  }
)

/* -------------------- Navigation helpers -------------------- */
function goDashboard()    { router.visit('/admin') }
function goApplications() { router.visit('/admin/applications') }
function goReservations() { router.visit('/admin/reservations') }
function goInterments()   { router.visit('/admin/interments') }
function goMapGL()        { router.visit('/admin/map-gl') }
function goAddPlot()      { router.visit('/admin/plots/create') }
function goAnnouncements() { router.visit('/admin/announcements') }

/* -------------------- Burger dropdown -------------------- */
const showNav = ref(false)
const navRef  = ref(null)

function toggleNav() { showNav.value = !showNav.value }
function closeNav()  { showNav.value = false }
function goTo(href)  { closeNav(); router.visit(href) }
function logout()    { closeNav(); router.post('/logout', {}, { onSuccess: () => router.visit('/') }) }

function onDocClick(e) {
  if (navRef.value && !navRef.value.contains(e.target)) closeNav()
}

onMounted(() => document.addEventListener('click', onDocClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))

/* -------------------- Shortcuts -------------------- */
// onMounted(() => {
//   const handler = (e) => {
//     if (!e.shiftKey) return
//     const k = e.key?.toLowerCase?.()
//     if (k === 'd') goDashboard()
//     if (k === 'a') goApplications()
//     if (k === 'r') goReservations()
//     if (k === 'i') goInterments()
//     if (k === 'm') goMapGL()
//   }
//   window.addEventListener('keydown', handler)
//   onBeforeUnmount(() => window.removeEventListener('keydown', handler))
// })

/* -------------------- Data helpers -------------------- */
const rows     = computed(() => props.plots?.data  ?? [])
const links    = computed(() => props.plots?.links ?? [])
const totalAll = computed(() => props.plots?.total ?? 0)

const statusDefs = [
  { value: 'vacant',   label: 'Vacant',   pill: 'bg-green-100 text-green-800 border border-green-300' },
  { value: 'reserved', label: 'Reserved', pill: 'bg-amber-100 text-amber-800 border border-amber-300' },
  { value: 'occupied', label: 'Occupied', pill: 'bg-red-100 text-red-800 border border-red-300' },
]

function statusPill(s) {
  return statusDefs.find(x => x.value === s)?.pill || 'bg-slate-100 text-slate-700 border border-slate-300'
}

const pageTotals = computed(() => {
  const c = { vacant: 0, reserved: 0, occupied: 0 }
  for (const r of rows.value) if (c[r.status] !== undefined) c[r.status]++
  return c
})

/* -------------------- Edit Modal -------------------- */
const showModal = ref(false)
const editing   = ref(null)
const form = reactive({
  section_id: '',
  lot_number: '',
  description: '',
  status: 'vacant',
  occupant_name: '',
  occupant_contact: '',
})

function openEdit(row) {
  editing.value = row
  Object.assign(form, {
    section_id:       row.section_id       ?? '',
    lot_number:       row.lot_number       ?? '',
    description:      row.description      ?? '',
    status:           row.status           ?? 'vacant',
    occupant_name:    row.occupant_name    ?? '',
    occupant_contact: row.occupant_contact ?? '',
  })
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editing.value   = null
}

/* -------------------- CRUD -------------------- */
async function save() {
  try {
    if (!editing.value?.id) return

    const payload = {
      section_id:  Number(form.section_id),
      lot_number:  form.lot_number,
      description: form.description || null,
      status:      form.status || null,
      // occupant_* not sent from here – they come from applications / backend logic
      _method:     'PATCH',
    }

    const url = `${PLOTS_BASE}/${editing.value.id}`
    console.log('POST (modal save, spoof PATCH) =>', url, payload)

    await axios.post(url, payload)
    Swal.fire('Saved', 'Plot updated.', 'success')
    closeModal()
    router.reload({ only: ['plots'] })
  } catch (e) {
    console.error(e)
    const msg = e?.response?.data?.message
      || (e?.response?.data?.errors && Object.values(e.response.data.errors).flat().join('\n'))
      || 'Failed to save plot.'
    Swal.fire('Error', msg, 'error')
  }
}

async function updateStatusInline(row, next) {
  const prev = row.status
  row.status = next

  try {
    const payload = {
      section_id: row.section_id,
      lot_number: row.lot_number,
      status:     next,
      _method:    'PATCH',
    }

    const url = `${PLOTS_BASE}/${row.id}`
    console.log('POST (inline status, spoof PATCH) =>', url, payload)

    await axios.post(url, payload)
    router.reload({ only: ['plots'] })
  } catch (e) {
    row.status = prev
    console.error(e)
    Swal.fire('Error', 'Failed to update status.', 'error')
  }
}

async function destroyPlot(id) {
  const yes = await Swal.fire({
    title: 'Delete plot?',
    text: 'This cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete',
  })
  if (!yes.isConfirmed) return

  try {
    await axios.post(`/admin/plots/${id}`, { _method: 'DELETE' })
    router.reload({ only: ['plots'] })
  } catch (e) {
    console.error(e)
    Swal.fire('Error', 'Failed to delete plot.', 'error')
  }
}

/* -------------------- GeoJSON Import -------------------- */
const fileRef = ref(null)
function pickFile() { fileRef.value?.click() }

async function importGeoJSON(e) {
  const file = e.target.files?.[0]
  if (!file) return

  const fd = new FormData()
  fd.append('geojson', file)

  try {
    await axios.post('/admin/plots/import', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    Swal.fire('Imported', 'GeoJSON processed successfully.', 'success')
    e.target.value = ''
    router.reload({ only: ['plots', 'sections'] })
  } catch (err) {
    console.error(err)
    const msg = err?.response?.data?.message
      || (err?.response?.data?.errors && Object.values(err.response.data.errors).flat().join('\n'))
      || 'Failed to import.'
    Swal.fire('Error', msg, 'error')
  }
}
</script>

<template>
  <div class="min-h-[80vh] bg-gradient-to-b from-green-50 to-white py-6 px-3 md:px-6">
    <div class="max-w-7xl mx-auto rounded-2xl border border-green-100 bg-white/80 shadow-sm p-4 md:p-6">
      <!-- Header / Toolbar -->
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-5">
        <div>
          <nav class="text-xs text-gray-500 mb-1 flex items-center gap-1">
            <button class="hover:underline" @click="goDashboard">Admin</button>
            <span>/</span>
            <span class="text-gray-700 font-medium">Plots</span>
          </nav>
          <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-2xl md:text-3xl font-extrabold text-green-800 tracking-tight">
              Plots
            </h2>
            <div class="hidden md:flex items-center gap-2">
              <span class="px-2 py-0.5 rounded-full text-[11px] bg-green-50 text-green-700 border border-green-200">
                Vacant: <span class="font-semibold">{{ pageTotals.vacant }}</span>
              </span>
              <span class="px-2 py-0.5 rounded-full text-[11px] bg-amber-50 text-amber-800 border border-amber-200">
                Reserved: <span class="font-semibold">{{ pageTotals.reserved }}</span>
              </span>
              <span class="px-2 py-0.5 rounded-full text-[11px] bg-red-50 text-red-700 border border-red-200">
                Occupied: <span class="font-semibold">{{ pageTotals.occupied }}</span>
              </span>
            </div>
          </div>
          <p class="text-sm text-gray-600">
            Browse, update statuses, import GeoJSON, and jump to the map.
          </p>
        </div>

        <!-- Right: filters + actions + burger -->
        <div class="flex flex-wrap items-center gap-2 justify-end">
          <!-- search / filters (desktop) -->
          <div class="hidden md:flex items-center gap-2">
            <input
              v-model="filters.q"
              @keyup.enter="applyFilters()"
              placeholder="Search lot # / description…"
              class="w-64 max-w-full px-3 py-2 rounded-lg text-sm bg-white border"
            />
            <select v-model="filters.status" class="px-3 py-2 rounded-lg text-sm bg-white border">
              <option value="">All statuses</option>
              <option v-for="s in statusDefs" :key="s.value" :value="s.value">
                {{ s.label }}
              </option>
            </select>
            <!-- <select v-model="filters.section_id" class="px-3 py-2 rounded-lg text-sm bg-white border min-w-[180px]">
              <option value="">All sections</option>
              <option v-for="s in sections" :key="s.id" :value="s.id">
                {{ s.name ?? ('Section #' + s.id) }} ({{ s.plots_count ?? 0 }})
              </option>
            </select> -->
            <button
              class="px-3 py-2 rounded-lg bg-green-600 text-white text-sm hover:bg-green-700"
              @click="applyFilters()"
            >
              Search
            </button>
          </div>

          <!-- Add Plot
          <button
            @click="goAddPlot"
            class="px-3 py-2 rounded-lg bg-green-700 text-white text-sm hover:bg-green-800"
          >
            + Add Plot
          </button> -->

          <!-- Import -->
          <button
            @click="pickFile"
            class="px-3 py-2 rounded-lg bg-white border text-sm hover:bg-gray-50"
          >
            Import GeoJSON
          </button>
          <input
            ref="fileRef"
            type="file"
            accept=".json,.geojson,.txt"
            class="hidden"
            @change="importGeoJSON"
          />

          <!-- Burger dropdown -->
          <div class="relative" ref="navRef">
            <button
              type="button"
              @click.stop="toggleNav"
              class="h-10 w-10 rounded-lg bg-white border border-gray-200 grid place-items-center shadow-sm hover:bg-gray-50"
              aria-label="Open navigation"
              :aria-expanded="showNav ? 'true' : 'false'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-800" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M3 12h18M3 18h18"/>
              </svg>
            </button>

            <div
              v-show="showNav"
              class="absolute right-0 mt-2 w-64 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden z-50"
              role="menu"
              @click="closeNav"
            >
              <div class="px-3 py-2 text-[11px] uppercase tracking-wide text-gray-500">Navigate</div>
              <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goDashboard">
                🏠 Dashboard
              </button>
              <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goApplications">
                📝 Applications
              </button>
              <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goReservations">
                📌 Reservations
              </button>
              <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goInterments">
                🕯️ Interments
              </button>
              <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goAnnouncements">
                📜 Announcements
              </button>
              <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goMapGL">
                🗺️ Map (GL)
              </button>
              <div class="h-px bg-gray-200 my-1"></div>
              <div class="h-px bg-gray-200 my-1"></div>
              <button
                class="w-full text-left px-3 py-2 hover:bg-red-50 text-red-600"
                @click="logout"
              >
                🚪 Logout
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary line (mobile) -->
      <div class="md:hidden mb-3 text-xs text-gray-600">
        Vacant: <b>{{ pageTotals.vacant }}</b> •
        Reserved: <b>{{ pageTotals.reserved }}</b> •
        Occupied: <b>{{ pageTotals.occupied }}</b>
      </div>

      <!-- Mobile search / filters -->
      <div class="md:hidden mb-4 flex flex-wrap gap-2">
        <input
          v-model="filters.q"
          @keyup.enter="applyFilters()"
          placeholder="Search lot # / description…"
          class="flex-1 min-w-[140px] px-3 py-2 rounded-lg text-xs bg-white border"
        />
        <select
          v-model="filters.status"
          class="px-3 py-2 rounded-lg text-xs bg-white border"
        >
          <option value="">All</option>
          <option v-for="s in statusDefs" :key="s.value" :value="s.value">
            {{ s.label }}
          </option>
        </select>
        <button
          class="px-3 py-2 rounded-lg bg-green-600 text-white text-xs hover:bg-green-700"
          @click="applyFilters()"
        >
          Go
        </button>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto bg-white rounded-xl shadow border">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
              <th class="px-4 py-3 text-left">Lot #</th>
              <th class="px-4 py-3 text-left">Section</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Description</th>
              <th class="px-4 py-3 text-left">Assigned / Occupant</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(p, i) in rows"
              :key="p.id"
              class="border-t hover:bg-gray-50 transition"
              :class="i % 2 ? 'bg-white' : 'bg-slate-50/20'"
            >
              <!-- Lot -->
              <td class="px-4 py-3 font-medium align-top">
                {{ p.lot_number ?? `#${p.id}` }}
                <div class="text-[11px] text-gray-500">ID: {{ p.id }}</div>
              </td>

              <!-- Section -->
              <td class="px-4 py-3 align-top">
                {{ p.section?.name ?? ('Section #' + (p.section_id ?? '—')) }}
              </td>

              <!-- Status -->
              <td class="px-4 py-3 align-top">
                <div class="flex flex-wrap items.center gap-2">
                  <span
                    class="px-2 py-0.5 rounded-full text-[11px] font-medium"
                    :class="statusPill(p.status)"
                  >
                    {{ p.status || '—' }}
                  </span>
                  <select
                    class="border rounded text-xs px-2 py-1 bg-white"
                    v-model="p.status"
                    @change="updateStatusInline(p, p.status)"
                  >
                    <option v-for="s in statusDefs" :key="s.value" :value="s.value">
                      {{ s.label }}
                    </option>
                  </select>
                </div>
              </td>

              <!-- Description -->
              <td class="px-4 py-3 text-xs max-w-[320px] align-top">
                <span class="line-clamp-2">{{ p.description || '—' }}</span>
              </td>

              <!-- Assigned / Occupant -->
              <td class="px-4 py-3 text-xs max-w-[260px] align-top">
                <div v-if="p.latest_approved_application">
                  <div class="font-medium">
                    {{ p.latest_approved_application.deceased_name || '—' }}
                  </div>
                  <div class="text-gray-500">
                    Applicant: {{ p.latest_approved_application.applicant_name || '—' }}
                    <span class="text-[11px] text-gray-400 ml-1">
                      (#{{ p.latest_approved_application.id }})
                    </span>
                  </div>
                </div>

                <div v-else-if="p.occupant_name || p.occupant_contact">
                  <div class="font-medium">{{ p.occupant_name || '—' }}</div>
                  <div class="text-gray-500">{{ p.occupant_contact || '' }}</div>
                </div>

                <span v-else>—</span>
              </td>

              <!-- Actions -->
              <td class="px-4 py-3 text-right space-x-2 align-top whitespace-nowrap">
                <button
                  class="px-2 py-1 text-xs border rounded-lg hover:bg-gray-50"
                  @click="openEdit(p)"
                >
                  Edit
                </button>
                <button
                  class="px-2 py-1 text-xs text-red-600 hover:text-red-800"
                  @click="destroyPlot(p.id)"
                >
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="rows.length === 0" class="p-12 text-center text-gray-600">
          No plots found.
        </div>
      </div>

      <!-- Footer chips + totals -->
      <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
        <span class="px-2 py-1 rounded-full bg-white border">
          Total (page): <b>{{ rows.length }}</b>
        </span>
        <span class="px-2 py-1 rounded-full bg-white border">
          Total (all): <b>{{ totalAll }}</b>
        </span>
      </div>

      <!-- Pagination -->
      <div v-if="links.length" class="mt-4 flex flex-wrap gap-2">
        <button
          v-for="(lnk, i) in links"
          :key="i"
          :disabled="!lnk.url"
          class="px-3 py-1.5 text-sm rounded border"
          :class="[
            lnk.active
              ? 'bg-green-600 text-white border-green-600'
              : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300',
            !lnk.url && 'opacity-50 cursor-not-allowed'
          ]"
          @click="lnk.url && applyFilters(lnk.url)"
          v-html="lnk.label"
        />
      </div>
    </div>

    <!-- Edit Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-3 sm:p-4"
    >
      <div
        class="bg-white rounded-xl w-full max-w-xl sm:max-w-2xl shadow-2xl border border-gray-200
               flex flex-col max-h-[90vh]"
      >
        <!-- Modal header -->
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 border-b">
          <div>
            <h3 class="text-base sm:text-lg font-semibold text-gray-900">
              Edit Plot
            </h3>
            <p class="text-xs text-gray-500">
              Update section, status, and description. Occupant is managed via applications.
            </p>
          </div>
          <button
            class="p-1.5 rounded-full hover:bg-gray-100 text-gray-500"
            @click="closeModal"
            aria-label="Close"
          >
            ✕
          </button>
        </div>

        <!-- Scrollable content -->
        <div class="px-4 sm:px-6 py-4 overflow-y-auto">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="text-sm text-gray-600 font-medium">Section</label>
              <select v-model="form.section_id" class="w-full border rounded px-3 py-2 bg-white mt-1">
                <option disabled value="">Select a section</option>
                <option v-for="s in sections" :key="s.id" :value="s.id">
                  {{ s.name ?? ('Section #' + s.id) }}
                </option>
              </select>
            </div>

            <div>
              <label class="text-sm text-gray-600 font-medium">Lot #</label>
              <input
                v-model="form.lot_number"
                class="w-full border rounded px-3 py-2 mt-1"
                placeholder="e.g. A-0123"
              />
            </div>

            <div class="md:col-span-2">
              <label class="text-sm text-gray-600 font-medium">Description</label>
              <textarea
                v-model="form.description"
                class="w-full border rounded px-3 py-2 mt-1"
                rows="3"
                placeholder="Optional notes"
              ></textarea>
            </div>

            <div>
              <label class="text-sm text-gray-600 font-medium">Status</label>
              <select
                v-model="form.status"
                class="w-full border rounded px-3 py-2 bg-white mt-1"
              >
                <option v-for="s in statusDefs" :key="s.value" :value="s.value">
                  {{ s.label }}
                </option>
              </select>
            </div>

            <!-- Linked application / occupant (read-only) -->
            <div class="md:col-span-2 mt-2">
              <label class="text-sm text-gray-600 font-medium">
                Linked Application / Occupant
              </label>
              <div class="mt-1 text-xs border rounded-lg px-3 py-2 bg-gray-50 text-gray-700">
                <template v-if="editing?.latest_approved_application">
                  <div class="font-semibold">
                    {{ editing.latest_approved_application.deceased_name || '—' }}
                  </div>
                  <div class="text-gray-600">
                    Applicant: {{ editing.latest_approved_application.applicant_name || '—' }}
                    <span class="text-[11px] text-gray-400 ml-1">
                      (#{{ editing.latest_approved_application.id }})
                    </span>
                  </div>
                  <div class="mt-1 text-[11px] text-gray-500">
                    To change the occupant, update the related application record.
                  </div>
                </template>

                <template v-else-if="editing?.occupant_name || editing?.occupant_contact">
                  <div class="font-semibold">
                    {{ editing.occupant_name || '—' }}
                  </div>
                  <div class="text-gray-600">
                    {{ editing.occupant_contact || '' }}
                  </div>
                  <div class="mt-1 text-[11px] text-gray-500">
                    Legacy manual occupant data. For future records, prefer linking via applications.
                  </div>
                </template>

                <template v-else>
                  <div class="text-gray-600">
                    No approved application is currently linked to this plot.
                  </div>
                  <div class="mt-1 text-[11px] text-gray-500">
                    To assign a legitimate occupant, link this plot to an application in the
                    Applications module.
                  </div>
                </template>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-4 sm:px-6 py-3 border-t flex justify-end gap-2">
          <button
            @click="closeModal"
            class="px-3 py-2 text-sm rounded bg-gray-100 hover:bg-gray-200"
          >
            Cancel
          </button>
          <button
            @click="save"
            class="px-3 py-2 text-sm rounded bg-green-600 text-white hover:bg-green-700"
          >
            Save
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
select, input, textarea, button { outline: none; }

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

* {
  transition:
    background-color .16s ease,
    color .16s ease,
    border-color .16s ease,
    box-shadow .16s ease,
    transform .16s ease;
}
@media (prefers-reduced-motion: reduce) {
  * { transition: none !important; }
}

:focus-visible {
  outline: 2px solid rgba(110,99,166,.5);
  outline-offset: 2px;
}
</style>
