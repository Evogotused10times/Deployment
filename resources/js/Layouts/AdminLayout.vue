<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import BrandBackground from '@/Components/BrandBackground.vue'
const open = ref(false)
</script>

<template>
  <div class="relative min-h-screen bg-white">
    <BrandBackground />

    <header class="sticky top-0 z-30 border-b bg-white/80 backdrop-blur">
      <div class="mx-auto max-w-7xl h-14 px-4 sm:px-6 lg:px-8 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button class="p-2 rounded hover:bg-gray-100" @click="open = !open" aria-label="Toggle">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path v-if="!open" d="M3 6h18M3 12h18M3 18h18"/><path v-else d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
          <Link href="/admin" class="font-extrabold">Admin • Memorabeth</Link>
        </div>
        <nav class="hidden md:flex items-center gap-4 text-sm">
          <Link href="/admin" class="hover:underline">Dashboard</Link>
          <Link href="/admin/plots" class="hover:underline">Plots</Link>
          <Link href="/admin/map" class="hover:underline">Cemetery Map</Link>
        </nav>
      </div>
      <div v-if="open" class="md:hidden border-t bg-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 py-3 grid gap-2 text-sm">
          <Link href="/admin" @click="open=false">Dashboard</Link>
          <Link href="/admin/plots" @click="open=false">Plots</Link>
          <Link href="/admin/map" @click="open=false">Cemetery Map</Link>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
      <slot />
    </main>
  </div>
</template>
