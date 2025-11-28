<script setup>
import { ref } from 'vue'
const props = defineProps({ title: String, summary: String })
const open = ref(false)
</script>

<template>
  <div class="rounded-xl border bg-white/80 backdrop-blur shadow-sm p-4 hover:shadow transition"
       @click="open = true" role="button" tabindex="0">
    <div class="font-semibold text-slate-800">{{ title }}</div>
    <div class="text-sm text-slate-600 mt-1">{{ summary }}</div>
    <div class="text-xs text-slate-500 mt-2">Tap to read</div>
  </div>

  <div v-if="open" class="fixed inset-0 z-40">
    <div class="absolute inset-0 bg-black/30" @click="open=false"></div>
    <div class="absolute inset-x-4 top-10 md:inset-x-auto md:left-1/2 md:-translate-x-1/2 md:w-[680px] bg-white rounded-2xl shadow-xl p-6 animate-[fadeIn_.2s_ease]">
      <div class="flex items-center justify-between">
        <h3 class="font-bold text-lg text-slate-800">{{ title }}</h3>
        <button class="text-slate-500 hover:text-slate-700" @click="open=false">✕</button>
      </div>
      <div class="mt-4 text-sm text-slate-700">
        <slot />
      </div>
    </div>
  </div>
</template>

<style>
@keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }
</style>
