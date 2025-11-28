<script setup>
/* =========================================================================
   Imports
   ========================================================================= */
import { ref, onMounted, watch, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import maplibregl from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import MapboxDraw from '@mapbox/mapbox-gl-draw'
import '@mapbox/mapbox-gl-draw/dist/mapbox-gl-draw.css'

/* =========================================================================
   UI State (burger / sidebar)
   ========================================================================= */
const ui = ref({ menuOpen: true }) // open by default; forced open on md+ via CSS
const logoUrl = '/images/Bethany.png'

/* Top-right nav dropdown (same pattern as other pages) */
const showNav = ref(false)
const navRef  = ref(null)

function toggleNav() { showNav.value = !showNav.value }
function closeNav()  { showNav.value = false }

function logout() {
  closeNav()
  router.post('/logout', {}, { onSuccess: () => router.visit('/') })
}

function onDocClick(e) {
  if (navRef.value && !navRef.value.contains(e.target)) closeNav()
}

/* Global navigation helpers, reused by header + logo control */
function goDashboard()    { router.visit('/admin') }
function goApplications() { router.visit('/admin/applications') }
function goReservations() { router.visit('/admin/reservations') }
function goInterments()   { router.visit('/admin/interments') }
function goPlots()        { router.visit('/admin/plots') }
function goAnnouncements(){ router.visit('/admin/announcements') }

/* =========================================================================
   Brand (gradient + palette)
   ========================================================================= */
const BRAND = {
  mintBgFrom: '#F7FCF9',
  mintBgTo:   '#FFFFFF',
  emerald:    '#4FA07F',
  yellow:     '#F5D146',
  violet:     '#6E63A6',
  ink:        '#1C1C1C',
}

/* =========================================================================
   Constants
   ========================================================================= */
// 1) Satellite basemap (ESRI World Imagery)
const SATELLITE_STYLE = {
  version: 8,
  glyphs: 'https://demotiles.maplibre.org/font/{fontstack}/{range}.pbf',
  sources: {
    esri: {
      type: 'raster',
      tiles: [
        'https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
      ],
      tileSize: 256,
      attribution: 'Imagery © Esri, Maxar, Earthstar Geographics'
    }
  },
  layers: [{ id: 'esri-satellite', type: 'raster', source: 'esri' }]
}

// Minimal MapLibre-friendly styles for MapboxDraw (no line-dasharray, no sprite)
const DRAW_STYLES = [
  { id: 'gl-draw-polygon-fill', type: 'fill',
    filter: ['all', ['==', '$type', 'Polygon'], ['!=', 'mode', 'static']],
    paint: { 'fill-color': '#1d4ed8', 'fill-opacity': 0.1 } },
  { id: 'gl-draw-polygon-stroke', type: 'line',
    filter: ['all', ['==', '$type', 'Polygon'], ['!=', 'mode', 'static']],
    paint: { 'line-color': '#1d4ed8', 'line-width': 2 } },
  { id: 'gl-draw-line', type: 'line',
    filter: ['all', ['==', '$type', 'LineString'], ['!=', 'mode', 'static']],
    paint: { 'line-color': '#1d4ed8', 'line-width': 2 } },
  { id: 'gl-draw-polygon-and-line-vertex-halo', type: 'circle',
    filter: ['all', ['==', 'meta', 'vertex'], ['!=', 'mode', 'static']],
    paint: { 'circle-radius': 5, 'circle-color': '#fff' } },
  { id: 'gl-draw-polygon-and-line-vertex', type: 'circle',
    filter: ['all', ['==', 'meta', 'vertex'], ['!=', 'mode', 'static']],
    paint: { 'circle-radius': 3, 'circle-color': '#1d4ed8' } },
  { id: 'gl-draw-polygon-fill-static', type: 'fill',
    filter: ['all', ['==', '$type', 'Polygon'], ['==', 'mode', 'static']],
    paint: { 'fill-color': '#111827', 'fill-opacity': 0.08 } },
  { id: 'gl-draw-polygon-stroke-static', type: 'line',
    filter: ['all', ['==', '$type', 'Polygon'], ['==', 'mode', 'static']],
    paint: { 'line-color': '#111827', 'line-width': 1 } },
  { id: 'gl-draw-line-static', type: 'line',
    filter: ['all', ['==', '$type', 'LineString'], ['==', 'mode', 'static']],
    paint: { 'line-color': '#111827', 'line-width': 2 } },
]

// --- Gap tuning ---
const GAP_SCALE       = 1.5
const EDGE_MARGIN_M   = 0.40
const AISLE_EVERY_ROWS= 8
const AISLE_GAP_M     = 1.40

// 2) Bethany focus
const CENTER = { lng: 123.9498, lat: 9.9469, zoom: 18.2 }

// 3) Plot packing defaults (size)
const PLOT_W_M = 1.0
const PLOT_H_M = 2.5
const MAUSOLEUM_SIZE_M = 5

// breathing space between plots (meters)
const LOT_GAP_X_M   = 0.35
const LOT_GAP_Y_M   = 0.35

// aisles (walkways)
const USE_AISLES       = true

// light origin-search so rows/cols align nicely
const GRID_TRIALS_X = 6
const GRID_TRIALS_Y = 6

// 4) Block → service mapping
const BLOCKS = [
  { name: 'BLOCK 1', service: 'Lawn Lot' },
  { name: 'BLOCK 2', service: 'Lawn Lot' },
  { name: 'BLOCK 3', service: 'Garden Lot' },
  { name: 'BLOCK 4', service: 'Community Vaults' },
]

// 5) Map/source/layer ids
const plotsSourceId = 'plots-src'
const fillLayerId   = 'plots-fill'
const lineLayerId   = 'plots-line'
const labelLayerId  = 'plots-label'

/* =========================================================================
   Reactive State
   ========================================================================= */
const map = ref(null)
const loading = ref(false)
const error = ref('')
const search = ref('')

const filterStatus  = ref('')
const filterSection = ref('')
const totals = ref({ total: 0, vacant: 0, reserved: 0, occupied: 0 })

// Draw mode (blocks)
const design = ref({ enabled: false, drawnIds: [] })
let draw = null

// Bulk selection for plots
const bulk = ref({ enabled: false })
const selectedPlotIds = new Set()

/* =========================================================================
   Utilities
   ========================================================================= */

function getOccupantDisplay(props = {}) {
  return (
    props.occupant_name ||          // ideal (what map expects)
    props.deceased_name ||          // maybe from interment/application
    props.reserved_by_name ||       // maybe from reservation
    props.applicant_name ||         // fallback from original application
    ''
  )
}

function statusColor(s) {
  if (s === 'vacant')   return '#22c55e'
  if (s === 'reserved') return '#eab308'
  if (s === 'occupied') return '#ef4444'
  return '#9ca3af'
}
function colorExpression() {
  return ['match', ['get', 'status'],
    'vacant',   statusColor('vacant'),
    'reserved', statusColor('reserved'),
    'occupied', statusColor('occupied'),
    '#9ca3af'
  ]
}
function buildFilter() {
  const f = ['all']
  if (filterStatus.value)  f.push(['==', ['get', 'status'],  filterStatus.value])
  if (filterSection.value) f.push(['==', ['get', 'section'], filterSection.value])
  return f
}

/* =========================================================================
   WebMercator + Geometry helpers
   ========================================================================= */
const R = 6378137
function lngLatToMeters([lng, lat]) {
  const x = R * lng * Math.PI / 180
  const y = R * Math.log(Math.tan((Math.PI/4) + (lat*Math.PI/360)))
  return [x, y]
}
function metersToLngLat([x, y]) {
  const lng = x / R * 180 / Math.PI
  const lat = (2 * Math.atan(Math.exp(y / R)) - Math.PI/2) * 180 / Math.PI
  return [lng, lat]
}
function ringLngLatToMeters(ring)  { return ring.map(lngLatToMeters) }
function ringMetersToLngLat(ringM) { return ringM.map(metersToLngLat) }

function polygonAreaM(ringM) {
  let a = 0
  for (let i=0,j=ringM.length-1; i<ringM.length; j=i++) {
    a += (ringM[j][0]*ringM[i][1] - ringM[i][0]*ringM[j][1])
  }
  return a / 2
}
function longestEdgeAngleDeg(ringM) {
  let best = 0, maxLen = -1
  for (let i=0; i<ringM.length-1; i++) {
    const [px,py] = ringM[i], [qx,qy] = ringM[i+1]
    const dx = qx-px, dy = qy-py
    const len = Math.hypot(dx, dy)
    if (len > maxLen) { maxLen = len; best = Math.atan2(dy, dx) }
  }
  return best * 180/Math.PI
}
function rotatePoint([x,y], pivot, deg) {
  const a = deg*Math.PI/180, cos=Math.cos(a), sin=Math.sin(a)
  const dx=x-pivot[0], dy=y-pivot[1]
  return [ pivot[0] + dx*cos - dy*sin, pivot[1] + dx*sin + dy*cos ]
}
function rotateRing(ring, pivot, deg) { return ring.map(p => rotatePoint(p, pivot, deg)) }
function bboxOfRing(ring) {
  let minX=Infinity,minY=Infinity,maxX=-Infinity,maxY=-Infinity
  for (const [x,y] of ring) { if(x<minX)minX=x; if(y<minY)minY=y; if(x>maxX)maxX=x; if(y>maxY)maxY=y }
  return {minX,minY,maxX,maxY}
}
function pip(pt, ring) {
  let inside=false
  for (let i=0,j=ring.length-1;i<ring.length;j=i++) {
    const [xi,yi] = ring[i], [xj,yj] = ring[j]
    const intersect = ((yi>pt[1]) !== (yj>pt[1])) &&
      (pt[0] < (xj - xi) * (pt[1] - yi) / (yj - yi + 1e-9) + xi)
    if (intersect) inside = !inside
  }
  return inside
}
// centroid (meters)
function centroidM(ringM) {
  let a = 0, cx = 0, cy = 0
  for (let i = 0, j = ringM.length - 1; i < ringM.length; j = i++) {
    const [x0, y0] = ringM[j], [x1, y1] = ringM[i]
    const f = x0 * y1 - x1 * y0
    a  += f
    cx += (x0 + x1) * f
    cy += (y0 + y1) * f
  }
  a *= 0.5
  if (Math.abs(a) < 1e-9) {
    const n = ringM.length
    const sx = ringM.reduce((s,p)=>s+p[0],0)
    const sy = ringM.reduce((s,p)=>s+p[1],0)
    return [sx/n, sy/n]
  }
  return [cx / (6 * a), cy / (6 * a)]
}
function mausoleumSquareAround([x, y], sizeM) {
  const s = sizeM/2
  return [[x-s,y-s],[x+s,y-s],[x+s,y+s],[x-s,y+s],[x-s,y-s]]
}

// extra helpers for dense packing
function rectAllCornersInside(rectRingM, ringM) {
  for (let i = 0; i < 4; i++) {
    if (!pip(rectRingM[i], ringM)) return false
  }
  return true
}
function buildRectRingAligned(x0, y0, w, h) {
  return [[x0,y0],[x0+w,y0],[x0+w,y0+h],[x0,y0+h],[x0,y0]]
}

/* =========================================================================
   Plot generation (per block) + GeoJSON download
   ========================================================================= */
function generatePlotsForBlock(blockIdx, poly) {
  const props = BLOCKS[blockIdx]
  if (!props) throw new Error('Only 4 blocks supported.')

  // ring in meters (closed)
  const ringDeg = poly.coordinates[0]
  const ringM = ringLngLatToMeters(ringDeg)
  if (ringM[0][0] !== ringM.at(-1)[0] || ringM[0][1] !== ringM.at(-1)[1]) ringM.push(ringM[0])

  // orient grid to the longest edge
  const angleDeg = longestEdgeAngleDeg(ringM)
  const bb0 = bboxOfRing(ringM)
  const pivot = [(bb0.minX + bb0.maxX)/2, (bb0.minY + bb0.maxY)/2]
  const ringAligned = rotateRing(ringM, pivot, -angleDeg)
  const bb = bboxOfRing(ringAligned)

  const baseGapX = LOT_GAP_X_M * GAP_SCALE
  const baseGapY = LOT_GAP_Y_M * GAP_SCALE
  const aisleEvery = USE_AISLES && AISLE_EVERY_ROWS > 0 && AISLE_GAP_M > 0 ? AISLE_EVERY_ROWS : 0

  const trialStepX = PLOT_W_M + baseGapX
  const trialStepY = PLOT_H_M + baseGapY + (aisleEvery ? (AISLE_GAP_M / Math.max(1, aisleEvery)) : 0)

  let best = { count: -1, rects: [] }

  for (let xi = 0; xi < GRID_TRIALS_X; xi++) {
    const xOffset = (xi / GRID_TRIALS_X) * trialStepX
    for (let yi = 0; yi < GRID_TRIALS_Y; yi++) {
      const yOffset = (yi / GRID_TRIALS_Y) * trialStepY

      const minX = bb.minX + EDGE_MARGIN_M + xOffset
      const maxX = bb.maxX - EDGE_MARGIN_M
      const minY = bb.minY + EDGE_MARGIN_M + yOffset
      const maxY = bb.maxY - EDGE_MARGIN_M

      const availX = Math.max(0, maxX - minX)
      const availY = Math.max(0, maxY - minY)

      let cols = Math.max(1, Math.floor((availX + baseGapX) / (PLOT_W_M + baseGapX)))
      let rows = Math.max(1, Math.floor((availY + baseGapY) / (PLOT_H_M + baseGapY)))

      const usedX = cols * PLOT_W_M + (cols - 1) * baseGapX
      const leftoverX = Math.max(0, availX - usedX)
      const extraPerGapX = cols > 1 ? leftoverX / (cols - 1) : 0
      const stepX = PLOT_W_M + baseGapX + extraPerGapX

      const spreadAisleY = aisleEvery ? AISLE_GAP_M / aisleEvery : 0
      const usedYNoAisle = rows * PLOT_H_M + (rows - 1) * baseGapY
      const usedYWithSpread = usedYNoAisle + (rows - 1) * spreadAisleY
      const leftoverY = Math.max(0, availY - usedYWithSpread)
      const extraPerGapY = rows > 1 ? leftoverY / (rows - 1) : 0
      const stepY = PLOT_H_M + baseGapY + extraPerGapY

      const rects = []
      let count = 0

      for (let r = 0; r < rows && count < 500; r++) {
        const aisleJumps = aisleEvery ? Math.floor(r / aisleEvery) : 0
        const y0base = minY + r * stepY + aisleJumps * AISLE_GAP_M

        for (let c = 0; c < cols && count < 500; c++) {
          const x0 = minX + c * stepX

          const rectAligned = buildRectRingAligned(x0, y0base, PLOT_W_M, PLOT_H_M)
          const rectM = rectAligned.map(p => rotatePoint(p, pivot, angleDeg))

          if (!rectAllCornersInside(rectM, ringM)) continue

          count++
          const lotNum = String(count).padStart(3, '0')
          rects.push({
            type: 'Feature',
            properties: {
              id: `${blockIdx + 1}-${lotNum}`,
              lot_number: `${blockIdx + 1}-${lotNum}`,
              status: 'vacant',
              block: props.name,
              section: props.name,
              service_type: props.service,
            },
            geometry: { type: 'Polygon', coordinates: [ ringMetersToLngLat(rectM) ] }
          })
        }
      }

      if (count > best.count) best = { count, rects }
    }
  }

  const features = [...best.rects]
  const ringAligned2 = rotateRing(ringM, pivot, -angleDeg)
  const bba = bboxOfRing(ringAligned2)
  const cornersAligned = [[bba.minX,bba.minY],[bba.maxX,bba.minY],[bba.maxX,bba.maxY],[bba.minX,bba.maxY]]
  cornersAligned.forEach((pt, i) => {
    const sqAligned = mausoleumSquareAround(pt, MAUSOLEUM_SIZE_M)
    const sqRot = sqAligned.map(p => rotatePoint(p, pivot, angleDeg))
    if (!rectAllCornersInside(sqRot, ringM)) return
    features.push({
      type: 'Feature',
      properties: {
        id: `${blockIdx+1}-M${i+1}`,
        lot_number: `${blockIdx+1}-M${i+1}`,
        status: 'vacant',
        block: props.name,
        section: props.name,
        service_type: 'Mausoleum',
      },
      geometry: { type:'Polygon', coordinates: [ ringMetersToLngLat(sqRot) ] }
    })
  })

  return features
}

function downloadFeatureCollection(fc, filename='bethany-block-plots.geojson') {
  const blob = new Blob([JSON.stringify(fc, null, 2)], { type: 'application/geo+json' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = filename
  a.click()
  URL.revokeObjectURL(a.href)
}

/* =========================================================================
   Map init + data loading + UI actions
   ========================================================================= */
function initMap() {
  map.value = new maplibregl.Map({
    container: 'mapgl',
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

    const marker = document.createElement('div')
    marker.style.cssText = 'width:10px;height:10px;border-radius:50%;background:#ef4444;border:2px solid #fff;box-shadow:0 0 0 2px rgba(0,0,0,.2)'
    new maplibregl.Marker({ element: marker, draggable: true })
      .setLngLat([CENTER.lng, CENTER.lat]).addTo(map.value)
      .on('dragend', e => {
        const { lng, lat } = e.target.getLngLat()
        console.log('Drop into CENTER:', { lng: +lng.toFixed(6), lat: +lat.toFixed(6) })
      })
  })

  initDrawControls()

  map.value.on('click', () => {
    if (window.innerWidth < 768 && ui.value.menuOpen) ui.value.menuOpen = false
  })
}

async function loadPlots() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await axios.get(`/api/plots?t=${Date.now()}`)

    if (!map.value.getSource(plotsSourceId)) {
      map.value.addSource(plotsSourceId, { type: 'geojson', data, promoteId: 'id' })

      map.value.addLayer({
        id: fillLayerId, type: 'fill', source: plotsSourceId,
        paint: { 'fill-color': colorExpression(), 'fill-opacity': 0.38 }
      })
      map.value.addLayer({
        id: lineLayerId, type: 'line', source: plotsSourceId,
        paint: { 'line-color': '#ffffff', 'line-opacity': 0.65, 'line-width': 0.8 }
      })
      map.value.addLayer({
        id: 'plots-selected',
        type: 'line',
        source: plotsSourceId,
        paint: {
          'line-color': '#38bdf8',
          'line-width': 3,
          'line-opacity': ['case', ['boolean', ['feature-state', 'selected'], false], 1, 0]
        }
      })
      map.value.addLayer({
        id: labelLayerId, type: 'symbol', source: plotsSourceId,
        layout: {
          'text-field': ['coalesce', ['get', 'lot_number'], ''],
          'text-size': 11, 'text-offset': [0, 0.2], 'text-optional': true
        },
        paint: { 'text-color': '#ffffff', 'text-halo-width': 0.9, 'text-halo-color': '#0b1220' },
      })

      bulkClearSelection()
    } else {
      map.value.getSource(plotsSourceId).setData(data)
      bulkClearSelection()
    }

    applyFilters()
    computeTotals(data)
    data?.features?.length
      ? fitToData(data)
      : map.value.flyTo({ center: [CENTER.lng, CENTER.lat], zoom: CENTER.zoom })

  } catch (e) {
    console.error(e); error.value = 'Failed to load plots.'
  } finally {
    loading.value = false
  }
}

function computeTotals(fc) {
  const c = { total: 0, vacant: 0, reserved: 0, occupied: 0 }
  for (const f of fc.features) {
    c.total++
    const s = f.properties?.status
    if (s && c[s] !== undefined) c[s]++
  }
  totals.value = c
}
function fitToData(fc) {
  if (!fc.features.length) return
  const b = new maplibregl.LngLatBounds()
  fc.features.forEach(f => {
    const g = f.geometry
    if (g.type === 'Point') b.extend(g.coordinates)
    else if (g.type === 'Polygon') g.coordinates[0].forEach(c => b.extend(c))
    else if (g.type === 'MultiPolygon') g.coordinates.forEach(poly => poly[0].forEach(c => b.extend(c)))
  })
  if (!b.isEmpty()) map.value.fitBounds(b, { padding: 40 })
}

/* ---- Feature-state helpers for bulk selection ---- */
function setSelectedState(id, selectedFlag) {
  if (!map.value?.getSource(plotsSourceId)) return
  map.value.setFeatureState({ source: plotsSourceId, id }, { selected: selectedFlag })
}
function togglePlotSelection(id) {
  if (!id) return
  const has = selectedPlotIds.has(id)
  if (has) {
    selectedPlotIds.delete(id)
    setSelectedState(id, false)
  } else {
    selectedPlotIds.add(id)
    setSelectedState(id, true)
  }
}
function bulkSelectAllVisible() {
  if (!map.value) return
  const feats = map.value.queryRenderedFeatures({ layers: [fillLayerId] })
  feats.forEach(f => {
    const id = f.id ?? f.properties?.id
    if (!id) return
    selectedPlotIds.add(id)
    setSelectedState(id, true)
  })
  alert(`Selected ${selectedPlotIds.size} plots.`)
}
function bulkClearSelection() {
  Array.from(selectedPlotIds).forEach(id => setSelectedState(id, false))
  selectedPlotIds.clear()
}
async function bulkDeleteSelected() {
  if (!selectedPlotIds.size) return alert('No plots selected.')
  if (!confirm(`Delete ${selectedPlotIds.size} selected plot(s)? This cannot be undone.`)) return
  try {
    for (const id of Array.from(selectedPlotIds)) {
      await axios.delete(`/api/plots/${id}`)
    }
    bulkClearSelection()
    await loadPlots()
    alert('Selected plots deleted.')
  } catch (e) {
    console.error(e)
    alert('Failed to delete some plots. Check console for details.')
  }
}

/* ---- Filters / Search / Map actions ---- */
function applyFilters() {
  const f = buildFilter()
  ;[fillLayerId, lineLayerId, labelLayerId, 'plots-selected'].forEach(id => {
    if (map.value.getLayer(id)) map.value.setFilter(id, f)
  })
}
function searchPlot() {
  const q = search.value.trim().toLowerCase()
  if (!q) return
  const src = map.value.getSource(plotsSourceId)
  const data = src?._data
  if (!data) return

  const f = data.features.find(f => {
    const p = f.properties || {}
    return (p.lot_number && p.lot_number.toLowerCase().includes(q))
        || (p.occupant_name && p.occupant_name.toLowerCase().includes(q))
  })
  if (!f) return

  const b = new maplibregl.LngLatBounds()
  const g = f.geometry
  if (g.type === 'Point') b.extend(g.coordinates)
  else if (g.type === 'Polygon') g.coordinates[0].forEach(c => b.extend(c))
  else if (g.type === 'MultiPolygon') g.coordinates.forEach(poly => poly[0].forEach(c => b.extend(c)))
  if (!b.isEmpty()) map.value.fitBounds(b, { padding: 44, maxZoom: 20 })
}
function recenter() { map.value.flyTo({ center: [CENTER.lng, CENTER.lat], zoom: CENTER.zoom }) }
function refresh()  { loadPlots() }

/* ---- Click handling (popup vs bulk selection) ---- */
function registerClickHandler() {
  map.value.on('click', (e) => {
    const features = map.value.queryRenderedFeatures(e.point, { layers: [fillLayerId, lineLayerId] })
    if (!features.length) return

    const f = features[0]
    const plotId = f.properties?.id ?? f.id

    const occupant = getOccupantDisplay(f.properties)
    const s        = f.properties.status

    // Bulk mode: just toggle selection, no popup
    if (bulk.value.enabled && (f.layer?.id === fillLayerId || f.layer?.id === lineLayerId)) {
      if (plotId) togglePlotSelection(plotId)
      return
    }

    const html = `
      <div style="font-size:12px;min-width:220px">
        <div style="margin-bottom:4px;">
          <strong>Lot:</strong> ${f.properties.lot_number ?? '—'}
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
        ${f.properties.section ? `<div><strong>Section:</strong> ${f.properties.section}</div>` : ''}

        ${
          (s === 'occupied' || s === 'reserved')
            ? `<div><strong>${s === 'reserved' ? 'Reserved for' : 'Occupant'}:</strong> ${occupant || '(no name set)'}</div>`
            : ''
        }

        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
          <button id="openPlotBtn" style="background:${BRAND.emerald};color:#fff;border:none;border-radius:6px;padding:5px 8px;cursor:pointer;">Open</button>
          <button id="deletePlotBtn" style="background:#dc2626;color:#fff;border:none;border-radius:6px;padding:5px 8px;cursor:pointer;">Delete</button>
        </div>
      </div>
    `

    new maplibregl.Popup()
      .setLngLat(e.lngLat)
      .setHTML(html)
      .addTo(map.value)

    // Bind popup buttons
    setTimeout(() => {
      const openBtn   = document.getElementById('openPlotBtn')
      const deleteBtn = document.getElementById('deletePlotBtn')

      if (openBtn && plotId) {
        openBtn.addEventListener('click', () => {
          router.visit(route('admin.plots.index', { plot_id: plotId }))
        })
      }

      if (deleteBtn && plotId) {
        deleteBtn.addEventListener('click', () => handleDelete(plotId))
      }
    }, 0)
  })
}

async function handleDelete(id) {
  if (!id) return
  if (!confirm('Delete this plot? This cannot be undone.')) return
  try {
    await axios.delete(`/api/plots/${id}`)
    await loadPlots()
    alert('Plot deleted.')
  } catch (e) {
    console.error(e); alert('Failed to delete plot.')
  }
}

/* =========================================================================
   Design Mode (draw polygons → order: left col top→bottom = 1,2,3; right = 4)
   ========================================================================= */
function initDrawControls() {
  draw = new MapboxDraw({
    displayControlsDefault: false,
    controls: {},
    defaultMode: 'simple_select',
    styles: DRAW_STYLES,
  })
  map.value.addControl(draw, 'top-left')
  map.value.on('draw.create', onDrawChange)
  map.value.on('draw.update', onDrawChange)
  map.value.on('draw.delete', onDrawChange)
}
function onDrawChange() {
  const fc = draw.getAll()
  design.value.drawnIds = fc.features.map(f => f.id)
}
function toggleDesignMode() {
  design.value.enabled = !design.value.enabled
  const canvas = map.value.getCanvas()
  if (design.value.enabled) {
    draw.changeMode('simple_select')
    canvas.style.cursor = 'crosshair'
  } else {
    draw.changeMode('simple_select')
    canvas.style.cursor = ''
  }
}
function selectAllBlocks() {
  if (!draw) return
  const ids = draw.getAll().features.map(f => f.id)
  if (ids.length) draw.changeMode('simple_select', { featureIds: ids })
}
function deleteAllBlocks() {
  if (!draw) return
  if (!confirm('Delete ALL drawn blocks?')) return
  draw.set({ type: 'FeatureCollection', features: [] })
  design.value.drawnIds = []
}

function generateAll() {
  const fc = draw.getAll()
  const polys = fc.features.filter(f => f.geometry?.type === 'Polygon')

  if (polys.length !== 4) {
    alert(`Draw exactly 4 polygons (you have ${polys.length}).`)
    return
  }

  const items = polys.map(p => {
    const ringM = ringLngLatToMeters(p.geometry.coordinates[0])
    if (ringM[0][0] !== ringM[ringM.length-1][0] || ringM[0][1] !== ringM[ringM.length-1][1]) {
      ringM.push(ringM[0])
    }
    const [cx, cy] = centroidM(ringM)
    return { feature: p, cx, cy }
  })

  const xs = items.map(i => i.cx).sort((a,b)=>a-b)
  const medianX = (xs[1] + xs[2]) / 2
  const leftCol  = items.filter(i => i.cx <= medianX)
  const rightCol = items.filter(i => i.cx >  medianX)

  if (leftCol.length !== 3 || rightCol.length !== 1) {
    console.warn('Expected 3 polygons on the left and 1 on the right; falling back to area-based order.')
    const fallback = polys
      .map(p => ({ feature: p, area: Math.abs(polygonAreaM(ringLngLatToMeters(p.geometry.coordinates[0]))) }))
      .sort((a,b) => b.area - a.area)
      .map(x => x.feature)

    const outFB = { type: 'FeatureCollection', features: [] }
    fallback.forEach((poly, idx) => outFB.features.push(...generatePlotsForBlock(idx, poly.geometry)))
    downloadFeatureCollection(outFB, 'bethany-block-plots.geojson')
    alert('Generated (fallback ordering). Now go to Admin → Plots → Import.')
    return
  }

  leftCol.sort((a,b) => b.cy - a.cy)

  const orderedForBlocks = [
    leftCol[0].feature, // BLOCK 1 (top-left)
    leftCol[1].feature, // BLOCK 2 (middle-left)
    leftCol[2].feature, // BLOCK 3 (bottom-left)
    rightCol[0].feature // BLOCK 4 (right)
  ]

  const out = { type: 'FeatureCollection', features: [] }
  orderedForBlocks.forEach((poly, idx) => {
    out.features.push(...generatePlotsForBlock(idx, poly.geometry))
  })

  downloadFeatureCollection(out, 'bethany-block-plots.geojson')
  alert('Generated! Now go to Admin → Plots → Import and upload the file.')
}

/* =========================================================================
   Lifecycle
   ========================================================================= */
onMounted(() => {
  document.addEventListener('click', onDocClick)
  initMap()
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
})
watch([filterStatus, filterSection], applyFilters)

// Simple logo control shown on the map (bottom-right)
class LogoControl {
  onAdd(mapInstance) {
    this._map = mapInstance
    const el = document.createElement('div')
    el.className = 'maplibregl-ctrl map-logo-ctrl'
    el.innerHTML = `
      <img src="${logoUrl}" alt="Logo" style="height:28px;width:auto;display:block;"/>
    `
    el.title = 'Go to Dashboard'
    el.addEventListener('click', () => {
      try { goDashboard() } catch (_) {}
    })
    this._container = el
    return el
  }
  onRemove() {
    this._container?.remove?.()
    this._map = undefined
  }
}
</script>

<template>
  <!-- PAGE WRAPPER -->
  <div
    class="h-screen w-full bg-gradient-to-b from-[#F7FCF9] to-white relative flex flex-col overflow-hidden"
  >
    <!-- Top brand accent (thin gradient bar) -->
    <div
      class="fixed top-0 left-0 right-0 h-1.5 z-[60]"
      :style="{ background:`linear-gradient(90deg, ${BRAND.emerald}, ${BRAND.yellow}, ${BRAND.violet})` }"
    ></div>

    <!-- HEADER -->
    <header
      class="h-16 pl-2 pr-3 md:px-4 flex items-center gap-3 border-b z-50
             bg-white/85 backdrop-blur supports-[backdrop-filter]:bg-white/70
             shadow-[0_1px_0_0_rgba(79,160,127,.08)] border-[rgba(79,160,127,0.28)]
             rounded-b-xl mx-2 mt-1.5 flex-shrink-0"
    >
      <!-- Burger (mobile) -->
      <button
        class="inline-flex md:hidden items-center justify-center w-10 h-10 rounded-lg border hover:bg-[#EFFEFA] text-slate-700"
        @click="ui.menuOpen = !ui.menuOpen"
        aria-label="Toggle sidebar"
      >
        ☰
      </button>

      <!-- Title + breadcrumb -->
      <div class="flex flex-col gap-1">
        <nav class="text-[11px] text-gray-500 flex items-center gap-1">
          <button class="hover:underline" @click="goDashboard">Admin</button>
          <span>/</span>
          <span class="text-gray-700 font-medium">Map (GL)</span>
        </nav>
        <div class="flex items-center gap-3">
          <img
            :src="logoUrl"
            alt="Logo"
            class="h-8 w-8 rounded-xl ring-2 ring-[#4FA07F]/40 shadow-sm bg-white object-contain p-1"
          />
          <div>
            <h1 class="text-lg md:text-xl font-extrabold text-slate-900 tracking-tight">
              Cemetery Map (GL)
            </h1>
            <p class="hidden md:block text-[11px] text-slate-600">
              Visualize plots across all blocks, with live status and design tools.
            </p>
          </div>

          <!-- Quick stats chips (similar to Plots page) -->
          <div class="hidden md:flex items-center gap-2 ml-2">
            <span class="px-2 py-0.5 rounded-full text-[11px] bg-white border border-gray-200">
              Total: <span class="font-semibold">{{ totals.total }}</span>
            </span>
            <span class="px-2 py-0.5 rounded-full text-[11px] bg-green-50 text-green-700 border border-green-200">
              Vacant: <span class="font-semibold">{{ totals.vacant }}</span>
            </span>
            <span class="px-2 py-0.5 rounded-full text-[11px] bg-amber-50 text-amber-800 border border-amber-200">
              Reserved: <span class="font-semibold">{{ totals.reserved }}</span>
            </span>
            <span class="px-2 py-0.5 rounded-full text-[11px] bg-red-50 text-red-700 border border-red-200">
              Occupied: <span class="font-semibold">{{ totals.occupied }}</span>
            </span>
          </div>
        </div>
      </div>

      <!-- Header actions -->
      <div class="ml-auto flex items-center gap-2">
        <!-- Top-right navigation burger -->
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
            <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goPlots">
              📋 Plots
            </button>
            <button class="w-full text-left px-3 py-2 hover:bg-gray-50" @click="goAnnouncements">
              📜 Announcements
            </button>
            <div class="h-px bg-gray-200 my-1"></div>
            <button
              class="w-full text-left px-3 py-2 hover:bg-red-50 text-red-600"
              @click.stop="logout"
            >
              🚪 Logout
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- BODY: sidebar + map, fills remaining height -->
    <div class="flex-1 relative flex min-h-0">
      <!-- SIDEBAR -->
      <aside
        class="z-40 w-[320px] max-w-full bg-white/92 backdrop-blur border-r shadow-sm
               h-full overflow-y-auto
               fixed top-[4.5rem] bottom-0 left-0
               md:static md:top-auto md:bottom-auto md:translate-x-0 md:h-full md:overflow-y-auto
               transition-transform duration-200"
        :class="ui.menuOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
      >
        <div class="h-full p-4 space-y-5">
          <!-- Filters -->
          <section class="card">
            <div class="card__title">Filters</div>
            <div class="grid grid-cols-2 gap-2">
              <select v-model="filterSection" class="input">
                <option value="">All sections</option>
                <option value="BLOCK 1">BLOCK 1</option>
                <option value="BLOCK 2">BLOCK 2</option>
                <option value="BLOCK 3">BLOCK 3</option>
                <option value="BLOCK 4">BLOCK 4</option>
              </select>
              <select v-model="filterStatus" class="input">
                <option value="">All statuses</option>
                <option value="vacant">Vacant</option>
                <option value="reserved">Reserved</option>
                <option value="occupied">Occupied</option>
              </select>
            </div>
          </section>

          <!-- Bulk plot tools -->
          <section class="card">
            <div class="card__title">Bulk plot tools</div>
            <div class="flex flex-wrap items-center gap-2">
              <button
                class="btn"
                :class="bulk.enabled ? 'ring-2 ring-[#4FA07F]' : ''"
                @click="bulk.enabled = !bulk.enabled"
              >
                🧺 Bulk {{ bulk.enabled ? 'ON' : 'OFF' }}
              </button>
              <button class="btn" @click="bulkSelectAllVisible()">✅ Select visible</button>
              <button class="btn" @click="bulkClearSelection()">✖️ Clear</button>
              <button class="btn btn--danger" @click="bulkDeleteSelected()">🗑️ Delete</button>
            </div>
          </section>

          <!-- Design mode -->
          <section class="card">
            <div class="card__title">Design Mode</div>
            <div class="flex flex-wrap items-center gap-2">
              <button class="btn" @click="toggleDesignMode">
                ✏️ {{ design.enabled ? 'On' : 'Off' }}
              </button>
              <template v-if="design.enabled">
                <button class="btn" @click="draw.changeMode('draw_polygon')">➕ Draw Block</button>
                <button class="btn" @click="selectAllBlocks()">✅ Select All</button>
                <button class="btn" @click="draw.trash()">🗑️ Delete Selected</button>
                <button class="btn" @click="deleteAllBlocks()">⚠️ Delete ALL</button>
                <button class="btn btn--mint" @click="generateAll">⚙️ Generate 4×500</button>
              </template>
            </div>
          </section>

          <!-- Legend + Totals -->
          <section class="card">
            <div class="card__title">Legend</div>
            <ul class="space-y-2 text-sm text-slate-700">
              <li class="flex items-center"><span class="legend legend--vacant"></span> Vacant</li>
              <li class="flex items-center"><span class="legend legend--reserved"></span> Reserved</li>
              <li class="flex items-center"><span class="legend legend--occupied"></span> Occupied</li>
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
        </div>
      </aside>

      <!-- MAIN (map) -->
      <main class="flex-1 h-full md:ml-0 ml-0">
        <div class="h-full p-4 relative">
          <div
            v-if="loading"
            class="absolute inset-4 z-10 bg-white/70 backdrop-blur-sm flex items-center justify-center
                   rounded-xl border border-emerald-100"
          >
            <div class="spinner"></div>
            <span class="ml-3 text-slate-700">Loading plots…</span>
          </div>
          <div
            v-if="error"
            class="absolute top-7 left-7 z-10 bg-red-50 text-red-700 border border-red-200 px-3 py-2 rounded"
          >
            {{ error }}
          </div>
          <div
            id="mapgl"
            class="h-full rounded-xl border border-emerald-100
                   shadow-[0_1px_0_0_rgba(79,160,127,.06),0_12px_28px_rgba(2,6,23,.06)]
                   overflow-hidden"
          ></div>
        </div>
      </main>
    </div>
  </div>
</template>

<style scoped>
/* Smooth transitions without overdoing it */
* {
  transition: background-color .16s ease,
              color .16s ease,
              border-color .16s ease,
              box-shadow .16s ease;
}
@media (prefers-reduced-motion: reduce) {
  * { transition: none !important; }
}

/* Buttons */
.btn { @apply px-3 py-2 text-sm rounded-lg border bg-white text-slate-800 hover:bg-[#EFFEFA]; }
.btn--mint {
  background: linear-gradient(180deg, #4FA07F, #3C8E6C);
  color: #fff; border: 0;
  box-shadow: 0 6px 18px rgba(79,160,127,.18);
}
.btn--mint:hover { filter: brightness(1.04); }
.btn--danger { @apply bg-red-600 text-white border-0 hover:bg-red-700; }
.btn--icon { @apply w-10 h-10 rounded-lg border bg-white hover:bg-[#EFFEFA]; }

.btn-group .btn { @apply rounded-none; }
.btn-group .btn:first-child { @apply rounded-l-lg; }
.btn-group .btn:last-child  { @apply rounded-r-lg; }

/* Inputs */
.input { @apply w-full px-3 py-2 border rounded-lg text-sm bg-white/90; }
.input:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(79,160,127,.18);
  border-color: #4FA07F;
}

/* Cards */
.card {
  @apply rounded-2xl border border-[rgba(79,160,127,0.20)]
         bg-white/95 backdrop-blur p-4
         shadow-[0_1px_0_0_rgba(79,160,127,.06)];
}
.card__title { @apply text-sm font-semibold text-[#4FA07F] mb-2; }

/* Legend squares */
.legend { @apply inline-block w-4 h-4 mr-2 rounded-sm; }
.legend--vacant   { background:#22c55e; }
.legend--reserved { background:#eab308; }
.legend--occupied { background:#ef4444; }

/* KPI chips */
.kpi { @apply rounded-xl border bg-white px-3 py-2 text-center; }
.kpi__label { @apply text-[11px] uppercase tracking-wide text-slate-500; }
.kpi__value { @apply text-base font-semibold text-slate-900; }

/* Spinner */
.spinner {
  width: 1.75rem; height: 1.75rem;
  border: 3px solid rgba(79,160,127,.25);
  border-top-color: #4FA07F;
  border-radius: 9999px;
  animation: spin .8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg) } }

/* Map logo control styling */
.map-logo-ctrl{
  padding: 6px;
  background: rgba(255,255,255,.9);
  backdrop-filter: blur(4px);
  border: 1px solid rgba(79,160,127,.25);
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(2,6,23,.12);
  cursor: pointer;
}
.map-logo-ctrl img { opacity: .95; }
.map-logo-ctrl:hover img { opacity: 1; }
</style>
