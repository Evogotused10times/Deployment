<script setup>
import { Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

/* ---------- Theme (single source of truth) ---------- */
const THEME = {
  yellow: '#F5D146',
  green:  '#4FA07F',
  purple: '#6E63A6',
  brown:  '#7A5B43',
  ink:    '#1C1C1C',
}

const props = defineProps({
  application: { type: Object, default: () => ({}) },
  endpoints:   { type: Object, default: () => ({}) },
})

/* ---------- derived ---------- */
const a = computed(() => props.application || {})

const statusClass = computed(() => {
  const s = (a.value.status || '').toLowerCase()
  return {
    base: 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ring-1 ring-inset',
    tone:
      s === 'approved'  ? 'bg-violet-50 text-violet-700 ring-violet-200' :
      s === 'denied'    ? 'bg-rose-50 text-rose-700 ring-rose-200'     :
      s === 'confirmed' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' :
      s === 'pending'   ? 'bg-amber-50 text-amber-800 ring-amber-200'  :
                          'bg-slate-50 text-slate-700 ring-slate-200',
    dot:
      s === 'approved'  ? 'bg-violet-500'  :
      s === 'denied'    ? 'bg-rose-500'    :
      s === 'confirmed' ? 'bg-emerald-500' :
      s === 'pending'   ? 'bg-amber-500'   :
                          'bg-slate-400',
    label: a.value.status || '—',
  }
})

const hasDocs = computed(() => Array.isArray(a.value.documents) && a.value.documents.length > 0)
const hasPlot = computed(() => !!(a.value.plot || a.value.assigned_plot_id))

/* ---------- actions ---------- */
const isBusy = ref(false)
function postTo(url, payload = {}, extra = {}) {
  if (!url) return
  isBusy.value = true
  router.post(url, payload, {
    preserveScroll: true,
    onFinish: () => (isBusy.value = false),
    ...extra,
  })
}
const approve = () => postTo(props.endpoints?.approve)
const deny    = () => postTo(props.endpoints?.deny)
function destroyItem() {
  if (!props.endpoints?.delete) return
  if (!confirm('Delete this application? This cannot be undone.')) return
  postTo(props.endpoints.delete, {}, {
    onSuccess: () => router.visit(props.endpoints.back || route('admin.applications.index')),
  })
}

/* ---------- utils ---------- */
const dateOnly = (d) => (d || '').slice?.(0,10) || '—'
const dash     = (v) => (v == null || v === '') ? '—' : v
const toMail   = (e) => e ? `mailto:${e}` : undefined
const toPhone  = (p) => p ? `tel:${String(p).replace(/\s+/g,'')}` : undefined
function printPage(){ window.print?.() }
</script>

<template>
  <div class="min-h-screen bg-[#ffffff] text-[color:var(--ink)]" :style="{'--ink': THEME.ink}">
    <!-- Inline brand accent bars -->
    <div class="w-full h-1.5 flex sticky top-0 z-[31]">
      <div class="basis-[50%]" :style="{ background: THEME.yellow }"></div>
      <div class="basis-[20%]" :style="{ background: THEME.green }"></div>
      <div class="basis-[20%]" :style="{ background: THEME.purple }"></div>
      <div class="basis-[10%]" :style="{ background: THEME.brown }"></div>
    </div>

    <!-- Sticky Toolbar (subtle glass) -->
    <div class="sticky top-[0.375rem] z-30 border-b bg-white/90 backdrop-blur supports-[backdrop-filter]:bg-white/70 shadow-[0_1px_0_0_rgba(0,0,0,0.04)]">
      <div class="mx-auto max-w-7xl px-6 h-14 flex items-center gap-3">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 text-sm">
            <Link :href="(props.endpoints && props.endpoints.back) || route('admin.applications.index')" class="text-slate-600 hover:underline">Applications</Link>
            <span class="text-slate-400">/</span>
            <span class="truncate font-medium text-slate-900">#{{ a.id ?? '—' }} – {{ a.applicant_name || 'Applicant' }}</span>
          </div>
        </div>

        <!-- Back to Applications -->
<Link
  :href="(props.endpoints && props.endpoints.back) || route('admin.applications.index')"
  class="btn-outline"
  aria-label="Back to Applications"
>
  ← Back
</Link>

        <!-- status chip (desktop) -->
        <span :class="[statusClass.base, statusClass.tone]" class="hidden sm:inline-flex">
          <span class="h-1.5 w-1.5 rounded-full" :class="statusClass.dot"></span>
          {{ statusClass.label }}
        </span>

        
        <!-- actions -->
        <div class="flex items-center gap-2">
          <button v-if="props.endpoints?.approve" :disabled="isBusy" @click="approve" class="btn-solid-violet">Approve</button>
          <button v-if="props.endpoints?.deny"    :disabled="isBusy" @click="deny"    class="btn-solid-rose">Deny</button>
          <button v-if="props.endpoints?.delete"  :disabled="isBusy" @click="destroyItem" class="btn-outline">Delete</button>
          <Link v-if="props.endpoints?.edit" :href="props.endpoints.edit" class="btn-outline">Edit</Link>
          <button @click="printPage" class="btn-ghost hidden sm:inline-flex">Print</button>
        </div>
      </div>
    </div>

    <!-- Hero band (soft gradient + conic wash) -->
    <header class="relative border-b">
      <div class="absolute inset-0 pointer-events-none -z-10">
        <div class="absolute inset-0 opacity-[0.08]" :style="{
          background: `conic-gradient(from 0deg, ${THEME.yellow} 0 180deg, ${THEME.green} 180deg 252deg, ${THEME.purple} 252deg 324deg, ${THEME.brown} 324deg 360deg)`
        }"></div>
        <div class="absolute inset-0"
             style="background:radial-gradient(60% 55% at 50% 44%, rgba(255,255,255,.92) 0%, rgba(255,255,255,.65) 46%, rgba(255,255,255,.15) 78%, rgba(255,255,255,0) 100%)"></div>
      </div>

      <div class="mx-auto max-w-7xl px-6 py-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">
              Application #{{ a.id ?? '—' }}
            </h1>
            <p class="text-sm text-slate-600">
              Submitted <span class="font-medium text-slate-800">{{ dateOnly(a.created_at) }}</span>
            </p>
          </div>
          <div class="sm:text-right">
            <div class="text-sm text-slate-600">Service Type</div>
            <div class="mt-0.5 text-lg font-semibold text-slate-900">{{ dash(a.service_type) }}</div>
          </div>
        </div>
      </div>
    </header>

    <!-- Content -->
    <main class="mx-auto max-w-7xl px-6 py-6 grid gap-6 lg:grid-cols-3 print:block">
      <!-- LEFT: Primary -->
      <section class="lg:col-span-2 space-y-6 print:space-y-4">
        <!-- Summary -->
        <div class="card card-accent-mint">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <h2 class="text-xl font-bold text-slate-900 truncate">{{ a.applicant_name || 'Applicant' }}</h2>
              <p class="text-sm text-slate-600 mt-0.5">
                <span class="inline sm:hidden">Status: </span>
                <span class="hidden sm:inline">Current status: </span>
                <span class="font-medium">{{ a.status || '—' }}</span>
              </p>
            </div>
            <span :class="[statusClass.base, statusClass.tone]" class="sm:hidden">
              <span class="h-1.5 w-1.5 rounded-full" :class="statusClass.dot"></span>
              {{ statusClass.label }}
            </span>
          </div>

          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="field">
              <div class="field-label">Schedule</div>
              <div class="field-value">
                {{ dash(a.schedule_date) }}<span v-if="a.schedule_time"> • {{ a.schedule_time }}</span>
              </div>
            </div>
            <div class="field">
              <div class="field-label">Reference Plot</div>
              <div class="field-value">
                <template v-if="hasPlot">
                  {{ a.plot?.lot_number ?? a.assigned_plot_id }}
                </template>
                <template v-else>—</template>
              </div>
            </div>
          </div>
        </div>

        <!-- Applicant & Contacts -->
        <div class="card">
          <div class="section-heading">
            <h3 class="section-title purple">Applicant & Contacts</h3>
            <div class="hairline" />
          </div>
          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="field">
              <div class="field-label">Applicant Name</div>
              <div class="field-value">{{ dash(a.applicant_name) }}</div>
            </div>
            <div class="field">
              <div class="field-label">Email</div>
              <div class="field-value">
                <a v-if="a.applicant_email" :href="toMail(a.applicant_email)" class="link-green">
                  {{ a.applicant_email }}
                </a>
                <span v-else>—</span>
              </div>
            </div>
            <div class="field">
              <div class="field-label">Phone</div>
              <div class="field-value">
                <a v-if="a.applicant_phone" :href="toPhone(a.applicant_phone)" class="link-green">
                  {{ a.applicant_phone }}
                </a>
                <span v-else>—</span>
              </div>
            </div>
            <div class="field">
              <div class="field-label">Address</div>
              <div class="field-value">{{ dash(a.applicant_address) }}</div>
            </div>
          </div>
        </div>

        <!-- Service Details -->
        <div class="card">
          <div class="section-heading">
            <h3 class="section-title purple">Service Details</h3>
            <div class="hairline" />
          </div>
          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="field">
              <div class="field-label">Deceased</div>
              <div class="field-value">{{ dash(a.deceased_name) }}</div>
            </div>
            <div class="field">
              <div class="field-label">Rites / Priest / Notes</div>
              <div class="field-value whitespace-pre-line">{{ dash(a.rites_notes) }}</div>
            </div>
            <div class="field sm:col-span-2">
              <div class="field-label">Remarks</div>
              <div class="field-value whitespace-pre-line">{{ dash(a.remarks) }}</div>
            </div>
          </div>
        </div>

        <!-- Documents -->
        <div class="card">
          <div class="flex items-center justify-between">
            <div class="section-heading">
              <h3 class="section-title purple">Documents</h3>
            </div>
            <span class="text-xs text-slate-500">{{ hasDocs ? `${a.documents.length} file(s)` : 'No files' }}</span>
          </div>

          <ul v-if="hasDocs" class="mt-3 divide-y rounded-xl border bg-white">
            <li v-for="d in a.documents" :key="d.id || d.url"
                class="p-3 text-sm flex items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="font-medium text-slate-900 truncate">
                  {{ d.name || d.filename || 'File' }}
                </div>
                <div class="text-slate-500 text-xs">
                  {{ (d.type || d.mime || '').toUpperCase() }} <span v-if="d.size">• {{ d.size }}</span>
                </div>
              </div>
              <a v-if="d.url" :href="d.url" target="_blank" rel="noopener"
                 class="px-3 py-1.5 rounded-lg border bg-white hover:bg-slate-50 text-xs">
                Open
              </a>
            </li>
          </ul>
          <p v-else class="mt-3 text-sm text-slate-500">No uploaded documents.</p>
        </div>
      </section>

      <!-- RIGHT: Meta / Plot / Timeline -->
      <aside class="space-y-6 print:space-y-4">
        <!-- Plot -->
        <div class="card">
          <div class="flex items-center justify-between">
            <h3 class="section-title green">Assigned Plot</h3>
            <Link v-if="props.endpoints?.assign_plot" :href="props.endpoints.assign_plot" class="btn-chip">
              Assign / Change
            </Link>
          </div>

          <div v-if="hasPlot" class="mt-3 grid gap-3">
            <div class="mini-field">
              <div class="mini-label">Lot</div>
              <div class="mini-value">{{ a.plot?.lot_number ?? a.assigned_plot_id }}</div>
            </div>
            <div v-if="a.plot?.section || a.plot?.block" class="mini-field">
              <div class="mini-label">Section / Block</div>
              <div class="mini-value">
                {{ a.plot?.section || '—' }}<span v-if="a.plot?.block"> • {{ a.plot.block }}</span>
              </div>
            </div>
          </div>
          <p v-else class="mt-3 text-sm text-slate-500">No plot assigned.</p>
        </div>

        <!-- Meta -->
        <div class="card">
          <h3 class="section-title green">Meta</h3>
          <dl class="mt-3 grid gap-3 text-sm">
            <div class="flex items-center justify-between">
              <dt class="text-slate-500">ID</dt><dd class="font-medium text-slate-900">#{{ a.id ?? '—' }}</dd>
            </div>
            <div class="flex items-center justify-between">
              <dt class="text-slate-500">Created</dt><dd class="font-medium text-slate-900">{{ dateOnly(a.created_at) }}</dd>
            </div>
            <div class="flex items-center justify-between">
              <dt class="text-slate-500">Updated</dt><dd class="font-medium text-slate-900">{{ dateOnly(a.updated_at) }}</dd>
            </div>
          </dl>
        </div>

        <!-- Timeline -->
        <div class="card">
          <h3 class="section-title green">Timeline</h3>
          <ol v-if="Array.isArray(a.timeline) && a.timeline.length" class="mt-4 space-y-3">
            <li v-for="(t, i) in a.timeline" :key="i" class="relative pl-5 text-sm">
              <span class="absolute left-0 top-1.5 h-2 w-2 rounded-full bg-emerald-500"></span>
              <div class="font-medium text-slate-900">{{ t.title || 'Event' }}</div>
              <div class="text-xs text-slate-500">{{ dateOnly(t.when) }}<span v-if="t.who"> • {{ t.who }}</span></div>
              <div v-if="t.note" class="text-slate-700 mt-0.5 whitespace-pre-line">{{ t.note }}</div>
            </li>
          </ol>
          <p v-else class="mt-3 text-sm text-slate-500">No timeline entries.</p>
        </div>
      </aside>
    </main>
  </div>
</template>

<style scoped>
/* Smooth micro-transitions; never janky */
* { transition: background-color .18s ease, color .18s ease, box-shadow .18s ease, border-color .18s ease; }
@media (prefers-reduced-motion: reduce) { * { transition: none !important; } }

/* Cards */
.card {
  @apply rounded-2xl border border-[rgba(17,24,39,0.08)] bg-white/90 backdrop-blur shadow-sm p-5;
}
.card-accent-mint::before {
  content: "";
  position: absolute;
  inset: -1px 12px auto 12px;
  height: 3px;
  border-radius: 999px;
  background: linear-gradient(90deg, rgba(79,160,127,0), rgba(79,160,127,.55), rgba(79,160,127,0));
}
.card.card-accent-mint { position: relative; }

/* Section headers */
.section-heading { @apply flex items-center justify-between; }
.section-title { @apply text-sm font-semibold tracking-tight; }
.section-title.purple { color: #6E63A6; }
.section-title.green  { color: #4FA07F; }
.hairline { @apply hidden sm:block h-px flex-1 ml-4 bg-gradient-to-r from-transparent via-slate-200 to-transparent rounded-full; }

/* Fields */
.field      { @apply rounded-xl border bg-white p-4; }
.field-label{ @apply text-[11px] font-medium uppercase tracking-wide text-slate-500; }
.field-value{ @apply mt-1 font-medium text-slate-900; }

.mini-field { @apply rounded-lg border bg-white p-3; }
.mini-label { @apply text-[11px] uppercase tracking-wide text-slate-500; }
.mini-value { @apply mt-0.5 text-sm font-medium text-slate-900; }

/* Links / Buttons */
.link-green { color: #4FA07F; }
.link-green:hover { text-decoration: underline; }

.btn-outline {
  @apply px-3 py-2 rounded-lg text-sm font-medium border bg-white hover:bg-slate-50;
}
.btn-ghost {
  @apply px-3 py-2 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50;
}
.btn-solid-violet {
  @apply px-3 py-2 rounded-lg text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60;
  background: #6E63A6;
}
.btn-solid-rose {
  @apply px-3 py-2 rounded-lg text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60;
  background: #ef4444; /* Tailwind rose-500 */
}
.btn-chip {
  @apply inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border bg-white hover:bg-emerald-50;
}

/* Print: clean handoff */
@media print {
  .btn-outline, .btn-ghost, .btn-solid-violet, .btn-solid-rose { display: none !important; }
  .sticky, header.border-b { position: static !important; background: transparent !important; box-shadow: none !important; }
  .card { box-shadow: none !important; background: #fff !important; }
  .hairline { display: none !important; }
}
</style>
