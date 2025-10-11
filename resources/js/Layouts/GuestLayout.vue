<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <div class="min-h-screen bg-slate-100 py-12">
    <div class="mx-auto w-full max-w-md rounded-xl bg-white p-8 shadow-lg">
      <div class="mb-6 text-center">
        <Link href="/" class="text-2xl font-semibold text-slate-900"
          >Fortress Gestao Imobiliaria</Link
        >
      </div>
      <slot />
    </div>
  </div>
</template>
