<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import maplibregl from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import Swal from 'sweetalert2'

// Props from controller
const props = defineProps({
  plots: {
    type: Array,
    default: () => [],
  },
  from: {
    type: String,
    default: null,
  },
  service_type: {
    type: String,
    default: null,
  },
})

const BRAND = {
  mintBgFrom: '#F7FCF9',
  mintBgTo:   '#FFFFFF',
  emerald:    '#4FA07F',
  yellow:     '#F5D146',
  violet:     '#6E63A6',
  ink:        '#1C1C1C',
}

const LOGO = '/images/Bethany.png'

// Undas special styling (Nov 1–2)
const today = new Date()
const IS_UNDAS =
  today.getMonth() === 10 && (today.getDate() === 1 || today.getDate() === 2)

/* ------------------------------------------------------------------ */
/* Map constants                                                      */
/* ------------------------------------------------------------------ */

// ESRI World Imagery
const SATELLITE_STYLE = {
  version: 8,
  glyphs: 'https://demotiles.maplibre.org/font/{fontstack}/{range}.pbf',
  sources: {
    esri: {
      type: 'raster',
      tiles: [
        'https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      ],
      tileSize: 256,
      attribution: 'Imagery © Esri, Maxar, Earthstar Geographics',
    },
  },
  layers: [{ id: 'esri-satellite', type: 'raster', source: 'esri' }],
}

// Focus over Bethany
const CENTER = { lng: 123.9498, lat: 9.9469, zoom: 18.2 }

// IDs
const plotsSourceId     = 'plots-src'
const fillLayerId       = 'plots-fill'
const lineLayerId       = 'plots-line'
const labelLayerId      = 'plots-label'
const highlightLayerId  = 'plots-highlight'
const candleLayerId     = 'plots-candles' // 🔥 candle emoji overlay

/* ------------------------------------------------------------------ */
/* Reactive state                                                     */
/* ------------------------------------------------------------------ */

const map          = ref(null)
const loading      = ref(false)
const error        = ref('')
const search       = ref('')
const filterStatus = ref('')
const filterBlock  = ref('')
const selectedLot  = ref(null)

const totals = ref({
  total: 0,
  vacant: 0,
  reserved: 0,
  occupied: 0,
})

/* ------------------------------------------------------------------ */
/* Helpers                                                            */
/* ------------------------------------------------------------------ */

function statusColor (s) {
  if (s === 'vacant')   return '#22c55e'
  if (s === 'reserved') return '#eab308'
  if (s === 'occupied') return '#ef4444'
  return '#9ca3af'
}

// Reuse the same occupant display logic as admin pages
function getOccupantDisplay (props = {}) {
  return (
    props.occupant_name ||
    props.deceased_name ||
    props.reserved_by_name ||
    props.applicant_name ||
    ''
  )
}

function colorExpression () {
  return [
    'match',
    ['get', 'status'],
    'vacant',   statusColor('vacant'),
    'reserved', statusColor('reserved'),
    'occupied', statusColor('occupied'),
    '#9ca3af',
  ]
}

function buildFilter () {
  const f = ['all']
  if (filterStatus.value) {
    f.push(['==', ['get', 'status'], filterStatus.value])
  }
  if (filterBlock.value) {
    f.push(['==', ['get', 'section'], filterBlock.value])
  }
  return f
}

function computeTotals (fc) {
  const c = { total: 0, vacant: 0, reserved: 0, occupied: 0 }
  for (const f of fc.features || []) {
    c.total++
    const s = f.properties?.status
    if (s && c[s] !== undefined) c[s]++
  }
  totals.value = c
}

function fitToData (fc) {
  if (!fc.features?.length) return
  const b = new maplibregl.LngLatBounds()
  fc.features.forEach(f => {
    const g = f.geometry
    if (!g) return
    if (g.type === 'Point') {
      b.extend(g.coordinates)
    } else if (g.type === 'Polygon') {
      g.coordinates[0].forEach(c => b.extend(c))
    } else if (g.type === 'MultiPolygon') {
      g.coordinates.forEach(poly => poly[0].forEach(c => b.extend(c)))
    }
  })
  if (!b.isEmpty()) {
    map.value.fitBounds(b, { padding: 40 })
  }
}

function setHighlight (lotNumber) {
  selectedLot.value = lotNumber || null
  if (!map.value?.getLayer(highlightLayerId)) return

  if (!lotNumber) {
    map.value.setFilter(highlightLayerId, ['==', ['get', 'lot_number'], '__none__'])
  } else {
    map.value.setFilter(highlightLayerId, ['==', ['get', 'lot_number'], lotNumber])
  }
}

/* ------------------------------------------------------------------ */
/* Map init + data loading                                            */
/* ------------------------------------------------------------------ */

function initMap () {
  map.value = new maplibregl.Map({
    container: 'public-map',
    style: SATELLITE_STYLE,
    center: [CENTER.lng, CENTER.lat],
    zoom: CENTER.zoom,
    attributionControl: true,
  })

  map.value.addControl(new maplibregl.NavigationControl(), 'top-right')
  map.value.addControl(new LogoControl(), 'bottom-right')

  map.value.on('load', () => {
    loadPlots()
    registerClickHandler()
  })
}

async function loadPlots () {
  loading.value = true
  error.value = ''
  try {
    const { data } = await axios.get('/api/public/plots?t=' + Date.now())

    if (!map.value.getSource(plotsSourceId)) {
      map.value.addSource(plotsSourceId, {
        type: 'geojson',
        data,
        promoteId: 'id',
      })

      map.value.addLayer({
        id: fillLayerId,
        type: 'fill',
        source: plotsSourceId,
        paint: {
          'fill-color': colorExpression(),
          'fill-opacity': 0.38,
        },
      })

      map.value.addLayer({
        id: lineLayerId,
        type: 'line',
        source: plotsSourceId,
        paint: {
          'line-color': '#ffffff',
          'line-opacity': 0.65,
          'line-width': 0.8,
        },
      })

      map.value.addLayer({
        id: labelLayerId,
        type: 'symbol',
        source: plotsSourceId,
        layout: {
          'text-field': ['coalesce', ['get', 'lot_number'], ''],
          'text-size': 11,
          'text-offset': [0, 0.2],
          'text-optional': true,
        },
        paint: {
          'text-color': '#ffffff',
          'text-halo-width': 0.9,
          'text-halo-color': '#0b1220',
        },
      })

      // 🔥 Candle emoji overlay for plots with candles
      if (!map.value.getLayer(candleLayerId)) {
        map.value.addLayer({
          id: candleLayerId,
          type: 'symbol',
          source: plotsSourceId,
          layout: {
            'text-field': '🕯',
            'text-size': 14,
            'text-offset': [0, -0.9],
            'text-allow-overlap': true,
          },
          paint: {
            'text-color': IS_UNDAS ? '#f59e0b' : '#fde68a',
            'text-halo-width': 1.1,
            'text-halo-color': '#0b1220',
          },
          // base filter: only where candle_count > 0
          filter: ['>', ['coalesce', ['get', 'candle_count'], 0], 0],
        })
      }

      // Highlight outline
      if (!map.value.getLayer(highlightLayerId)) {
        map.value.addLayer({
          id: highlightLayerId,
          type: 'line',
          source: plotsSourceId,
          paint: {
            'line-color': '#0ea5e9',
            'line-width': 3,
            'line-opacity': 0.9,
          },
          filter: ['==', ['get', 'lot_number'], '__none__'],
        })
      }
    } else {
      map.value.getSource(plotsSourceId).setData(data)
    }

    applyFilters()
    computeTotals(data)
    data?.features?.length
      ? fitToData(data)
      : map.value.flyTo({ center: [CENTER.lng, CENTER.lat], zoom: CENTER.zoom })
  } catch (e) {
    console.error(e)
    error.value = 'Failed to load plots.'
  } finally {
    loading.value = false
  }
}

/* ------------------------------------------------------------------ */
/* Filters + search                                                   */
/* ------------------------------------------------------------------ */

function applyFilters () {
  const baseFilter = buildFilter() // ['all', ...conditions]

  // Apply to fill/line/label as-is
  ;[fillLayerId, lineLayerId, labelLayerId].forEach(id => {
    if (map.value.getLayer(id)) {
      map.value.setFilter(id, baseFilter)
    }
  })

  // For candle layer, combine status/section filters with candle_count > 0
  if (map.value.getLayer(candleLayerId)) {
    const candleFilter = [
      'all',
      ...baseFilter.slice(1),
      ['>', ['coalesce', ['get', 'candle_count'], 0], 0],
    ]
    map.value.setFilter(candleLayerId, candleFilter)
  }
}

function searchPlot () {
  const q = search.value.trim().toLowerCase()
  if (!q || !map.value?.getSource(plotsSourceId)) {
    setHighlight(null)
    return
  }

  const src = map.value.getSource(plotsSourceId)
  const data = src?._data
  if (!data?.features?.length) {
    setHighlight(null)
    return
  }

  const match = data.features.find(f => {
    const p = f.properties || {}
    const lot = p.lot_number ? p.lot_number.toLowerCase() : ''
    const name = getOccupantDisplay(p).toLowerCase()
    return (lot && lot.includes(q)) || (name && name.includes(q))
  })

  if (!match) {
    setHighlight(null)
    return
  }

  const p = match.properties || {}
  setHighlight(p.lot_number || null)

  const b = new maplibregl.LngLatBounds()
  const g = match.geometry
  if (g.type === 'Point') {
    b.extend(g.coordinates)
  } else if (g.type === 'Polygon') {
    g.coordinates[0].forEach(c => b.extend(c))
  } else if (g.type === 'MultiPolygon') {
    g.coordinates.forEach(poly => poly[0].forEach(c => b.extend(c)))
  }
  if (!b.isEmpty()) {
    map.value.fitBounds(b, { padding: 44, maxZoom: 20 })
  }
}

function recenter () {
  if (!map.value) return
  setHighlight(null)
  map.value.flyTo({ center: [CENTER.lng, CENTER.lat], zoom: CENTER.zoom })
}

function refresh () {
  loadPlots()
}

function clearFilters () {
  filterStatus.value = ''
  filterBlock.value  = ''
  setHighlight(null)
}

/* ------------------------------------------------------------------ */
/* Back button                                                        */
/* ------------------------------------------------------------------ */

function goBack () {
  if (window.history.length > 1) {
    window.history.back()
  } else {
    try {
      router.visit(route('landing'))
    } catch (e) {
      window.location.href = '/'
    }
  }
}

/* ------------------------------------------------------------------ */
/* Virtual candle helpers                                             */
/* ------------------------------------------------------------------ */

function formatRelativeTime(isoString) {
  if (!isoString) return ''
  const date = new Date(isoString)
  const now  = new Date()
  const diff = (now.getTime() - date.getTime()) / 1000 // sec

  if (diff < 60) return 'just now'
  if (diff < 3600) {
    const m = Math.floor(diff / 60)
    return `${m} min${m === 1 ? '' : 's'} ago`
  }
  if (diff < 86400) {
    const h = Math.floor(diff / 3600)
    return `${h} hour${h === 1 ? '' : 's'} ago`
  }
  const d = Math.floor(diff / 86400)
  return `${d} day${d === 1 ? '' : 's'} ago`
}

/**
 * Shows the "all candles" history in a SweetAlert modal
 */
async function showCandleHistory(plotId, lotNumber, occupant) {
  try {
    const { data } = await axios.get('/api/public/candles', {
      params: { plot_id: plotId, limit: 100 }
    })

    const list = data.candles || []
    const itemsHtml = list.length
      ? list.map(c => `
          <div class="candle-history-item">
            <div class="candle-history-header">
              <span class="candle-history-name">🕯 ${c.name || 'Anonymous'}</span>
              <span class="candle-history-time">${formatRelativeTime(c.lit_at || c.created_at)}</span>
            </div>
            ${c.message ? `<div class="candle-history-msg">${c.message}</div>` : ''}
          </div>
        `).join('')
      : '<div class="candle-history-empty">No candles yet for this plot.</div>'

    await Swal.fire({
      title: `Candles for ${lotNumber || 'this plot'}`,
      html: `
        <div class="candle-history-wrapper">
          ${occupant
            ? `<div class="candle-history-occupant">In memory of <strong>${occupant}</strong></div>`
            : ''
          }
          <div class="candle-history-list">
            ${itemsHtml}
          </div>
        </div>
      `,
      width: 480,
      confirmButtonText: 'Close',
      confirmButtonColor: '#4FA07F',
    })
  } catch (err) {
    console.error(err)
    const msg = err?.response?.data?.message || 'Could not load candles.'
    Swal.fire('Error', msg, 'error')
  }
}

/* ------------------------------------------------------------------ */
/* Click popup (Apply button + Virtual candle)                        */
/* ------------------------------------------------------------------ */

function registerClickHandler () {
  map.value.on('click', (e) => {
    const features = map.value.queryRenderedFeatures(e.point, {
      layers: [fillLayerId, lineLayerId],
    })
    if (!features.length) return

    const f = features[0]
    const p = f.properties || {}
    const s = p.status
    const occupant = getOccupantDisplay(p)
    const candleCount = typeof p.candle_count !== 'undefined'
      ? Number(p.candle_count)
      : null

    setHighlight(p.lot_number || null)

    const candleInfoHtml =
      candleCount !== null && s === 'occupied'
        ? `<div style="margin-top:4px;font-size:11px;color:#6b7280;">
             🕯 <strong>${candleCount}</strong> virtual candle${candleCount === 1 ? '' : 's'} lit
           </div>
           <div id="candleListSummary" style="margin-top:4px;font-size:11px;color:#6b7280;">
             Loading recent candles…
           </div>`
        : ''

    const html = `
      <div style="font-size:12px;min-width:230px">
        <div style="margin-bottom:4px;">
          <strong>Lot:</strong> ${p.lot_number ?? '—'}
          <span style="
            background:${statusColor(s)}22;
            border:1px solid ${statusColor(s)};
            border-radius:9999px;
            padding:1px 6px;
            margin-left:4px;
            font-size:10px;
          ">
            ${s || '—'}
          </span>
        </div>
        ${p.section ? `<div><strong>Section:</strong> ${p.section}</div>` : ''}

        ${
          (s === 'occupied' || s === 'reserved')
            ? `<div><strong>${s === 'reserved' ? 'Reserved for' : 'Occupant'}:</strong> ${occupant || '(no name set)'}</div>`
            : ''
        }

        ${candleInfoHtml}

        ${
          s === 'vacant'
            ? `<div style="margin-top:10px;">
                 <button id="applyPlotBtn" style="
                   background:${BRAND.yellow};
                   color:#1C1C1C;
                   border:none;
                   border-radius:9999px;
                   padding:5px 10px;
                   font-size:11px;
                   font-weight:600;
                   cursor:pointer;
                 ">
                   Apply for this plot
                 </button>
               </div>`
            : ''
        }

        ${
          s === 'occupied'
            ? `<div style="margin-top:8px;display:flex;flex-direction:column;gap:6px;">
                 <button id="lightCandleBtn" style="
                   background:${IS_UNDAS ? '#fbbf24' : BRAND.emerald};
                   color:#ffffff;
                   border:none;
                   border-radius:9999px;
                   padding:5px 10px;
                   font-size:11px;
                   font-weight:600;
                   cursor:pointer;
                 ">
                   🕯 Light a virtual candle
                 </button>
                 <button id="viewCandlesBtn" style="
                   background:#ffffff;
                   color:#4B5563;
                   border:1px solid #E5E7EB;
                   border-radius:9999px;
                   padding:4px 10px;
                   font-size:11px;
                   font-weight:500;
                   cursor:pointer;
                 ">
                   View candle messages
                 </button>
               </div>`
            : ''
        }
      </div>
    `

    const popup = new maplibregl.Popup({ closeOnClick: true })
      .setLngLat(e.lngLat)
      .setHTML(html)
      .addTo(map.value)

    // Bind events after DOM is rendered
    setTimeout(async () => {
      const root = popup.getElement()
      if (!root) return

      // --- Apply button for vacant plots ---
      const applyBtn = root.querySelector('#applyPlotBtn')
      if (applyBtn && s === 'vacant') {
        applyBtn.addEventListener('click', () => {
          popup.remove()

          const params = {
            assigned_plot_id: p.id,
          }

          if (p.lot_number) {
            params.selected_lot = p.lot_number
          }

          // Use the service_type from the Apply page when in picker mode,
          // otherwise default to Lawn Lot for direct public map access.
          if (props.service_type) {
            params.service_type = props.service_type
          } else {
            params.service_type = 'Lawn Lot'
          }

          // Let the Apply page know we came from map picker
          if (props.from === 'apply') {
            params.from = 'apply'
          }

          try {
            router.visit(route('apply.create', params))
          } catch (err) {
            const qs = new URLSearchParams(params).toString()
            window.location.href = `/apply?${qs}`
          }
        })
      }

      // --- Recent candles summary (top 3) ---
      if (s === 'occupied') {
        const listEl = root.querySelector('#candleListSummary')
        if (listEl) {
          try {
            const { data } = await axios.get('/api/public/candles', {
              params: { plot_id: p.id, limit: 3 }
            })
            const candles = data.candles || []

            if (!candles.length) {
              listEl.innerHTML = '<span style="font-size:11px;color:#9ca3af;">No recent candles yet.</span>'
            } else {
              listEl.innerHTML = candles.map(c => `
                <div style="margin-top:2px;">
                  <strong>${c.name || 'Anonymous'}</strong>
                  <span style="color:#9ca3af;"> • ${formatRelativeTime(c.lit_at || c.created_at)}</span>
                  ${c.message ? `<div style="color:#6b7280;">“${c.message.slice(0,60)}${c.message.length > 60 ? '…' : ''}”</div>` : ''}
                </div>
              `).join('')
            }
          } catch (err) {
            console.error(err)
            listEl.innerHTML = '<span style="font-size:11px;color:#b91c1c;">Failed to load candles.</span>'
          }
        }
      }

      // --- Light candle button for occupied plots ---
      const candleBtn = root.querySelector('#lightCandleBtn')
      if (candleBtn && s === 'occupied') {
        candleBtn.addEventListener('click', async () => {
          popup.remove()

          const { value: formValues } = await Swal.fire({
            title: 'Light a virtual candle',
            html: `
              <div class="swal-candle-form">
                <p class="swal-candle-caption">
                  In memory of <strong>${occupant || ('Lot ' + (p.lot_number ?? ''))}</strong>
                </p>
                <input id="swal-candle-name" class="swal2-input" placeholder="Your name (optional)" />
                <textarea id="swal-candle-message" class="swal2-textarea" placeholder="Short message or prayer (optional)"></textarea>
                <label class="swal-candle-sound">
                  <input type="checkbox" id="swal-candle-sound-toggle" checked />
                  <span>Play soft candle sound</span>
                </label>
              </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Light candle',
            confirmButtonColor: '#4FA07F',
            preConfirm: () => {
              const name = (document.getElementById('swal-candle-name') || {}).value || ''
              const message = (document.getElementById('swal-candle-message') || {}).value || ''
              const playSound = !!(document.getElementById('swal-candle-sound-toggle') || {}).checked
              if (!name && !message) {
                Swal.showValidationMessage('Please enter a name or a short message.')
                return false
              }
              return { name, message, playSound }
            }
          })

          if (!formValues) return

          try {
            await axios.post('/api/public/candles', {
              plot_id: p.id,
              name: formValues.name,
              message: formValues.message,
            })

            // Optional sound (place your file at /public/sounds/candle-light.mp3)
            if (formValues.playSound) {
              try {
                const audio = new Audio('/sounds/candle-light.mp3')
                audio.volume = 0.6
                audio.play().catch(() => {})
              } catch (e) {
                console.warn('Candle sound failed to play', e)
              }
            }

            // Ritual animation modal
            await Swal.fire({
              title: 'Candle lit',
              html: `
                <div class="candle-ceremony-wrapper ${IS_UNDAS ? 'candle-ceremony-undas' : ''}">
                  <div class="candle-ceremony">
                    <div class="candle-base"></div>
                    <div class="candle-flame"></div>
                    <div class="candle-glow"></div>
                    <div class="candle-sparkles"></div>
                  </div>
                  <p class="candle-ceremony-text">
                    Your candle now burns in honor and remembrance.
                  </p>
                </div>
              `,
              showConfirmButton: true,
              confirmButtonText: 'Close',
              confirmButtonColor: '#4FA07F',
              width: 360,
            })

            // Refresh map data so candle count & emoji update
            loadPlots()
          } catch (err) {
            console.error(err)
            const msg = err?.response?.data?.message || 'Could not light a candle. Please try again.'
            Swal.fire('Error', msg, 'error')
          }
        })
      }

      // --- View candle history ---
      const viewBtn = root.querySelector('#viewCandlesBtn')
      if (viewBtn && s === 'occupied') {
        viewBtn.addEventListener('click', () => {
          showCandleHistory(p.id, p.lot_number, occupant)
        })
      }
    }, 0)
  })
}

/* ------------------------------------------------------------------ */
/* Simple logo control (public: go home)                              */
/* ------------------------------------------------------------------ */

class LogoControl {
  onAdd (mapInstance) {
    this._map = mapInstance
    const el = document.createElement('div')
    el.className = 'maplibregl-ctrl public-map-logo-ctrl'
    el.innerHTML = `<img src="${LOGO}" alt="Bethany Memorial Park" style="height:28px;width:auto;display:block;"/>`
    el.title = 'Back to home'
    el.addEventListener('click', () => {
      try {
        router.visit('/')
      } catch (_) {
        window.location.href = '/'
      }
    })
    this._container = el
    return el
  }
  onRemove () {
    this._container?.remove?.()
    this._map = undefined
  }
}

/* ------------------------------------------------------------------ */
/* Lifecycle & watchers                                               */
/* ------------------------------------------------------------------ */

onMounted(() => {
  initMap()
})

onBeforeUnmount(() => {
  if (map.value) {
    map.value.remove()
    map.value = null
  }
})

watch([filterStatus, filterBlock], applyFilters)
</script>

<template>
  <div class="h-screen bg-gradient-to-b from-[#F7FCF9] to-white flex flex-col overflow-hidden">
    <!-- Top brand bar -->
    <div
      class="h-1.5 w-full"
      :style="{ background:`linear-gradient(90deg, ${BRAND.emerald}, ${BRAND.yellow}, ${BRAND.violet})` }"
    ></div>

    <!-- Header -->
    <header
      class="h-16 flex items-center justify-between px-4 sm:px-6 lg:px-8 border-b bg-white/85 backdrop-blur
             shadow-[0_1px_0_0_rgba(79,160,127,.10)]"
    >
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="hidden sm:inline-flex items-center gap-1 px-3 py-2 mr-1 rounded-lg border border-slate-200 text-slate-700 hover:bg-[#EFFEFA] text-sm"
          @click="goBack"
        >
          ← Back
        </button>
        <img
          :src="LOGO"
          alt="Bethany Memorial Park"
          class="h-9 w-9 rounded-lg object-contain bg-white ring-2 ring-[#4FA07F]/40 shadow-sm"
        />
        <div>
          <div class="text-sm text-gray-500">Bethany Memorial Park</div>
          <h1 class="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight">
            Cemetery Map
          </h1>
        </div>
      </div>

      <div class="flex items-center gap-2 text-sm">
        <button
          type="button"
          class="sm:hidden inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-slate-200 text-slate-700 hover:bg-[#EFFEFA]"
          @click="goBack"
        >
          ← Back
        </button>
        <Link
          :href="route('apply.create')"
          class="hidden sm:inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-[#F5D146] text-[#1C1C1C] font-semibold hover:brightness-95"
        >
          Apply for Services
        </Link>
        <Link
          :href="route('login')"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-[#6E63A6] text-[#6E63A6] font-medium hover:bg-[#EFFEFA]"
        >
          Admin
        </Link>
      </div>
    </header>

    <!-- Body -->
    <div class="flex-1 flex flex-col md:flex-row overflow-hidden">
      <!-- Sidebar (filters + legend) -->
      <aside
        class="w-full md:w-80 max-w-full bg-white/95 backdrop-blur border-b md:border-b-0 md:border-r
               border-[rgba(79,160,127,0.28)] shadow-sm z-10"
      >
        <div class="h-full overflow-y-auto p-4 space-y-5">
          <!-- Filters -->
          <section class="card">
            <div class="card__title">Find a lot</div>
            <div class="space-y-2">
              <label class="text-xs text-slate-500">Search by lot # or name</label>
              <div class="flex gap-2">
                <div class="flex-1">
                  <input
                    v-model="search"
                    @keyup.enter="searchPlot"
                    placeholder="e.g. 1-023 or Dela Cruz"
                    class="input"
                  />
                </div>
                <button class="btn flex.items-center gap-1" @click="searchPlot">
                  🔎 <span>Go</span>
                </button>
              </div>
            </div>
          </section>

          <section class="card">
            <div class="card__title">Filters</div>
            <div class="grid grid-cols-1 gap-2">
              <div>
                <label class="text-xs text-slate-500">Block / Section</label>
                <select v-model="filterBlock" class="input mt-1">
                  <option value="">All</option>
                  <option value="BLOCK 1">BLOCK 1</option>
                  <option value="BLOCK 2">BLOCK 2</option>
                  <option value="BLOCK 3">BLOCK 3</option>
                  <option value="BLOCK 4">BLOCK 4</option>
                </select>
              </div>
              <div>
                <label class="text-xs text-slate-500">Status</label>
                <select v-model="filterStatus" class="input mt-1">
                  <option value="">All</option>
                  <option value="vacant">Vacant</option>
                  <option value="reserved">Reserved</option>
                  <option value="occupied">Occupied</option>
                </select>
              </div>
              <button class="btn mt-1" @click="clearFilters">
                Clear filters
              </button>
            </div>
          </section>

          <!-- Legend + totals -->
          <section class="card">
            <div class="card__title">Legend</div>
            <ul class="space-y-2 text-sm text-slate-700">
              <li class="flex items-center"><span class="legend legend--vacant"></span> Vacant</li>
              <li class="flex items-center"><span class="legend legend--reserved"></span> Reserved</li>
              <li class="flex items-center"><span class="legend legend--occupied"></span> Occupied</li>
              <li class="flex items-center"><span class="legend legend--candle"></span> Plot has candles</li>
            </ul>

            <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
              <div class="kpi">
                <div class="kpi__label">Total</div>
                <div class="kpi__value">{{ totals.total }}</div>
              </div>
              <div class="kpi">
                <div class="kpi__label">Vacant</div>
                <div class="kpi__value text-green-700">{{ totals.vacant }}</div>
              </div>
              <div class="kpi">
                <div class="kpi__label">Reserved</div>
                <div class="kpi__value text-yellow-700">{{ totals.reserved }}</div>
              </div>
              <div class="kpi">
                <div class="kpi__label">Occupied</div>
                <div class="kpi__value text-red-700">{{ totals.occupied }}</div>
              </div>
            </div>
          </section>

          <section class="text-xs text-slate-500">
            Tap a lot on the map for more information or to light a virtual candle.
          </section>

          <div class="flex gap-2">
            <button class="btn flex-1" @click="recenter">Recenter</button>
            <button class="btn flex-1" @click="refresh">Refresh</button>
          </div>
        </div>
      </aside>

      <!-- Map -->
      <main class="flex-1 relative flex flex-col">
        <div class="flex-1 p-3 md:p-4">
          <div
            v-if="loading"
            class="absolute inset-4 z-10 bg-white/70 backdrop-blur-sm flex items-center justify-center
                   rounded-xl border border-emerald-100"
          >
            <div class="spinner"></div>
            <span class="ml-3 text-slate-700 text-sm">Loading map…</span>
          </div>

          <div
            v-if="error"
            class="absolute top-6 left-6 z-10 bg-red-50 text-red-700 border border-red-200 px-3 py-2 rounded text-sm"
          >
            {{ error }}
          </div>

          <div
            id="public-map"
            class="w-full h-full rounded-xl border border-emerald-100 bg-slate-100
                   shadow-[0_1px_0_0_rgba(79,160,127,.06),0_12px_28px_rgba(2,6,23,.06)]
                   overflow-hidden"
          ></div>
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
* {
  transition:
    background-color .16s ease,
    color .16s ease,
    border-color .16s ease,
    box-shadow .16s ease;
}
@media (prefers-reduced-motion: reduce) {
  * { transition: none !important; }
}

/* Buttons & inputs */
.btn {
  padding: 0.45rem 0.9rem;
  font-size: 0.875rem;
  border-radius: 0.75rem;
  border: 1px solid rgba(15,23,42,0.12);
  background: #fff;
  color: #0f172a;
  cursor: pointer;
}
.btn:hover { background: #EFFEFA; }

.input {
  width: 100%;
  padding: 0.45rem 0.75rem;
  font-size: 0.875rem;
  border-radius: 0.75rem;
  border: 1px solid rgba(15,23,42,0.12);
  background: #fff;
}
.input:focus {
  outline: none;
  border-color: #4FA07F;
  box-shadow: 0 0 0 3px rgba(79,160,127,.18);
}

/* Cards */
.card {
  border-radius: 0.9rem;
  border: 1px solid rgba(79,160,127,0.20);
  background: rgba(255,255,255,0.96);
  backdrop-filter: blur(8px);
  padding: 0.9rem;
  box-shadow: 0 1px 0 0 rgba(79,160,127,0.06);
}
.card__title {
  font-size: 0.85rem;
  font-weight: 600;
  color: #4FA07F;
  margin-bottom: 0.35rem;
}

/* Legend squares */
.legend {
  display: inline-block;
  width: 1rem;
  height: 1rem;
  margin-right: 0.5rem;
  border-radius: 0.2rem;
}
.legend--vacant   { background:#22c55e; }
.legend--reserved { background:#eab308; }
.legend--occupied { background:#ef4444; }
.legend--candle   {
  background: radial-gradient(circle at 50% 30%, #fbbf24 0%, #ea580c 40%, transparent 70%);
}

/* KPI chips */
.kpi {
  border-radius: 0.85rem;
  border: 1px solid rgba(15,23,42,0.08);
  background: #fff;
  padding: 0.4rem 0.65rem;
  text-align: center;
}
.kpi__label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
}
.kpi__value {
  font-size: 0.95rem;
  font-weight: 600;
  color: #0f172a;
}

/* Spinner */
.spinner {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 999px;
  border: 3px solid rgba(79,160,127,.25);
  border-top-color: #4FA07F;
  animation: spin .8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg) } }

/* Map logo control */
.public-map-logo-ctrl {
  padding: 6px;
  background: rgba(255,255,255,.9);
  backdrop-filter: blur(4px);
  border: 1px solid rgba(79,160,127,.25);
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(2,6,23,.12);
  cursor: pointer;
}
.public-map-logo-ctrl img { opacity: .95; }
.public-map-logo-ctrl:hover img { opacity: 1; }
</style>

<!-- Global styles for SweetAlert content & candle animation -->
<style>
.candle-ceremony-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.candle-ceremony {
  position: relative;
  width: 80px;
  height: 100px;
  margin-bottom: 8px;
}
.candle-base {
  position: absolute;
  bottom: 0;
  left: 50%;
  width: 40px;
  height: 55px;
  transform: translateX(-50%);
  border-radius: 10px;
  background: linear-gradient(180deg, #f9fafb, #e5e7eb);
  box-shadow: 0 4px 10px rgba(15,23,42,0.25);
}
.candle-flame {
  position: absolute;
  bottom: 52px;
  left: 50%;
  width: 18px;
  height: 28px;
  transform: translateX(-50%);
  border-radius: 50% 50% 50% 50%;
  background: radial-gradient(circle at 50% 20%, #fef9c3 0%, #facc15 40%, #ea580c 70%);
  animation: flameFlicker 1.2s infinite ease-in-out;
}

.candle-glow {
  position: absolute;
  bottom: 42px;
  left: 50%;
  width: 70px;
  height: 70px;
  transform: translateX(-50%);
  border-radius: 999px;
  background: radial-gradient(circle, rgba(250,204,21,0.18) 0%, transparent 65%);
  filter: blur(2px);
  animation: glowPulse 2.2s infinite ease-in-out;
}

.candle-sparkles {
  position: absolute;
  bottom: 80px;
  left: 50%;
  width: 70px;
  height: 40px;
  transform: translateX(-50%);
  pointer-events: none;
  background-image:
    radial-gradient(circle, rgba(252,211,77,0.6) 1px, transparent 1px),
    radial-gradient(circle, rgba(252,211,77,0.5) 1px, transparent 1px);
  background-size: 12px 12px, 16px 16px;
  animation: sparkFloat 3s infinite linear;
  opacity: 0.8;
}
.candle-ceremony-text {
  font-size: 12px;
  color: #4b5563;
}

/* Undas variant */
.candle-ceremony-undas .candle-base {
  background: linear-gradient(180deg, #fef3c7, #facc15);
}
.candle-ceremony-undas .candle-flame {
  background: radial-gradient(circle at 50% 20%, #fffbeb 0%, #fbbf24 35%, #ea580c 70%);
}

@keyframes flameFlicker {
  0%, 100% { transform: translateX(-50%) scaleY(1); opacity: 1; }
  50%      { transform: translateX(-50%) scaleY(1.15); opacity: 0.88; }
}
@keyframes glowPulse {
  0%, 100% { opacity: 0.7; transform: translateX(-50%) scale(1); }
  50%      { opacity: 1; transform: translateX(-50%) scale(1.1); }
}
@keyframes sparkFloat {
  0%   { transform: translate(-50%, 0); opacity: 0.9; }
  100% { transform: translate(-50%, -10px); opacity: 0.2; }
}

/* Candle history modal */
.candle-history-wrapper {
  text-align: left;
}
.candle-history-occupant {
  font-size: 13px;
  color: #4b5563;
  margin-bottom: 6px;
}
.candle-history-list {
  max-height: 260px;
  overflow-y: auto;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  padding: 6px 8px;
  background: #f9fafb;
}
.candle-history-item {
  padding: 5px 4px;
  border-bottom: 1px solid #e5e7eb;
}
.candle-history-item:last-child {
  border-bottom: none;
}
.candle-history-header {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: #4b5563;
}
.candle-history-name {
  font-weight: 600;
}
.candle-history-time {
  color: #9ca3af;
}
.candle-history-msg {
  font-size: 12px;
  color: #6b7280;
}
.candle-history-empty {
  font-size: 12px;
  color: #9ca3af;
  padding: 10px 4px;
}

/* SweetAlert candle form tweaks */
.swal-candle-form {
  text-align: left;
}
.swal-candle-caption {
  font-size: 12px;
  color: #4b5563;
  margin-bottom: 6px;
}
.swal-candle-sound {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 6px;
  font-size: 11px;
  color: #4b5563;
}
</style>
