<script setup>
import { ref, onMounted } from 'vue'
const show = ref(false)

onMounted(() => {
  // only show after a small delay (gentle)
  setTimeout(() => { show.value = true }, 600)
})
</script>

<template>
  <transition enter-active-class="duration-300 ease-out"
              enter-from-class="opacity-0 translate-y-2"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="duration-200 ease-in"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 translate-y-2">
    <div v-if="show" class="fixed right-4 bottom-4 z-50 w-[320px] rounded-xl border bg-white/90 backdrop-blur p-3 shadow-lg">
      <div class="text-sm">
        <p class="font-semibold">Welcome to Memorabeth 👋</p>
        <p class="mt-1 text-gray-600">
          Memorabeth is simple and easy to navigate. Need help?
          Ask anyone nearby, call our staff, or visit our office for a quick orientation.
        </p>
      </div>
      <div class="mt-3 flex justify-end">
        <button class="px-3 py-1.5 text-sm rounded-md bg-gray-100 hover:bg-gray-200" @click="show=false">Got it</button>
      </div>
    </div>
  </transition>
</template>
