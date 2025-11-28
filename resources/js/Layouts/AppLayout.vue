<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import BrandBackground from '/Components/BrandBackground.vue'

const open = ref(false)
function toggle() { open.value = !open.value }
function close() { open.value = false }
</script>

<template>
  <div class="relative min-h-screen bg-white text-brand-ink">
    <BrandBackground />

    <!-- Top bar -->
    <header class="sticky top-0 z-30 border-b bg-white/80 backdrop-blur">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button
            class="inline-flex items-center justify-center rounded-md p-2 hover:bg-gray-100 focus:outline-none focus:ring"
            @click="toggle"
            aria-label="Toggle navigation"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
              <path v-if="!open" d="M3 6h18M3 12h18M3 18h18"/>
              <path v-else d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
          <Link href="/" class="font-extrabold tracking-tight text-lg">
            Memorabeth
          </Link>
        </div>

        <nav class="hidden md:flex items-center gap-6 text-sm">
          <Link href="/" class="hover:underline">Home</Link>
          <Link href="/about" class="hover:underline">About</Link>
          <Link href="/announcements" class="hover:underline">Announcements</Link>
          <Link href="/careers" class="hover:underline">Careers</Link>
          <Link href="/legal" class="hover:underline">Philippine Law</Link>
        </nav>

        <div class="hidden md:flex items-center gap-2">
          <Link href="/login" class="px-3 py-1.5 rounded-md hover:bg-gray-100">Log in</Link>
          <Link href="/register" class="px-3 py-1.5 rounded-md bg-brand.green text-white hover:brightness-95">Sign up</Link>
        </div>
      </div>

      <!-- Mobile drawer -->
      <transition enter-active-class="duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2"
                  enter-to-class="opacity-100 translate-y-0" leave-active-class="duration-150 ease-in"
                  leave-from-class="opacity-100" leave-to-class="opacity-0 -translate-y-2">
        <div v-if="open" class="md:hidden border-t bg-white">
          <div class="mx-auto max-w-7xl px-4 sm:px-6 py-3 grid gap-2 text-sm">
            <Link href="/"        class="py-1 hover:underline" @click="close">Home</Link>
            <Link href="/about"   class="py-1 hover:underline" @click="close">About</Link>
            <Link href="/announcements" class="py-1 hover:underline" @click="close">Announcements</Link>
            <Link href="/careers" class="py-1 hover:underline" @click="close">Careers</Link>
            <Link href="/legal"   class="py-1 hover:underline" @click="close">Philippine Law</Link>
            <div class="flex gap-2 pt-2">
              <Link href="/login" class="flex-1 px-3 py-1.5 rounded-md bg-gray-50 hover:bg-gray-100 text-center" @click="close">Log in</Link>
              <Link href="/register" class="flex-1 px-3 py-1.5 rounded-md bg-brand.green text-white text-center hover:brightness-95" @click="close">Sign up</Link>
            </div>
          </div>
        </div>
      </transition>
    </header>

    <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
      <slot />
    </main>

    <footer class="mt-12 border-t py-6 text-xs text-gray-500">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        © {{ new Date().getFullYear() }} Bethany Memorial Park – Memorabeth
      </div>
    </footer>
  </div>
</template>
