<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from '@/bootstrap';
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { reactive, ref, watch } from 'vue';
import DatePicker from '@/Components/Form/DatePicker.vue';

type Defaults = {
  parcela_atual?: number;
  total_parcelas?: number;
  status?: string;
  vencimento?: string;
};

const props = defineProps<{
  defaults: Defaults;
}>();

const statusOptions = ['aberto', 'em_atraso', 'quitado', 'cancelado'];

const form = reactive({
  titulo: '',
  valor_total: '',
  parcela_atual: props.defaults.parcela_atual ?? 1,
  total_parcelas: props.defaults.total_parcelas ?? 1,
  vencimento: props.defaults.vencimento ?? '',
  status: props.defaults.status ?? 'aberto',
});

const fieldErrors = reactive<Record<string, string>>({});
const feedback = ref('');
const saving = ref(false);

watch(
  () => form.parcela_atual,
  (value) => {
    if (value > form.total_parcelas) {
      form.total_parcelas = value;
    }

    if (value < 0) {
      form.parcela_atual = 0;
    }
  },
);

watch(
  () => form.total_parcelas,
  (value) => {
    if (value < 1) {
      form.total_parcelas = 1;
    }
  },
);

const resetErrors = () => {
  feedback.value = '';
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key]);
};

const submit = async () => {
  resetErrors();
  saving.value = true;

  try {
    await axios.post('/api/payment-schedules', {
      titulo: form.titulo,
      valor_total: form.valor_total,
      parcela_atual: form.parcela_atual,
      total_parcelas: form.total_parcelas,
      vencimento: form.vencimento,
      status: form.status || null,
    });

    router.visit(route('financeiro.payment-schedules'), {
      preserveScroll: true,
    });
  } catch (error: any) {
    if (error?.response?.status === 422) {
      const { errors = {}, message } = error.response.data ?? {};

      Object.entries(errors as Record<string, string[]>)
        .forEach(([field, messages]) => {
          fieldErrors[field] = Array.isArray(messages) ? messages.join(' ') : String(messages);
        });

      feedback.value = message ?? 'Não foi possível validar os dados informados.';
    } else {
      feedback.value = 'Não foi possível salvar o agendamento.';
    }
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <AuthenticatedLayout title="Novo agendamento">
    <Head title="Novo agendamento" />

    <section class="space-y-6">
      <header class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-semibold text-white">Novo agendamento</h1>
          <p class="text-sm text-slate-400">
            Registre pagamentos planejados para manter o financeiro em dia.
          </p>
        </div>
        <Link
          :href="route('financeiro.payment-schedules')"
          class="text-sm font-semibold text-indigo-400 hover:text-indigo-300"
        >
          Voltar para a lista
        </Link>
      </header>

      <div
        v-if="feedback"
        class="rounded-lg border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200"
      >
        {{ feedback }}
      </div>

      <form
        class="grid gap-6 rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-black/40 md:grid-cols-2"
        @submit.prevent="submit"
      >
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-slate-300" for="titulo">Título</label>
          <input
            id="titulo"
            v-model.trim="form.titulo"
            type="text"
            required
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            placeholder="Ex: Parcela do aluguel"
          />
          <p v-if="fieldErrors.titulo" class="mt-1 text-xs text-rose-300">{{ fieldErrors.titulo }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-300" for="valor_total">Valor total</label>
          <input
            id="valor_total"
            v-model="form.valor_total"
            type="text"
            inputmode="decimal"
            required
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            placeholder="0,00"
          />
          <p v-if="fieldErrors.valor_total" class="mt-1 text-xs text-rose-300">{{ fieldErrors.valor_total }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-300" for="parcela_atual">Parcela atual</label>
            <input
              id="parcela_atual"
              v-model.number="form.parcela_atual"
              type="number"
              min="0"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            <p v-if="fieldErrors.parcela_atual" class="mt-1 text-xs text-rose-300">{{ fieldErrors.parcela_atual }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-300" for="total_parcelas">Total de parcelas</label>
            <input
              id="total_parcelas"
              v-model.number="form.total_parcelas"
              type="number"
              min="1"
              class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            />
            <p v-if="fieldErrors.total_parcelas" class="mt-1 text-xs text-rose-300">{{ fieldErrors.total_parcelas }}</p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-300" for="vencimento">Vencimento</label>
          <DatePicker
            id="vencimento"
            v-model="form.vencimento"
            placeholder="dd/mm/aaaa"
            required
            :invalid="Boolean(fieldErrors.vencimento)"
          />
          <p v-if="fieldErrors.vencimento" class="mt-1 text-xs text-rose-300">{{ fieldErrors.vencimento }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-300" for="status">Status</label>
          <select
            id="status"
            v-model="form.status"
            class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          >
            <option value="">Selecione</option>
            <option v-for="option in statusOptions" :key="option" :value="option">
              {{ option }}
            </option>
          </select>
          <p v-if="fieldErrors.status" class="mt-1 text-xs text-rose-300">{{ fieldErrors.status }}</p>
        </div>

        <div class="md:col-span-2 flex items-center justify-end gap-3">
          <Link
            :href="route('financeiro.payment-schedules')"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800"
          >
            Cancelar
          </Link>
          <button
            type="submit"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="saving"
          >
            Salvar agendamento
          </button>
        </div>
      </form>
    </section>
  </AuthenticatedLayout>
</template>
