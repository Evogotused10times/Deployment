<script setup>
import { onMounted, ref } from 'vue'
const show = ref(false)

onMounted(() => {
  // Show once per session
  if (!sessionStorage.getItem('login_tip_shown')) {
    show.value = true
    sessionStorage.setItem('login_tip_shown', '1')
  }
})
</script>

<template>
  <div v-if="show" class="fixed right-4 bottom-4 z-40">
    <div class="rounded-2xl border bg-white shadow-lg p-4 w-[300px] animate-[fadeIn_.25s_ease]">
      <div class="font-semibold text-slate-800">Welcome to Memorabeth 👋</div>
      <p class="text-sm text-slate-600 mt-1">
        Simple, guided, and dignified. Need help? Ask anyone available or call our staff for a walk-through. You may also visit our office for a face-to-face orientation.
      </p>
      <div class="mt-3 flex justify-end">
        <button class="text-sm px-3 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-800"
                @click="show = false">Got it</button>
      </div>
    </div>
  </div>
</template>

<style>
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px) } to { opacity: 1; transform: translateY(0) } }
</style>
