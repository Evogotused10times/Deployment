<script setup>
import { ref, computed } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { Mail, Lock, LogIn, Eye, EyeOff } from 'lucide-vue-next'

const form = useForm({ email: '', password: '' })
const submit = () => form.post(route('login'))

const showPwd = ref(false)
const emailErr = computed(() => !!form.errors.email)
const passErr  = computed(() => !!form.errors.password)

// Point this to your icon-only logo (SVG or PNG)
const LOGO_URL = '/images/Bethany.png'
</script>

<template>
  <div class="relative min-h-screen text-[#1C1C1C] overflow-hidden antialiased">
    <!-- Soft mint site wash -->
    <div
      class="fixed inset-0 -z-20"
      style="background:linear-gradient(180deg,#F7FCF9 0%,#FFFFFF 42%,#F7FCF9 100%)"
    ></div>

    <!-- Brand backdrop with exact 50/20/20/10 composition -->
    <div class="pointer-events-none fixed inset-0 -z-10">
      <div
        class="absolute inset-0 opacity-20"
        style="background:conic-gradient(from 0deg,#F5D146 0 180deg,#4FA07F 180deg 252deg,#6E63A6 252deg 324deg,#7A5B43 324deg 360deg);filter:saturate(1.02) contrast(1.02);"
      />
      <div
        class="absolute inset-0"
        style="background:
          radial-gradient(60% 55% at 50% 40%,rgba(255,255,255,.94) 0%,rgba(255,255,255,.72) 46%,rgba(255,255,255,.18) 78%,rgba(255,255,255,0) 100%)"
      />
    </div>

    <!-- Top ribbon -->
    <div class="fixed top-0 left-0 right-0 -z-10 pointer-events-none">
      <div class="h-1.5 w-full flex">
        <div class="basis-[50%]" style="background:#F5D146"></div>
        <div class="basis-[20%]" style="background:#4FA07F"></div>
        <div class="basis-[20%]" style="background:#6E63A6"></div>
        <div class="basis-[10%]" style="background:#7A5B43"></div>
      </div>
    </div>

    <!-- Angled mint slices -->
    <div class="pointer-events-none absolute -z-10 inset-x-0 top-24">
      <div class="mx-auto max-w-7xl relative">
        <div
          class="absolute -left-24 right-0 h-28 rotate-[8deg] opacity-25"
          style="background:linear-gradient(90deg,rgba(239,254,250,0),rgba(239,254,250,.9),rgba(239,254,250,0))"
        ></div>
        <div
          class="absolute left-0 -right-24 top-24 h-24 -rotate-[6deg] opacity-20"
          style="background:linear-gradient(90deg,rgba(239,254,250,0),rgba(239,254,250,.9),rgba(239,254,250,0))"
        ></div>
      </div>
    </div>

    <!-- Grid layout -->
    <div class="mx-auto max-w-7xl px-6 lg:px-10 py-10 lg:py-20">
      <div class="grid lg:grid-cols-12 gap-12 items-center">
        <!-- LEFT: Brand panel -->
        <section class="lg:col-span-6">
          <div class="max-w-xl space-y-6">
            <div class="inline-flex items-center gap-4">
              <!-- Bigger logo with subtle float -->
              <div
                class="shrink-0 aspect-square h-16 w-16 sm:h-20 sm:w-20 md:h-24 md:w-24 rounded-2xl bg-white/90 p-2 ring-2 ring-[#4FA07F]/50 shadow-[0_14px_40px_rgba(15,23,42,0.18)] flex items-center justify-center"
              >
                <img
                  :src="LOGO_URL"
                  alt="MemoraBeth logo"
                  class="h-full w-full object-contain"
                />
              </div>
              <div>
                <p class="uppercase tracking-[0.2em] text-xs font-semibold text-[#4FA07F] mb-1">
                  Bethany Memorial Park · Tubigon
                </p>
                <p class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                  <span class="text-[#6E63A6]">Memora</span>
                  <span
                    class="bg-gradient-to-r from-[#4FA07F] via-[#73C5A2] to-[#6E63A6] bg-clip-text text-transparent"
                    >Beth</span
                  >
                </p>
              </div>
            </div>

            <div class="space-y-4">
              <h1 class="text-4xl sm:text-5xl font-extrabold leading-tight">
                A Caring, Organized &amp;
                <span class="text-[#4FA07F]">Dignified</span> Experience
              </h1>
              <p class="text-base sm:text-lg text-gray-700 leading-relaxed">
                “A place to remember. A place to rest.”
                <br class="hidden sm:block" />
                This portal helps administrators manage interments, plot records, and park operations with
                clarity and respect.
              </p>
            </div>

            <!-- Feature chips -->
            <!-- <dl class="grid grid-cols-2 gap-3 text-sm text-gray-700 max-w-md">
              <div class="rounded-xl bg-white/90 border border-[#4FA07F]/15 px-3.5 py-3 shadow-sm">
                <dt class="font-semibold text-[#1C1C1C]">Burial Registry</dt>
                <dd class="text-xs mt-1 text-gray-600">
                  Keep interment records accurate, searchable, and up to date.
                </dd>
              </div>
              <div class="rounded-xl bg-white/90 border border-[#4FA07F]/10 px-3.5 py-3 shadow-sm">
                <dt class="font-semibold text-[#1C1C1C]">Grave Mapping</dt>
                <dd class="text-xs mt-1 text-gray-600">
                  Visual lot assignments for faster assistance to families.
                </dd>
              </div>
              <div class="rounded-xl bg-white/90 border border-[#4FA07F]/10 px-3.5 py-3 shadow-sm">
                <dt class="font-semibold text-[#1C1C1C]">Invoice Tracking</dt>
                <dd class="text-xs mt-1 text-gray-600">
                  Monitor payments, renewals, and reservations in one place.
                </dd>
              </div>
              <div class="rounded-xl bg-white/90 border border-[#4FA07F]/10 px-3.5 py-3 shadow-sm">
                <dt class="font-semibold text-[#1C1C1C]">Staff-Only Access</dt>
                <dd class="text-xs mt-1 text-gray-600">
                  Admin tools secured behind authenticated access.
                </dd>
              </div>
            </dl> -->
          </div>
        </section>

        <!-- RIGHT: Login card -->
        <section class="lg:col-span-6 flex lg:justify-end">
          <div
            class="w-full max-w-md rounded-3xl border border-white/70 bg-white/95 backdrop-blur-xl shadow-[0_22px_60px_rgba(15,23,42,0.22)] p-7 sm:p-8 relative overflow-hidden
                   transition-transform duration-300 hover:-translate-y-0.5 hover:shadow-[0_26px_72px_rgba(15,23,42,0.28)]"
          >
            <!-- Top glow -->
            <div
              class="absolute left-6 right-6 -top-[1px] h-[3px] rounded-full"
              style="background:linear-gradient(90deg,rgba(79,160,127,0),rgba(79,160,127,.7),rgba(79,160,127,0))"
            ></div>

            <!-- Soft badge in corner -->
            <div
              class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full border border-[#F5D146]/40 bg-[#FFF9D9]/40 blur-xl"
            ></div>

            <!-- Card header -->
            <div class="mb-6 flex items-start justify-between gap-3 relative z-10">
              <div class="flex items-center gap-3">
                <div
                  class="shrink-0 rounded-2xl bg-[#EFFEFA] border border-[#4FA07F]/20 p-1.5 flex items-center justify-center"
                >
                  <img :src="LOGO_URL" alt="" class="h-7 w-7 sm:h-8 sm:w-8 object-contain" />
                </div>
                <div>
                  <h2 class="text-2xl font-bold tracking-tight text-[#121212]">Admin Login</h2>
                  <p class="text-xs sm:text-sm text-gray-600">
                    Secure access for Bethany Memorial Park staff.
                  </p>
                </div>
              </div>
              <span
                class="hidden sm-inline-block rounded-full bg-[#EFFEFA] text-[10px] font-semibold uppercase tracking-[0.16em] px-3 py-1 text-[#4FA07F]"
              >
                Staff Only
              </span>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-5 relative z-10">
              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-[0.16em]">
                  Email Address
                </label>
                <div class="relative">
                  <Mail
                    class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2"
                    :class="[emailErr ? 'text-red-500' : 'text-gray-400']"
                  />
                  <input
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    placeholder="admin@example.com"
                    :aria-invalid="emailErr"
                    class="w-full pl-9 pr-3 py-2.5 rounded-lg text-sm transition
                           border focus:outline-none [--mint:#4FA07F]
                           border-gray-300/90 focus:ring-2 focus:ring-[color:var(--mint)] focus:border-[color:var(--mint)]
                           data-[error=true]:border-red-400 bg-white/90"
                    :data-error="emailErr"
                  />
                </div>
                <p v-if="form.errors.email" class="text-red-500 text-xs mt-1">
                  {{ form.errors.email }}
                </p>
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-[0.16em]">
                  Password
                </label>
                <div class="relative">
                  <Lock
                    class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2"
                    :class="[passErr ? 'text-red-500' : 'text-gray-400']"
                  />
                  <input
                    v-model="form.password"
                    :type="showPwd ? 'text' : 'password'"
                    autocomplete="current-password"
                    placeholder="••••••••"
                    :aria-invalid="passErr"
                    class="w-full pl-9 pr-10 py-2.5 rounded-lg text-sm transition
                           border focus:outline-none [--mint:#4FA07F]
                           border-gray-300/90 focus:ring-2 focus:ring-[color:var(--mint)] focus:border-[color:var(--mint)]
                           data-[error=true]:border-red-400 bg-white/90"
                    :data-error="passErr"
                  />
                  <button
                    type="button"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 rounded-md text-gray-400 hover:text-[#4FA07F] hover:bg-[#EFFEFA] transition"
                    @click="showPwd = !showPwd"
                    :aria-label="showPwd ? 'Hide password' : 'Show password'"
                  >
                    <EyeOff v-if="showPwd" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                  </button>
                </div>
                <p v-if="form.errors.password" class="text-red-500 text-xs mt-1">
                  {{ form.errors.password }}
                </p>
              </div>

              <!-- Helper row -->
              <div class="flex items-center justify-between text-xs text-gray-600">
                <p>Use your assigned admin credentials to sign in.</p>
                <!-- Optional: hook to password reset route if you have one -->
                <!-- <Link :href="route('password.request')" class="font-semibold text-[#4FA07F] hover:underline">
                  Forgot password?
                </Link> -->
              </div>

              <button
                type="submit"
                :disabled="form.processing"
                class="w-full inline-flex items-center justify-center gap-2 rounded-xl
                       bg-[#F5D146] text-[#1C1C1C] font-semibold py-2.5 text-sm
                       hover:brightness-95 hover:-translate-y-0.5 active:translate-y-0 transition
                       disabled:opacity-60 disabled:cursor-not-allowed shadow-[0_10px_30px_rgba(245,209,70,0.55)]"
              >
                <LogIn class="w-5 h-5" />
                <span v-if="!form.processing">Log in to Dashboard</span>
                <span v-else>Signing in…</span>
              </button>
            </form>

            <!-- Footer -->
            <div class="mt-7 text-center space-y-2 relative z-10">
              <Link
                href="/"
                class="inline-flex items-center gap-1.5 text-[#4FA07F] hover:bg-[#EFFEFA] px-2.5 py-1.5 rounded-full text-xs font-medium transition"
              >
                ← Back to Memorial Park site
              </Link>
              <p class="text-[11px] text-gray-500">
                © {{ new Date().getFullYear() }} MemoraBeth · Bethany Memorial Park, Tubigon, Bohol
              </p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
input {
  transition: all 0.18s ease;
}
input:focus {
  box-shadow: 0 0 0 3px rgba(79, 160, 127, 0.18);
}
@media (prefers-color-scheme: dark) {
  .opacity-20 {
    opacity: 0.16 !important;
  }
}
</style>
