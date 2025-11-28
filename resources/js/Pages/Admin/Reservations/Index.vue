<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

/* -------------------- CSRF for axios (defensive) -------------------- */
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
if (csrf) axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf

const props = defineProps({
  reservations:   { type: Object, required: true },   // Inertia paginator
  availablePlots: { type: Array,  default: () => [] },
  approvedApps:   { type: Array,  default: () => [] }, // {id, applicant_name, applicant_email, applicant_phone, deceased_name, assigned_plot_id, assigned_plot:{lot_number}}
})

/* -------------------- State -------------------- */
const showModal = ref(false)
const filters = reactive({ status: '', plot_id: '', q: '' })

const form = reactive({
  plot_id: '',
  application_id: '',
  // identity is mirrored & readonly
  reserved_by_name: '',
  reserved_by_email: '',
  reserved_by_phone: '',
  start_date: '',
  expires_at: '',
  notes: '',
})

/* pick approved application and lock identity */
const selectedAppId = ref('')
const selectedApp = computed(() => props.approvedApps.find(a => String(a.id) === String(selectedAppId.value)))

watch(selectedApp, (a) => {
  if (!a) return
  form.application_id   = a.id
  form.reserved_by_name = a.applicant_name || a.deceased_name || ''
  form.reserved_by_email= a.applicant_email || ''
  form.reserved_by_phone= a.applicant_phone || ''
  if (a.assigned_plot_id) form.plot_id = String(a.assigned_plot_id)
})

/* -------------------- Helpers -------------------- */
const statuses = [
  { value: 'pending',   label: 'Pending',   color: 'bg-slate-100 text-slate-800 border border-slate-300' },
  { value: 'reserved',  label: 'Reserved',  color: 'bg-amber-50 text-amber-900 border border-amber-300' },
  { value: 'confirmed', label: 'Confirmed', color: 'bg-emerald-50 text-emerald-900 border border-emerald-300' },
  { value: 'cancelled', label: 'Cancelled', color: 'bg-rose-50 text-rose-900 border border-rose-300' },
  { value: 'expired',   label: 'Expired',   color: 'bg-slate-50 text-slate-700 border border-slate-300' },
]

function statusClass(s) {
  const f = statuses.find(x => x.value === s)
  return f ? f.color : 'bg-slate-100 text-slate-700 border border-slate-300'
}

function resetForm() {
  selectedAppId.value = ''
  form.plot_id = ''
  form.application_id = ''
  form.reserved_by_name = ''
  form.reserved_by_email = ''
  form.reserved_by_phone = ''
  form.start_date = ''
  form.expires_at = ''
  form.notes = ''
}

function openModal() {
  resetForm()
  showModal.value = true
}

/* -------------------- CRUD -------------------- */
async function saveReservation() {
  try {
    if (!selectedApp.value) return Swal.fire('Missing', 'Select an approved application.', 'warning')
    if (!form.plot_id)     return Swal.fire('Missing', 'Please select a plot.', 'warning')

    await axios.post('/admin/reservations', {
      application_id: form.application_id,
      plot_id: form.plot_id,
      start_date: form.start_date || null,
      expires_at: form.expires_at || null,
      notes: form.notes || null,
    })
    Swal.fire('Success', 'Reservation created successfully.', 'success')
    showModal.value = false
    router.reload({ only: ['reservations'] })
  } catch (e) {
    console.error(e)
    const msg =
      e?.response?.data?.message ||
      (e?.response?.data?.errors && Object.values(e.response.data.errors).flat().join('\n')) ||
      'Failed to create reservation.'
    Swal.fire('Error', msg, 'error')
  }
}

/** Optimistic status update with rollback on failure */
async function updateStatus(row, nextStatus) {
  const id = row.id
  const prev = row.status
  if (!id) return

  const url = `/admin/reservations/${encodeURIComponent(id)}/status`
  try {
    await axios.post(url, { _method: 'PATCH', status: nextStatus })
    router.reload({ only: ['reservations'] })
  } catch (e) {
    console.error(e)
    row.status = prev // rollback
    Swal.fire('Error', 'Failed to update reservation.', 'error')
  }
}

async function destroy(id) {
  const rid = Number(id)
  if (!Number.isFinite(rid)) {
    console.error('Invalid reservation id:', id)
    return Swal.fire('Error', 'Invalid reservation id.', 'error')
  }

  const url = `/admin/reservations/${rid}`

  const yes = await Swal.fire({
    title: 'Delete reservation?',
    text: 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it',
    cancelButtonText: 'Cancel'
  })
  if (!yes.isConfirmed) return

  try {
    await axios.post(url, { _method: 'DELETE' })
    router.reload({ only: ['reservations'] })
  } catch (e) {
    console.error(e)
    Swal.fire('Error', 'Failed to delete reservation.', 'error')
  }
}

/* -------------------- Navigation (burger) -------------------- */
function goDashboard()    { router.visit(route('admin.dashboard')) }
function goApplications() { router.visit(route('admin.applications.index')) }
function goInterments()   { router.visit(route('admin.interments.index')) }
function goMap()          { router.visit(route('admin.map')) }
function goAddPlot()      { router.visit(route('admin.plots.create')) }
function goPlots()        { router.visit(route('admin.plots.index')) }

const showNav = ref(false)
const navRef = ref(null)
function toggleNav() { showNav.value = !showNav.value }
function closeNav()  { showNav.value = false }
function goTo(href)  { closeNav(); router.visit(href) }
function logout()    { closeNav(); router.post('/logout', {}, { onSuccess: () => router.visit('/') }) }

function onDocClick(e) {
  if (navRef.value && !navRef.value.contains(e.target)) closeNav()
}
function onEsc(e) {
  if (e.key === 'Escape') closeNav()
}
onMounted(() => {
  document.addEventListener('click', onDocClick)
  document.addEventListener('keydown', onEsc)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  document.removeEventListener('keydown', onEsc)
})

/* -------------------- Filters + Search -------------------- */
function applyFilters(pageUrl = null) {
  const params = {}
  if (filters.status) params.status = filters.status
  if (filters.plot_id) params.plot_id = filters.plot_id
  if (filters.q) params.q = filters.q

  const url = pageUrl || route('admin.reservations.index')
  router.visit(url, { method: 'get', data: params, preserveState: true, preserveScroll: true })
}

watch(() => filters.status, () => applyFilters())
watch(() => filters.plot_id, () => applyFilters())

/* -------------------- Derived -------------------- */
const totalCount = computed(() => props.reservations?.total ?? 0)
const pageLinks  = computed(() => props.reservations?.links ?? [])
const pageTotals = computed(() => {
  const c = { pending: 0, reserved: 0, confirmed: 0, cancelled: 0, expired: 0 }
  for (const r of (props.reservations?.data || [])) {
    if (c[r.status] !== undefined) c[r.status]++
  }
  return c
})

/* -------------------- Shortcuts -------------------- */
// onMounted(() => {
//   const handler = (e) => {
//     if (e.shiftKey && e.key.toLowerCase() === 'd') goDashboard()
//     if (e.shiftKey && e.key.toLowerCase() === 'a') goApplications()
//     if (e.shiftKey && e.key.toLowerCase() === 'i') goInterments()
//     if (e.shiftKey && e.key.toLowerCase() === 'm') goMap()
//   }
//   window.addEventListener('keydown', handler)
//   onBeforeUnmount(() => window.removeEventListener('keydown', handler))
// })
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-green-50 via-white to-green-50 px-4 py-6 sm:px-6 lg:px-10">
    <!-- Header: breadcrumb + title + burger -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
      <div>
        <nav class="text-xs text-gray-500 mb-1 flex items-center gap-1">
          <button class="hover:underline" @click="goDashboard">Admin</button>
          <span>/</span>
          <span class="text-gray-700 font-medium">Reservations</span>
        </nav>
        <div class="flex items-center flex-wrap gap-3">
          <div>
            <h2 class="text-2xl md:text-3xl font-extrabold text-green-800 tracking-tight flex items-center gap-2">
              Reservations
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                📌 Plots • Holds
              </span>
            </h2>
            <p class="text-sm text-gray-600 mt-1">
              Manage plot reservations, statuses, expiries, and link them back to approved applications.
            </p>
          </div>

          <!-- Status pills -->
          <div class="hidden md:flex items-center flex-wrap gap-2 md:ml-4 text-[11px]">
            <span class="px-2.5 py-1 rounded-full bg-slate-50 text-slate-700 border border-slate-200">
              Pending: <b>{{ pageTotals.pending }}</b>
            </span>
            <span class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
              Reserved: <b>{{ pageTotals.reserved }}</b>
            </span>
            <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
              Confirmed: <b>{{ pageTotals.confirmed }}</b>
            </span>
            <span class="px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200">
              Cancelled: <b>{{ pageTotals.cancelled }}</b>
            </span>
            <span class="px-2.5 py-1 rounded-full bg-slate-50 text-slate-700 border border-slate-200">
              Expired: <b>{{ pageTotals.expired }}</b>
            </span>
          </div>
        </div>
      </div>

      <!-- Burger dropdown -->
      <div class="flex items-center gap-3 justify-end">
        <div class="relative" ref="navRef">
          <button
            type="button"
            @click.stop="toggleNav"
            class="h-10 w-10 rounded-xl bg-white border border-gray-200 grid place-items-center shadow-sm hover:bg-gray-50"
            aria-label="Open navigation"
            :aria-expanded="showNav ? 'true' : 'false'"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-800" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M3 12h18M3 18h18"/>
            </svg>
          </button>

          <div
            v-show="showNav"
            class="absolute right-0 mt-2 w-60 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden z-50"
            role="menu"
            @click="closeNav"
          >
            <div class="px-3 py-2 text-[11px] uppercase tracking-wide text-gray-500 bg-gray-50">Navigate</div>
            <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.dashboard'))">🏠 Dashboard</button>
            <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.applications.index'))">📝 Applications</button>
            <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.reservations.index'))">📌 Reservations</button>
            <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.interments.index'))">🕯️ Interments</button>
            <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.plots.index'))">📦 Plots</button>
            <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.announcements.index'))">📜 Announcements</button>
            <!-- <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.plots.create'))">➕ Add Plot</button> -->
            <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goTo(route('admin.map.gl'))">🗺️ Map (GL)</button>
            <div class="h-px bg-gray-200 my-1"></div>
            <div class="px-3 py-2 text-[11px] uppercase tracking-wide text-gray-500">Session</div>
            <button class="w-full text-left px-3 py-2 hover:bg-red-50 text-red-600" @click="logout">🚪 Logout</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters / Summary -->
    <div class="bg-white/90 backdrop-blur border border-emerald-50 rounded-2xl shadow-sm p-4 mb-5">
      <div class="flex flex-wrap items-center gap-2">
        <select
          v-model="filters.status"
          class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
        >
          <option value="">All statuses</option>
          <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>

        <select
          v-model="filters.plot_id"
          class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white min-w-[180px] focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
        >
          <option value="">All plots</option>
          <option v-for="p in availablePlots" :key="p.id" :value="p.id">
            {{ p.lot_number }} ({{ p.status }})
          </option>
        </select>

        <div class="relative">
          <input
            v-model="filters.q"
            @keyup.enter="applyFilters()"
            placeholder="Search name / email / phone…"
            class="pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm w-64 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
          />
          <span class="absolute left-2.5 top-2.5 text-gray-400">🔎</span>
        </div>

        <button
          @click="applyFilters()"
          class="px-3.5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 shadow-sm"
        >
          Search
        </button>

        <div class="ml-auto flex flex-wrap items-center gap-2 text-xs">
          <span class="px-2.5 py-1 rounded-full bg-white border border-gray-200">
            Total (page): <b>{{ reservations.data?.length || 0 }}</b>
          </span>
          <span class="px-2.5 py-1 rounded-full bg-white border border-gray-200">
            Total (all): <b>{{ totalCount }}</b>
          </span>
          <button
            @click="openModal"
            class="px-3.5 py-2 rounded-xl bg-emerald-700 text-white text-xs font-semibold hover:bg-emerald-800 shadow-sm"
          >
            + Add Reservation
          </button>
        </div>
      </div>
      <p class="text-[11px] text-gray-500 mt-2">
        Shortcuts:
        <b>Shift+D</b> (Dashboard) • <b>Shift+A</b> (Applications) • <b>Shift+I</b> (Interments) • <b>Shift+M</b> (Map)
      </p>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto bg-white/95 backdrop-blur rounded-2xl shadow border border-gray-100">
      <table class="min-w-full text-sm">
        <thead class="bg-gradient-to-r from-emerald-50 via-green-50 to-amber-50 text-gray-700 uppercase text-xs sticky top-0 z-10 border-b border-emerald-100">
          <tr>
            <th class="px-4 py-3 text-left">Plot</th>
            <th class="px-4 py-3 text-left">Reserved By</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Dates</th>
            <th class="px-4 py-3 text-left">Notes</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(res, i) in reservations.data"
            :key="res.id"
            class="border-t border-gray-100 hover:bg-slate-50/60 transition-colors"
            :class="i % 2 ? 'bg-white' : 'bg-slate-50/20'"
          >
            <td class="px-4 py-3 align-top">
              <div class="font-semibold text-gray-900">
                {{ res.plot?.lot_number ?? '—' }}
              </div>
              <div class="text-[11px] text-gray-500 mt-0.5">
                Plot status: <span class="font-medium text-gray-700">{{ res.plot?.status ?? '—' }}</span>
              </div>
            </td>

            <td class="px-4 py-3 align-top">
              <div class="font-semibold text-gray-900 flex items-center gap-2">
                <span>{{ res.reserved_by_name || '—' }}</span>
              </div>
              <div class="text-xs text-gray-500 mt-0.5">
                {{ res.reserved_by_phone || res.reserved_by_email || '—' }}
              </div>
            </td>

            <td class="px-4 py-3 align-top">
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium" :class="statusClass(res.status)">
                <span
                  class="w-1.5 h-1.5 rounded-full mr-1"
                  :class="{
                    'bg-slate-500': res.status === 'pending',
                    'bg-amber-500': res.status === 'reserved',
                    'bg-emerald-500': res.status === 'confirmed',
                    'bg-rose-500': res.status === 'cancelled',
                    'bg-slate-400': res.status === 'expired',
                  }"
                ></span>
                {{ res.status }}
              </span>
            </td>

            <td class="px-4 py-3 align-top text-xs text-gray-700">
              <div>
                {{ res.start_date || '—' }}
              </div>
              <div class="text-[11px] text-gray-500">
                → {{ res.expires_at || '—' }}
              </div>
            </td>

            <td class="px-4 py-3 align-top text-xs max-w-[280px]">
              <span class="line-clamp-2 text-gray-700">{{ res.notes || '—' }}</span>
            </td>

            <td class="px-4 py-3 align-top text-right space-y-1">
              <select
                class="border rounded-lg text-xs px-2 py-1 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
                v-model="res.status"
                @change="updateStatus(res, res.status)"
              >
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
              <div>
                <button
                  class="px-2.5 py-1 text-xs text-rose-600 hover:text-rose-800 hover:underline"
                  @click="destroy(res.id)"
                >
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="reservations.data.length === 0" class="p-12 text-center">
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-50 border border-slate-200 mb-3 text-xs text-slate-600">
          📌 No reservations match your filters
        </div>
        <p class="text-gray-600 text-sm">Adjust the filters, or add a new reservation linked to an approved application.</p>
        <button
          @click="openModal"
          class="mt-4 px-4 py-2.5 bg-emerald-700 text-white rounded-xl text-sm font-semibold hover:bg-emerald-800 shadow-sm"
        >
          + Add Reservation
        </button>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pageLinks.length" class="mt-4 flex flex-wrap gap-2">
      <button
        v-for="(lnk, i) in pageLinks"
        :key="i"
        :disabled="!lnk.url"
        class="px-3 py-1.5 text-xs rounded-lg border font-medium"
        :class="[
          lnk.active ? 'bg-emerald-600 text-white border-emerald-600'
                     : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300',
          !lnk.url && 'opacity-50 cursor-not-allowed'
        ]"
        @click="lnk.url && applyFilters(lnk.url)"
        v-html="lnk.label"
      />
    </div>

    <!-- Create Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg p-6 shadow-2xl relative">
        <h3 class="text-lg font-semibold text-gray-800 mb-1">New Reservation</h3>
        <p class="text-xs text-gray-500 mb-4">
          Link a reservation to an approved application and optionally auto-use its assigned plot.
        </p>

        <div class="space-y-3">
          <div>
            <label class="text-sm text-gray-600">Application (Approved)</label>
            <select
              v-model="selectedAppId"
              class="mt-1 w-full border rounded-xl px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
            >
              <option disabled value="">Select an approved application</option>
              <option v-for="a in approvedApps" :key="a.id" :value="String(a.id)">
                #{{ a.id }} — {{ a.applicant_name }} ({{ a.deceased_name || '—' }})
                • Plot {{ a.assigned_plot?.lot_number || a.assigned_plot_id }}
              </option>
            </select>
            <p class="text-[11px] text-gray-500 mt-1">Only applications marked as <b>approved</b> are listed.</p>
          </div>

          <div>
            <label class="text-sm text-gray-600">Plot</label>
            <select
              v-model="form.plot_id"
              :disabled="!!selectedApp?.assigned_plot_id"
              class="mt-1 w-full border rounded-xl px-3 py-2 text-sm bg-white disabled:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
            >
              <option disabled value="">Select a Plot</option>
              <option v-for="p in availablePlots" :key="p.id" :value="String(p.id)">
                {{ p.lot_number }} ({{ p.status }})
              </option>
            </select>
            <p class="text-[11px] text-gray-500 mt-1">
              If the application already has an assigned plot, it will be auto-filled here.
            </p>
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="text-sm text-gray-600">Name</label>
              <input
                v-model="form.reserved_by_name"
                class="mt-1 w-full border rounded-xl px-3 py-2 text-sm bg-gray-50 text-gray-700"
                readonly
              />
            </div>
            <div>
              <label class="text-sm text-gray-600">Email</label>
              <input
                v-model="form.reserved_by_email"
                class="mt-1 w-full border rounded-xl px-3 py-2 text-sm bg-gray-50 text-gray-700"
                readonly
              />
            </div>
            <div>
              <label class="text-sm text-gray-600">Phone</label>
              <input
                v-model="form.reserved_by_phone"
                class="mt-1 w-full border rounded-xl px-3 py-2 text-sm bg-gray-50 text-gray-700"
                readonly
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm text-gray-600">Start Date</label>
              <input
                v-model="form.start_date"
                type="date"
                class="mt-1 w-full border rounded-xl px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
              />
            </div>
            <div>
              <label class="text-sm text-gray-600">Expires At</label>
              <input
                v-model="form.expires_at"
                type="date"
                class="mt-1 w-full border rounded-xl px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
              />
            </div>
          </div>

          <div>
            <label class="text-sm text-gray-600">Notes</label>
            <textarea
              v-model="form.notes"
              rows="3"
              class="mt-1 w-full border rounded-xl px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
              placeholder="Optional instructions or context (e.g. family preference, internal remarks)…"
            ></textarea>
          </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
          <button
            @click="showModal=false"
            class="px-3 py-2 text-sm rounded-xl bg-gray-100 text-gray-700 hover:bg-gray-200"
          >
            Cancel
          </button>
          <button
            @click="saveReservation"
            class="px-3.5 py-2 text-sm rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700 shadow-sm"
          >
            Save Reservation
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
textarea, select, button, input { outline: none; }
</style>
