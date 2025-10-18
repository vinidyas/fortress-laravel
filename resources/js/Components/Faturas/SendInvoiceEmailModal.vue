<script setup lang="ts">
import { computed, ref, watch } from 'vue';

type Nullable<T> = T | null;

type EmailDefaults = {
  to: string[];
  cc: string[];
};

type EmailLog = {
  id: number;
  subject: string;
  recipients: string[];
  cc: string[];
  bcc: string[];
  message: Nullable<string>;
  status: string;
  error_message: Nullable<string>;
  created_at: string;
  user?: {
    id: number;
    nome?: string;
    name?: string;
    email?: string;
  } | null;
};

type Props = {
  show: boolean;
  defaults: EmailDefaults;
  submitting: boolean;
  error: string;
  history: EmailLog[];
};

const props = withDefaults(defineProps<Props>(), {
  defaults: () => ({ to: [], cc: [] }),
  submitting: false,
  error: '',
  history: () => [],
});

type SendPayload = {
  recipients: string;
  cc: string;
  bcc: string;
  message: string;
};

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'send', payload: SendPayload): void;
}>();

const recipients = ref('');
const cc = ref('');
const bcc = ref('');
const message = ref('');

function applyDefaults() {
  recipients.value = props.defaults.to.join('\n');
  cc.value = props.defaults.cc.join('\n');
}

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      applyDefaults();
      bcc.value = '';
      message.value = '';
    } else {
      recipients.value = '';
      cc.value = '';
      bcc.value = '';
      message.value = '';
    }
  }
);

function closeModal() {
  if (props.submitting) return;
  emit('close');
}

function submitForm() {
  emit('send', {
    recipients: recipients.value,
    cc: cc.value,
    bcc: bcc.value,
    message: message.value,
  });
}

function statusBadgeClass(status: string): string {
  switch (status) {
    case 'sent':
      return 'bg-emerald-500/15 text-emerald-200 border border-emerald-500/40';
    case 'failed':
      return 'bg-rose-500/15 text-rose-200 border border-rose-500/40';
    default:
      return 'bg-slate-600/20 text-slate-200 border border-slate-500/40';
  }
}

function formatRecipients(list: string[]): string {
  if (!list?.length) return '—';
  return list.join(', ');
}

function formatDate(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return date.toLocaleString('pt-BR');
}

const hasHistory = computed(() => props.history?.length > 0);
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur"
      @keydown.esc.prevent.stop="closeModal"
    >
      <div class="relative w-full max-w-4xl overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
        <header class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-white">Enviar fatura por e-mail</h2>
            <p class="text-xs text-slate-400">Revise os destinatários e, se desejar, inclua uma mensagem personalizada.</p>
          </div>
          <button type="button" class="rounded-md p-2 text-slate-400 transition hover:text-white" @click="closeModal">
            <span class="sr-only">Fechar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </header>

        <form class="max-h-[75vh] overflow-y-auto px-6 py-5 text-sm text-slate-200" @submit.prevent="submitForm">
          <div class="grid gap-6 md:grid-cols-[minmax(0,2fr)]">
            <div class="grid gap-4">
              <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">Destinatários *</label>
                <textarea
                  v-model="recipients"
                  rows="2"
                  class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  placeholder="e-mail do locatário"
                />
                <p class="mt-2 text-xs text-slate-500">
                  Sugestão: {{ formatRecipients(defaults.to) || 'nenhum destinatário padrão encontrado' }}
                </p>
              </div>

              <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">
                  Cópia (CC)
                </label>
                <textarea
                  v-model="cc"
                  rows="2"
                  class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  placeholder="e-mail do proprietário ou equipe"
                />
                <p class="mt-2 text-xs text-slate-500">
                  Sugestão: {{ formatRecipients(defaults.cc) || 'nenhum e-mail sugerido' }}
                </p>
              </div>

              <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">
                  Cópia oculta (BCC)
                </label>
                <textarea
                  v-model="bcc"
                  rows="2"
                  class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  placeholder="Informe e-mails separados por vírgula, ponto e vírgula ou quebra de linha"
                />
              </div>

              <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">
                  Mensagem ao destinatário
                </label>
                <textarea
                  v-model="message"
                  rows="4"
                  class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                  placeholder="Mensagem adicional para acompanhar a fatura (opcional)"
                />
                <p class="mt-2 text-xs text-slate-500">Esta mensagem será exibida acima do resumo enviado por e-mail.</p>
              </div>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
              <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Histórico recente</h3>
              <div v-if="hasHistory" class="mt-3 space-y-3 text-xs text-slate-300">
                <div
                  v-for="log in history"
                  :key="log.id"
                  class="rounded-lg border border-slate-800 bg-slate-900/60 p-3"
                >
                  <div class="flex items-center justify-between gap-2">
                    <span class="font-semibold text-white">{{ log.subject }}</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="statusBadgeClass(log.status)">
                      {{ log.status === 'sent' ? 'Enviado' : log.status === 'failed' ? 'Falhou' : log.status }}
                    </span>
                  </div>
                  <p class="mt-1 text-slate-400">{{ formatDate(log.created_at) }}</p>
                  <p class="mt-2 text-slate-300">
                    <strong>Para:</strong> {{ formatRecipients(log.recipients) }}
                  </p>
                  <p v-if="log.cc?.length" class="text-slate-300">
                    <strong>CC:</strong> {{ formatRecipients(log.cc) }}
                  </p>
                  <p v-if="log.message" class="mt-2 rounded-md bg-slate-800/60 p-2 text-slate-200">
                    {{ log.message }}
                  </p>
                  <p v-if="log.error_message" class="mt-2 rounded-md bg-rose-500/10 p-2 text-rose-200">
                    {{ log.error_message }}
                  </p>
                  <p class="mt-2 text-slate-400">
                    Enviado por {{ log.user?.nome || log.user?.name || log.user?.email || 'Sistema' }}
                  </p>
                </div>
              </div>
              <p v-else class="mt-3 text-xs text-slate-500">
                Ainda não há histórico de envio para esta fatura.
              </p>
            </div>
          </div>

          <div v-if="error" class="mt-4 rounded-lg border border-rose-500/40 bg-rose-500/15 px-3 py-2 text-xs text-rose-200">
            {{ error }}
          </div>
        </form>

        <footer class="flex flex-col gap-3 border-t border-slate-800 px-6 py-4 text-xs text-slate-300 md:flex-row md:items-center md:justify-between">
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:border-indigo-500 hover:text-white"
              @click="applyDefaults"
              :disabled="submitting"
            >
              Reaplicar destinatários sugeridos
            </button>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:border-slate-600 hover:bg-slate-800/80 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="submitting"
              @click="closeModal"
            >
              Cancelar
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg border border-indigo-500/60 bg-indigo-600/80 px-4 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500/80 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="submitting"
              @click="submitForm"
            >
              <svg
                v-if="submitting"
                class="h-4 w-4 animate-spin"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M16.5 4.5a6 6 0 11-9 0"
                />
              </svg>
              <svg
                v-else
                class="h-4 w-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a1 1 0 001.22 0L20 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              <span>{{ submitting ? 'Enviando...' : 'Enviar e-mail' }}</span>
            </button>
          </div>
        </footer>
      </div>
    </div>
  </transition>
</template>
