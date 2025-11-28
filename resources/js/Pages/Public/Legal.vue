<script setup>
import { Link } from '@inertiajs/vue3'
import { Scale, ShieldCheck, ScrollText, Info, ExternalLink, Home } from 'lucide-vue-next'

/** graceful back: go history(-1) else fall back to announcements */
function goBack(e) {
  e?.preventDefault?.()
  if (window.history.length > 1) {
    window.history.back()
  } else {
    window.location.href = '/announcements'
  }
}
</script>

<template>
  <div class="relative min-h-screen bg-white text-slate-900">
    <!-- Soft brand wash -->
    <div class="absolute inset-0 -z-10 bg-gradient-to-b from-[#F7FCF9] via-white to-[#F7FCF9]"></div>

    <!-- Header / Hero -->
    <header class="border-b border-emerald-100/60 bg-white/70 backdrop-blur">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
        <!-- Top row: Back + Actions -->
        <div class="flex items-center justify-between gap-2 mb-3">
          <button
            @click="goBack"
            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 text-sm"
            aria-label="Go back"
          >
            <!-- chevron-left (inline to avoid extra imports) -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back
          </button>

          <div class="hidden sm:flex items-center gap-2">
            <Link
              :href="route('landing')"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 text-sm"
            >
              <Home class="w-4 h-4" /> Home
            </Link>
            <Link
              :href="route('apply.create')"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-600 text-white hover:brightness-110 text-sm"
            >
              Apply Now
            </Link>
          </div>
        </div>

        <div class="flex items-start justify-between gap-4">
          <div class="flex items-start gap-4">
            <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-violet-600 text-white grid place-items-center shadow-lg">
              <Scale class="w-6 h-6" />
            </div>
            <div>
              <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                Philippine Law &amp; Perpetual Commitment
              </h1>
              <p class="mt-2 text-slate-600 max-w-3xl">
                How we fund, protect, and maintain Bethany Memorial Park—guided by national policy and industry rules.
              </p>
            </div>
          </div>

          <!-- Actions (mobile below) -->
          <div class="hidden sm:flex items-center gap-2"></div>
        </div>

        <!-- Mobile actions -->
        <div class="mt-4 sm:hidden flex items-center gap-2">
          <Link
            :href="route('landing')"
            class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 text-sm"
          >
            <Home class="w-4 h-4" /> Home
          </Link>
          <Link
            :href="route('apply.create')"
            class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-emerald-600 text-white hover:brightness-110 text-sm"
          >
            Apply Now
          </Link>
        </div>
      </div>
    </header>

    <!-- Main -->
    <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 lg:py-10 grid lg:grid-cols-[240px_minmax(0,1fr)] gap-8">
      <!-- Sticky TOC (desktop) -->
      <aside class="hidden lg:block">
        <div class="sticky top-20 rounded-2xl border border-emerald-100/70 bg-white/80 backdrop-blur p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700 mb-2">
            On this page
          </div>
          <nav class="space-y-1 text-sm">
            <a href="#foundation" class="block px-2 py-1 rounded hover:bg-emerald-50 text-slate-700">Legal foundation</a>
            <a href="#perpetual-care" class="block px-2 py-1 rounded hover:bg-emerald-50 text-slate-700">Perpetual Care Fund</a>
            <a href="#for-families" class="block px-2 py-1 rounded hover:bg-emerald-50 text-slate-700">What it means for families</a>
            <a href="#references" class="block px-2 py-1 rounded hover:bg-emerald-50 text-slate-700">References</a>
            <a href="#notes" class="block px-2 py-1 rounded hover:bg-emerald-50 text-slate-700">Notes</a>
          </nav>
        </div>
      </aside>

      <!-- Content -->
      <section class="space-y-8">
        <!-- Legal foundation -->
        <article id="foundation" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="h-1.5 w-full bg-gradient-to-r from-emerald-500 via-amber-300 to-violet-600"></div>
          <div class="p-6 sm:p-8">
            <div class="flex items-start gap-3">
              <div class="mt-0.5 rounded-lg bg-emerald-50 text-emerald-700 p-2">
                <ScrollText class="w-5 h-5" />
              </div>
              <div class="prose prose-slate max-w-none">
                <h2 class="!mt-0">Legal foundation</h2>
                <p>
                  Our perpetual commitment to maintenance is grounded in
                  <strong>Presidential Decree No. 957</strong> (Subdivision and Condominium Buyers’ Protective Decree) and the
                  <strong>DHSUD Rules &amp; Regulations Governing Memorial Parks and Cemeteries</strong>.
                </p>
              </div>
            </div>
          </div>
        </article>

        <!-- Perpetual Care Fund -->
        <article id="perpetual-care" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="h-1.5 w-full bg-gradient-to-r from-violet-600 via-emerald-500 to-amber-300"></div>
          <div class="p-6 sm:p-8">
            <div class="flex items-start gap-3">
              <div class="mt-0.5 rounded-lg bg-violet-50 text-violet-700 p-2">
                <ShieldCheck class="w-5 h-5" />
              </div>
              <div class="prose prose-slate max-w-none">
                <h2 class="!mt-0">Perpetual Care Fund</h2>
                <p>
                  A portion of every sale is placed into a <strong>Perpetual Care Fund</strong>, held in trust and
                  used only for maintenance, repairs, and improvements—even after all lots are sold.
                </p>

                <ul class="grid sm:grid-cols-2 gap-x-6">
                  <li>Dedicated trust—separate from operating revenue.</li>
                  <li>Exclusive purpose: grounds, facilities, and safety.</li>
                  <li>Supports long-term improvements and restoration.</li>
                  <li>Survives beyond sell-out for continuity of care.</li>
                </ul>
              </div>
            </div>
          </div>
        </article>

        <!-- Families -->
        <article id="for-families" class="rounded-2xl border border-emerald-100 bg-emerald-50/60 shadow-inner">
          <div class="p-6 sm:p-8">
            <div class="prose prose-slate max-w-none">
              <h2 class="!mt-0 text-emerald-800">What this means for families</h2>
              <ul>
                <li>Continuous grounds upkeep and safety.</li>
                <li>Transparent fund management, separate from company profits.</li>
                <li>A dignified environment for remembrance—today and for generations.</li>
              </ul>
            </div>
          </div>
        </article>

        <!-- References -->
        <article id="references" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
          <div class="h-1.5 w-full bg-gradient-to-r from-amber-300 via-emerald-500 to-violet-600"></div>
          <div class="p-6 sm:p-8">
            <div class="prose prose-slate max-w-none">
              <h2 class="!mt-0">References</h2>
              <ul class="not-prose grid gap-3">
                <li class="flex items-start gap-3">
                  <ExternalLink class="w-5 h-5 mt-0.5 text-slate-500 shrink-0" />
                  <span>Presidential Decree No. 957 (Subdivision and Condominium Buyers’ Protective Decree)</span>
                </li>
                <li class="flex items-start gap-3">
                  <ExternalLink class="w-5 h-5 mt-0.5 text-slate-500 shrink-0" />
                  <span>DHSUD Implementing Rules on Memorial Parks &amp; Cemeteries</span>
                </li>
              </ul>
            </div>
          </div>
        </article>

        <!-- Note -->
        <article id="notes" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div class="p-6 sm:p-8 flex items-start gap-3">
            <div class="rounded-lg bg-amber-50 text-amber-700 p-2">
              <Info class="w-5 h-5" />
            </div>
            <p class="text-sm text-slate-600">
              This page is provided for general information and does not replace official documents or legal advice.
            </p>
          </div>
        </article>

        <!-- CTA -->
        <div
          class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 rounded-2xl border border-emerald-100 bg-white shadow-sm p-5">
          <div class="text-sm">
            Want the official text? You can review the statute and implementing rules from government sources.
          </div>
          <div class="flex gap-2">
            <button
              @click="goBack"
              class="px-3 py-2 rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 text-sm"
            >
              Back
            </button>
            <Link :href="route('apply.create')"
                  class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:brightness-110 text-sm">
              Apply for Services
            </Link>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
* { transition: background-color .18s ease, color .18s ease, border-color .18s ease, box-shadow .18s ease; }
@media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
.prose :where(h1,h2,h3){ scroll-margin-top: 96px; }
</style>
