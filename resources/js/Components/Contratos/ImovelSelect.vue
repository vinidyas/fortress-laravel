<script setup lang="ts">
import axios from '@/bootstrap';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

type ImovelOption = {
  id: number;
  codigo: string;
  descricao: string;
  cidade: string | null;
  bairro: string | null;
  disponibilidade: string | null;
};

const props = withDefaults(
  defineProps<{
    modelValue: number | null;
    disabled?: boolean;
    required?: boolean;
    error?: string | null;
    placeholder?: string;
    label?: string;
  }>(),
  {
    modelValue: null,
    disabled: false,
    required: false,
    error: null,
    placeholder: 'Buscar por código, cidade, bairro…',
    label: 'Imóvel',
  },
);

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | null): void;
}>();

const state = reactive({
  searchTerm: '',
  options: [] as ImovelOption[],
  open: false,
  loading: false,
  loadedOnce: false,
  errorMessage: '',
  highlightedIndex: -1,
});

const selectedOption = ref<ImovelOption | null>(null);
const skipNextSearch = ref(false);
let searchDebounce: ReturnType<typeof setTimeout> | null = null;
let closeTimeout: ReturnType<typeof setTimeout> | null = null;

const hasError = computed(() => Boolean(props.error ?? state.errorMessage));
const helperMessage = computed(() => props.error ?? state.errorMessage);

const formatOption = (option: ImovelOption): string => option.descricao;

const toOption = (payload: unknown): ImovelOption | null => {
  if (!payload || typeof payload !== 'object') return null;
  const source = payload as Record<string, any>;
  const condominioNome = source.condominio?.nome ?? 'Sem condomínio';
  const complemento = source.enderecos?.complemento ?? '';
  const descricao =
    complemento && complemento.trim() !== ''
      ? `${condominioNome} — ${complemento}`
      : condominioNome;
  return {
    id: Number(source.id),
    codigo: String(source.codigo ?? ''),
    descricao,
    cidade: source.enderecos?.cidade ?? null,
    bairro: source.enderecos?.bairro ?? null,
    disponibilidade: source.disponibilidade ?? null,
  };
};

const fetchOptions = async (term: string) => {
  state.loading = true;
  state.errorMessage = '';
  try {
    const params: Record<string, unknown> = { per_page: 10 };
    if (term.trim() !== '') params['filter[search]'] = term.trim();
    const { data } = await axios.get('/api/imoveis', { params });
    const items = Array.isArray(data?.data) ? data.data : [];
    state.options = items
      .map(toOption)
      .filter((option): option is ImovelOption => Boolean(option?.id && option?.codigo));
    state.highlightedIndex = state.options.length > 0 ? 0 : -1;
    state.loadedOnce = true;
  } catch (error) {
    console.error(error);
    state.errorMessage = 'Não foi possível carregar os imóveis. Tente novamente.';
    state.options = [];
    state.highlightedIndex = -1;
  } finally {
    state.loading = false;
  }
};

const fetchById = async (id: number) => {
  try {
    const { data } = await axios.get(`/api/imoveis/${id}`);
    const option = toOption(data?.data ?? data);
    if (option) {
      selectedOption.value = option;
      skipNextSearch.value = true;
      state.searchTerm = formatOption(option);
    }
  } catch (error) {
    console.error(error);
    state.errorMessage = 'Não foi possível carregar o imóvel selecionado.';
    selectedOption.value = null;
    state.searchTerm = '';
  }
};

const selectOption = (option: ImovelOption | null) => {
  selectedOption.value = option;
  emit('update:modelValue', option?.id ?? null);
  skipNextSearch.value = true;
  state.searchTerm = option ? formatOption(option) : '';
  state.open = false;
  state.options = [];
  state.highlightedIndex = -1;
};

const clearSelection = () => {
  selectOption(null);
  nextTick(() => {
    state.open = true;
    void fetchOptions('');
  });
};

const openDropdown = () => {
  if (props.disabled) return;
  state.open = true;
  state.highlightedIndex = state.options.length > 0 ? 0 : -1;
  if (!state.loadedOnce) {
    void fetchOptions('');
  }
};

const closeDropdown = () => {
  state.open = false;
  state.highlightedIndex = -1;
};

const handleInput = () => {
  if (skipNextSearch.value) {
    skipNextSearch.value = false;
    return;
  }

  if (!state.open) {
    openDropdown();
  }

  if (searchDebounce) {
    clearTimeout(searchDebounce);
  }

  searchDebounce = setTimeout(() => {
    void fetchOptions(state.searchTerm);
  }, 250);
};

const handleFocus = () => {
  openDropdown();
  nextTick(() => {
    (inputRef.value as HTMLInputElement | null)?.select?.();
  });
};

const handleBlur = () => {
  if (closeTimeout) {
    clearTimeout(closeTimeout);
  }
  closeTimeout = setTimeout(() => {
    closeDropdown();
    if (!selectedOption.value && state.searchTerm !== '') {
      skipNextSearch.value = true;
      state.searchTerm = '';
    }
  }, 120);
};

const handleKeydown = (event: KeyboardEvent) => {
  if (props.disabled) return;

  if (!state.open && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
    event.preventDefault();
    openDropdown();
    return;
  }

  if (!state.open) return;

  if (event.key === 'ArrowDown') {
    event.preventDefault();
    if (state.options.length === 0) return;
    state.highlightedIndex = (state.highlightedIndex + 1) % state.options.length;
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    if (state.options.length === 0) return;
    state.highlightedIndex =
      state.highlightedIndex <= 0 ? state.options.length - 1 : state.highlightedIndex - 1;
  } else if (event.key === 'Enter') {
    event.preventDefault();
    if (state.highlightedIndex >= 0 && state.highlightedIndex < state.options.length) {
      selectOption(state.options[state.highlightedIndex]);
    }
  } else if (event.key === 'Escape') {
    event.preventDefault();
    closeDropdown();
  }
};

const handleOptionClick = (option: ImovelOption) => {
  if (closeTimeout) {
    clearTimeout(closeTimeout);
  }
  selectOption(option);
};

const inputRef = ref<HTMLInputElement | null>(null);

watch(
  () => props.modelValue,
  (value) => {
    if (value === null || value === undefined) {
      selectOption(null);
      return;
    }

    const numericValue = Number(value);
    if (Number.isNaN(numericValue)) {
      selectOption(null);
      return;
    }

    if (selectedOption.value?.id === numericValue) {
      skipNextSearch.value = true;
      state.searchTerm = formatOption(selectedOption.value);
      return;
    }

    void fetchById(numericValue);
  },
  { immediate: true },
);

onMounted(() => {
  if (!props.modelValue) {
    skipNextSearch.value = true;
    state.searchTerm = '';
  }
});

onBeforeUnmount(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
  if (closeTimeout) clearTimeout(closeTimeout);
});
</script>

<template>
  <div class="flex flex-col gap-1">
    <label class="text-sm font-medium text-slate-200">
      {{ label }}
      <span v-if="required" class="text-rose-300">*</span>
    </label>

    <div class="relative">
      <input
        ref="inputRef"
        v-model="state.searchTerm"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required && !selectedOption"
        class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-70"
        :class="hasError ? 'border-rose-500 focus:border-rose-400 focus:ring-rose-400/40' : ''"
        autocomplete="off"
        @input="handleInput"
        @focus="handleFocus"
        @blur="handleBlur"
        @keydown="handleKeydown"
      />

      <button
        v-if="!disabled && selectedOption"
        type="button"
        class="absolute inset-y-0 right-9 flex items-center px-2 text-xs text-slate-400 transition hover:text-white"
        @mousedown.prevent
        @click="clearSelection"
      >
        Limpar
      </button>
      <span
        class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400"
      >
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="m6 8 4 4 4-4" />
        </svg>
      </span>

      <transition name="fade">
        <div
          v-if="state.open"
          class="absolute z-20 mt-1 w-full rounded-lg border border-slate-700 bg-slate-900/95 text-sm text-slate-100 shadow-lg backdrop-blur"
        >
          <div v-if="state.loading" class="px-3 py-2 text-xs text-slate-400">
            Carregando imóveis...
          </div>
          <div
            v-else-if="state.options.length === 0"
            class="px-3 py-2 text-xs text-slate-400"
          >
            {{ state.errorMessage || 'Nenhum imóvel encontrado.' }}
          </div>
          <ul v-else class="max-h-60 overflow-y-auto py-1">
            <li
              v-for="(option, index) in state.options"
              :key="option.id"
              class="cursor-pointer px-3 py-2 transition"
              :class="[
                index === state.highlightedIndex
                  ? 'bg-indigo-600/30 text-white'
                  : 'hover:bg-slate-800/80',
              ]"
              @mousedown.prevent
              @click="handleOptionClick(option)"
            >
              <div class="font-semibold text-slate-100">{{ option.descricao }}</div>
              <div class="text-xs text-slate-400">
                <span class="font-semibold text-indigo-300">{{ option.codigo }}</span>
                <span v-if="option.cidade || option.bairro"> — </span>
                <span v-if="option.cidade">{{ option.cidade }}</span>
                <span v-if="option.cidade && option.bairro"> • </span>
                <span v-if="option.bairro">{{ option.bairro }}</span>
              </div>
              <div v-if="option.disponibilidade" class="text-[11px] uppercase text-slate-500">
                {{ option.disponibilidade }}
              </div>
            </li>
          </ul>
        </div>
      </transition>
    </div>

    <p v-if="hasError" class="text-xs text-rose-400">
      {{ helperMessage }}
    </p>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.12s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
