<script setup lang="ts">
/**
 * BrandBackdropDropIn.vue
 * Drop-in background with exact 50/20/20/10 composition (Yellow/Green/Purple/Brown)
 * - Fixed behind content
 * - Gentle center wash for readability
 * - Optional top ribbon for visible brand cue
 *
 * Props let you tune intensity without code changes.
 */
interface Props {
  /** Show the thin 4px ribbon under the sticky header */
  showRibbon?: boolean
  /** Base opacity of the conic gradient layer (0..1) */
  baseOpacity?: number
  /** Center wash strength (0..1), 0 = none, 1 = strong white */
  washStrength?: number
  /** Enable subtle paper grain */
  grain?: boolean
}
const props = withDefaults(defineProps<Props>(), {
  showRibbon: true,
  baseOpacity: 0.20,
  washStrength: 0.85,
  grain: true,
})

// brand colors (keep in sync with Tailwind tokens if you have them)
const Y = '#F5D146' // 50%
const G = '#4FA07F' // 20%
const P = '#6E63A6' // 20%
const B = '#7A5B43' // 10%
</script>

<template>
  <!-- Backdrop -->
  <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
    <!-- Conic composition layer -->
    <div
      class="absolute inset-0 will-change-transform"
      :style="{
        opacity: String(baseOpacity),
        background: `conic-gradient(
          from 0deg,
          ${Y} 0 180deg,          /* Yellow 50% */
          ${G} 180deg 252deg,     /* Green 20% (+72deg) */
          ${P} 252deg 324deg,     /* Purple 20% (+72deg) */
          ${B} 324deg 360deg      /* Brown 10% (+36deg) */
        )`,
        filter: 'saturate(1) contrast(1.02)'
      }"
      aria-hidden="true"
    />

    <!-- Center wash for legibility on content -->
    <div
      class="absolute inset-0"
      :style="{
        background: `radial-gradient(60% 55% at 50% 40%,
          rgba(255,255,255,${washStrength}) 0%,
          rgba(255,255,255,${Math.max(0, washStrength - 0.3).toFixed(2)}) 45%,
          rgba(255,255,255,0) 100%)`
      }"
      aria-hidden="true"
    />

    <!-- Subtle paper grain -->
    <div v-if="grain" class="absolute inset-0 opacity-[0.05] mix-blend-multiply" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
        <filter id="mb-grain" x="-20%" y="-20%" width="140%" height="140%">
          <feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="2" stitchTiles="stitch"/>
          <feColorMatrix type="saturate" values="0"/>
          <feComponentTransfer>
            <feFuncA type="table" tableValues="0 0 0 .07 .12 .07 0 0"/>
          </feComponentTransfer>
        </filter>
        <rect width="100%" height="100%" filter="url(#mb-grain)"/>
      </svg>
    </div>
  </div>

  <!-- Optional top ribbon (exact proportions) -->
  <div v-if="showRibbon" class="pointer-events-none fixed top-0 left-0 right-0 -z-10">
    <div class="w-full h-1.5 flex">
      <div class="basis-[50%]" :style="{ background: Y }" />
      <div class="basis-[20%]" :style="{ background: G }" />
      <div class="basis-[20%]" :style="{ background: P }" />
      <div class="basis-[10%]" :style="{ background: B }" />
    </div>
  </div>
</template>

<style scoped>
/* Dark mode: slightly lower background opacity to keep contrast */
@media (prefers-color-scheme: dark) {
  .absolute[inset="0"] {
    opacity: 0.14;
  }
}
</style>
