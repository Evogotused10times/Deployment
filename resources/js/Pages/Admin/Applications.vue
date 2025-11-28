<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import ApplicationViewModal from '@/Components/Admin/ApplicationViewModal.vue'

/* -------- Modal state -------- */
const viewing = ref({ open:false, application:null, endpoints:null })

/** Open floating viewer (fetches JSON from inspect route) */
async function viewApplication(id) {
  try {
    const res = await fetch(route('admin.applications.inspect', id), {
      headers: { 'X-Requested-With':'XMLHttpRequest' }
    })
    if (!res.ok) throw new Error('Failed to load application')
    const data = await res.json()
    viewing.value = { open:true, application:data.application, endpoints:data.endpoints }
  } catch (e) {
    console.error(e)
    Swal.fire('Error', e.message || 'Failed to load application.', 'error')
  }
}
function closeViewer() { viewing.value.open = false }

/* -------- Props -------- */
const props = defineProps({
  applications:   { type: Object, required: true },
  // now expected to include both vacant + any already-assigned plots
  availablePlots: { type: Array,  default: () => [] },
})

/* -------- Plot helpers (ID -> details) -------- */
const plotIndex = computed(() => {
  const idx = Object.create(null)
  ;(props.availablePlots || []).forEach(p => {
    if (!p || typeof p.id === 'undefined') return
    idx[p.id] = p
  })
  return idx
})

function getPlotLabel(id) {
  if (!id) return '—'
  const p = plotIndex.value[id]
  if (!p) return `Plot #${id}`

  const section = p.section ?? p.section_code ?? null
  const lot     = p.lot_number || `Plot #${p.id}`
  const pieces  = []
  if (section) pieces.push(section)
  pieces.push(lot)

  // e.g. "BLOCK 1 • L-023 (vacant)"
  const base = pieces.join(' • ')
  return p.status ? `${base} (${p.status})` : base
}

/* -------- Local state / filters -------- */
const q = ref('')
const statusFilter = ref('')
const busyId = ref(null)

/* -------- Burger dropdown -------- */
const showNav = ref(false)
const navRef = ref(null)
function toggleNav() { showNav.value = !showNav.value }
function closeNav() { showNav.value = false }
function onDocClick(e) {
  if (!navRef.value) return
  if (!navRef.value.contains(e.target)) closeNav()
}
onMounted(() => document.addEventListener('click', onDocClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))

/* -------- Data / filters -------- */
const rows = computed(() => props.applications?.data ?? [])
const filtered = computed(() => {
  const query = q.value.trim().toLowerCase()
  const s = statusFilter.value
  return rows.value.filter(r => {
    const matchStatus = !s || r.status === s
    if (!query) return matchStatus
    const hay = `${r.applicant_name ?? ''} ${r.applicant_email ?? ''} ${r.service_type ?? ''} ${r.deceased_name ?? ''}`.toLowerCase()
    return matchStatus && hay.includes(query)
  })
})
const counts = computed(() => {
  const c = { total: rows.value.length, pending: 0, approved: 0, denied: 0 }
  rows.value.forEach(r => { if (c[r.status] !== undefined) c[r.status]++ })
  return c
})

/* -------- Nav helpers -------- */
function go(href) { closeNav(); router.visit(href) }
function refresh() { closeNav(); router.reload({ only: ['applications', 'availablePlots'] }) }
function logout() { closeNav(); router.post('/logout', {}, { onSuccess: () => router.visit('/') }) }
function goDashboard()    { go(route('admin.dashboard')) }
function goApplications() { go(route('admin.applications.index')) }
function goReservations() { go(route('admin.reservations.index')) }
function goInterments()   { go(route('admin.interments.index')) }
function goPlots()        { go(route('admin.plots.index')) }
function goAddPlot()      { go(route('admin.plots.create')) }
function goMapGL()        { go(route('admin.map.gl')) }
function goAnnouncements() { go(route('admin.announcements.index')) }

/* -------- Lock logic (12-hour window) -------- */
function isLocked(app) {
  if (!app) return true

  // Prefer backend truth (Application::$appends => is_editable)
  if (typeof app.is_editable !== 'undefined') {
    return app.is_editable === false
  }

  // Fallback: emulate on the client if needed
  if (app.status === 'pending') return false
  if (!app.decision_at) return false

  const decidedAt = new Date(app.decision_at)
  const twelveHoursMs = 12 * 60 * 60 * 1000
  return (Date.now() - decidedAt.getTime()) > twelveHoursMs
}

/* -------- Reservation helper fields (per row) -------- */
function initReservationFields(app) {
  if (app._inited) return
  app.makeReservation = false
  const today = new Date()
  const toISO = d => d.toISOString().slice(0,10)
  app.res_start_date = toISO(today)
  const exp = new Date(today); exp.setDate(exp.getDate() + 7)
  app.res_expires_at = toISO(exp)
  app.res_notes = ''
  app._inited = true
}

function ensureCanUpdate(app) {
  if (isLocked(app)) {
    Swal.fire('Locked', 'This application is already locked and can no longer be changed.', 'info')
    return false
  }
  initReservationFields(app)
  if (!app.newStatus) {
    Swal.fire('Select an action', 'Choose Approve or Deny first.', 'info'); return false
  }
  if (app.newStatus === 'approved' && !app.assigned_plot_id) {
    Swal.fire('Assign a plot', 'Please pick an available plot before approving.', 'warning'); return false
  }
  if (app.newStatus === 'approved' && app.makeReservation) {
    if (!app.res_start_date || !app.res_expires_at) {
      Swal.fire('Dates required', 'Provide reservation start and expiry dates.', 'warning'); return false
    }
    if (new Date(app.res_expires_at) < new Date(app.res_start_date)) {
      Swal.fire('Invalid dates', 'Expiry must be after start.', 'warning'); return false
    }
  }
  return true
}

/* -------- UPDATE STATUS + INLINE RESERVATION -------- */
async function updateStatus(app) {
  if (!ensureCanUpdate(app)) return
  busyId.value = app.id

  const approvePayload = {
    // if app.assigned_plot_id is already set (from public map), it is used automatically
    assigned_plot_id: app.assigned_plot_id ?? null,
    admin_notes: app.admin_notes ?? null
  }

  const endpoint = app.newStatus === 'approved'
    ? route('admin.applications.approve', app.id)
    : route('admin.applications.deny', app.id)

  try {
    // 1) FIRST: approve/deny the application
    await router.post(endpoint, approvePayload, {
      preserveScroll: true,
      onError: () => { throw new Error('Status update failed') },
    })

    // 2) THEN: if approved + checkbox ticked, create reservation record
    if (app.newStatus === 'approved' && app.makeReservation) {
      const resPayload = {
        plot_id: app.assigned_plot_id,
        application_id: app.id,
        status: 'reserved',
        start_date: app.res_start_date,
        expires_at: app.res_expires_at,
        notes: app.res_notes || null,
      }

      await router.post(route('admin.reservations.store'), resPayload, {
        preserveScroll: true,
        onError: () => { throw new Error('Reservation failed') },
      })
    }

    Swal.fire({
      icon: 'success',
      title: 'Updated',
      text: `Application #${app.id} marked as ${app.newStatus}${app.makeReservation && app.newStatus === 'approved' ? ' with reservation.' : '.'}`,
      timer: 1600,
      showConfirmButton: false
    })
  } catch (e) {
    console.error(e)
    Swal.fire('Error', e.message || 'Failed to update.', 'error')
  } finally {
    busyId.value = null
  }
}

/* -------- Delete Application -------- */
function deleteApplication(id, app) {
  if (isLocked(app)) {
    return Swal.fire('Locked', 'This application is already locked and can no longer be deleted.', 'info')
  }

  Swal.fire({
    title: 'Delete application?', text: 'This cannot be undone.', icon: 'warning',
    showCancelButton: true, confirmButtonText: 'Delete', cancelButtonText: 'Cancel'
  }).then(res => {
    if (!res.isConfirmed) return
    router.delete(route('admin.applications.destroy', id), {
      onSuccess: () => Swal.fire('Deleted', 'The application was removed.', 'success'),
      onError: () => Swal.fire('Error', 'Could not delete application.', 'error')
    })
  })
}

/* -------- CSV export -------- */
function exportCsv() {
  closeNav()
  const cols = ['ID','Applicant','Email','Phone','Deceased','Service Type','Status','Assigned Plot','Created At']
  const lines = [cols.join(',')]
  filtered.value.forEach(r => {
    const vals = [
      r.id, r.applicant_name ?? '', r.applicant_email ?? '', r.applicant_phone ?? '',
      r.deceased_name ?? '', r.service_type ?? '', r.status ?? '', getPlotLabel(r.assigned_plot_id), r.created_at ?? ''
    ]
    lines.push(vals.map(v => `"${String(v).replace(/"/g,'""')}"`).join(','))
  })
  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `applications_export_${new Date().toISOString().slice(0,10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-green-50 via-white to-green-50 px-4 py-6 sm:px-6 lg:px-10">
    <!-- Top: breadcrumb + title + burger -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-6">
      <div>
        <nav class="text-xs text-gray-500 mb-1 flex items-center gap-1">
          <button class="hover:underline" @click="goDashboard">Admin</button>
          <span>/</span>
          <span class="text-gray-700 font-medium">Applications</span>
        </nav>

        <div class="flex items-center flex-wrap gap-3">
          <div>
            <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-green-800 flex items-center gap-2">
              Applications
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                MemoraBeth • Admin
              </span>
            </h2>
            <p class="mt-1 text-sm text-gray-600">
              Review submissions, assign plots, and optionally create reservations in one view.
            </p>
          </div>

          <!-- Stats pills -->
          <div class="flex flex-wrap gap-2 mt-2 lg:mt-0 lg:ml-4 text-xs">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-50 text-gray-700 border border-gray-200">
              <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
              Total: <b>{{ counts.total }}</b>
            </span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-yellow-50 text-yellow-800 border border-yellow-200">
              <span class="w-1.5 h-1.5 rounded-full bg-yellow-400"></span>
              Pending: <b>{{ counts.pending }}</b>
            </span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              Approved: <b>{{ counts.approved }}</b>
            </span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200">
              <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
              Denied: <b>{{ counts.denied }}</b>
            </span>
          </div>
        </div>
      </div>

      <!-- Burger / actions -->
      <div class="flex items-center gap-3 justify-end">
        <button
          type="button"
          @click="exportCsv"
          class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100"
        >
          ⬇️ Export CSV
        </button>

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
            class="absolute right-0 mt-2 w-64 rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden z-30"
            role="menu"
            @click="closeNav"
          >
            <div class="px-3 py-2 text-[11px] uppercase tracking-wide text-gray-500 bg-gray-50">
              Navigate
            </div>
            <button class="menu-item" @click="goDashboard">🏠 Dashboard</button>
            <button class="menu-item" @click="goApplications">📝 Applications</button>
            <button class="menu-item" @click="goReservations">📌 Reservations</button>
            <button class="menu-item" @click="goInterments">🕊 Interments</button>
            <button class="menu-item" @click="goAnnouncements">📜 Announcements</button>
            <button class="menu-item" @click="goPlots">📦 Plots</button>
            <!-- <button class="menu-item" @click="goAddPlot">➕ Add Plot</button> -->
            <button class="menu-item" @click="goMapGL">🗺️ Map (GL)</button>

            <div class="my-1 border-t"></div>
            <button class="menu-item text-red-600 hover:bg-red-50" @click="logout">🚪 Logout</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters / Quick search card -->
    <div class="bg-white/90 backdrop-blur border border-emerald-50 rounded-2xl shadow-sm mb-5">
      <div class="px-4 py-3 sm:px-5 sm:py-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-wrap items-center gap-2">
          <div class="relative">
            <input
              v-model="q"
              type="text"
              placeholder="Search applicant, email, service, deceased…"
              class="w-64 sm:w-80 pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
            />
            <span class="absolute left-2.5 top-2.5 text-gray-400">🔎</span>
          </div>

          <select
            v-model="statusFilter"
            class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
          >
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="denied">Denied</option>
          </select>
        </div>

        <div class="flex flex-wrap gap-2 text-[11px] text-gray-500 md:justify-end">
          <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-slate-50 border border-slate-200">
            ⌨️ Tip: You can approve, assign a plot, and create a reservation in one step.
          </span>
        </div>
      </div>
    </div>

    <!-- Table card -->
    <div class="bg-white/95 backdrop-blur border border-gray-100 shadow-md rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left border-collapse">
          <thead class="bg-gradient-to-r from-emerald-50 via-green-50 to-amber-50 text-emerald-900 uppercase text-xs font-semibold border-b border-emerald-100 sticky top-0 z-10">
            <tr>
              <th class="px-4 py-3">Applicant</th>
              <th class="px-4 py-3">Email</th>
              <th class="px-4 py-3">Service</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Notes</th>
              <th class="px-4 py-3 w-[360px]">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="(app, idx) in filtered"
              :key="app.id"
              class="border-b border-gray-100"
              :class="idx % 2 ? 'bg-white' : 'bg-slate-50/40'"
            >
              <!-- Applicant column -->
              <td class="px-4 py-3 align-top">
                <div class="flex items-start gap-3">
                  <div class="mt-0.5 hidden sm:block">
                    <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 grid place-items-center text-xs font-semibold">
                      {{ (app.applicant_name || '?').slice(0,2).toUpperCase() }}
                    </div>
                  </div>
                  <div class="flex flex-col">
                    <div class="flex items-center gap-2">
                      <span class="font-semibold text-gray-900">
                        {{ app.applicant_name || '—' }}
                      </span>
                      <span
                        v-if="isLocked(app)"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-700 border border-slate-200"
                      >
                        🔒 Locked
                      </span>
                    </div>
                    <div class="text-xs text-gray-500">
                      #{{ app.id }} • {{ app.created_at?.slice(0,10) || '—' }}
                    </div>
                    <div v-if="app.deceased_name" class="text-[11px] text-gray-500 mt-0.5">
                      For: <span class="font-medium text-gray-700">{{ app.deceased_name }}</span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Email -->
              <td class="px-4 py-3 align-top text-gray-600">
                <div class="text-xs break-all">{{ app.applicant_email || '—' }}</div>
                <div v-if="app.applicant_phone" class="text-[11px] text-gray-500 mt-0.5">
                  📞 {{ app.applicant_phone }}
                </div>
              </td>

              <!-- Service -->
              <td class="px-4 py-3 align-top">
                <div class="inline-flex items-center px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-100 text-[11px] font-medium">
                  {{ app.service_type || '—' }}
                </div>
              </td>

              <!-- Status -->
              <td class="px-4 py-3 align-top">
                <span
                  :class="[
                    'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold',
                    app.status === 'pending'  && 'bg-yellow-50 text-yellow-800 border border-yellow-200',
                    app.status === 'approved' && 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                    app.status === 'denied'   && 'bg-rose-50 text-rose-700 border border-rose-200',
                  ]"
                >
                  <span class="w-1.5 h-1.5 rounded-full mr-1"
                        :class="{
                          'bg-yellow-400': app.status === 'pending',
                          'bg-emerald-500': app.status === 'approved',
                          'bg-rose-400': app.status === 'denied'
                        }"
                  ></span>
                  {{ app.status }}
                </span>

                <div v-if="app.assigned_plot_id" class="text-[11px] text-gray-500 mt-1">
                  Plot:
                  <span class="font-medium text-gray-700">
                    {{ getPlotLabel(app.assigned_plot_id) }}
                  </span>
                </div>
              </td>

              <!-- Admin notes -->
              <td class="px-4 py-3 align-top w-64">
                <textarea
                  v-model="app.admin_notes"
                  :disabled="isLocked(app)"
                  class="w-full px-2.5 py-1.5 border rounded-xl text-xs resize-none min-h-[56px]"
                  :class="isLocked(app)
                    ? 'bg-gray-50 text-gray-500 cursor-not-allowed border-dashed border-gray-200'
                    : 'bg-white text-gray-800 border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400'"
                  placeholder="Internal notes or context…"
                />
              </td>

              <!-- Actions -->
              <td class="px-4 py-3 align-top">
                <div class="flex flex-col gap-2">
                  <!-- Status select -->
                  <div class="flex items-center gap-2">
                    <select
                      v-model="app.newStatus"
                      :disabled="isLocked(app)"
                      class="border rounded-lg px-2 py-1.5 text-xs w-36"
                      :class="isLocked(app)
                        ? 'bg-gray-50 text-gray-500 cursor-not-allowed border-gray-200'
                        : 'bg-white text-gray-800 border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400'"
                    >
                      <option disabled value="" placeholder="Choose...">Select action</option>
                      <option value="approved">Approve</option>
                      <option value="denied">Deny</option>
                    </select>

                    <button
                      :disabled="isLocked(app) || busyId === app.id"
                      @click.prevent="updateStatus(app)"
                      class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      {{ busyId === app.id ? 'Saving…' : 'Apply' }}
                    </button>
                  </div>

                  <!-- Approve extras: plot + reservation -->
                  <div
                    v-if="app.newStatus === 'approved' && !isLocked(app)"
                    class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-2.5 space-y-2"
                  >
                    <div>
                      <label class="text-[11px] font-semibold text-emerald-900">Assign plot</label>
                      <select
                        v-model="app.assigned_plot_id"
                        class="mt-1 border rounded-lg px-2 py-1.5 text-xs w-full bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
                      >
                        <option disabled value="">Select a plot</option>
                        <option
                          v-for="p in availablePlots"
                          :key="p.id"
                          :value="p.id"
                        >
                          {{ getPlotLabel(p.id) }}
                        </option>
                      </select>

                      <div
                        v-if="!availablePlots.length"
                        class="text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1 mt-1 flex items-center justify-between gap-1"
                      >
                        <span>No available plots yet.</span>
                        <button class="underline" @click="goMapGL">Manage plots</button>
                      </div>
                    </div>

                    <!-- Reservation opt-in -->
                    <div class="mt-1 rounded-lg border border-emerald-100 bg-white px-2.5 py-2">
                      <label class="flex items-center gap-2 text-xs text-emerald-900">
                        <input type="checkbox" v-model="app.makeReservation" @change="initReservationFields(app)" />
                        <span>Create a reservation for this applicant</span>
                      </label>

                      <div v-if="app.makeReservation" class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div>
                          <label class="block text-[11px] text-gray-600 mb-1">Start date</label>
                          <input
                            type="date"
                            v-model="app.res_start_date"
                            class="w-full border rounded-lg px-2 py-1.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
                          />
                        </div>
                        <div>
                          <label class="block text-[11px] text-gray-600 mb-1">Expires at</label>
                          <input
                            type="date"
                            v-model="app.res_expires_at"
                            class="w-full border rounded-lg px-2 py-1.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
                          />
                        </div>
                        <div class="sm:col-span-3">
                          <label class="block text-[11px] text-gray-600 mb-1">Reservation notes</label>
                          <input
                            type="text"
                            v-model="app.res_notes"
                            class="w-full border rounded-lg px-2 py-1.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400"
                            placeholder="Optional notes…"
                          />
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Buttons row -->
                  <div class="flex flex-wrap gap-2 pt-1">
                    <button
                      @click="viewApplication(app.id)"
                      class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50"
                    >
                      View
                    </button>

                    <button
                      :disabled="isLocked(app)"
                      @click="deleteApplication(app.id, app)"
                      class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium bg-rose-500 text-white hover:bg-rose-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      Delete
                    </button>
                  </div>
                </div>
              </td>
            </tr>

            <tr v-if="!filtered.length">
              <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-50 border border-slate-200 mb-2 text-xs text-slate-600">
                  📭 No applications match your filters
                </div>
                <p class="text-sm text-gray-500">
                  Try clearing the search or adjusting the status filter.
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <nav
      v-if="applications?.links?.length"
      class="mt-5 flex flex-wrap gap-2 items-center"
    >
      <button
        v-for="l in applications.links"
        :key="l.label"
        :disabled="!l.url"
        @click="l.url && router.visit(l.url, { preserveScroll: true })"
        class="px-3 py-1.5 rounded-lg border text-xs font-medium"
        :class="[
          l.active
            ? 'bg-emerald-600 text-white border-emerald-600'
            : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300',
          !l.url && 'opacity-50 cursor-not-allowed'
        ]"
        v-html="l.label"
      />
    </nav>

    <!-- Floating Application Viewer -->
    <ApplicationViewModal
      v-model:open="viewing.open"
      :application="viewing.application"
      :endpoints="viewing.endpoints"
      @close="closeViewer"
    />
  </div>
</template>

<style scoped>
textarea, select, button, input { outline: none; }
.menu-item { @apply w-full text-left px-3 py-2 text-sm hover:bg-gray-50; }
</style>
