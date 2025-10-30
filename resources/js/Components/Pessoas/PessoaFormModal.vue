<script setup lang="ts">
import axios from '@/bootstrap';
import { computed, reactive, ref, watch } from 'vue';
import { useToast } from '@/composables/useToast';

type PessoaRole = 'Proprietario' | 'Locatario' | 'Fiador' | 'Fornecedor' | 'Cliente';

const props = withDefaults(
  defineProps<{
    show: boolean;
    roles?: PessoaRole[];
    appearance?: 'dark' | 'light';
  }>(),
  {
    roles: () => [],
    appearance: 'dark',
  }
);

const emit = defineEmits<{
  (e: 'close'): void;
  (
    e: 'created',
    payload: {
      id: number;
      nome: string;
      papeis: PessoaRole[];
    }
  ): void;
}>();

const toast = useToast();

const form = reactive({
  nome_razao_social: '',
  tipo_pessoa: 'Fisica',
  cpf_cnpj: '',
  email: '',
  telefone: '',
  papeis: [] as PessoaRole[],
});

const errors = reactive<Record<string, string>>({});
const submitting = ref(false);
const formError = ref('');

const baseRoleOptions: PessoaRole[] = ['Cliente', 'Fornecedor'];

const availableRoles = computed<PessoaRole[]>(() => baseRoleOptions);

const initialSelectedRoles = computed<PessoaRole[]>(() => {
  if (props.roles && props.roles.length) {
    const normalized = props.roles.filter((role): role is PessoaRole =>
      baseRoleOptions.includes(role)
    );
    if (normalized.length > 0) {
      return [...new Set(normalized)];
    }
  }

  return ['Cliente'];
});

const appearanceClasses = computed(() =>
  props.appearance === 'dark'
    ? {
        panel: 'border border-slate-800 bg-slate-900 text-slate-100',
        label: 'text-sm font-medium text-slate-200',
        input:
          'w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 placeholder:text-slate-500',
        hint: 'text-[11px] text-slate-400',
        error: 'text-[11px] text-rose-300',
        badge: 'bg-slate-800 text-slate-300 border border-slate-700',
      }
    : {
        panel: 'border border-slate-200 bg-white text-slate-900',
        label: 'text-sm font-medium text-slate-700',
        input:
          'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 placeholder:text-slate-400',
        hint: 'text-[11px] text-slate-500',
        error: 'text-[11px] text-rose-600',
        badge: 'bg-slate-100 text-slate-600 border border-slate-200',
      }
);

const dividerClass = computed(() =>
  props.appearance === 'dark' ? 'border-white/10' : 'border-slate-200'
);

const errorBoxClass = computed(() =>
  props.appearance === 'dark'
    ? 'rounded-md border border-rose-500/40 bg-rose-950/30 px-3 py-2 text-sm text-rose-200'
    : 'rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700'
);

const selectedRoleClass = computed(() =>
  props.appearance === 'dark'
    ? 'ring-2 ring-indigo-500 ring-offset-1 ring-offset-slate-900 bg-indigo-600/90 text-white'
    : 'ring-2 ring-indigo-500 ring-offset-1 ring-offset-white bg-indigo-600 text-white'
);

const cancelButtonClass = computed(() =>
  props.appearance === 'dark'
    ? 'rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-200 transition hover:bg-slate-800 disabled:opacity-60'
    : 'rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-100 disabled:opacity-60'
);

const submitButtonClass = computed(() =>
  props.appearance === 'dark'
    ? 'inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 disabled:opacity-60'
    : 'inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500 disabled:opacity-60'
);

const resetForm = () => {
  form.nome_razao_social = '';
  form.tipo_pessoa = 'Fisica';
  form.cpf_cnpj = '';
  form.email = '';
  form.telefone = '';
  form.papeis = [...initialSelectedRoles.value];
  formError.value = '';
  Object.keys(errors).forEach((key) => {
    delete errors[key];
  });
};

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      resetForm();
    } else {
      Object.keys(errors).forEach((key) => {
        delete errors[key];
      });
      formError.value = '';
      submitting.value = false;
    }
  }
);

watch(
  () => props.roles,
  () => {
    if (props.show) {
      form.papeis = [...initialSelectedRoles.value];
    }
  },
  { deep: true }
);

const close = () => {
  if (submitting.value) return;
  emit('close');
};

const toggleRole = (role: PessoaRole) => {
  if (form.papeis.includes(role)) {
    form.papeis = form.papeis.filter((item) => item !== role);
  } else {
    form.papeis = [...form.papeis, role];
  }
};

const hasRole = (role: PessoaRole) => form.papeis.includes(role);

const submit = async () => {
  if (submitting.value) return;

  submitting.value = true;
  formError.value = '';
  Object.keys(errors).forEach((key) => {
    delete errors[key];
  });

  try {
    if (!form.papeis.length) {
      formError.value = 'Selecione pelo menos um papel para o cadastro.';
      submitting.value = false;
      return;
    }

    const payload = {
      nome_razao_social: form.nome_razao_social,
      tipo_pessoa: form.tipo_pessoa,
      cpf_cnpj: form.cpf_cnpj || null,
      email: form.email || null,
      telefone: form.telefone || null,
      papeis: form.papeis,
    };

    const response = await axios.post('/api/pessoas', payload);
    const resource = response.data?.data ?? response.data ?? {};

    const mapped = {
      id: Number(resource.id ?? 0),
      nome: String(resource.nome ?? resource.nome_razao_social ?? form.nome_razao_social),
      papeis: Array.isArray(resource.papeis) ? (resource.papeis.filter((role: unknown): role is PessoaRole => typeof role === 'string') as PessoaRole[]) : form.papeis,
    };

    toast.success('Pessoa cadastrada com sucesso.');
    emit('created', mapped);
    resetForm();
  } catch (error: any) {
    const status = error?.response?.status;
    if (status === 422) {
      const validationErrors = error?.response?.data?.errors ?? {};
      Object.entries(validationErrors).forEach(([key, messages]) => {
        errors[key] = Array.isArray(messages) ? String(messages[0]) : String(messages);
      });
      formError.value =
        error?.response?.data?.message ?? 'Revise os campos destacados e tente novamente.';
      return;
    }

    const message =
      error?.response?.data?.message ?? 'Não foi possível cadastrar a pessoa. Tente novamente.';
    formError.value = message;
    toast.error(message);
  } finally {
    submitting.value = false;
  }
};
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="show"
        class="fixed inset-0 z-[1200] flex items-center justify-center bg-slate-950/80 px-3 py-6 backdrop-blur"
        @keydown.esc.prevent.stop="close"
        @click.self="close"
      >
        <div
          class="relative w-full max-w-2xl overflow-hidden rounded-2xl shadow-2xl shadow-black/40"
          :class="appearanceClasses.panel"
        >
          <header :class="['flex items-start justify-between border-b px-6 py-4', dividerClass]">
            <div>
              <h2 class="text-lg font-semibold">Nova pessoa / empresa</h2>
              <p class="text-xs opacity-70">
                Preencha os dados essenciais para continuar lançando sem sair desta tela.
              </p>
            </div>
            <button
              type="button"
              class="rounded-md p-2 text-slate-400 transition hover:text-white"
              @click.stop="close"
            >
              <span class="sr-only">Fechar</span>
              <svg
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
              </svg>
            </button>
          </header>

          <section class="max-h-[75vh] overflow-y-auto px-6 py-5 space-y-5">
            <div v-if="formError" :class="errorBoxClass">
              {{ formError }}
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="md:col-span-2 flex flex-col gap-1.5">
                <label class="text-sm font-medium" :class="appearanceClasses.label">
                  Nome / Razão social *
                </label>
                <input
                  v-model="form.nome_razao_social"
                  type="text"
                  :class="appearanceClasses.input"
                  placeholder="Ex.: Maria Silva / ACME LTDA"
                  :disabled="submitting"
                />
                <span v-if="errors.nome_razao_social" :class="appearanceClasses.error">
                  {{ errors.nome_razao_social }}
                </span>
              </div>

              <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium" :class="appearanceClasses.label">
                  Tipo de pessoa *
                </label>
                <div class="inline-flex rounded-lg border border-white/10 bg-white/5 text-xs">
                  <button
                    type="button"
                    class="px-3 py-1.5 transition"
                    :class="[
                      form.tipo_pessoa === 'Fisica'
                        ? 'bg-indigo-600 text-white shadow'
                        : 'text-slate-300 hover:bg-white/10',
                    ]"
                    @click="form.tipo_pessoa = 'Fisica'"
                    :disabled="submitting"
                  >
                    Pessoa física
                  </button>
                  <button
                    type="button"
                    class="px-3 py-1.5 transition"
                    :class="[
                      form.tipo_pessoa === 'Juridica'
                        ? 'bg-indigo-600 text-white shadow'
                        : 'text-slate-300 hover:bg-white/10',
                    ]"
                    @click="form.tipo_pessoa = 'Juridica'"
                    :disabled="submitting"
                  >
                    Pessoa jurídica
                  </button>
                </div>
              </div>

              <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium" :class="appearanceClasses.label">
                  CPF / CNPJ
                </label>
                <input
                  v-model="form.cpf_cnpj"
                  type="text"
                  inputmode="numeric"
                  maxlength="18"
                  :class="appearanceClasses.input"
                  placeholder="Somente números"
                  :disabled="submitting"
                />
                <span v-if="errors.cpf_cnpj" :class="appearanceClasses.error">
                  {{ errors.cpf_cnpj }}
                </span>
              </div>

              <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium" :class="appearanceClasses.label">E-mail</label>
                <input
                  v-model="form.email"
                  type="email"
                  :class="appearanceClasses.input"
                  placeholder="email@exemplo.com"
                  :disabled="submitting"
                />
                <span v-if="errors.email" :class="appearanceClasses.error">
                  {{ errors.email }}
                </span>
              </div>

              <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium" :class="appearanceClasses.label">Telefone</label>
                <input
                  v-model="form.telefone"
                  type="tel"
                  :class="appearanceClasses.input"
                  placeholder="(00) 00000-0000"
                  :disabled="submitting"
                />
                <span v-if="errors.telefone" :class="appearanceClasses.error">
                  {{ errors.telefone }}
                </span>
              </div>

              <div class="md:col-span-2 flex flex-col gap-2">
                <label class="text-sm font-medium" :class="appearanceClasses.label">
                  Papel no sistema *
                </label>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="role in availableRoles"
                    :key="role"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium transition"
                    :class="[
                      appearanceClasses.badge,
                      hasRole(role) ? selectedRoleClass : '',
                      'cursor-pointer hover:opacity-90',
                    ]"
                    @click="toggleRole(role)"
                    :disabled="submitting"
                  >
                    <span
                      class="h-2 w-2 rounded-full"
                      :class="hasRole(role) ? 'bg-emerald-300' : 'bg-slate-500'"
                    ></span>
                    {{ role }}
                  </button>
                </div>
                <p :class="appearanceClasses.hint">
                  Se necessário, marque os papéis que a pessoa/empresa deve receber.
                </p>
                <span v-if="errors.papeis" :class="appearanceClasses.error">
                  {{ errors.papeis }}
                </span>
              </div>
            </div>
          </section>

          <footer :class="['flex items-center justify-end gap-2 border-t px-6 py-4', dividerClass]">
            <button
              type="button"
              :class="cancelButtonClass"
              @click="close"
              :disabled="submitting"
            >
              Cancelar
            </button>
            <button
              type="button"
              :class="submitButtonClass"
              @click="submit"
              :disabled="submitting"
            >
              <svg
                v-if="submitting"
                class="h-4 w-4 animate-spin"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M12 4v2" />
                <path d="M18.364 5.636l-1.414 1.414" />
                <path d="M20 12h-2" />
                <path d="M18.364 18.364l-1.414-1.414" />
                <path d="M12 20v-2" />
                <path d="M5.636 18.364l1.414-1.414" />
                <path d="M4 12h2" />
                <path d="M5.636 5.636l1.414 1.414" />
              </svg>
              <span>{{ submitting ? 'Salvando...' : 'Salvar e usar' }}</span>
            </button>
          </footer>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
