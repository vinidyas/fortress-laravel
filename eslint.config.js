import vueParser from 'vue-eslint-parser';
import vue from 'eslint-plugin-vue';
import ts from '@typescript-eslint/eslint-plugin';
import tsParser from '@typescript-eslint/parser';

export default [
  {
    ignores: [
      'vendor/**',
      'node_modules/**',
      'public/build/**',
      'resources/js/**/dist/**',
      'resources/js/**/.vite/**',
      'storage/**',
    ],
  },
  {
    files: ['resources/js/**/*.{js,ts,vue}'],
    languageOptions: {
      parser: vueParser,
      parserOptions: {
        parser: tsParser,
        ecmaVersion: 'latest',
        sourceType: 'module',
        extraFileExtensions: ['.vue'],
      },
    },
    plugins: {
      vue,
      '@typescript-eslint': ts,
    },
    rules: {
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'vue/multi-word-component-names': 'off',
      '@typescript-eslint/no-explicit-any': 'off',
    },
  },
];
