<script setup lang="ts">
import { computed, ref, watch } from 'vue';

const props = defineProps<{
  modelValue: string | number | null;
  name?: string;
  id?: string;
  label?: string;
  placeholder?: string;
  disabled?: boolean;
  required?: boolean;
  inputClass?: string;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | null): void;
  (e: 'blur', value: FocusEvent): void;
  (e: 'focus', value: FocusEvent): void;
}>();

const inputRef = ref<HTMLInputElement | null>(null);
const locale = 'pt-BR';
const currency = 'BRL';

const formatter = computed(
  () => new Intl.NumberFormat(locale, { style: 'currency', currency, minimumFractionDigits: 2 })
);

const displayValue = ref('');

const toNumber = (value: string | number | null): number | null => {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  if (typeof value === 'number') {
    return value;
  }

  const raw = String(value).trim();
  if (!raw) {
    return null;
  }

  const cleaned = raw.replace(/[^0-9,\.\-]/g, '');
  if (!cleaned) {
    return null;
  }

  const hasComma = cleaned.includes(',');
  const hasDot = cleaned.includes('.');
  let normalized = cleaned;

  if (hasComma && hasDot) {
    normalized =
      cleaned.lastIndexOf(',') > cleaned.lastIndexOf('.')
        ? cleaned.replace(/\./g, '').replace(',', '.')
        : cleaned.replace(/,/g, '');
  } else if (hasComma) {
    normalized = cleaned.replace(/\./g, '').replace(',', '.');
  } else if (hasDot) {
    normalized = cleaned.replace(/,/g, '');
  }

  const parsed = Number.parseFloat(normalized);

  return Number.isNaN(parsed) ? null : parsed;
};

const syncDisplay = (value: string | number | null) => {
  const numeric = toNumber(value);
  displayValue.value = numeric === null ? '' : formatter.value.format(numeric);
};

watch(
  () => props.modelValue,
  (value) => {
    syncDisplay(value ?? null);
  },
  { immediate: true }
);

const updateValue = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const digitsOnly = target.value.replace(/[^0-9]/g, '');

  if (!digitsOnly) {
    displayValue.value = '';
    emit('update:modelValue', null);
    return;
  }

  const numeric = Number.parseInt(digitsOnly, 10) / 100;
  displayValue.value = formatter.value.format(numeric);
  emit('update:modelValue', numeric.toFixed(2));
};

const handleBlur = (event: FocusEvent) => {
  emit('blur', event);
};

const handleFocus = (event: FocusEvent) => {
  const target = event.target as HTMLInputElement;
  if (!target.value) {
    target.select();
  }
  emit('focus', event);
};
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label
      v-if="props.label"
      :for="props.id ?? props.name"
      class="text-sm font-medium text-slate-700"
    >
      {{ props.label }}
    </label>
    <input
      ref="inputRef"
      :id="props.id ?? props.name"
      type="text"
      inputmode="decimal"
      autocomplete="off"
      :name="props.name"
      :placeholder="props.placeholder ?? '0,00'"
      :value="displayValue"
      :disabled="props.disabled"
      :required="props.required"
      :class="[
        'w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:border-indigo-500 focus:ring-indigo-500',
        props.inputClass ?? 'border-slate-300 bg-white text-slate-900',
      ]"
      @input="updateValue"
      @blur="handleBlur"
      @focus="handleFocus"
    />
  </div>
</template>
