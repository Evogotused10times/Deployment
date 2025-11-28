<script setup>
import { ref } from 'vue'

const open = ref(false)
const tone = ref('success') // 'success' | 'error' | 'info'
const msg  = ref('')

function show({ type = 'success', message = '' } = {}) {
  // guard: ignore empty messages
  if (!message) return
  tone.value = type
  msg.value  = message
  open.value = true
  // auto-hide
  setTimeout(() => (open.value = false), 3000)
}

defineExpose({ show })
</script>

<template>
  <div
    v-if="open"
    class="fixed top-4 right-4 z-[9999] rounded-lg shadow-lg px-4 py-3 text-sm text-white"
    :class="{
      'bg-emerald-600': tone === 'success',
      'bg-rose-600': tone === 'error',
      'bg-slate-700': tone === 'info',
    }"
    role="status"
    aria-live="polite"
  >
    {{ msg }}
  </div>
</template>
