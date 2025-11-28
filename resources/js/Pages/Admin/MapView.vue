<template>
  <div class="min-h-[80vh] rounded-2xl border border-green-100 bg-gradient-to-b from-green-50 to-white p-5">
    <!-- Top Bar -->
    <header class="flex flex-col gap-3 lg:gap-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-extrabold text-green-700 tracking-tight">Cemetery Map</h2>
          <p class="text-sm text-gray-600">View, search, and manage plots by status and location.</p>
        </div>

        <!-- Back to Dashboard + Add Plot -->
        <div class="hidden sm:flex items-center gap-2">
          <button
            @click="router.visit(route('admin.map.gl'))"
            class="px-3 py-2 rounded-lg bg-sky-600 text-white text-sm hover:bg-sky-700"
          >
          Open Map (GL)
          </button>

          <button
            @click="router.visit(route('admin.applications.index'))"
            class="btn-soft"
            title="Back to Dashboard"
          >
            ← Back to Dashboard
          </button>

          <button
            @click="router.visit(route('admin.plots.create'))"
            class="btn-primary"
            title="Add a new plot"
          >
            + Add Plot
          </button>
        </div>
      </div>

      <!-- Toolbar (wraps nicely on small screens) -->
      <div class="flex flex-wrap items-center gap-2.5">
        <!-- Status Filter -->
        <div class="toolbar-chip">
          <label class="chip-label">Status</label>
          <select v-model="filters.status" class="chip-select">
            <option value="">All</option>
            <option value="vacant">Vacant</option>
            <option value="reserved">Reserved</option>
            <option value="occupied">Occupied</option>
          </select>
        </div>

        <!-- Search -->
        <div class="toolbar-chip">
          <label class="chip-label">Search</label>
          <div class="relative">
            <input
              v-model="search"
              @keyup.enter="searchPlot"
              placeholder="Lot # or occupant…"
              class="chip-input pl-8"
            />
            <span class="absolute left-2 top-2.5 text-gray-400">🔎</span>
          </div>
          <button @click="searchPlot" class="chip-button">Find</button>
        </div>

        <!-- Map Actions -->
        <div class="toolbar-chip">
          <label class="chip-label">Map</label>
          <div class="flex items-center gap-1.5">
            <button @click="fitAll" class="chip-button">Fit to Plots</button>
            <button @click="recenter" class="chip-button">Recenter</button>
            <button @click="refresh" class="chip-button">Refresh</button>
          </div>
        </div>

        <!-- Mobile: Back & Add -->
        <div class="flex sm:hidden w-full justify-end gap-2 mt-1.5">
          <button
            @click="router.visit(route('admin.applications.index'))"
            class="btn-soft w-auto"
          >
            ← Dashboard
          </button>
          <button
            @click="router.visit(route('admin.plots.create'))"
            class="btn-primary w-auto"
          >
            + Add Plot
          </button>
        </div>
      </div>
    </header>

    <!-- Map + Sidebar -->
    <section class="mt-4 grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-4">
      <!-- Map -->
      <div class="relative">
        <div
          v-if="loading"
          class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/70 backdrop-blur-sm"
        >
          <div class="h-8 w-8 animate-spin rounded-full border-t-2 border-green-600"></div>
          <span class="ml-3 text-gray-700">Loading plots…</span>
        </div>
        <div
          v-if="error"
          class="absolute left-3 top-3 z-10 rounded border border-red-200 bg-red-50 px-3 py-2 text-red-700 shadow-sm"
        >
          {{ error }}
        </div>

        <div id="map" class="h-[72vh] rounded-xl border border-green-100 shadow-sm"></div>
      </div>

      <!-- Sidebar -->
      <aside class="sticky top-5 h-fit rounded-xl border border-green-100 bg-white p-4 shadow-sm">
        <h3 class="mb-2 font-semibold text-gray-800">Legend</h3>
        <ul class="space-y-2 text-sm text-gray-700">
          <li class="flex items-center">
            <span class="mr-2 inline-block h-3.5 w-3.5 rounded-sm" :style="{background: colors.vacant}"></span>
            Vacant
          </li>
          <li class="flex items-center">
            <span class="mr-2 inline-block h-3.5 w-3.5 rounded-sm" :style="{background: colors.reserved}"></span>
            Reserved
          </li>
          <li class="flex items-center">
            <span class="mr-2 inline-block h-3.5 w-3.5 rounded-sm" :style="{background: colors.occupied}"></span>
            Occupied
          </li>
        </ul>

        <!-- Totals -->
        <div class="mt-5 grid grid-cols-2 gap-2 text-sm">
          <div class="stat">
            <span class="stat-label">Total</span>
            <span class="stat-value">{{ totals.total }}</span>
          </div>
          <div class="stat">
            <span class="stat-label">Vacant</span>
            <span class="stat-value text-green-700">{{ totals.vacant }}</span>
          </div>
          <div class="stat">
            <span class="stat-label">Reserved</span>
            <span class="stat-value text-yellow-700">{{ totals.reserved }}</span>
          </div>
          <div class="stat">
            <span class="stat-label">Occupied</span>
            <span class="stat-value text-red-700">{{ totals.occupied }}</span>
          </div>
        </div>

        <!-- Selected Plot -->
        <div v-if="selected" class="mt-6 border-t pt-4">
          <h4 class="mb-2 font-semibold text-gray-800">Selected Plot</h4>
          <div class="space-y-1 text-sm text-gray-700">
            <div>
              <span class="text-gray-500">Lot:</span>
              <span class="font-medium">{{ selected.lot_number || '—' }}</span>
            </div>
            <div>
              <span class="text-gray-500">Status:</span>
              <span class="font-medium capitalize">{{ selected.status }}</span>
            </div>
            <div v-if="selected.occupant_name">
              <span class="text-gray-500">Occupant:</span>
              <span class="font-medium">{{ selected.occupant_name }}</span>
            </div>
          </div>

          <div class="mt-3 flex gap-2">
            <button class="btn-soft w-full" @click="openPlot(selected.id)">Open Details</button>
          </div>
        </div>
      </aside>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import axios from 'axios'

const data = ref(null)

onMounted(async () => {
  const res = await axios.get('/api/plots')
  data.value = res.data
})
// MapView.vue / MapViewGL.vue
// const { data } = await axios.get(`/api/plots?t=${Date.now()}`)
const BETHANY = { lat: 9.942455, lng: 123.96799, zoom: 18 }
const colors = { vacant: '#28a745', reserved: '#ffc107', occupied: '#dc3545' }

const map = ref(null)
let layerAll = null
let layerFiltered = null

const loading = ref(false)
const error = ref('')
const search = ref('')
const filters = ref({ status: '' })
const selected = ref(null)
const totals = ref({ total: 0, vacant: 0, reserved: 0, occupied: 0 })

onMounted(async () => {
  initMap()
  await loadPlots()
})

function initMap() {
  map.value = L.map('map', { zoomControl: true })
    .setView([BETHANY.lat, BETHANY.lng], BETHANY.zoom)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map.value)
}

async function loadPlots() {
  loading.value = true
  error.value = ''
  selected.value = null

  try {
    const { data } = await axios.get('/api/plots')

    if (layerAll) map.value.removeLayer(layerAll)
    if (layerFiltered) map.value.removeLayer(layerFiltered)

    layerAll = L.geoJSON(data, {
      style: f => styleByStatus(f.properties.status),
      onEachFeature: (f, l) => bindPopup(f, l),
    })

    layerFiltered = L.geoJSON(data, {
      style: f => styleByStatus(f.properties.status),
      onEachFeature: (f, l) => bindPopup(f, l),
    }).addTo(map.value)

    updateTotals(layerAll)
    fitAll()
  } catch (e) {
    console.error(e)
    error.value = 'Failed to load plots. Please try again.'
  } finally {
    loading.value = false
  }
}

function styleByStatus(s) {
  const base = { weight: 1, fillOpacity: 0.5 }
  if (s === 'vacant') return { ...base, color: colors.vacant, fillColor: colors.vacant }
  if (s === 'reserved') return { ...base, color: colors.reserved, fillColor: colors.reserved }
  return { ...base, color: colors.occupied, fillColor: colors.occupied }
}

function bindPopup(f, l) {
  const p = f.properties
  const html = `
    <div class="text-sm">
      <div><strong>Lot:</strong> ${p.lot_number ?? '—'}</div>
      <div><strong>Status:</strong> ${p.status}</div>
      ${p.occupant_name ? `<div><strong>Occupant:</strong> ${p.occupant_name}</div>` : ''}
      <div class="mt-2">
        <button class="popup-btn" onclick="window.openPlot(${p.id})">Open</button>
      </div>
    </div>
  `
  l.bindPopup(html)
  l.on('click', () => { selected.value = p })
}

function updateTotals(layer) {
  const c = { vacant: 0, reserved: 0, occupied: 0, total: 0 }
  layer.eachLayer(l => {
    const s = l.feature?.properties?.status
    c.total++
    if (c[s] !== undefined) c[s]++
  })
  totals.value = c
}

function searchPlot() {
  const q = search.value.trim().toLowerCase()
  if (!q) return
  let found = null
  layerFiltered.eachLayer(l => {
    const p = l.feature.properties
    if ((p.lot_number && p.lot_number.toLowerCase().includes(q)) ||
        (p.occupant_name && p.occupant_name.toLowerCase().includes(q))) {
      found = l
    }
  })
  if (found) {
    found.openPopup()
    map.value.fitBounds(found.getBounds(), { maxZoom: 19 })
  }
}

function fitAll() {
  if (!layerFiltered) return
  const b = layerFiltered.getBounds()
  if (b.isValid()) map.value.fitBounds(b, { padding: [20, 20] })
}

function recenter() {
  map.value.setView([BETHANY.lat, BETHANY.lng], BETHANY.zoom)
}

function refresh() {
  loadPlots()
}

window.openPlot = id => router.visit(route('admin.plots.index', { plot_id: id }))
watch(() => filters.value.status, applyFilters)

function applyFilters() {
  if (!layerAll) return
  if (layerFiltered) map.value.removeLayer(layerFiltered)

  const s = filters.value.status
  layerFiltered = L.geoJSON(layerAll.toGeoJSON(), {
    filter: f => !s || f.properties.status === s,
    style: f => styleByStatus(f.properties.status),
    onEachFeature: (f, l) => bindPopup(f, l)
  }).addTo(map.value)
}
</script>

<style scoped>
/* Buttons */
.btn-primary {
  @apply px-3 py-2 rounded-lg bg-green-700 text-white text-sm hover:bg-green-800 transition;
}
.btn-soft {
  @apply px-3 py-2 rounded-lg bg-gray-100 text-gray-800 text-sm hover:bg-gray-200 border border-gray-300 transition;
}

/* Toolbar chips */
.toolbar-chip {
  @apply flex items-center gap-2.5 rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm;
}
.chip-label {
  @apply text-xs font-medium text-gray-500;
}
.chip-select {
  @apply rounded-md border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500;
}
.chip-input {
  @apply w-64 rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500;
}
.chip-button {
  @apply rounded-md border border-gray-300 px-2.5 py-1.5 text-sm hover:bg-gray-50;
}

/* Sidebar stats */
.stat {
  @apply rounded-md border border-gray-200 bg-gray-50 px-3 py-2;
}
.stat-label {
  @apply block text-[11px] uppercase tracking-wide text-gray-500;
}
.stat-value {
  @apply text-sm font-semibold text-gray-800;
}

/* Popup button */
.popup-btn {
  background: #16a34a;
  color: #fff;
  padding: 4px 10px;
  border-radius: 5px;
  border: none;
  cursor: pointer;
  font-size: .8rem;
}
.popup-btn:hover { background: #15803d; }
</style>
