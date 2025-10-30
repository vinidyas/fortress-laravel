<script setup lang="ts">
import { computed } from 'vue';
import Flatpickr from 'vue-flatpickr-component';
import { Portuguese } from 'flatpickr/dist/l10n/pt.js';
import type { Options } from 'flatpickr/dist/types/options';
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect';

import 'flatpickr/dist/flatpickr.css';
import 'flatpickr/dist/plugins/monthSelect/style.css';

type PickerMode = 'date' | 'month';

const props = withDefaults(
  defineProps<{
    modelValue: string | null;
    id?: string;
    name?: string;
    mode?: PickerMode;
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
    invalid?: boolean;
    appearance?: 'dark' | 'light';
  }>(),
  {
    modelValue: '',
    mode: 'date',
    placeholder: undefined,
    disabled: false,
    required: false,
    invalid: false,
    appearance: 'dark',
  },
);

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'blur'): void;
  (e: 'focus'): void;
}>();

const model = computed({
  get: () => props.modelValue ?? '',
  set: (value: string) => {
    emit('update:modelValue', value ?? '');
  },
});

const isLight = computed(() => props.appearance === 'light');

const baseClasses = computed(() =>
  [
    'w-full rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1',
    isLight.value
      ? 'border border-slate-300 bg-white text-slate-900 focus:border-indigo-500 focus:ring-indigo-500'
      : 'border border-slate-700 bg-slate-900 text-white focus:border-indigo-500 focus:ring-indigo-500',
  ].join(' ')
);

const altInputClasses = computed(() =>
  [
    'w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1',
    props.invalid
      ? isLight.value
        ? 'border-rose-500 bg-rose-50 text-rose-700 focus:border-rose-500 focus:ring-rose-400/40'
        : 'border-rose-500 bg-rose-950/40 text-rose-100 focus:border-rose-400 focus:ring-rose-400/40'
      : isLight.value
        ? 'border-slate-300 bg-white text-slate-900 focus:border-indigo-500 focus:ring-indigo-500'
        : 'border-slate-700 bg-slate-900 text-white focus:border-indigo-500 focus:ring-indigo-500',
  ].join(' '),
);

const config = computed<Partial<Options>>(() => {
  const options: Partial<Options> = {
    altInput: true,
    allowInput: true,
    locale: Portuguese,
    disableMobile: true,
    clickOpens: true,
    dateFormat: props.mode === 'month' ? 'Y-m' : 'Y-m-d',
    altFormat: props.mode === 'month' ? 'm/Y' : 'd/m/Y',
    altInputClass: altInputClasses.value,
    onOpen: () => emit('focus'),
    onClose: () => emit('blur'),
  };

  if (props.mode === 'month') {
    options.plugins = [
      monthSelectPlugin({
        shorthand: false,
        dateFormat: 'Y-m',
        altFormat: 'm/Y',
      }),
    ];
  }

  return options;
});
</script>

<template>
  <Flatpickr
    v-model="model"
    :id="id"
    :name="name"
    :config="config"
    :placeholder="placeholder"
    :disabled="disabled"
    :required="required"
    class="flatpickr-input"
    :class="baseClasses"
  />
</template>
