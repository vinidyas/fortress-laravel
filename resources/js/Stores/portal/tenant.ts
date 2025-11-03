import { defineStore } from 'pinia';
import { ref } from 'vue';

export type TenantProfile = {
  id: number;
  nome: string;
  cpf_cnpj: string;
  email?: string | null;
};

export const useTenantStore = defineStore('portalTenant', () => {
  const profile = ref<TenantProfile | null>(null);

  function setProfile(data: TenantProfile | null) {
    profile.value = data;
  }

  return {
    profile,
    setProfile,
  };
});
