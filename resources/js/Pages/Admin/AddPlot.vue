<script setup>
import { reactive, ref, computed } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { router } from '@inertiajs/vue3'

// Bethany Memorial Park, Ubujan, Tubigon (approx center)
const CENTER = { lng: 123.95248, lat: 9.94468 }

// Form state (server expects section_code, not section_id)
const form = reactive({
  section_code: '',     // must exist in sections.code (A/B/C/D)
  lot_number: '',
  status: 'vacant',
  block_level: '',
  description: '',
  price: '',
  occupant_name: '',
  occupant_contact: '',
})

// Geometry editor state
const geometryType = ref('Point') // 'Point' | 'Polygon'

// Point editor
const pointLon = ref(CENTER.lng)
const pointLat = ref(CENTER.lat)

// Polygon editor (start as small square near center)
const polygonCoords = ref([
  [CENTER.lng - 0.00006, CENTER.lat - 0.00006],
  [CENTER.lng + 0.00006, CENTER.lat - 0.00006],
  [CENTER.lng + 0.00006, CENTER.lat + 0.00006],
  [CENTER.lng - 0.00006, CENTER.lat + 0.00006],
])

function ensureClosedRing(coords) {
  if (!coords.length) return coords
  const first = coords[0]
  const last = coords[coords.length - 1]
  if (first[0] !== last[0] || first[1] !== last[1]) {
    return [...coords, [...first]]
  }
  return coords
}

function switchType(type) {
  geometryType.value = type
}

// Helpers — Point
function snapPointToCenter() {
  pointLon.value = CENTER.lng
  pointLat.value = CENTER.lat
}
function nudgePoint(dx, dy) {
  pointLon.value = Number((pointLon.value + dx).toFixed(6))
  pointLat.value = Number((pointLat.value + dy).toFixed(6))
}

// Helpers — Polygon
function resetSquare(size = 0.00006) {
  polygonCoords.value = [
    [CENTER.lng - size, CENTER.lat - size],
    [CENTER.lng + size, CENTER.lat - size],
    [CENTER.lng + size, CENTER.lat + size],
    [CENTER.lng - size, CENTER.lat + size],
  ]
}
function addVertex() {
  const last = polygonCoords.value[polygonCoords.value.length - 1]
  polygonCoords.value.push([Number(last[0]) + 0.00003, Number(last[1]) + 0.00003])
}
function removeVertex(i) {
  if (polygonCoords.value.length > 3) polygonCoords.value.splice(i, 1)
}
function nudgePolygon(dx, dy) {
  polygonCoords.value = polygonCoords.value.map(([x, y]) => [
    Number((x + dx).toFixed(6)),
    Number((y + dy).toFixed(6))
  ])
}

// Build & validate geometry
function buildGeometryOrThrow() {
  if (geometryType.value === 'Point') {
    const lon = Number(pointLon.value)
    const lat = Number(pointLat.value)
    if (Number.isNaN(lon) || Number.isNaN(lat)) throw new Error('Point coordinates must be numbers.')
    if (lon < -180 || lon > 180 || lat < -90 || lat > 90) throw new Error('Point coordinates are out of bounds.')
    return { type: 'Point', coordinates: [lon, lat] }
  } else {
    const ring = ensureClosedRing(
      polygonCoords.value.map(([x, y]) => [Number(x), Number(y)])
    )
    if (ring.length < 4) throw new Error('Polygon needs at least 4 rows (first = last).')
    for (const [lon, lat] of ring) {
      if (Number.isNaN(lon) || Number.isNaN(lat)) throw new Error('Polygon coordinates must be numbers.')
      if (lon < -180 || lon > 180 || lat < -90 || lat > 90) throw new Error('Polygon coordinates out of bounds.')
    }
    return { type: 'Polygon', coordinates: [ring] }
  }
}

// Live preview (read-only)
const geoPreview = computed(() => {
  try {
    const g = buildGeometryOrThrow()
    return JSON.stringify(g, null, 2)
  } catch {
    return '// geometry invalid (will show error on Save)'
  }
})

// Quick UI helpers
const statuses = [
  { v: 'vacant', label: 'Vacant' },
  { v: 'reserved', label: 'Reserved' },
  { v: 'occupied', label: 'Occupied' },
]
function statusChipClass(v) {
  return {
    'bg-green-50 text-green-700 border-green-200': v === 'vacant',
    'bg-yellow-50 text-yellow-800 border-yellow-200': v === 'reserved',
    'bg-red-50 text-red-700 border-red-200': v === 'occupied',
  }
}

// Submit
async function submit() {
  try {
    if (!form.section_code.trim()) {
      return Swal.fire('Missing field', 'Please select a Section (A / B / C / D).', 'warning')
    }
    if (!form.lot_number.trim()) {
      return Swal.fire('Missing field', 'Please enter the Lot Number (e.g. A-12).', 'warning')
    }
    const geometry = buildGeometryOrThrow()

    await axios.post('/api/plots', {
      section_code: form.section_code.trim(),
      lot_number: form.lot_number.trim(),
      status: form.status,
      block_level: form.block_level || null,
      description: form.description || null,
      price: form.price || null,
      occupant_name: form.occupant_name || null,
      occupant_contact: form.occupant_contact || null,
      geometry,
    })

    await Swal.fire({ icon: 'success', title: 'Plot added', text: 'The plot was saved successfully.' })
    // Force a clean map reload (Leaflet page). Use admin.map.gl if you prefer the GL page.
    router.visit(route('admin.map'), { preserveState: false })
  } catch (e) {
    console.error(e)
    const msg =
      e?.response?.data?.message ||
      (e?.response?.data?.errors && Object.values(e.response.data.errors).flat().join('\n')) ||
      e?.message ||
      'Failed to add plot. Check the fields and the geometry.'
    Swal.fire('Error', msg, 'error')
  }
}
</script>

<template>
  <div class="min-h-[80vh] bg-gradient-to-b from-green-50 to-white p-5 rounded-2xl border border-green-100">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
      <div>
        <h2 class="text-2xl font-extrabold text-green-700 tracking-tight">Add Plot</h2>
        <p class="text-sm text-gray-600">Create a new plot with a precise location and status.</p>
      </div>
      <div class="flex gap-2">
        <button
          @click="router.visit(route('admin.map'))"
          class="px-3 py-2 rounded-lg bg-gray-100 text-gray-800 text-sm hover:bg-gray-200 border border-gray-300"
        >
          ← Back to Map
        </button>
      </div>
    </div>

    <!-- Card: Plot Details -->
    <div class="bg-white rounded-xl border border-green-100 shadow-sm p-5 mb-5">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div class="md:col-span-1">
          <label class="text-sm text-gray-600">Section (A / B / C / D) *</label>
          <div class="mt-2 flex gap-2">
            <button
              v-for="s in ['A','B','C','D']"
              :key="s"
              type="button"
              class="px-3 py-2 rounded-lg border text-sm"
              :class="form.section_code === s ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
              @click="form.section_code = s"
            >
              {{ s }}
            </button>
          </div>
          <p class="text-xs text-gray-500 mt-2">
            Must match an existing <code>sections.code</code> in your database.
          </p>
        </div>

        <div class="md:col-span-1">
          <label class="text-sm text-gray-600">Lot Number *</label>
          <input v-model="form.lot_number" class="mt-2 w-full border rounded-lg px-3 py-2" placeholder="e.g. A-12" />
          <p class="text-xs text-gray-500 mt-1">Tip: Use a consistent format (e.g. A-12).</p>
        </div>

        <div class="md:col-span-1">
          <label class="text-sm text-gray-600">Status</label>
          <div class="mt-2 flex gap-2 flex-wrap">
            <button
              v-for="s in statuses" :key="s.v" type="button"
              class="px-3 py-2 rounded-full border text-xs font-medium"
              :class="[{ 'ring-1 ring-offset-1': form.status === s.v }, statusChipClass(s.v)]"
              @click="form.status = s.v"
            >
              {{ s.label }}
            </button>
          </div>
        </div>

        <div>
          <label class="text-sm text-gray-600">Block / Level (optional)</label>
          <input v-model="form.block_level" class="mt-2 w-full border rounded-lg px-3 py-2" />
        </div>

        <div>
          <label class="text-sm text-gray-600">Price (optional)</label>
          <input v-model="form.price" type="number" step="0.01" class="mt-2 w-full border rounded-lg px-3 py-2" />
        </div>

        <div class="md:col-span-3">
          <label class="text-sm text-gray-600">Description (optional)</label>
          <textarea v-model="form.description" class="mt-2 w-full border rounded-lg px-3 py-2"></textarea>
        </div>

        <div>
          <label class="text-sm text-gray-600">Occupant Name (if any)</label>
          <input v-model="form.occupant_name" class="mt-2 w-full border rounded-lg px-3 py-2" />
        </div>

        <div>
          <label class="text-sm text-gray-600">Occupant Contact (if any)</label>
          <input v-model="form.occupant_contact" class="mt-2 w-full border rounded-lg px-3 py-2" />
        </div>
      </div>
    </div>

    <!-- Card: Geometry -->
    <div class="bg-white rounded-xl border border-green-100 shadow-sm p-5">
      <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm text-gray-600">Geometry Type:</span>
        <label class="flex items-center gap-2 text-sm">
          <input type="radio" value="Point" v-model="geometryType" @change="switchType('Point')" /> Point
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input type="radio" value="Polygon" v-model="geometryType" @change="switchType('Polygon')" /> Polygon
        </label>
      </div>

      <!-- Point editor -->
      <div v-if="geometryType === 'Point'" class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div>
          <label class="text-sm text-gray-600">Longitude (x)</label>
          <input v-model.number="pointLon" type="number" step="0.000001" class="mt-2 w-full border rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="text-sm text-gray-600">Latitude (y)</label>
          <input v-model.number="pointLat" type="number" step="0.000001" class="mt-2 w-full border rounded-lg px-3 py-2" />
        </div>
        <div class="flex items-end gap-2">
          <button @click="snapPointToCenter" type="button" class="w-full px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 border border-gray-300">
            Use Park Center
          </button>
        </div>

        <div class="lg:col-span-3 flex flex-wrap items-center gap-2 mt-2">
          <span class="text-xs text-gray-500">Nudge:</span>
          <button class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 border text-xs" @click="nudgePoint( 0.00002,  0)">→</button>
          <button class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 border text-xs" @click="nudgePoint(-0.00002,  0)">←</button>
          <button class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 border text-xs" @click="nudgePoint( 0,  0.00002)">↑</button>
          <button class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 border text-xs" @click="nudgePoint( 0, -0.00002)">↓</button>
          <span class="text-xs text-gray-500 ml-2">Tip: Order is <b>[longitude, latitude]</b>.</span>
        </div>
      </div>

      <!-- Polygon editor -->
      <div v-else class="mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-600">Polygon Coordinates (outer ring)</span>
          </div>
          <div class="flex items-center gap-2">
            <button class="px-3 py-1.5 text-sm rounded bg-gray-100 hover:bg-gray-200 border" @click="resetSquare(0.00006)" type="button">
              Reset Small Square
            </button>
            <button class="px-3 py-1.5 text-sm rounded bg-gray-100 hover:bg-gray-200 border" @click="resetSquare(0.00012)" type="button">
              Reset Bigger Square
            </button>
            <button class="px-3 py-1.5 text-sm rounded bg-gray-100 hover:bg-gray-200 border" @click="addVertex" type="button">
              + Add Vertex
            </button>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 mt-3">
          <span class="text-xs text-gray-500">Nudge all:</span>
          <button class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 border" @click="nudgePolygon( 0.00002,  0)">→</button>
          <button class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 border" @click="nudgePolygon(-0.00002,  0)">←</button>
          <button class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 border" @click="nudgePolygon( 0,  0.00002)">↑</button>
          <button class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 border" @click="nudgePolygon( 0, -0.00002)">↓</button>
        </div>

        <div class="mt-3 overflow-x-auto border rounded">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left">#</th>
                <th class="px-3 py-2 text-left">Longitude</th>
                <th class="px-3 py-2 text-left">Latitude</th>
                <th class="px-3 py-2"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(pair, idx) in polygonCoords" :key="idx" class="border-t">
                <td class="px-3 py-2">{{ idx + 1 }}</td>
                <td class="px-3 py-2">
                  <input v-model.number="pair[0]" type="number" step="0.000001" class="w-full border rounded-lg px-2 py-1" />
                </td>
                <td class="px-3 py-2">
                  <input v-model.number="pair[1]" type="number" step="0.000001" class="w-full border rounded-lg px-2 py-1" />
                </td>
                <td class="px-3 py-2 text-right">
                  <button
                    class="px-2 py-1 text-xs rounded bg-red-50 text-red-700 hover:bg-red-100 border"
                    type="button"
                    @click="removeVertex(idx)"
                    :disabled="polygonCoords.length <= 3"
                  >
                    Remove
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <p class="text-xs text-gray-500 mt-2">
          A valid polygon needs at least 4 rows (the last equals the first; we close the ring on save). Coordinates are <b>[longitude, latitude]</b>.
        </p>
      </div>

      <!-- Live GeoJSON Preview -->
      <div class="mt-5">
        <label class="text-sm text-gray-600">GeoJSON Preview (read-only)</label>
        <pre class="w-full border rounded-lg p-3 text-xs bg-gray-50 overflow-auto">{{ geoPreview }}</pre>
      </div>
    </div>

    <!-- Sticky actions -->
    <div class="sticky bottom-3 mt-6 flex justify-end">
      <div class="bg-white/90 backdrop-blur rounded-xl shadow border px-3 py-2 flex items-center gap-2">
        <button
          @click="router.visit(route('admin.map'))"
          class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"
        >
          Cancel
        </button>
        <button
          @click="submit"
          class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
        >
          Save Plot
        </button>
      </div>
    </div>
  </div>
</template>
