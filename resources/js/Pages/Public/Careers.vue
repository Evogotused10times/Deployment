<script setup>
import { Link } from '@inertiajs/vue3'

/** Brand tokens (keep in sync with site) */
const C = {
  yellow: '#F5D146',
  green:  '#4FA07F',
  purple: '#6E63A6',
  brown:  '#7A5B43',
  ink:    '#1C1C1C',
}
</script>

<template>
  <div class="min-h-[70vh] bg-gradient-to-b from-white via-[#F8FAF9] to-white"
       :style="{ '--ink': C.ink }">
    <!-- Hero -->
    <header
      class="relative w-full bg-center bg-cover h-[220px] md:h-[260px] rounded-b-[2rem] shadow-[0_10px_40px_rgba(79,160,127,.18)]"
      :style="{
        backgroundImage: `linear-gradient(180deg, rgba(0,0,0,.50), rgba(0,0,0,.15)), radial-gradient(1200px 240px at 20% 0%, ${C.green}22, transparent), radial-gradient(1200px 240px at 80% 0%, ${C.yellow}22, transparent)`,
        backgroundPosition: 'center'
      }"
      role="img"
      aria-label="Careers banner"
    >
      <!-- soft brand glaze -->
      <div class="absolute inset-0 pointer-events-none mix-blend-soft-light"
           :style="{ background: `linear-gradient(90deg, ${C.green}66, ${C.yellow}55)` }" />
      <div class="max-w-7xl mx-auto h-full flex items-end px-6 pb-8">
        <div class="text-white drop-shadow">
          <p class="text-xs uppercase tracking-[0.2em] opacity-90">Join Our Team</p>
          <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">Careers</h1>
          <p class="mt-1 text-sm md:text-base opacity-90">
            Post job openings and hiring announcements.
          </p>
        </div>
      </div>
    </header>

    <!-- Content -->
    <main class="max-w-7xl mx-auto px-6 py-8">
      <!-- Top bar: Back + CTA placeholder -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex items-center gap-2">
          <Link
            :href="route('landing')"
            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium
                   bg-white border border-gray-200 hover:bg-gray-50 shadow-sm"
            aria-label="Back to Home"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back
          </Link>
        </div>

        <!-- Optional HR contact (keep for later) -->
        <a href="mailto:hr@bethanymemorialpark.site"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold text-white
                  hover:brightness-110"
           :style="{ backgroundColor: C.green }">
          Contact HR
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16v16H4zM22 6l-10 7L2 6"/>
          </svg>
        </a>
      </div>

      <!-- Empty state / listings wrapper -->
      <section
        class="rounded-2xl border border-[rgba(17,24,39,0.08)] bg-white/80 backdrop-blur shadow-sm p-6">
        <!-- Header row -->
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-xl font-semibold text-gray-900">Open Roles</h2>
            <p class="text-sm text-gray-600">We’ll post vacancies here when available.</p>
          </div>

          <!-- Legend / brand chips -->
          <div class="hidden md:flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-[11px] text-white"
                  :style="{ backgroundColor: C.purple }">Full-time</span>
            <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-[11px] text-white"
                  :style="{ backgroundColor: C.green }">Part-time</span>
            <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-[11px] text-[color:var(--ink)]"
                  :style="{ backgroundColor: C.yellow }">Internship</span>
          </div>
        </div>

        <!-- Empty state -->
        <article
          class="mt-5 rounded-xl border border-gray-100 bg-gradient-to-br from-white to-[#FCFEFD] p-6">
          <div class="flex items-start gap-4">
            <div class="shrink-0 grid place-items-center h-12 w-12 rounded-xl ring-1 ring-black/5"
                 :style="{ backgroundColor: C.yellow + '22' }">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                   :style="{ color: C.purple }" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <div class="min-w-0">
              <h3 class="font-semibold text-gray-900">No openings yet</h3>
              <p class="text-sm text-gray-600">
                Check back soon or send us an email so we can keep your résumé on file.
              </p>

              <!-- “Keep me posted” row -->
              <div class="mt-4 flex flex-col sm:flex-row gap-2">
                <a href="mailto:hr@bethanymemorialpark.site?subject=General%20Application"
                   class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-white
                          hover:brightness-110"
                   :style="{ backgroundColor: C.purple }">
                  Send Resume
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7"/>
                  </svg>
                </a>
                <Link
                  :href="route('announcements.index')"
                  class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-sm font-medium
                         bg-white border border-gray-200 hover:bg-gray-50">
                  View Announcements
                </Link>
              </div>
            </div>
          </div>
        </article>

        <!-- Placeholder grid for future listings (keep structure ready) -->
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <!-- Example card (hidden until you have real roles) -->
          <article class="hidden rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
              <h3 class="font-semibold text-gray-900">Sample Role</h3>
              <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] text-white"
                    :style="{ backgroundColor: C.green }">Part-time</span>
            </div>
            <p class="mt-1 text-sm text-gray-600 line-clamp-3">
              Short description goes here. Responsibilities, tools, and basics.
            </p>
            <div class="mt-4 flex items-center gap-2">
              <button
                class="px-3 py-2 rounded-lg text-sm font-semibold text-white hover:brightness-110"
                :style="{ backgroundColor: C.green }">
                Apply
              </button>
              <button class="px-3 py-2 rounded-lg text-sm bg-white border hover:bg-gray-50">Details</button>
            </div>
          </article>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
* { transition: background-color .18s ease, color .18s ease, box-shadow .18s ease, border-color .18s ease, transform .18s ease; }
@media (prefers-reduced-motion: reduce) { * { transition: none !important; } }

/* Mobile hero rounding harmony */
@media (max-width: 640px) {
  .rounded-b-\[2rem\] {
    border-bottom-left-radius: 1.25rem;
    border-bottom-right-radius: 1.25rem;
  }
}

/* Clamp utility without plugin */
.line-clamp-3 {
  display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
</style>
