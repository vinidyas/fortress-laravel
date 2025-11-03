<template>
  <PortalLayout>
    <section class="space-y-6">
      <header class="flex flex-col gap-2">
        <h1 class="text-2xl font-semibold text-white">Portal do Locatário</h1>
        <p class="text-sm text-slate-400">
          Utilize o menu lateral para acessar contratos, faturas e gerar 2ª via dos seus boletos.
        </p>
      </header>
      <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-6 text-sm text-slate-300">
        Selecione uma opção no menu para começar.
      </div>
    </section>
  </PortalLayout>
</template>

<script setup lang="ts">
import PortalLayout from '@/Layouts/PortalLayout.vue';
</script>
