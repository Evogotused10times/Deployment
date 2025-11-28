<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  open: { type: Boolean, default: false },
  application: { type: Object, default: null },
  endpoints: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:open','close','approved','denied','deleted'])

const THEME = { yellow:'#F5D146', green:'#4FA07F', purple:'#6E63A6', brown:'#7A5B43' }

const a = computed(() => props.application || {})
function close() {
  emit('update:open', false)
  emit('close')
}

/* lock page scroll while modal is open */
const lock = () => { document.documentElement.style.overflow = 'hidden' }
const unlock = () => { document.documentElement.style.overflow = '' }
watch(() => props.open, v => v ? lock() : unlock())
onMounted(() => { if (props.open) lock(); document.addEventListener('keydown', onKey) })
onBeforeUnmount(() => { unlock(); document.removeEventListener('keydown', onKey) })

function onKey(e){ if (e.key === 'Escape') close() }

/* helpers */
const dash = v => (v == null || v === '') ? '—' : v
const dateOnly = d => (d || '').slice?.(0,10) || '—'
const toMail  = e => e ? `mailto:${e}` : undefined
const toPhone = p => p ? `tel:${String(p).replace(/\s+/g,'')}` : undefined

/* actions */
const isBusy = ref(false)
function postTo(url, payload = {}, then) {
  if (!url) return
  isBusy.value = true
  router.post(url, payload, {
    preserveScroll: true,
    onFinish: () => (isBusy.value = false),
    onSuccess: then,
  })
}
const approve = () => postTo(props.endpoints?.approve, {}, () => emit('approved'))
const deny    = () => postTo(props.endpoints?.deny,    {}, () => emit('denied'))
function destroyItem() {
  if (!props.endpoints?.delete) return
  if (!confirm('Delete this application? This cannot be undone.')) return
  postTo(props.endpoints.delete, {}, () => emit('deleted'))
}

const hasDocs = computed(() => Array.isArray(a.value?.documents) && a.value.documents.length > 0)
const hasPlot = computed(() => !!(a.value?.plot || a.value?.assigned_plot_id))

const statusClass = computed(() => {
  const s = (a.value?.status || '').toLowerCase()
  return {
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
    label: a.value?.status || '—',
  }
})
</script>

<template>
  <teleport to="body">
    <transition name="fade">
      <div v-if="open" class="fixed inset-0 z-[70]">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40" @click="close" />

        <!-- Panel wrapper -->
        <div class="absolute inset-0 flex items-start justify-center p-3 sm:p-6">
          <!-- Panel (smaller + capped height + column layout) -->
          <div
            class="w-[95vw] sm:w-[90vw] max-w-2xl md:max-w-3xl bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden flex flex-col"
            style="max-height: 86vh;"
          >
            <!-- Accent bars -->
            <div class="h-1.5 w-full flex">
              <div class="flex-1" :style="{background:THEME.yellow}"></div>
              <div class="w-20"  :style="{background:THEME.green}"></div>
              <div class="w-14"  :style="{background:THEME.purple}"></div>
              <div class="w-10"  :style="{background:THEME.brown}"></div>
            </div>

            <!-- Header (sticky within panel) -->
            <div class="px-4 md:px-5 py-3 border-b bg-white/90 backdrop-blur flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="text-[11px] uppercase tracking-wide text-slate-500">Application</div>
                <div class="text-lg md:text-xl font-semibold text-slate-900 truncate">
                  #{{ a.id ?? '—' }} — {{ a.applicant_name || 'Applicant' }}
                </div>
                <div class="text-xs text-slate-500">Submitted {{ dateOnly(a.created_at) }}</div>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ring-1 ring-inset"
                      :class="statusClass.tone">
                  <span class="h-1.5 w-1.5 rounded-full" :class="statusClass.dot"></span>
                  {{ statusClass.label }}
                </span>
                <button class="px-2.5 py-1.5 rounded-lg border bg-white hover:bg-slate-50 text-sm" @click="close">Close</button>
              </div>
            </div>

            <!-- Body (scrollable) -->
            <div class="flex-1 overflow-auto px-4 md:px-5 py-4">
              <div class="grid gap-4 md:gap-5 sm:grid-cols-2">
                <!-- Left column -->
                <div class="space-y-3.5 md:space-y-4">
                  <div class="rounded-xl border bg-white p-3.5 md:p-4">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Service Type</div>
                    <div class="mt-1 font-semibold text-slate-900">{{ dash(a.service_type) }}</div>
                  </div>

                  <div class="rounded-xl border bg-white p-3.5 md:p-4">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Schedule</div>
                    <div class="mt-1 text-slate-900">
                      {{ dash(a.schedule_date) }}<span v-if="a.schedule_time"> • {{ a.schedule_time }}</span>
                    </div>
                  </div>

                  <div class="rounded-xl border bg-white p-3.5 md:p-4">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Applicant & Contacts</div>
                    <div class="mt-2 space-y-1.5 text-sm">
                      <div><span class="text-slate-500">Name:</span> <span class="font-medium">{{ dash(a.applicant_name) }}</span></div>
                      <div>
                        <span class="text-slate-500">Email:</span>
                        <a v-if="a.applicant_email" :href="toMail(a.applicant_email)" class="text-emerald-700 hover:underline">
                          {{ a.applicant_email }}
                        </a>
                        <span v-else>—</span>
                      </div>
                      <div>
                        <span class="text-slate-500">Phone:</span>
                        <a v-if="a.applicant_phone" :href="toPhone(a.applicant_phone)" class="text-emerald-700 hover:underline">
                          {{ a.applicant_phone }}
                        </a>
                        <span v-else>—</span>
                      </div>
                      <div><span class="text-slate-500">Address:</span> <span>{{ dash(a.applicant_address) }}</span></div>
                    </div>
                  </div>
                </div>

                <!-- Right column -->
                <div class="space-y-3.5 md:space-y-4">
                  <div class="rounded-xl border bg-white p-3.5 md:p-4">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Assigned Plot</div>
                    <div class="mt-2 text-sm">
                      <template v-if="hasPlot">
                        <div><span class="text-slate-500">Lot:</span> <span class="font-medium">{{ a.plot?.lot_number ?? a.assigned_plot_id }}</span></div>
                        <div v-if="a.plot?.section || a.plot?.block">
                          <span class="text-slate-500">Section/Block:</span>
                          <span>{{ a.plot?.section || '—' }}<span v-if="a.plot?.block"> • {{ a.plot.block }}</span></span>
                        </div>
                      </template>
                      <template v-else>—</template>
                    </div>
                  </div>

                  <div class="rounded-xl border bg-white p-3.5 md:p-4">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Remarks</div>
                    <div class="mt-2 text-sm whitespace-pre-line">{{ dash(a.remarks) }}</div>
                  </div>
                </div>

                <!-- Documents full width -->
                <div class="sm:col-span-2 rounded-xl border bg-white p-3.5 md:p-4">
                  <div class="flex items-center justify-between">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Documents</div>
                    <span class="text-xs text-slate-500">{{ hasDocs ? `${a.documents.length} file(s)` : 'No files' }}</span>
                  </div>
                  <ul v-if="hasDocs" class="mt-3 divide-y rounded-lg border bg-white">
                    <li v-for="d in a.documents" :key="d.id || d.url" class="p-3 text-sm flex items-center justify-between">
                      <div class="min-w-0">
                        <div class="font-medium text-slate-900 truncate">{{ d.name || d.filename || 'File' }}</div>
                        <div class="text-slate-500 text-xs">
                          {{ (d.type || d.mime || '').toUpperCase() }} <span v-if="d.size">• {{ d.size }}</span>
                        </div>
                      </div>
                      <a v-if="d.url" :href="d.url" target="_blank" rel="noopener" class="px-3 py-1.5 rounded-lg border bg-white hover:bg-slate-50 text-xs">Open</a>
                    </li>
                  </ul>
                  <p v-else class="mt-3 text-sm text-slate-500">No uploaded documents.</p>
                </div>
              </div>
            </div>

            <!-- Footer (sticks at bottom of panel) -->
            <div class="px-4 md:px-5 py-3 border-t bg-white/90 backdrop-blur flex flex-wrap justify-end gap-2">
              <Link :href="props.endpoints?.back || route('admin.applications.index')" class="btn-outline">← Back</Link>
              <!-- <button v-if="props.endpoints?.approve" :disabled="isBusy" @click="approve" class="btn-solid-violet">Approve</button>
              <button v-if="props.endpoints?.deny"    :disabled="isBusy" @click="deny"    class="btn-solid-rose">Deny</button> -->
              <button v-if="props.endpoints?.delete"  :disabled="isBusy" @click="destroyItem" class="btn-outline">Delete</button>
              <Link v-if="props.endpoints?.edit" :href="props.endpoints.edit" class="btn-outline">Full Screen</Link>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .12s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.btn-outline { @apply px-3 py-2 rounded-lg text-sm font-medium border bg-white hover:bg-slate-50; }
.btn-solid-violet { @apply px-3 py-2 rounded-lg text-sm font-semibold text-white; background:#6E63A6; }
.btn-solid-rose   { @apply px-3 py-2 rounded-lg text-sm font-semibold text-white; background:#ef4444; }
</style>
