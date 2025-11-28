<script setup>
import { useForm, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { onMounted, ref, watch } from 'vue'
import { Home, ArrowLeftRight } from 'lucide-vue-next'

// Props from controller (plot chosen on map + context)
const props = defineProps({
  preselectedPlot: {
    type: Object,
    default: () => ({ id: null, lot_number: null }),
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

// --- Draft persistence so form doesn't reset when opening the map ---

const DRAFT_KEY = 'public-apply-draft-v2'

function loadDraft () {
  try {
    const raw = localStorage.getItem(DRAFT_KEY)
    if (!raw) return {}
    return JSON.parse(raw)
  } catch {
    return {}
  }
}

function saveDraft (data) {
  try {
    localStorage.setItem(DRAFT_KEY, JSON.stringify(data))
  } catch (_) {}
}

function clearDraft () {
  try {
    localStorage.removeItem(DRAFT_KEY)
  } catch (_) {}
}

const draft = loadDraft()

// Label for the lot chosen from the map (if any)
const selectedFromMap = ref(null)

// Form state
const form = useForm({
  // Applicant (split)
  applicant_last_name:  draft.applicant_last_name  ?? '',
  applicant_first_name: draft.applicant_first_name ?? '',
  applicant_middle_name: draft.applicant_middle_name ?? '',
  applicant_email:      draft.applicant_email      ?? '',
  applicant_phone:      draft.applicant_phone      ?? '',

  // Deceased (split)
  deceased_last_name:   draft.deceased_last_name   ?? '',
  deceased_first_name:  draft.deceased_first_name  ?? '',
  deceased_middle_name: draft.deceased_middle_name ?? '',
  deceased_dod:         draft.deceased_dod         ?? '',
  deceased_age:         draft.deceased_age         ?? '',

  // Existing fields
  service_type: draft.service_type ?? 'Lawn Lot',
  remarks:      draft.remarks      ?? '',
  allow_auto_assign: draft.allow_auto_assign ?? true,
  selection_mode:    draft.selection_mode    ?? 'staff-guided',
  family_reference:  draft.family_reference  ?? '',
  rites:             draft.rites             ?? '',
  rites_notes:       draft.rites_notes       ?? '',
  wake_pref:         draft.wake_pref         ?? '',
  interment_window:  draft.interment_window  ?? '',

  // selected plot coming from public map
  assigned_plot_id: draft.assigned_plot_id ?? null,

  // IA extras
  bot_field:        draft.bot_field        ?? '',          // honeypot
  privacy_consent:  draft.privacy_consent  ?? false,       // required flag
})

// Keep draft in sync with form data
watch(
  () => form.data(),
  (val) => {
    saveDraft(val)
  },
  { deep: true }
)

// Prefill service type + assigned plot from URL or props
onMounted(() => {
  const params = new URLSearchParams(window.location.search)

  const st =
    params.get('service_type') ||
    props.service_type ||
    form.service_type ||
    'Lawn Lot'

  form.service_type = st

  const assignedId =
    params.get('assigned_plot_id') ||
    props.preselectedPlot.id ||
    form.assigned_plot_id

  const lotLabel =
    params.get('selected_lot') ||
    props.preselectedPlot.lot_number ||
    null

  if (assignedId) {
    form.assigned_plot_id = Number(assignedId)
    selectedFromMap.value = lotLabel
      ? `Lot ${lotLabel}`
      : `Plot #${assignedId}`

    // If user came from the map, selection_mode should be "map"
    if (form.service_type !== 'Lawn Lot') {
      form.selection_mode = 'map'
    }
  }

  // Save merged state back
  saveDraft(form.data())
})

// For HTML max= on date-of-death (no future date via browser)
const todayStr = new Date().toISOString().slice(0, 10)

function goHome() {
  router.visit('/')
}

function joinName(last, first, middle) {
  const l = (last || '').trim()
  const f = (first || '').trim()
  const m = (middle || '').trim()
  return [l, [f, m].filter(Boolean).join(' ')].filter(Boolean).join(', ')
}

// Open public map in picker mode
function openMapPicker() {
  // ensure current form data is saved
  saveDraft(form.data())

  router.visit(route('public.map', {
    from: 'apply',
    service_type: form.service_type,
  }))
}

function submit() {
  // Honeypot: if this has value, likely a bot — silently drop
  if (form.bot_field && String(form.bot_field).trim().length > 0) {
    return
  }

  const applicant_name = joinName(
    form.applicant_last_name,
    form.applicant_first_name,
    form.applicant_middle_name,
  )
  const deceased_name = joinName(
    form.deceased_last_name,
    form.deceased_first_name,
    form.deceased_middle_name,
  )

  // --- Basic front-end checks (defense in depth; backend must still validate) ---

  // Required names
  if (!form.applicant_last_name || !form.applicant_first_name) {
    return Swal.fire(
      'Missing field',
      'Please complete the applicant’s Last and First names.',
      'warning',
    )
  }
  if (!form.deceased_last_name || !form.deceased_first_name) {
    return Swal.fire(
      'Missing field',
      'Please complete the deceased’s Last and First names.',
      'warning',
    )
  }

  // Email
  const email = (form.applicant_email || '').trim()
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!email || !emailPattern.test(email)) {
    return Swal.fire(
      'Invalid email',
      'Please provide a valid email address.',
      'warning',
    )
  }

  // Phone (optional, but if present check roughly)
  const phone = (form.applicant_phone || '').trim()
  if (phone && !/^(\+?\d{10,15}|0\d{9,10})$/.test(phone)) {
    return Swal.fire(
      'Invalid phone number',
      'Please enter a valid mobile or landline number.',
      'warning',
    )
  }

  // Date of death (cannot be in the future)
  if (form.deceased_dod) {
    const dod = new Date(form.deceased_dod)
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    if (dod > today) {
      return Swal.fire(
        'Invalid date of death',
        'Date of death cannot be in the future.',
        'warning',
      )
    }
  }

  // Age (optional, but if present must be reasonable integer)
  if (form.deceased_age) {
    const age = Number(form.deceased_age)
    if (!Number.isInteger(age) || age < 0 || age > 120) {
      return Swal.fire(
        'Invalid age',
        'Please enter a realistic age between 0 and 120.',
        'warning',
      )
    }
  }

  // Consent
  if (!form.privacy_consent) {
    return Swal.fire(
      'Consent required',
      'Please confirm that you understand and agree to the use of your information for processing this application.',
      'warning',
    )
  }

  form
    .transform(() => ({
      // Same payload as before + consent flag + assigned plot
      applicant_name,
      applicant_email: form.applicant_email,
      applicant_phone: form.applicant_phone,

      deceased_name,
      deceased_dod: form.deceased_dod,
      deceased_age: form.deceased_age,

      service_type: form.service_type,
      remarks: form.remarks,
      allow_auto_assign: form.allow_auto_assign,
      selection_mode: form.selection_mode,
      family_reference: form.family_reference,
      rites: form.rites,
      rites_notes: form.rites_notes,
      wake_pref: form.wake_pref,
      interment_window: form.interment_window,

      assigned_plot_id: form.assigned_plot_id,

      privacy_consent: form.privacy_consent,
    }))
    .post(route('apply.store'), {
      preserveScroll: true,
      onSuccess: () => {
        clearDraft()
        Swal.fire({
          icon: 'success',
          title: 'Application Submitted',
          text: 'Your application has been received. We will contact you shortly.',
          confirmButtonColor: '#4FA07F',
          confirmButtonText: 'OK',
        }).then(() => router.visit('/'))
        form.reset()
        selectedFromMap.value = null
      },
      onError: () => {
        console.log('FORM ERRORS:', JSON.parse(JSON.stringify(form.errors)))
        const first = Object.keys(form.errors)[0]
        if (first) {
          const el =
            document.querySelector(`[name="${first}"]`) ||
            document.querySelector('[name]')
          if (el) el.focus()
        }
      },
    })
}

function reset() {
  form.reset()
  selectedFromMap.value = null
  clearDraft()
}
</script>

<template>
  <div class="min-h-screen bg-white text-[#1C1C1C]">
    <!-- Brand backdrop -->
    <div class="fixed inset-0 -z-10">
      <div class="absolute inset-0 bg-gradient-to-b from-[#F7FCF9] via-white to-[#F7FCF9]"></div>
      <div
        class="absolute inset-0 opacity-[0.06] pointer-events-none"
        style="background:radial-gradient(70% 40% at 50% 10%, rgba(79,160,127,.18) 0%, transparent 60%)"
      ></div>
    </div>

    <!-- Top bar with LOGO -->
    <header
      class="sticky top-0 z-10 bg-white/80 backdrop-blur border-b border-[rgba(79,160,127,0.18)]"
    >
      <div
        class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between"
      >
        <div class="flex items-center gap-3">
          <img
            src="/images/Bethany.png"
            alt="Bethany Memorial Park logo"
            class="h-8 w-auto select-none"
            decoding="async"
          />
          <span class="text-sm font-semibold tracking-tight text-[#6E63A6]">
            MemoraBeth • Application
          </span>
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="btn-secondary"
            @click="goHome"
            aria-label="Go to Home"
          >
            <Home class="w-4 h-4" /> Home
          </button>
          <button
            type="button"
            class="btn-ghost hidden sm:inline-flex"
            @click="reset"
            aria-label="Reset form"
          >
            <ArrowLeftRight class="w-4 h-4" /> Reset
          </button>
        </div>
      </div>
      <div
        class="h-1 w-full bg-gradient-to-r from-[#F5D146] via-[#4FA07F] to-[#6E63A6]"
      ></div>
    </header>

    <!-- Form shell -->
    <main class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-8">
      <div
        class="rounded-2xl border border-[rgba(79,160,127,0.20)] bg-white/80 backdrop-blur shadow-lg"
      >
        <!-- Title block -->
        <div class="p-6 sm:p-8 border-b border-[rgba(79,160,127,0.14)]">
          <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
            Burial Service Application
          </h1>
          <p class="mt-1 text-sm text-gray-600">
            Fields marked with <span class="text-red-500">*</span> are required.
          </p>
        </div>

        <!-- FORM -->
        <form @submit.prevent="submit" class="p-6 sm:p-8 space-y-10">
          <!-- Honeypot -->
          <div class="hidden" aria-hidden="true">
            <label>
              If you are a human, leave this field empty
              <input
                type="text"
                name="contact_time"
                v-model="form.bot_field"
                autocomplete="off"
              />
            </label>
          </div>

          <!-- Applicant Details -->
          <section>
            <div class="section-head">
              <span class="section-dot" style="background:#6E63A6"></span>
              <h2 class="section-title">Applicant Details</h2>
            </div>

            <!-- Name (split) -->
            <div class="form-grid md:grid-cols-3">
              <div>
                <label class="label" for="app_last">* Last name</label>
                <input
                  name="applicant_last_name"
                  id="app_last"
                  v-model="form.applicant_last_name"
                  placeholder="Dela Cruz"
                  class="input"
                  maxlength="100"
                  autocomplete="family-name"
                />
                <p v-if="form.errors.applicant_last_name" class="err">
                  {{ form.errors.applicant_last_name }}
                </p>
              </div>
              <div>
                <label class="label" for="app_first">* First name</label>
                <input
                  name="applicant_first_name"
                  id="app_first"
                  v-model="form.applicant_first_name"
                  placeholder="Juan"
                  class="input"
                  maxlength="100"
                  autocomplete="given-name"
                />
                <p v-if="form.errors.applicant_first_name" class="err">
                  {{ form.errors.applicant_first_name }}
                </p>
              </div>
              <div>
                <label class="label" for="app_mid">Middle name</label>
                <input
                  name="applicant_middle_name"
                  id="app_mid"
                  v-model="form.applicant_middle_name"
                  placeholder="Santos"
                  class="input"
                  maxlength="100"
                  autocomplete="additional-name"
                />
              </div>
            </div>

            <div class="form-grid mt-3">
              <div>
                <label class="label" for="app_email">* Email</label>
                <input
                  name="applicant_email"
                  id="app_email"
                  v-model="form.applicant_email"
                  type="email"
                  placeholder="name@gmail.com"
                  class="input"
                  maxlength="254"
                  autocomplete="email"
                />
                <p v-if="form.errors.applicant_email" class="err">
                  {{ form.errors.applicant_email }}
                </p>
              </div>
              <div>
                <label class="label" for="app_phone">Phone</label>
                <input
                  name="applicant_phone"
                  id="app_phone"
                  v-model="form.applicant_phone"
                  type="tel"
                  inputmode="tel"
                  placeholder="09xx or landline"
                  class="input"
                  maxlength="20"
                  autocomplete="tel"
                />
              </div>
              <small class="hint md:col-span-2">
                Please provide an active number for updates and confirmation.
              </small>
            </div>
          </section>

          <!-- Deceased Details -->
          <section>
            <div class="section-head">
              <span class="section-dot" style="background:#4FA07F"></span>
              <h2 class="section-title">Deceased Details</h2>
            </div>

            <!-- Name (split) -->
            <div class="form-grid md:grid-cols-3">
              <div>
                <label class="label" for="dec_last">* Last name</label>
                <input
                  name="deceased_last_name"
                  id="dec_last"
                  v-model="form.deceased_last_name"
                  placeholder="Dela Cruz"
                  class="input"
                  maxlength="100"
                  autocomplete="off"
                />
                <p v-if="form.errors.deceased_last_name" class="err">
                  {{ form.errors.deceased_last_name }}
                </p>
              </div>
              <div>
                <label class="label" for="dec_first">* First name</label>
                <input
                  name="deceased_first_name"
                  id="dec_first"
                  v-model="form.deceased_first_name"
                  placeholder="Maria"
                  class="input"
                  maxlength="100"
                  autocomplete="off"
                />
                <p v-if="form.errors.deceased_first_name" class="err">
                  {{ form.errors.deceased_first_name }}
                </p>
              </div>
              <div>
                <label class="label" for="dec_mid">Middle name</label>
                <input
                  name="deceased_middle_name"
                  id="dec_mid"
                  v-model="form.deceased_middle_name"
                  placeholder="Reyes"
                  class="input"
                  maxlength="100"
                  autocomplete="off"
                />
              </div>
            </div>

            <div class="form-grid md:grid-cols-3 mt-3">
              <div>
                <label class="label" for="dec_dod">Date of death</label>
                <input
                  name="deceased_dod"
                  id="dec_dod"
                  type="date"
                  v-model="form.deceased_dod"
                  class="input"
                  :max="todayStr"
                />
              </div>
              <div>
                <label class="label" for="dec_age">Age (optional)</label>
                <input
                  name="deceased_age"
                  id="dec_age"
                  v-model="form.deceased_age"
                  placeholder="e.g. 72"
                  class="input"
                  type="number"
                  min="0"
                  max="120"
                />
                <p v-if="form.errors.deceased_age" class="err">
                  {{ form.errors.deceased_age }}
                </p>
              </div>
            </div>
          </section>

          <!-- Burial Preferences -->
          <section>
            <div class="section-head">
              <span class="section-dot" style="background:#F5D146"></span>
              <h2 class="section-title">Burial Preferences</h2>
            </div>

            <div class="form-grid space-y-4">
              <div>
                <label class="label" for="svc_type">Service type</label>
                <select
                  name="service_type"
                  id="svc_type"
                  v-model="form.service_type"
                  class="input"
                >
                  <option>Lawn Lot</option>
                  <option>Garden Lot</option>
                  <option>Mausoleum</option>
                  <option>Community Vaults</option>
                </select>
              </div>

              <!-- Lawn Lot -->
              <div
                v-if="form.service_type === 'Lawn Lot'"
                class="info-box bg-[#F7FCF9] border-[#4FA07F]/20"
              >
                <label class="flex items-start gap-2">
                  <input
                    name="allow_auto_assign"
                    type="checkbox"
                    v-model="form.allow_auto_assign"
                  />
                  <span>
                    I authorize Bethany Memorial staff to assign a suitable lawn
                    lot on our behalf (standard practice).
                  </span>
                </label>
                <p class="hint mt-2">
                  Para sa Lawn Lot, staff ang pumipili ng akmang lote para
                  maayos, mabilis, at magalang ang proseso.
                </p>

                <div v-if="form.assigned_plot_id && selectedFromMap" class="mt-2">
                  <p class="hint">
                    Selected from map:
                    <span class="font-semibold text-slate-800">
                      {{ selectedFromMap }}
                    </span>
                  </p>
                </div>
              </div>

              <!-- Mausoleum / Garden / CV -->
              <div
                v-else
                class="info-box bg-gray-50 border-gray-200 space-y-3"
              >
                <p class="text-sm font-medium">
                  How would you like to choose a location?
                </p>
                <label class="option-row">
                  <input
                    name="selection_mode"
                    type="radio"
                    value="staff-guided"
                    v-model="form.selection_mode"
                  />
                  <span>Have staff guide me on-site (recommended)</span>
                </label>
                <label class="option-row">
                  <input
                    name="selection_mode"
                    type="radio"
                    value="map"
                    v-model="form.selection_mode"
                  />
                  <span>Let me choose from the map/list</span>
                </label>

                <!-- NEW: Choose from map -->
                <div
                  v-if="form.selection_mode === 'map'"
                  class="mt-2 space-y-1"
                >
                  <button
                    type="button"
                    class="btn-ghost"
                    @click="openMapPicker"
                  >
                    Choose from map
                  </button>

                  <p v-if="selectedFromMap" class="hint">
                    Selected from map:
                    <span class="font-semibold text-slate-800">
                      {{ selectedFromMap }}
                    </span>
                  </p>
                  <p v-else class="hint">
                    No plot selected yet. Click “Choose from map” and tap a
                    vacant lot.
                  </p>
                </div>

                <label class="option-row">
                  <input
                    name="selection_mode"
                    type="radio"
                    value="near-family"
                    v-model="form.selection_mode"
                  />
                  <span>Near a family member / same section</span>
                </label>

                <input
                  v-if="form.selection_mode === 'near-family'"
                  name="family_reference"
                  v-model="form.family_reference"
                  class="input"
                  placeholder="e.g. Same section as Nanay Pilar, Lot L-12"
                  maxlength="255"
                />
                <small class="hint">
                  No need to select an exact spot here — our staff will
                  coordinate with you respectfully.
                </small>
              </div>

              <div>
                <label class="label" for="remarks">Remarks (optional)</label>
                <textarea
                  name="remarks"
                  id="remarks"
                  v-model="form.remarks"
                  placeholder="Example: Preferred near entrance or shaded area."
                  class="input"
                  rows="3"
                  maxlength="500"
                ></textarea>
              </div>
            </div>
          </section>

          <!-- Religious Rites -->
          <section>
            <div class="section-head">
              <span class="section-dot" style="background:#6E63A6"></span>
              <h2 class="section-title">Rites & Schedule (Optional)</h2>
            </div>

            <div class="form-grid md:grid-cols-3">
              <div>
                <label class="label" for="rites">Religious rites</label>
                <select
                  name="rites"
                  id="rites"
                  v-model="form.rites"
                  class="input"
                >
                  <option value="">—</option>
                  <option>Catholic</option>
                  <option>Protestant</option>
                  <option>Iglesia</option>
                  <option>Islam</option>
                  <option>Other</option>
                </select>
              </div>
              <div>
                <label class="label" for="wake_pref">Wake preference</label>
                <input
                  name="wake_pref"
                  id="wake_pref"
                  v-model="form.wake_pref"
                  class="input"
                  placeholder="Chapel / Home"
                  maxlength="255"
                />
              </div>
              <div>
                <label class="label" for="window">Interment window</label>
                <input
                  name="interment_window"
                  id="window"
                  v-model="form.interment_window"
                  class="input"
                  placeholder="e.g. Fri–Sun afternoon"
                  maxlength="255"
                />
              </div>
            </div>

            <div class="mt-3">
              <label class="label" for="rites_notes">Notes</label>
              <textarea
                name="rites_notes"
                id="rites_notes"
                v-model="form.rites_notes"
                class="input"
                placeholder="Priest/Pastor contact, blessing notes, or coordination reminders (optional)"
                rows="3"
                maxlength="500"
              ></textarea>
            </div>
          </section>

          <!-- Privacy & Consent -->
          <section>
            <div class="section-head">
              <span class="section-dot" style="background:#4FA07F"></span>
              <h2 class="section-title">Privacy & Consent</h2>
            </div>
            <div class="info-box bg-[#F7FCF9] border-[#4FA07F]/25 space-y-2">
              <label class="option-row items-start">
                <input
                  type="checkbox"
                  name="privacy_consent"
                  v-model="form.privacy_consent"
                />
                <span class="text-sm text-gray-700">
                  I confirm that the information provided is accurate to the
                  best of my knowledge and that I authorize Bethany Memorial Park
                  to process this information for the purpose of evaluating and
                  coordinating burial services.
                </span>
              </label>
              <p class="hint">
                Your information will be handled confidentially and used only
                for legitimate service and contact purposes.
              </p>
            </div>
          </section>

          <!-- Submit Actions -->
          <div
            class="flex flex-wrap gap-4 justify-end items-center pt-4 border-t border-gray-100 mt-6"
          >
            <button type="button" class="btn-ghost" @click="reset">
              Reset
            </button>
            <button
              type="submit"
              class="btn-primary"
              :disabled="form.processing"
            >
              Submit Application
            </button>
          </div>

          <p class="text-xs text-gray-500 mt-2 text-center">
            By submitting, you agree to receive updates via email or phone for
            the processing of this application.
          </p>
        </form>
      </div>
    </main>
  </div>
</template>

<style scoped>
* {
  transition: background-color 0.18s ease, color 0.18s ease,
    border-color 0.18s ease, box-shadow 0.18s ease, transform 0.06s.ease;
}
@media (prefers-reduced-motion: reduce) {
  * {
    transition: none !important;
  }
}

.section-head {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 0.5rem;
}
.section-dot {
  width: 0.66rem;
  height: 0.66rem;
  border-radius: 999px;
  box-shadow: 0 0 0 3px rgba(79, 160, 127, 0.1);
}
.section-title {
  font-weight: 700;
  color: #1c1c1c;
}

.form-grid {
  display: grid;
  gap: 1rem;
}
.input {
  width: 100%;
  padding: 0.625rem 1rem;
  border: 1px solid rgba(2, 6, 23, 0.1);
  border-radius: 0.75rem;
  background: white;
  color: #1f2937;
  font-size: 0.875rem;
  box-shadow: 0 1px 0 rgba(2, 6, 23, 0.02) inset;
}
.input:focus {
  outline: none;
  border-color: #4fa07f;
  box-shadow: 0 0 0 4px rgba(79, 160, 127, 0.14);
}
.hint {
  font-size: 0.75rem;
  color: #64748b;
}
.err {
  margin-top: 0.25rem;
  font-size: 0.75rem;
  color: #dc2626;
}

.info-box {
  padding: 1rem;
  border-radius: 0.75rem;
  border: 1px solid rgba(2, 6, 23, 0.08);
}

.option-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #374151;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background-image: linear-gradient(180deg, #4fa07f, #3f8e6d);
  color: #fff;
  padding: 0.625rem 1.25rem;
  border-radius: 0.75rem;
  font-weight: 700;
  box-shadow: 0 10px 20px rgba(79, 160, 127, 0.18);
}
.btn-primary:hover {
  filter: brightness(1.03);
}
.btn-primary:active {
  transform: translateY(1px);
}
.btn-primary:disabled {
  opacity: 0.6;
  pointer-events: none;
}

.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background-color: #6e63a6;
  color: #fff;
  padding: 0.5rem 0.875rem;
  border-radius: 0.6rem;
  font-weight: 600;
}
.btn-secondary:hover {
  filter: brightness(1.05);
}

.btn-ghost {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background-color: transparent;
  border: 1px solid rgba(2, 6, 23, 0.12);
  color: #1f2937;
  padding: 0.6rem 1rem;
  border-radius: 0.6rem;
  font-weight: 600;
}
.btn-ghost:hover {
  background: #f8fafc;
}
</style>
