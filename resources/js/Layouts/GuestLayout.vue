<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <div class="relative min-h-screen overflow-hidden bg-slate-950 text-slate-100">
    <div class="pointer-events-none absolute inset-0">
      <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900/70 to-slate-950" />
      <div class="absolute -top-32 -left-24 h-96 w-96 rounded-full bg-indigo-600/25 blur-3xl" />
      <div class="absolute -bottom-40 -right-32 h-[28rem] w-[28rem] rounded-full bg-violet-500/10 blur-[160px]" />
      <div class="absolute top-1/4 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-indigo-400/10 blur-3xl" />
    </div>

    <div class="relative z-10 flex min-h-screen items-center justify-center px-6 py-12">
      <div class="grid w-full max-w-6xl items-center gap-10 lg:grid-cols-[1.1fr_0.9fr]">
        <div class="hidden lg:flex flex-col gap-8">
          <div class="space-y-5">
            <Link href="/" class="inline-flex items-center gap-2 text-3xl font-semibold text-white">
              <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-600 text-lg font-semibold">FG</span>
              Fortress Gestão Imobiliária
            </Link>
            <h2 class="text-3xl font-semibold leading-tight text-slate-100">
              Simplifique a gestão financeira e operacional do seu portfólio.
            </h2>
            <p class="text-base text-slate-300">
              Acesse relatórios avançados, emita boletos com segurança e acompanhe a performance das suas locações em tempo real.
            </p>
          </div>
          <div class="grid gap-4 text-sm text-slate-300 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 shadow-inner shadow-black/30">
              <p class="text-lg font-semibold text-indigo-300">Financeiro conectado</p>
              <p class="mt-2 text-sm text-slate-400">Centralize lançamentos, concilie extratos e mantenha sua operação alinhada sem esforço.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 shadow-inner shadow-black/30">
              <p class="text-lg font-semibold text-indigo-300">Insights precisos</p>
              <p class="mt-2 text-sm text-slate-400">Dashboard inteligente com alertas, projeções e controle total sobre contratos e locatários.</p>
            </div>
          </div>
        </div>

        <div class="relative">
          <div class="relative overflow-hidden rounded-[2rem] border border-slate-800/80 bg-slate-900/75 shadow-2xl shadow-black/40 backdrop-blur-xl">
            <div class="absolute -top-24 -right-16 h-56 w-56 rounded-full bg-indigo-500/15 blur-3xl" />
            <div class="absolute -bottom-32 -left-20 h-64 w-64 rounded-full bg-violet-500/10 blur-3xl" />
            <div class="relative z-10 p-8 sm:p-10">
              <div class="mb-8 flex items-center gap-3 lg:hidden">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-600 text-base font-semibold">FG</span>
                <div>
                  <p class="text-lg font-semibold text-white">Fortress Gestão Imobiliária</p>
                  <p class="text-sm text-slate-400">Acesse sua plataforma segura</p>
                </div>
              </div>
              <slot />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
