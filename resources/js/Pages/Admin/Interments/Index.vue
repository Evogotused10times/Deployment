<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

/* -------------------- CSRF for axios (defensive) -------------------- */
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
if (csrf) axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf

const props = defineProps({
  interments:     { type: Object, required: true },       // paginator
  availablePlots: { type: Array, default: () => [] },     // id,lot_number,status
  approvedApps:   { type: Array, default: () => [] },     // {id, applicant_name, deceased_name, assigned_plot_id, assigned_plot:{lot_number}}
})

/* -------------------- Filters -------------------- */
const filters = reactive({
  status: '',
  plot_id: '',
  from: '',
  to: '',
  q: '',
})

/* -------------------- Modal / Form -------------------- */
const showModal = ref(false)
const editing   = ref(null)

const form = reactive({
  plot_id: '',
  application_id: '',
  reservation_id: '',
  start_at: '',
  end_at: '',
  status: 'scheduled',
  service_type: '',
  rites: '',
  officiant_name: '',
  officiant_contact: '',
  notes: '',
})

/* App select & auto-plot */
const selectedAppId = ref('')
const selectedApp = computed(() =>
  props.approvedApps.find(a => String(a.id) === String(selectedAppId.value))
)

watch(selectedApp, (a) => {
  if (!a) return
  form.application_id = a.id
  if (a.assigned_plot_id) form.plot_id = String(a.assigned_plot_id)
})

/* -------------------- Helpers -------------------- */
const statuses = [
  { value: 'scheduled', label: 'Scheduled',  color: 'bg-blue-100 text-blue-800 border border-blue-300' },
  { value: 'completed', label: 'Completed',  color: 'bg-green-100 text-green-800 border border-green-300' },
  { value: 'cancelled', label: 'Cancelled',  color: 'bg-red-100 text-red-800 border border-red-300' },
]

function statusClass(s) {
  return statuses.find(x => x.value === s)?.color || 'bg-slate-100 text-slate-700 border border-slate-300'
}

function resetForm() {
  editing.value      = null
  selectedAppId.value = ''
  Object.assign(form, {
    plot_id: '',
    application_id: '',
    reservation_id: '',
    start_at: '',
    end_at: '',
    status: 'scheduled',
    service_type: '',
    rites: '',
    officiant_name: '',
    officiant_contact: '',
    notes: '',
  })
}

function openCreate() {
  resetForm()
  // default timing: next round hour + 1h
  const now = new Date()
  now.setMinutes(0, 0, 0)
  now.setHours(now.getHours() + 1)
  const end = new Date(now)
  end.setHours(end.getHours() + 1)
  form.start_at = now.toISOString().slice(0, 16) // datetime-local
  form.end_at   = end.toISOString().slice(0, 16)
  showModal.value = true
}

function openEdit(row) {
  editing.value      = row
  selectedAppId.value = String(row.application_id || '')
  Object.assign(form, {
    plot_id: row.plot_id ? String(row.plot_id) : '',
    application_id: row.application_id || '',
    reservation_id: row.reservation_id || '',
    start_at: (row.start_at || '').replace(' ', 'T').slice(0, 16),
    end_at:   (row.end_at   || '').replace(' ', 'T').slice(0, 16),
    status: row.status,
    service_type: row.service_type || '',
    rites: row.rites || '',
    officiant_name: row.officiant_name || '',
    officiant_contact: row.officiant_contact || '',
    notes: row.notes || '',
  })
  showModal.value = true
}

/* -------------------- CRUD -------------------- */
async function save() {
  try {
    if (!selectedApp.value) {
      return Swal.fire('Missing', 'Select an approved application.', 'warning')
    }
    if (!form.plot_id) {
      return Swal.fire('Missing', 'Please select a plot.', 'warning')
    }
    if (!form.start_at || !form.end_at) {
      return Swal.fire('Missing', 'Please set start and end time.', 'warning')
    }
    if (new Date(form.end_at) <= new Date(form.start_at)) {
      return Swal.fire('Invalid', 'End must be after start.', 'warning')
    }

    const basePayload = {
      application_id: selectedApp.value.id,
      plot_id: Number(form.plot_id),
      reservation_id: form.reservation_id || null,
      start_at: new Date(form.start_at).toISOString(),
      end_at: new Date(form.end_at).toISOString(),
      status: form.status,
      service_type: form.service_type || null,
      rites: form.rites || null,
      officiant_name: form.officiant_name || null,
      officiant_contact: form.officiant_contact || null,
      notes: form.notes || null,
    }

    if (!editing.value) {
      // CREATE -> POST /admin/interments
      await axios.post(route('admin.interments.store'), basePayload)
      Swal.fire('Scheduled', 'Interment booked.', 'success')
    } else {
      // UPDATE -> POST + _method=PATCH to /admin/interments/{id}
      const url = route('admin.interments.update', editing.value.id)
      console.log('POST (spoof PATCH interment update) =>', url, basePayload)

      await axios.post(url, {
        ...basePayload,
        _method: 'PATCH',
      })

      Swal.fire('Updated', 'Interment updated.', 'success')
    }

    showModal.value = false
    router.reload({ only: ['interments'] })
  } catch (e) {
    console.error(e)
    const msg =
      e?.response?.data?.message ||
      (e?.response?.data?.errors && Object.values(e.response.data.errors).flat().join('\n')) ||
      'Failed to save.'
    Swal.fire('Error', msg, 'error')
  }
}

async function destroyRow(id) {
  const yes = await Swal.fire({
    title: 'Delete interment?',
    text: 'This cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes',
  })
  if (!yes.isConfirmed) return
  try {
    await axios.post(route('admin.interments.destroy', id), { _method: 'DELETE' })
    router.reload({ only: ['interments'] })
  } catch (e) {
    console.error(e)
    Swal.fire('Error', 'Failed to delete.', 'error')
  }
}

async function setStatus(row, next) {
  const prev = row.status
  row.status = next
  try {
    await axios.post(route('admin.interments.status', row.id), {
      _method: 'PATCH',
      status: next,
    })
    router.reload({ only: ['interments'] })
  } catch (e) {
    row.status = prev
    Swal.fire('Error', 'Failed to update status.', 'error')
  }
}

/* -------------------- Burger dropdown nav -------------------- */
const showNav = ref(false)
const navRef  = ref(null)
function toggleNav() { showNav.value = !showNav.value }
function closeNav()  { showNav.value = false }
function goTo(href)  { closeNav(); router.visit(href) }
function logout()    { closeNav(); router.post('/logout', {}, { onSuccess: () => router.visit('/') }) }

function onDocClick(e) { if (navRef.value && !navRef.value.contains(e.target)) closeNav() }
function onEsc(e)      { if (e.key === 'Escape') closeNav() }

onMounted(() => {
  document.addEventListener('click', onDocClick)
  document.addEventListener('keydown', onEsc)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  document.removeEventListener('keydown', onEsc)
})

/* -------------------- Filtering -------------------- */
function applyFilters(pageUrl = null) {
  const params = {}
  if (filters.status) params.status = filters.status
  if (filters.plot_id) params.plot_id = filters.plot_id
  if (filters.from) params.from = filters.from
  if (filters.to)   params.to   = filters.to
  if (filters.q)    params.q    = filters.q

  const url = pageUrl || route('admin.interments.index')
  router.visit(url, {
    method: 'get',
    data: params,
    preserveScroll: true,
    preserveState: true,
  })
}

watch(() => filters.status, () => applyFilters())
watch(() => filters.plot_id, () => applyFilters())

/* -------------------- Derived -------------------- */
const rows  = computed(() => props.interments?.data  ?? [])
const links = computed(() => props.interments?.links ?? [])

// Per-page counts for status chips
const pageTotals = computed(() => {
  const c = { scheduled: 0, completed: 0, cancelled: 0 }
  for (const r of rows.value) {
    if (c[r.status] !== undefined) c[r.status]++
  }
  return c
})
</script>

<template>
  <div class="min-h-[80vh] bg-gradient-to-b from-green-50 to-white py-6 px-3 md:px-6">
    <div class="max-w-7xl mx-auto rounded-2xl border border-green-100 bg-white/80 shadow-sm p-4 md:p-6">
      <!-- Top: breadcrumb + title + burger -->
      <div class="mb-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
          <div>
            <nav class="text-xs text-gray-500 mb-1 flex items-center gap-1">
              <button class="hover:underline" @click="goTo(route('admin.dashboard'))">Admin</button>
              <span>/</span>
              <span class="text-gray-700 font-medium">Interments</span>
            </nav>
            <div class="flex flex-wrap items-center gap-3">
              <h2 class="text-2xl md:text-3xl font-extrabold text-green-800 tracking-tight">
                Interment Bookings
              </h2>
              <div class="flex flex-wrap items-center gap-2 text-[11px]">
                <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                  Scheduled: <span class="font-semibold">{{ pageTotals.scheduled }}</span>
                </span>
                <span class="px-2 py-0.5 rounded-full bg-green-50 text-green-700 border border-green-200">
                  Completed: <span class="font-semibold">{{ pageTotals.completed }}</span>
                </span>
                <span class="px-2 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-200">
                  Cancelled: <span class="font-semibold">{{ pageTotals.cancelled }}</span>
                </span>
              </div>
            </div>
            <p class="text-sm text-gray-600">
              Schedule, update, and manage interment services with conflict protection.
            </p>
          </div>

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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M3 12h18M3 18h18" />
              </svg>
            </button>

            <div
              v-show="showNav"
              class="absolute right-0 mt-2 w-60 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden z-50"
              role="menu"
              @click="closeNav"
            >
              <div class="px-3 py-2 text-[11px] uppercase tracking-wide text-gray-500">Navigate</div>
              <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.dashboard'))">🏠 Dashboard</button>
              <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.applications.index'))">📝 Applications</button>
              <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.reservations.index'))">📌 Reservations</button>
              <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.interments.index'))">🕯️ Interments</button>
              <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.plots.index'))">📦 Plots</button>
              <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.announcements.index'))">📜 Announcements</button>
              <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.map.gl'))">🗺️ Map (GL)</button>
              <div class="h-px bg-gray-200 my-1"></div>
              <div class="px-3 py-2 text-[11px] uppercase tracking-wide text-gray-500">Session</div>
              <button class="w-full text-left px-3 py-2 hover:bg-red-50 text-red-600" @click="logout">🚪 Logout</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white border rounded-lg shadow-sm p-4 mb-4">
        <div class="flex flex-wrap items-center gap-2">
          <select v-model="filters.status" class="px-3 py-2 border rounded-lg text-sm bg-white">
            <option value="">All statuses</option>
            <option value="scheduled">Scheduled</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>

          <select v-model="filters.plot_id" class="px-3 py-2 border rounded-lg text-sm bg-white min-w-[180px]">
            <option value="">All plots</option>
            <option v-for="p in availablePlots" :key="p.id" :value="p.id">
              {{ p.lot_number }} ({{ p.status }})
            </option>
          </select>

          <input v-model="filters.from" type="date" class="px-3 py-2 border rounded-lg text-sm bg-white" />
          <!-- <input v-model="filters.to"   type="date" class="px-3 py-2 border rounded-lg text-sm bg-white" /> -->

          <div class="relative">
            <input
              v-model="filters.q"
              @keyup.enter="applyFilters()"
              placeholder="Search notes / applicant / deceased…"
              class="pl-9 pr-3 py-2 border rounded-lg text-sm w-64 bg-white"
            />
            <span class="absolute left-2 top-2.5 text-gray-400">🔎</span>
          </div>

          <button
            @click="applyFilters()"
            class="px-3 py-2 rounded-lg bg-green-600 text-white text-sm hover:bg-green-700"
          >
            Search
          </button>

          <div class="ml-auto">
            <button
              @click="openCreate"
              class="px-3 py-2 rounded-lg bg-green-700 text-white text-sm hover:bg-green-800"
            >
              + Schedule Interment
            </button>
          </div>
        </div>

        <p class="mt-2 text-[11px] text-gray-500">
          Tip: filter by date range or plot. “Cancelled” items don’t block future bookings on the same plot.
        </p>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto bg-white rounded-xl shadow border">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
              <th class="px-4 py-3 text-left">When</th>
              <th class="px-4 py-3 text-left">Plot</th>
              <th class="px-4 py-3 text-left">Service</th>
              <th class="px-4 py-3 text-left">Rites / Officiant</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Notes</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(r, i) in rows"
              :key="r.id"
              class="border-t hover:bg-gray-50 transition"
              :class="i % 2 === 0 ? 'bg-slate-50/30' : 'bg-white'"
            >
              <td class="px-4 py-3 align-top">
                <div class="font-medium text-gray-900">
                  {{ (r.start_at || '').replace('T', ' ').slice(0, 16) || '—' }}
                </div>
                <div class="text-xs text-gray-500">
                  → {{ (r.end_at || '').replace('T', ' ').slice(0, 16) || '—' }}
                </div>
              </td>
              <td class="px-4 py-3 align-top">
                <div class="font-semibold text-gray-900">
                  {{ r.plot?.lot_number ?? '—' }}
                </div>
                <div class="text-xs text-gray-500">
                  Plot status: {{ r.plot?.status ?? '—' }}
                </div>
              </td>
              <td class="px-4 py-3 align-top">
                {{ r.service_type || '—' }}
              </td>
              <td class="px-4 py-3 text-xs align-top">
                {{ r.rites || '—' }}
                <div class="text-gray-500">
                  {{ r.officiant_name || '' }}
                  <span v-if="r.officiant_contact">• {{ r.officiant_contact }}</span>
                </div>
              </td>
              <td class="px-4 py-3 align-top">
                <select
                  class="border rounded text-xs px-2 py-1 bg-white"
                  v-model="r.status"
                  @change="setStatus(r, r.status)"
                >
                  <option value="scheduled">Scheduled</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
                <div
                  class="mt-1 inline-block px-2 py-0.5 rounded-full text-[11px] font-medium"
                  :class="statusClass(r.status)"
                >
                  {{ r.status }}
                </div>
              </td>
              <td class="px-4 py-3 text-xs max-w-[320px] align-top">
                <span class="line-clamp-2">{{ r.notes || '—' }}</span>
              </td>
              <td class="px-4 py-3 text-right space-x-2 align-top whitespace-nowrap">
                <button
                  class="px-2 py-1 text-xs border rounded-lg hover:bg-gray-50"
                  @click="openEdit(r)"
                >
                  Edit
                </button>
                <button
                  class="px-2 py-1 text-xs text-red-600 hover:text-red-800"
                  @click="destroyRow(r.id)"
                >
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="rows.length === 0" class="p-12 text-center">
          <div
            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs bg-green-50 text-green-700 border border-green-200 mb-3"
          >
            📅 No results
          </div>
          <p class="text-gray-600">
            No interments match your filters. Try adjusting the date range or status.
          </p>
          <button
            class="mt-4 px-3 py-2 rounded-lg bg-green-700 text-white text-sm hover:bg-green-800"
            @click="openCreate"
          >
            + Schedule Interment
          </button>
        </div>
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

    <!-- Create/Edit Modal -->
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
              {{ editing ? 'Edit Interment' : 'Schedule Interment' }}
            </h3>
            <p class="text-xs text-gray-500">
              Link to an approved application and assign a plot and time window.
            </p>
          </div>
          <button
            class="p-1.5 rounded-full hover:bg-gray-100 text-gray-500"
            @click="showModal = false"
            aria-label="Close"
          >
            ✕
          </button>
        </div>

        <!-- Scrollable content -->
        <div class="px-4 sm:px-6 py-4 overflow-y-auto">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="md:col-span-2">
              <label class="text-sm text-gray-600 font-medium">Application (Approved)</label>
              <select v-model="selectedAppId" class="w-full border rounded px-3 py-2 bg-white mt-1">
                <option disabled value="">Select an approved application</option>
                <option v-for="a in approvedApps" :key="a.id" :value="String(a.id)">
                  #{{ a.id }} — {{ a.applicant_name }} ({{ a.deceased_name || '—' }})
                  • Plot {{ a.assigned_plot?.lot_number || a.assigned_plot_id }}
                </option>
              </select>
            </div>

            <div>
              <label class="text-sm text-gray-600 font-medium">Plot</label>
              <select
                v-model="form.plot_id"
                :disabled="!!selectedApp?.assigned_plot_id"
                class="w-full border rounded px-3 py-2 bg-white disabled:bg-gray-100 mt-1"
              >
                <option disabled value="">Select a Plot</option>
                <option v-for="p in availablePlots" :key="p.id" :value="String(p.id)">
                  {{ p.lot_number }} ({{ p.status }})
                </option>
              </select>
              <p class="text-[11px] text-gray-500 mt-1">
                If the application has an assigned plot, it will auto-fill here.
              </p>
            </div>

            <div>
              <label class="text-sm text-gray-600 font-medium">Service Type</label>
              <select v-model="form.service_type" class="w-full border rounded px-3 py-2 bg-white mt-1">
                <option value="">—</option>
                <option>Lawn Lot</option>
                <option>Garden Lot</option>
                <option>Mausoleum</option>
                <option>Community Vaults</option>
              </select>
            </div>

            <div>
              <label class="text-sm text-gray-600 font-medium">Start</label>
              <input v-model="form.start_at" type="datetime-local" class="w-full border rounded px-3 py-2 mt-1" />
            </div>

            <div>
              <label class="text-sm text-gray-600 font-medium">End</label>
              <input v-model="form.end_at" type="datetime-local" class="w-full border rounded px-3 py-2 mt-1" />
            </div>

            <div>
              <label class="text-sm text-gray-600 font-medium">Rites</label>
              <input
                v-model="form.rites"
                class="w-full border rounded px-3 py-2 mt-1"
                placeholder="Catholic / Protestant / Iglesia / Islam / Other"
              />
            </div>

            <div>
              <label class="text-sm text-gray-600 font-medium">Status</label>
              <select v-model="form.status" class="w-full border rounded px-3 py-2 bg-white mt-1">
                <option value="scheduled">Scheduled</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>

            <div>
              <label class="text-sm text-gray-600 font-medium">Officiant Name</label>
              <input v-model="form.officiant_name" class="w-full border rounded px-3 py-2 mt-1" />
            </div>

            <div>
              <label class="text-sm text-gray-600 font-medium">Officiant Contact</label>
              <input v-model="form.officiant_contact" class="w-full border rounded px-3 py-2 mt-1" />
            </div>

            <div class="md:col-span-2">
              <label class="text-sm text-gray-600 font-medium">Notes</label>
              <textarea
                v-model="form.notes"
                class="w-full border rounded px-3 py-2 mt-1"
                rows="3"
                placeholder="Setup, procession, special instructions…"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-4 sm:px-6 py-3 border-t flex justify-end gap-2">
          <button
            @click="showModal = false"
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
textarea, select, button, input { outline: none; }

/* simple line clamp without Tailwind plugin */
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* softer transitions like other admin pages */
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

/* nicer focus-visible for keyboard users */
:focus-visible {
  outline: 2px solid rgba(110,99,166,.5);
  outline-offset: 2px;
}
</style>
