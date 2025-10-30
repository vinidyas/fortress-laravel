<script setup lang="ts">
import axios from '@/bootstrap';
import { useToast } from '@/composables/useToast';
import { computed, reactive, ref, watch, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { lookupCep, normalizeCep } from '@/utils/cep';
import type { PageProps } from '@/types/page';

type Nullable<T> = T | null;

type ModalMode = 'create' | 'edit';

type ImovelFormDraft = {
  form: ImovelPayload;
  condominioSearchTerm: string;
  proprietarioSearchTerm: string;
};
export type { ImovelFormDraft };

type Props = {
  mode: ModalMode;
  imovelId?: number | null;
  draft?: ImovelFormDraft | null;
};

type ImovelPayload = {
  codigo: string;
  proprietario_id: Nullable<number>;
  agenciador_id: Nullable<number>;
  responsavel_id: Nullable<number>;
  tipo_imovel: string;
  finalidade: string[];
  disponibilidade: string;
  cep: string;
  estado: string;
  cidade: string;
  bairro: string;
  rua: string;
  condominio_id: Nullable<number>;
  numero: string;
  complemento: string;
  valor_locacao: string;
  valor_condominio: string;
  condominio_isento: boolean;
  valor_iptu: string;
  iptu_isento: boolean;
  outros_valores: string;
  outros_isento: boolean;
  periodo_iptu: string;
  dormitorios: Nullable<number>;
  suites: Nullable<number>;
  banheiros: Nullable<number>;
  vagas_garagem: Nullable<number>;
  area_total: string;
  area_construida: string;
  comodidades: string[];
};

type AttachmentUploader = {
  id: number;
  name: string;
} | null;

type ExistingAttachment = {
  id: number;
  displayName: string;
  originalName: string;
  mimeType: string | null;
  uploadedAt: string | null;
  uploadedBy: AttachmentUploader;
  url: string;
  markedForRemoval: boolean;
  initialDisplayName: string;
};

type NewAttachment = {
  id: string;
  file: File;
  displayName: string;
  mimeType: string;
  uploadedAt: string;
  uploadedByName: string;
};

type ExistingPhotoItem = {
  kind: 'existing';
  id: number;
  legenda: string;
  initialLegenda: string;
  originalName: string;
  mimeType: string | null;
  url: string;
  thumbnailUrl: string;
  size: number | null;
  ordem: number;
  width: number | null;
  height: number | null;
  markedForRemoval: boolean;
};

type NewPhotoItem = {
  kind: 'new';
  id: string;
  file: File;
  legenda: string;
  previewUrl: string;
};

type PhotoItem = ExistingPhotoItem | NewPhotoItem;

type PhotoPreview = {
  url: string;
  legenda: string | null;
  originalName: string;
};

const props = defineProps<Props>();
const emit = defineEmits<{
  (e: 'saved', id?: number): void;
  (e: 'cancel'): void;
  (e: 'request-create-condominio', draft: ImovelFormDraft): void;
}>();

const page = usePage<PageProps>();
const toast = useToast();
const isEditing = computed(() => props.mode === 'edit' && Boolean(props.imovelId));
const loading = ref(isEditing.value);
const saving = ref(false);
const errorMessage = ref('');
const cepLoading = ref(false);
const condominioLoading = ref(false);
const condominiosLoaded = ref(false);

const finalidadeOptions = ['Locacao', 'Venda'];
const disponibilidadeOptions = ['Disponivel', 'Indisponivel'];
const periodoOptions = ['Mensal', 'Anual'];
const tipoImovelOptions = ref<string[]>([
  'Apartamento',
  'Casa',
  'Flat',
  'Galpão',
  'Loft',
  'Sala Comercial',
  'Studio',
  'Sítio',
  'Terreno',
  'Terreno Comercial',
  'Triplex',
  'Área Rural',
  'Apartamento Garden',
  'Box',
  'Campo',
  'Casa Comercial',
  'Casa de Condomínio',
  'Chácara',
  'Cobertura',
  'Conjunto Comercial',
  'Duplex',
  'Fazenda',
  'Geminado',
  'Haras',
  'Hotel',
  'Kitnet',
  'Loja',
  'Pavilhão',
  'Ponto Comercial',
  'Pousada',
  'Prédio Comercial',
  'Prédio Residencial',
  'Salão comercial',
  'Sobrado',
]);
const condominioOptions = ref<Array<{ id: number; nome: string }>>([]);
const condominioSearchTerm = ref('');
const condominioDropdownOpen = ref(false);
let condominioSearchTimeout: ReturnType<typeof setTimeout> | null = null;
const condominioSearchHasResults = ref(true);
const proprietarioOptions = ref<Array<{ id: number; nome: string }>>([]);
const proprietarioSearchTerm = ref('');
const proprietarioDropdownOpen = ref(false);
const proprietarioLoading = ref(false);
const proprietarioSearchHasResults = ref(true);
let proprietarioSearchTimeout: ReturnType<typeof setTimeout> | null = null;
const existingAttachments = ref<ExistingAttachment[]>([]);
const newAttachments = ref<NewAttachment[]>([]);
const fileInputRef = ref<HTMLInputElement | null>(null);
const photoItems = ref<PhotoItem[]>([]);
const photoFileInputRef = ref<HTMLInputElement | null>(null);
const photoPreview = ref<PhotoPreview | null>(null);
const downloadingPhotos = ref(false);
const maxPhotos = 15;
const maxPhotoSizeBytes = 5 * 1024 * 1024;
const currentUserName = computed(() => {
  const name = page.props.auth?.user?.name;
  return typeof name === 'string' && name.trim() !== '' ? name : 'Você';
});

function isExistingPhoto(item: PhotoItem): item is ExistingPhotoItem {
  return item.kind === 'existing';
}

function isNewPhoto(item: PhotoItem): item is NewPhotoItem {
  return item.kind === 'new';
}

const activePhotosCount = computed(() =>
  photoItems.value.filter((item) => !isExistingPhoto(item) || !item.markedForRemoval).length
);
const photosLimitReached = computed(() => activePhotosCount.value >= maxPhotos);
const hasAnyPhotos = computed(() => activePhotosCount.value > 0);
const canDownloadPhotos = computed(
  () => isEditing.value && photoItems.value.some((item) => isExistingPhoto(item) && !item.markedForRemoval)
);

const fieldLabels: Record<string, string> = {
  proprietario_id: 'Proprietário',
  tipo_imovel: 'Tipo de imóvel',
  finalidade: 'Finalidade',
  disponibilidade: 'Disponibilidade',
  numero: 'Número',
  periodo_iptu: 'Período do IPTU',
  agenciador_id: 'Agenciador',
  responsavel_id: 'Responsável',
  condominio_id: 'Condomínio',
  cep: 'CEP',
};

async function populateCondominioAddress(condominioId: number): Promise<void> {
  if (!condominioId) return;

  try {
    const { data } = await axios.get(`/api/condominios/${condominioId}`);
    const condominio = data?.data ?? {};

    form.cep = condominio.cep ?? '';
    form.estado = condominio.estado ?? '';
    form.cidade = condominio.cidade ?? '';
    form.bairro = condominio.bairro ?? '';
    form.rua = condominio.rua ?? condominio.logradouro ?? '';
    form.numero = condominio.numero ?? '';
    form.complemento = condominio.complemento ?? '';
  } catch (error) {
    console.error(error);
  }
}

function cloneImovelDraft(): ImovelFormDraft {
  return {
    form: JSON.parse(JSON.stringify(form)) as ImovelPayload,
    condominioSearchTerm: condominioSearchTerm.value,
    proprietarioSearchTerm: proprietarioSearchTerm.value,
  };
}

function applyDraft(draft: ImovelFormDraft): void {
  Object.assign(form, JSON.parse(JSON.stringify(draft.form)));
  condominioSearchTerm.value = draft.condominioSearchTerm ?? '';
  proprietarioSearchTerm.value = draft.proprietarioSearchTerm ?? '';
}

const comodidadesLista = [
  'Acessibilidade para PCD',
  'Acesso asfaltado',
  'Adega',
  'Alarme',
  'Ar condicionado',
  'Armário cozinha',
  'Churrasqueira',
  'Closet',
  'Cozinha',
  'Escritório',
  'Espaço gourmet',
  'Interfone',
  'Jardim',
  'Lavabo',
  'Móveis planejados',
  'Piscina',
  'Portão eletrônico',
  'Quintal',
  'Sala de estar',
  'Sala de jantar',
  'Salão de festas',
  'Varanda',
  'Vista panorâmica',
];

const form = reactive<ImovelPayload>({
  codigo: '',
  proprietario_id: null,
  agenciador_id: null,
  responsavel_id: null,
  tipo_imovel: '',
  finalidade: [],
  disponibilidade: 'Disponivel',
  cep: '',
  estado: '',
  cidade: '',
  bairro: '',
  rua: '',
  condominio_id: null,
  numero: '',
  complemento: '',
  valor_locacao: '',
  valor_condominio: '',
  condominio_isento: false,
  valor_iptu: '',
  iptu_isento: false,
  outros_valores: '',
  outros_isento: false,
  periodo_iptu: 'Mensal',
  dormitorios: null,
  suites: null,
  banheiros: null,
  vagas_garagem: null,
  area_total: '',
  area_construida: '',
  comodidades: [],
});


const inputClass =
  'w-full rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40';
const checkboxClass = 'rounded border-slate-600 bg-slate-800 text-indigo-500 focus:ring-indigo-500';

function resetForm(): void {
  form.codigo = '';
  form.proprietario_id = null;
  form.agenciador_id = null;
  form.responsavel_id = null;
  form.tipo_imovel = '';
  form.finalidade = [];
  form.disponibilidade = 'Disponivel';
  form.cep = '';
  form.estado = '';
  form.cidade = '';
  form.bairro = '';
  form.rua = '';
  form.condominio_id = null;
  form.numero = '';
  form.complemento = '';
  form.valor_locacao = '';
  form.valor_condominio = '';
  form.condominio_isento = false;
  form.valor_iptu = '';
  form.iptu_isento = false;
  form.outros_valores = '';
  form.outros_isento = false;
  form.periodo_iptu = 'Mensal';
  form.dormitorios = null;
  form.suites = null;
  form.banheiros = null;
  form.vagas_garagem = null;
  form.area_total = '';
  form.area_construida = '';
  form.comodidades = [];
  errorMessage.value = '';
  condominioSearchTerm.value = '';
  condominioDropdownOpen.value = false;
  condominioSearchHasResults.value = true;
  proprietarioSearchTerm.value = '';
  proprietarioDropdownOpen.value = false;
  proprietarioSearchHasResults.value = true;
  proprietarioOptions.value = [];
  existingAttachments.value = [];
  newAttachments.value = [];
  resetPhotos();
}

function normalizeDecimal(value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return '';
  }

  const str = String(value).trim();
  if (str === '') {
    return '';
  }

  // Remove currency symbols or spaces but keep separators
  const sanitized = str.replace(/[^\d.,-]/g, '');
  if (sanitized === '') {
    return '';
  }

  const lastComma = sanitized.lastIndexOf(',');
  const lastDot = sanitized.lastIndexOf('.');

  // Both comma and dot present: determine decimal separator by last occurrence
  if (lastComma !== -1 && lastDot !== -1) {
    if (lastComma > lastDot) {
      // Format like 1.234,56 -> remove thousand dots, swap decimal comma for dot
      return sanitized.replace(/\./g, '').replace(',', '.');
    }

    // Format like 1,234.56 -> remove thousand commas, keep decimal dot
    return sanitized.replace(/,/g, '');
  }

  if (lastComma !== -1) {
    // Only comma present -> treat as decimal separator
    return sanitized.replace(',', '.');
  }

  // Only dots (already decimal point or thousands without decimal)
  return sanitized;
}

async function loadImovel(id: number): Promise<void> {
  loading.value = true;
  errorMessage.value = '';

  try {
    const { data } = await axios.get(`/api/imoveis/${id}`);
    const payload = data.data;

    form.proprietario_id = payload.proprietario?.id ?? null;
    const selectedProprietarioName = payload.proprietario?.nome_razao_social ?? '';
    await fetchProprietarios(selectedProprietarioName);

    form.condominio_id = payload.condominio?.id ?? null;
    const selectedCondominioName = payload.condominio?.nome ?? '';
    await fetchCondominios(selectedCondominioName);

    form.codigo = payload.codigo ?? '';
    form.agenciador_id = payload.agenciador?.id ?? null;
    form.responsavel_id = payload.responsavel?.id ?? null;
    form.tipo_imovel = payload.tipo_imovel ?? '';
    if (form.tipo_imovel && !tipoImovelOptions.value.includes(form.tipo_imovel)) {
      tipoImovelOptions.value = [...tipoImovelOptions.value, form.tipo_imovel];
    }
    form.finalidade = Array.isArray(payload.finalidade) ? payload.finalidade : [];
    form.disponibilidade = payload.disponibilidade ?? 'Disponivel';
    form.cep = payload.enderecos?.cep ?? '';
    form.estado = payload.enderecos?.estado ?? '';
    form.cidade = payload.enderecos?.cidade ?? '';
    form.bairro = payload.enderecos?.bairro ?? '';
    form.rua = payload.enderecos?.rua ?? payload.enderecos?.logradouro ?? '';
    form.numero = payload.enderecos?.numero ?? '';
    form.complemento = payload.enderecos?.complemento ?? '';
    form.valor_locacao = normalizeDecimal(payload.valores?.valor_locacao);
    form.valor_condominio = normalizeDecimal(payload.valores?.valor_condominio);
    form.condominio_isento = Boolean(payload.valores?.condominio_isento);
    form.valor_iptu = normalizeDecimal(payload.valores?.valor_iptu);
    form.iptu_isento = Boolean(payload.valores?.iptu_isento);
    form.outros_valores = normalizeDecimal(payload.valores?.outros_valores);
    form.outros_isento = Boolean(payload.valores?.outros_isento);
    form.periodo_iptu = payload.valores?.periodo_iptu ?? 'Mensal';
    form.dormitorios = payload.caracteristicas?.dormitorios ?? null;
    form.suites = payload.caracteristicas?.suites ?? null;
    form.banheiros = payload.caracteristicas?.banheiros ?? null;
    form.vagas_garagem = payload.caracteristicas?.vagas_garagem ?? null;
    form.area_total = normalizeDecimal(payload.caracteristicas?.area_total);
    form.area_construida = normalizeDecimal(payload.caracteristicas?.area_construida);
    form.comodidades = Array.isArray(payload.caracteristicas?.comodidades)
      ? payload.caracteristicas.comodidades
      : [];
    existingAttachments.value = Array.isArray(payload.anexos)
      ? payload.anexos.map((attachment: any) => ({
          id: Number(attachment.id),
          displayName:
            typeof attachment.display_name === 'string' && attachment.display_name.trim() !== ''
              ? attachment.display_name
              : attachment.original_name ?? `Anexo ${attachment.id}`,
          originalName: attachment.original_name ?? `Anexo ${attachment.id}`,
          mimeType: attachment.mime_type ?? null,
          uploadedAt: attachment.uploaded_at ?? null,
          uploadedBy:
            attachment.uploaded_by && typeof attachment.uploaded_by === 'object'
              ? {
                  id: Number(attachment.uploaded_by.id),
                  name: attachment.uploaded_by.name ?? 'Usuário',
                }
              : null,
          url: attachment.url ?? '',
          markedForRemoval: false,
          initialDisplayName:
            typeof attachment.display_name === 'string' && attachment.display_name.trim() !== ''
              ? attachment.display_name
              : attachment.original_name ?? `Anexo ${attachment.id}`,
        }))
      : [];
    newAttachments.value = [];
    resetPhotos();
    photoItems.value = Array.isArray(payload.fotos)
      ? [...payload.fotos]
          .sort((a: any, b: any) => Number(a.ordem ?? 0) - Number(b.ordem ?? 0))
          .map(
            (foto: any): ExistingPhotoItem => ({
              kind: 'existing',
              id: Number(foto.id),
              legenda: typeof foto.legenda === 'string' ? foto.legenda : '',
              initialLegenda: typeof foto.legenda === 'string' ? foto.legenda : '',
              originalName: foto.original_name ?? `Foto ${foto.id}`,
              mimeType: foto.mime_type ?? null,
              url: foto.url ?? '',
              thumbnailUrl: foto.thumbnail_url ?? foto.url ?? '',
              size: typeof foto.size === 'number' ? foto.size : null,
              ordem: typeof foto.ordem === 'number' ? foto.ordem : 0,
              width: typeof foto.width === 'number' ? foto.width : null,
              height: typeof foto.height === 'number' ? foto.height : null,
              markedForRemoval: false,
            })
          )
      : [];
    proprietarioSearchTerm.value = selectedProprietarioName;
    proprietarioDropdownOpen.value = false;
    condominioSearchTerm.value = selectedCondominioName;
    condominioDropdownOpen.value = false;
  } catch (error) {
    console.error(error);
    errorMessage.value = 'Não foi possível carregar o imóvel.';
  } finally {
    loading.value = false;
  }
}

async function fetchProprietarios(searchTerm = ''): Promise<void> {
  const term = searchTerm.trim();

  proprietarioLoading.value = true;

  try {
    const params: Record<string, unknown> = {
      per_page: 20,
      'filter[papel]': 'Proprietario',
    };

    if (term.length > 0) {
      params['filter[search]'] = term;
    }

    const { data } = await axios.get('/api/pessoas', { params });

    const rows = Array.isArray(data?.data) ? data.data : [];
    const mapped = rows.map((item: { id: number; nome_razao_social?: string }) => ({
      id: item.id,
      nome: item.nome_razao_social ?? `Pessoa #${item.id}`,
    }));

    const unique = new Map<number, { id: number; nome: string }>();
    mapped.forEach((item) => unique.set(item.id, item));

    if (form.proprietario_id) {
      const currentName =
        proprietarioSearchTerm.value ||
        mapped.find((item) => item.id === form.proprietario_id)?.nome ||
        `Pessoa #${form.proprietario_id}`;

      unique.set(form.proprietario_id, {
        id: form.proprietario_id,
        nome: currentName,
      });
    }

    proprietarioOptions.value = Array.from(unique.values());
    proprietarioSearchHasResults.value = proprietarioOptions.value.length > 0;
  } catch (error) {
    console.error(error);
    proprietarioSearchHasResults.value = proprietarioOptions.value.length > 0;
    toast.error('Não foi possível carregar a lista de proprietários.');
  } finally {
    proprietarioLoading.value = false;
  }
}

function scheduleProprietarioSearch(value: string): void {
  proprietarioSearchTerm.value = value;
  proprietarioDropdownOpen.value = true;
  proprietarioSearchHasResults.value = true;

  if (value.trim() === '') {
    form.proprietario_id = null;
  }

  if (proprietarioSearchTimeout) {
    clearTimeout(proprietarioSearchTimeout);
  }

  proprietarioSearchTimeout = setTimeout(() => {
    const term = proprietarioSearchTerm.value.trim();
    if (term.length === 0) {
      void fetchProprietarios();
    } else {
      void fetchProprietarios(term);
    }
  }, 250);
}

async function openProprietarioDropdown(): Promise<void> {
  proprietarioDropdownOpen.value = true;

  if (proprietarioOptions.value.length === 0) {
    await fetchProprietarios(proprietarioSearchTerm.value);
  }
}

function closeProprietarioDropdown(): void {
  setTimeout(() => {
    proprietarioDropdownOpen.value = false;
  }, 150);
}

function selectProprietario(option: { id: number; nome: string }): void {
  form.proprietario_id = option.id;
  proprietarioSearchTerm.value = option.nome;
  proprietarioDropdownOpen.value = false;
}

function clearProprietarioSelection(): void {
  form.proprietario_id = null;
  proprietarioSearchTerm.value = '';
  void fetchProprietarios();
}

async function fetchCondominios(searchTerm = ''): Promise<void> {
  const term = searchTerm.trim();

  condominioLoading.value = true;

  try {
    const params: Record<string, unknown> = { per_page: 20 };
    if (term.length > 0) {
      params['filter[search]'] = term;
    }

    const { data } = await axios.get('/api/condominios', { params });

    const rows = Array.isArray(data?.data) ? data.data : [];
    const mapped = rows.map((item: { id: number; nome?: string }) => ({
      id: item.id,
      nome: item.nome ?? `Condomínio #${item.id}`,
    }));

    const unique = new Map<number, { id: number; nome: string }>();
    mapped.forEach((item) => unique.set(item.id, item));

    if (form.condominio_id) {
      const currentName =
        condominioSearchTerm.value ||
        mapped.find((item) => item.id === form.condominio_id)?.nome ||
        `Condomínio #${form.condominio_id}`;
      unique.set(form.condominio_id, {
        id: form.condominio_id,
        nome: currentName,
      });
    }

    condominioOptions.value = Array.from(unique.values());
    condominioSearchHasResults.value = condominioOptions.value.length > 0;
    condominiosLoaded.value = true;
  } catch (error) {
    console.error(error);
    condominioSearchHasResults.value = condominioOptions.value.length > 0;
    toast.error('Não foi possível carregar a lista de condomínios.');
  } finally {
    condominioLoading.value = false;
  }
}

function scheduleCondominioSearch(value: string): void {
  condominioSearchTerm.value = value;
  condominioDropdownOpen.value = true;
  condominioSearchHasResults.value = true;

  if (value.trim() === '') {
    form.condominio_id = null;
  }

  if (condominioSearchTimeout) {
    clearTimeout(condominioSearchTimeout);
  }

  condominioSearchTimeout = setTimeout(() => {
    const term = condominioSearchTerm.value.trim();
    if (term.length === 0) {
      fetchCondominios();
    } else {
      fetchCondominios(term);
    }
  }, 250);
}

async function openCondominioDropdown(): Promise<void> {
  condominioDropdownOpen.value = true;

  if (!condominiosLoaded.value) {
    await fetchCondominios(condominioSearchTerm.value);
  }
}

function closeCondominioDropdown(): void {
  setTimeout(() => {
    condominioDropdownOpen.value = false;
  }, 150);
}

function selectCondominio(option: { id: number; nome: string }): void {
  form.condominio_id = option.id;
  condominioSearchTerm.value = option.nome;
  condominioDropdownOpen.value = false;
  void populateCondominioAddress(option.id);
}

function clearCondominioSelection(): void {
  form.condominio_id = null;
  condominioSearchTerm.value = '';
  void fetchCondominios();
}

function goToCreateCondominio(): void {
  condominioDropdownOpen.value = false;
  emit('request-create-condominio', cloneImovelDraft());
}

function toggleComodidade(item: string, checked: boolean): void {
  if (checked) {
    if (!form.comodidades.includes(item)) {
      form.comodidades.push(item);
    }
  } else {
    form.comodidades = form.comodidades.filter((value) => value !== item);
  }
}

async function fetchGeneratedCodigo(): Promise<void> {
  try {
    const { data } = await axios.get('/api/imoveis/generate-codigo');
    form.codigo = data?.codigo ?? '';
  } catch (error) {
    console.error(error);
    errorMessage.value = 'Não foi possível gerar um código automático. Tente novamente mais tarde.';
  }
}

async function prepareCreateForm(): Promise<void> {
  loading.value = true;
  resetForm();
  let searchTerm = '';
  let proprietarioTerm = '';

  if (props.draft) {
    applyDraft(props.draft);
    searchTerm = props.draft.condominioSearchTerm ?? '';
    proprietarioTerm = props.draft.proprietarioSearchTerm ?? '';
  }

  await fetchProprietarios(proprietarioTerm);
  condominiosLoaded.value = false;
  await fetchCondominios(searchTerm);

  if (!props.draft?.form?.codigo) {
    await fetchGeneratedCodigo();
  } else {
    form.codigo = props.draft.form.codigo;
  }

  loading.value = false;
}

async function fetchCep(): Promise<void> {
  cepLoading.value = true;
  errorMessage.value = '';
  try {
    const cep = normalizeCep(form.cep);
    if (cep.length !== 8) {
      errorMessage.value = 'Informe um CEP válido com 8 dígitos.';
      return;
    }
    const data = await lookupCep(cep);
    if (!data) {
      errorMessage.value = 'CEP não encontrado.';
      return;
    }
    form.cep = data.cep;
    form.estado = data.uf || form.estado;
    form.cidade = data.cidade || form.cidade;
    form.bairro = (data.bairro ?? '') || form.bairro;
    if (data.logradouro) {
      form.rua = form.rua || data.logradouro;
    }
    if (data.complemento) form.complemento = data.complemento;
  } finally {
    cepLoading.value = false;
  }
}

function buildPayload(): Record<string, unknown> {
  return {
    ...form,
    finalidade: form.finalidade,
    comodidades: form.comodidades,
    proprietario_id: form.proprietario_id ?? null,
    agenciador_id: form.agenciador_id ?? null,
    responsavel_id: form.responsavel_id ?? null,
    condominio_id: form.condominio_id ?? null,
    logradouro: form.rua,
    dormitorios: form.dormitorios ?? null,
    suites: form.suites ?? null,
    banheiros: form.banheiros ?? null,
    vagas_garagem: form.vagas_garagem ?? null,
  };
}

function buildFormData(): FormData {
  const payload = buildPayload();
  const formData = new FormData();

  const normalizeFormValue = (value: unknown): string => {
    if (value === null || value === undefined) {
      return '';
    }

    if (typeof value === 'boolean') {
      return value ? '1' : '0';
    }

    if (typeof value === 'number' && Number.isNaN(value)) {
      return '';
    }

    return String(value);
  };

  const appendArray = (key: string, values: unknown[]) => {
    values.forEach((value, index) => {
      formData.append(`${key}[${index}]`, normalizeFormValue(value));
    });
  };

  Object.entries(payload).forEach(([key, value]) => {
    if (Array.isArray(value)) {
      appendArray(key, value);
      return;
    }

    formData.append(key, normalizeFormValue(value));
  });

  const photoOrderItems = photoItems.value.filter(
    (item) => !isExistingPhoto(item) || !item.markedForRemoval
  );

  photoOrderItems.forEach((item, index) => {
    const descriptor = isExistingPhoto(item) ? `existing:${item.id}` : `new:${item.id}`;
    formData.append(`fotos_ordem[${index}]`, descriptor);
  });

  photoItems.value
    .filter((item): item is ExistingPhotoItem => isExistingPhoto(item) && !item.markedForRemoval)
    .forEach((photo) => {
      const trimmed = photo.legenda.trim();
      const initial = photo.initialLegenda.trim();
      if (trimmed !== initial) {
        formData.append(`fotos_legendas_existentes[${photo.id}]`, trimmed);
      }
    });

  photoItems.value
    .filter((item): item is ExistingPhotoItem => isExistingPhoto(item) && item.markedForRemoval)
    .forEach((photo, index) => {
      formData.append(`fotos_remover[${index}]`, String(photo.id));
    });

  const newPhotoItems = photoItems.value.filter((item): item is NewPhotoItem => isNewPhoto(item));
  newPhotoItems.forEach((photo, index) => {
    formData.append(`fotos[${index}]`, photo.file, photo.file.name);
    formData.append(`fotos_ids[${index}]`, photo.id);
    formData.append(`fotos_legendas[${index}]`, photo.legenda.trim());
  });

  newAttachments.value.forEach((attachment, index) => {
    formData.append(`anexos[${index}]`, attachment.file, attachment.file.name);
    const label = (attachment.displayName ?? '').trim();
    formData.append(`anexos_legendas[${index}]`, label);
  });

  existingAttachments.value
    .filter((attachment) => !attachment.markedForRemoval)
    .forEach((attachment) => {
      const trimmed = (attachment.displayName ?? '').trim();
      const initial = (attachment.initialDisplayName ?? '').trim();
      if (trimmed !== initial) {
        formData.append(`anexos_legendas_existentes[${attachment.id}]`, trimmed);
      }
    });

  const attachmentsToRemove = existingAttachments.value.filter((attachment) => attachment.markedForRemoval);
  attachmentsToRemove.forEach((attachment, index) => {
    formData.append(`anexos_remover[${index}]`, String(attachment.id));
  });

  return formData;
}

function toggleFinalidade(option: string): void {
  if (form.finalidade.includes(option)) {
    form.finalidade = form.finalidade.filter((item) => item !== option);
  } else {
    form.finalidade.push(option);
  }
}

const hasAnyAttachments = computed(() => existingAttachments.value.length > 0 || newAttachments.value.length > 0);

function generateTemporaryId(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return crypto.randomUUID();
  }

  return `temp-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function formatAttachmentType(mimeType: string | null, name: string): string {
  if (mimeType && mimeType.includes('/')) {
    const subtype = mimeType.split('/')[1];
    if (subtype) {
      return subtype.toUpperCase();
    }
  }

  const segments = name.split('.');
  const extension = segments.length > 1 ? segments.pop() : null;
  return extension ? extension.toUpperCase() : 'Arquivo';
}

function formatAttachmentDate(value: string | null): string {
  if (!value) {
    return '-';
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '-';
  }

  return date.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function openFilePicker(): void {
  fileInputRef.value?.click();
}

function handleFilesSelected(event: Event): void {
  const input = event.target as HTMLInputElement;
  if (!input.files) {
    return;
  }

  const now = new Date().toISOString();
  const uploadedByName = currentUserName.value;

  Array.from(input.files).forEach((file) => {
    newAttachments.value.push({
      id: generateTemporaryId(),
      file,
      displayName: file.name,
      mimeType: file.type || '',
      uploadedAt: now,
      uploadedByName,
    });
  });

  input.value = '';
}

function removeNewAttachment(id: string): void {
  newAttachments.value = newAttachments.value.filter((attachment) => attachment.id !== id);
}

function toggleExistingAttachmentRemoval(attachment: ExistingAttachment): void {
  attachment.markedForRemoval = !attachment.markedForRemoval;
  if (attachment.markedForRemoval) {
    attachment.displayName = attachment.initialDisplayName;
  }
}

function cleanupPhotoPreviews(): void {
  photoItems.value.forEach((item) => {
    if (isNewPhoto(item)) {
      URL.revokeObjectURL(item.previewUrl);
    }
  });
}

function resetPhotos(): void {
  cleanupPhotoPreviews();
  photoItems.value = [];
  photoPreview.value = null;
}

function formatFileSize(bytes: number | null | undefined): string {
  if (!bytes || bytes <= 0) {
    return '-';
  }

  const units = ['B', 'KB', 'MB', 'GB'];
  let size = bytes;
  let unitIndex = 0;

  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex += 1;
  }

  const precision = size < 10 && unitIndex > 0 ? 1 : 0;
  return `${size.toFixed(precision)} ${units[unitIndex]}`;
}

function extractFilenameFromDisposition(disposition: unknown, fallback: string): string {
  if (typeof disposition !== 'string') {
    return fallback;
  }

  const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8Match?.[1]) {
    try {
      return decodeURIComponent(utf8Match[1]);
    } catch {
      return utf8Match[1];
    }
  }

  const asciiMatch = disposition.match(/filename="?([^\";]+)"?/i);
  if (asciiMatch?.[1]) {
    return asciiMatch[1];
  }

  return fallback;
}

function openPhotoPicker(): void {
  photoFileInputRef.value?.click();
}

function handlePhotoFilesSelected(event: Event): void {
  const input = event.target as HTMLInputElement;
  if (!input.files) {
    return;
  }

  const files = Array.from(input.files);
  input.value = '';

  const remainingSlots = maxPhotos - activePhotosCount.value;
  if (remainingSlots <= 0) {
    toast.error(`Limite de ${maxPhotos} fotos atingido.`);
    return;
  }

  if (files.length > remainingSlots) {
    toast.error(
      `Você só pode adicionar mais ${remainingSlots} foto${remainingSlots > 1 ? 's' : ''} neste imóvel.`
    );
  }

  files.slice(0, remainingSlots).forEach((file) => {
    if (!file.type.startsWith('image/')) {
      toast.error(`"${file.name}" não é uma imagem suportada e foi ignorada.`);
      return;
    }

    if (file.size > maxPhotoSizeBytes) {
      toast.error(`"${file.name}" excede o limite de 5MB e foi ignorada.`);
      return;
    }

    const previewUrl = URL.createObjectURL(file);
    const baseLegend = file.name.replace(/\.[^/.]+$/, '').trim();

    photoItems.value.push({
      kind: 'new',
      id: generateTemporaryId(),
      file,
      legenda: baseLegend,
      previewUrl,
    });
  });
}

function removeNewPhoto(id: string): void {
  const index = photoItems.value.findIndex((item) => item.kind === 'new' && item.id === id);
  if (index === -1) {
    return;
  }

  const [removed] = photoItems.value.splice(index, 1);
  if (removed && isNewPhoto(removed)) {
    URL.revokeObjectURL(removed.previewUrl);
  }
}

function toggleExistingPhotoRemovalFlag(photo: ExistingPhotoItem): void {
  photo.markedForRemoval = !photo.markedForRemoval;
  if (photo.markedForRemoval) {
    photo.legenda = photo.initialLegenda;
  }
}

function movePhotoUp(index: number): void {
  if (index <= 0) {
    return;
  }

  const items = photoItems.value;
  [items[index - 1], items[index]] = [items[index], items[index - 1]];
}

function movePhotoDown(index: number): void {
  if (index < 0 || index >= photoItems.value.length - 1) {
    return;
  }

  const items = photoItems.value;
  [items[index + 1], items[index]] = [items[index], items[index + 1]];
}

function openPhotoPreview(photo: PhotoItem): void {
  const url = isExistingPhoto(photo) ? photo.url : photo.previewUrl;
  const legenda = photo.legenda?.trim() ?? null;
  const original = isExistingPhoto(photo) ? photo.originalName : photo.file.name;

  photoPreview.value = {
    url,
    legenda: legenda && legenda !== '' ? legenda : null,
    originalName: original,
  };
}

function closePhotoPreview(): void {
  photoPreview.value = null;
}

async function downloadPhotosZip(): Promise<void> {
  if (!props.imovelId || downloadingPhotos.value) {
    return;
  }

  downloadingPhotos.value = true;
  try {
    const response = await axios.get(`/api/imoveis/${props.imovelId}/fotos/download`, {
      responseType: 'blob',
    });

    const blob = new Blob([response.data], { type: response.headers['content-type'] ?? 'application/zip' });
    const filename = extractFilenameFromDisposition(
      response.headers['content-disposition'],
      `imovel-${props.imovelId}-fotos.zip`
    );

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  } catch (error: any) {
    if (error?.response?.status === 404) {
      toast.info('Nenhuma foto disponível para download.');
    } else {
      toast.error('Não foi possível gerar o download das fotos.');
    }
  } finally {
    downloadingPhotos.value = false;
  }
}

async function submit(): Promise<void> {
  if (saving.value) return;
  saving.value = true;
  errorMessage.value = '';

  const formData = buildFormData();

  try {
    let response;
    if (isEditing.value && props.imovelId) {
      formData.append('_method', 'PUT');
      response = await axios.post(`/api/imoveis/${props.imovelId}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      toast.success('Imóvel atualizado com sucesso.');
      await loadImovel(props.imovelId);
    } else {
      response = await axios.post('/api/imoveis', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      toast.success('Imóvel criado com sucesso.');
      resetForm();
      await fetchGeneratedCodigo();
    }

    const createdId: number | undefined = response?.data?.data?.id;
    emit('saved', createdId ?? props.imovelId ?? undefined);
  } catch (error: any) {
    console.error(error);
    if (error?.response?.status === 422) {
      const validation = error.response.data?.errors ?? {};
      const normalizedMessages = Object.entries(validation).flatMap(([field, messages]) => {
        const label = fieldLabels[field] ?? field;
        const list = Array.isArray(messages) ? messages : [messages];

        return list
          .map((message) => {
            if (typeof message !== 'string') return null;
            if (message === 'validation.required') return `${label} é obrigatório.`;
            if (message === 'validation.array') return `${label} deve conter pelo menos um item.`;
            if (message === 'validation.exists') return `${label} informado é inválido.`;
            if (message === 'validation.integer') return `${label} precisa ser um número válido.`;
            if (message === 'validation.numeric') return `${label} precisa ser um valor numérico válido.`;
            if (message === 'validation.string') return `${label} precisa ser um texto válido.`;
            if (message === 'validation.max.file') return `${label} excede o tamanho máximo permitido.`;
            if (message === 'validation.mimes') return `${label} está em um formato inválido.`;

            return message;
          })
          .filter((value): value is string => Boolean(value));
      });

      errorMessage.value =
        normalizedMessages.join(' ') ||
        error.response.data?.message ||
        'Corrija os campos obrigatórios e tente novamente.';
      const firstMessage = normalizedMessages.length > 0 ? normalizedMessages[0] : errorMessage.value;
      toast.error(firstMessage);
      return;
    } else {
      errorMessage.value = error?.response?.data?.message ?? 'Não foi possível salvar o imóvel.';
    }
    toast.error(errorMessage.value || 'Não foi possível salvar o imóvel.');
  } finally {
    saving.value = false;
  }
}

watch(
  () => form.proprietario_id,
  (value) => {
    if (!value) {
      if (proprietarioSearchTerm.value !== '') {
        proprietarioSearchTerm.value = '';
      }
      return;
    }

    const option = proprietarioOptions.value.find((item) => item.id === value);
    if (option && option.nome !== proprietarioSearchTerm.value) {
      proprietarioSearchTerm.value = option.nome;
    }
  }
);

watch(
  () => form.condominio_id,
  (value) => {
    if (!value) {
      if (condominioSearchTerm.value !== '') {
        condominioSearchTerm.value = '';
      }
      return;
    }

    const option = condominioOptions.value.find((item) => item.id === value);
    if (option && option.nome !== condominioSearchTerm.value) {
      condominioSearchTerm.value = option.nome;
    }
  }
);

watch(
  () => [props.mode, props.imovelId, props.draft] as const,
  async ([mode, id]) => {
    if (mode === 'edit' && id) {
      await loadImovel(id);
    } else if (mode === 'create') {
      await prepareCreateForm();
    }
  },
  { immediate: true }
);

onBeforeUnmount(() => {
  if (condominioSearchTimeout) {
    clearTimeout(condominioSearchTimeout);
  }
  if (proprietarioSearchTimeout) {
    clearTimeout(proprietarioSearchTimeout);
  }
  cleanupPhotoPreviews();
});
</script>

<template>
  <div class="space-y-6">
    <div
      v-if="errorMessage"
      class="rounded-xl border border-rose-500/40 bg-rose-500/15 px-4 py-3 text-sm text-rose-200"
    >
      {{ errorMessage }}
    </div>

    <div
      v-if="loading"
      class="flex items-center justify-center rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-10 text-sm text-slate-300"
    >
      Carregando dados do imóvel...
    </div>

    <form v-else class="space-y-8" @submit.prevent="submit">
      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex items-center gap-3">
          <span class="h-6 w-1 rounded-full bg-indigo-500"></span>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Identificação</h3>
        </header>
        <div class="grid gap-4 md:grid-cols-2">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Código</label>
            <input
              v-model="form.codigo"
              type="text"
              readonly
              :class="inputClass"
              placeholder="Gerado automaticamente"
            />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Tipo de imóvel *</label>
            <select v-model="form.tipo_imovel" :class="inputClass" required>
              <option disabled value="">Selecione uma opção</option>
              <option v-for="option in tipoImovelOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>
          </div>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium text-slate-200">Proprietário *</label>
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:gap-3">
            <div class="relative flex-1">
              <input
                type="text"
                :value="proprietarioSearchTerm"
                :class="inputClass"
                placeholder="Digite para pesquisar"
                autocomplete="off"
                @focus="openProprietarioDropdown"
                @input="scheduleProprietarioSearch(($event.target as HTMLInputElement).value)"
                @blur="closeProprietarioDropdown"
              />
              <div
                v-if="proprietarioDropdownOpen"
                class="absolute z-20 mt-2 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-700 bg-slate-900/95 shadow-xl shadow-black/30"
              >
                <div v-if="proprietarioLoading" class="px-3 py-2 text-xs text-slate-400">
                  Carregando proprietários...
                </div>
                <template v-else>
                  <button
                    v-for="option in proprietarioOptions"
                    :key="option.id"
                    type="button"
                    class="flex w-full items-center px-3 py-2 text-left text-sm text-slate-200 transition hover:bg-slate-800/70"
                    @mousedown.prevent="selectProprietario(option)"
                  >
                    {{ option.nome }}
                  </button>
                  <div v-if="!proprietarioSearchHasResults" class="px-3 py-2 text-xs text-slate-400">
                    Nenhum proprietário encontrado.
                  </div>
                </template>
              </div>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-400">
              <button
                v-if="form.proprietario_id"
                type="button"
                class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:bg-slate-800/60"
                @click="clearProprietarioSelection"
              >
                Remover seleção
              </button>
            </div>
          </div>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Disponibilidade</label>
            <select v-model="form.disponibilidade" :class="inputClass">
              <option v-for="option in disponibilidadeOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>
          </div>
          <div class="md:col-span-2 flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Condomínio</label>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:gap-3">
              <div class="relative flex-1">
                <input
                  type="text"
                  :value="condominioSearchTerm"
                  :class="inputClass"
                  placeholder="Digite para pesquisar"
                  autocomplete="off"
                  @focus="openCondominioDropdown"
                  @input="scheduleCondominioSearch(($event.target as HTMLInputElement).value)"
                  @blur="closeCondominioDropdown"
                />
                <div
                  v-if="condominioDropdownOpen"
                  class="absolute z-20 mt-2 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-700 bg-slate-900/95 shadow-xl shadow-black/30"
                >
                  <div v-if="condominioLoading" class="px-3 py-2 text-xs text-slate-400">
                    Carregando condomínios...
                  </div>
                  <template v-else>
                    <button
                      v-for="option in condominioOptions"
                      :key="option.id"
                      type="button"
                      class="flex w-full items-center px-3 py-2 text-left text-sm text-slate-200 transition hover:bg-slate-800/70"
                      @mousedown.prevent="selectCondominio(option)"
                    >
                      {{ option.nome }}
                    </button>
                    <div v-if="!condominioSearchHasResults" class="px-3 py-2 text-xs text-slate-400">
                      Nenhum condomínio encontrado.
                    </div>
                  </template>
                </div>
              </div>
              <div class="flex items-center gap-2 text-xs text-slate-400">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 rounded-lg border border-indigo-500/40 bg-indigo-600/80 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500/80"
                  @click="goToCreateCondominio"
                >
                  + Novo condomínio
                </button>
                <button
                  v-if="form.condominio_id"
                  type="button"
                  class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:bg-slate-800/60"
                  @click="clearCondominioSelection"
                >
                  Remover seleção
                </button>
              </div>
            </div>
          </div>
          <div class="md:col-span-3">
            <label class="text-sm font-medium text-slate-200">Finalidade</label>
            <div class="mt-2 flex flex-wrap gap-3">
              <label
                v-for="option in finalidadeOptions"
                :key="option"
                class="flex items-center gap-2 text-sm text-slate-200"
              >
                <input
                  type="checkbox"
                  :value="option"
                  :checked="form.finalidade.includes(option)"
                  @change="toggleFinalidade(option)"
                  :class="checkboxClass"
                />
                {{ option }}
              </label>
            </div>
          </div>
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex items-center gap-3">
          <span class="h-6 w-1 rounded-full bg-emerald-500"></span>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Localização</h3>
        </header>
        <div class="grid gap-4 md:grid-cols-3">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">CEP</label>
            <div class="flex items-center gap-2">
              <input
                v-model="form.cep"
                type="text"
                :class="inputClass"
                placeholder="Somente números"
              />
              <button
                type="button"
                class="rounded-lg border border-indigo-500/40 bg-indigo-600/80 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-500/80"
                :disabled="cepLoading"
                @click="fetchCep"
              >
                {{ cepLoading ? 'Buscando...' : 'Buscar' }}
              </button>
            </div>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Estado</label>
            <input v-model="form.estado" type="text" maxlength="2" :class="inputClass" class="uppercase" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Cidade</label>
            <input v-model="form.cidade" type="text" :class="inputClass" />
          </div>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Bairro</label>
            <input v-model="form.bairro" type="text" :class="inputClass" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Rua</label>
            <input v-model="form.rua" type="text" :class="inputClass" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Número *</label>
            <input v-model="form.numero" type="text" required :class="inputClass" />
          </div>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium text-slate-200">Complemento / Apto</label>
          <input v-model="form.complemento" type="text" :class="inputClass" />
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex items-center gap-3">
          <span class="h-6 w-1 rounded-full bg-amber-500"></span>
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">
            Valores e características
          </h3>
        </header>
        <div class="grid gap-4 md:grid-cols-4">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Valor locação</label>
            <input v-model="form.valor_locacao" type="text" inputmode="decimal" :class="inputClass" placeholder="0.00" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Valor condomínio</label>
            <input v-model="form.valor_condominio" type="text" inputmode="decimal" :class="inputClass" placeholder="0.00" />
            <label class="mt-1 flex items-center gap-2 text-xs text-slate-400">
              <input v-model="form.condominio_isento" type="checkbox" :class="checkboxClass" />
              Isento
            </label>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Valor IPTU</label>
            <input v-model="form.valor_iptu" type="text" inputmode="decimal" :class="inputClass" placeholder="0.00" />
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
              <label class="flex items-center gap-2 text-xs text-slate-400">
                <input v-model="form.iptu_isento" type="checkbox" :class="checkboxClass" />
                Isento
              </label>
              <div class="flex flex-col gap-1">
                <span class="text-xs font-medium uppercase tracking-wide text-slate-400">Período IPTU</span>
                <select
                  v-model="form.periodo_iptu"
                  class="w-40 rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40"
                >
                  <option v-for="option in periodoOptions" :key="option" :value="option">
                    {{ option }}
                  </option>
                </select>
              </div>
            </div>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Outros valores</label>
            <input v-model="form.outros_valores" type="text" inputmode="decimal" :class="inputClass" placeholder="0.00" />
            <label class="mt-1 flex items-center gap-2 text-xs text-slate-400">
              <input v-model="form.outros_isento" type="checkbox" :class="checkboxClass" />
              Isento
            </label>
          </div>
        </div>
        <div class="grid gap-4 md:grid-cols-4">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Dormitórios</label>
            <input v-model.number="form.dormitorios" type="number" min="0" :class="inputClass" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Suítes</label>
            <input v-model.number="form.suites" type="number" min="0" :class="inputClass" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Banheiros</label>
            <input v-model.number="form.banheiros" type="number" min="0" :class="inputClass" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Vagas</label>
            <input v-model.number="form.vagas_garagem" type="number" min="0" :class="inputClass" />
          </div>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Área total (m²)</label>
            <input v-model="form.area_total" type="text" inputmode="decimal" :class="inputClass" placeholder="0.00" />
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Área construída (m²)</label>
            <input v-model="form.area_construida" type="text" inputmode="decimal" :class="inputClass" placeholder="0.00" />
          </div>
        </div>
        <div class="grid gap-4">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-slate-200">Comodidades</label>
            <div class="grid gap-2 md:grid-cols-3">
              <label
                v-for="item in comodidadesLista"
                :key="item"
                class="flex items-center gap-2 text-sm text-slate-200"
              >
                <input
                  type="checkbox"
                  :value="item"
                  :checked="form.comodidades.includes(item)"
                  @change="(event) => toggleComodidade(item, (event.target as HTMLInputElement).checked)"
                  class="rounded border-slate-600 text-indigo-500 focus:ring-indigo-500"
                />
                {{ item }}
              </label>
            </div>
          </div>
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-3">
            <span class="h-6 w-1 rounded-full bg-fuchsia-500"></span>
            <div>
              <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Fotos do imóvel</h3>
              <p class="text-xs text-slate-400">
                Envie até {{ maxPhotos }} imagens (JPG, JPEG, PNG ou WEBP). Geramos automaticamente miniaturas leves
                para o cadastro.
              </p>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <input
              ref="photoFileInputRef"
              type="file"
              class="hidden"
              accept="image/*"
              multiple
              @change="handlePhotoFilesSelected"
            />
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="photosLimitReached"
              @click="openPhotoPicker"
            >
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              Adicionar fotos
            </button>
            <button
              v-if="canDownloadPhotos"
              type="button"
              class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800/60 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="downloadingPhotos"
              @click="downloadPhotosZip"
            >
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4 4 4-4m-4 4V4" />
              </svg>
              {{ downloadingPhotos ? 'Gerando...' : 'Baixar ZIP' }}
            </button>
          </div>
        </header>
        <div class="flex items-center justify-between text-xs text-slate-400">
          <span>
            {{ activePhotosCount }}
            /
            {{ maxPhotos }}
            foto{{ activePhotosCount === 1 ? '' : 's' }} ativas
          </span>
          <span v-if="photosLimitReached" class="font-semibold text-rose-300">Limite atingido.</span>
        </div>
        <div v-if="photoItems.length > 0" class="grid gap-4 md:grid-cols-3">
          <article
            v-for="(photo, index) in photoItems"
            :key="photo.kind === 'existing' ? `existing-photo-${photo.id}` : `new-photo-${photo.id}`"
            class="space-y-3 rounded-2xl border border-slate-800 bg-slate-950/70 p-4 shadow-inner shadow-black/20"
            :class="photo.kind === 'existing' && photo.markedForRemoval ? 'opacity-60' : ''"
          >
            <div class="relative aspect-[4/3] overflow-hidden rounded-xl border border-slate-800 bg-slate-900/60">
              <img
                :src="photo.kind === 'existing' ? photo.thumbnailUrl : photo.previewUrl"
                alt="Foto do imóvel"
                class="h-full w-full cursor-pointer object-cover transition hover:scale-[1.02]"
                loading="lazy"
                @click="openPhotoPreview(photo)"
              />
              <div
                v-if="photo.kind === 'existing' && photo.markedForRemoval"
                class="absolute inset-0 flex items-center justify-center bg-slate-950/70 text-xs font-semibold uppercase tracking-wide text-rose-200"
              >
                Remoção pendente
              </div>
              <div class="absolute bottom-2 left-2 rounded-full bg-slate-900/80 px-3 py-1 text-xs font-semibold text-slate-200">
                #{{ index + 1 }}
              </div>
              <button
                type="button"
                class="absolute right-2 top-2 inline-flex items-center justify-center rounded-full bg-slate-900/70 p-2 text-white transition hover:bg-slate-800/90"
                title="Ver em tela cheia"
                @click.stop="openPhotoPreview(photo)"
              >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h6m0 0v6m0-6L13 11m-4 10H3m0 0v-6m0 6 8-8" />
                </svg>
              </button>
            </div>
            <div class="space-y-2">
              <input
                v-model="photo.legenda"
                type="text"
                :class="inputClass"
                placeholder="Legenda da foto"
                :disabled="photo.kind === 'existing' && photo.markedForRemoval"
              />
              <p class="text-xs text-slate-400">
                <span class="font-semibold text-slate-200">
                  {{ photo.kind === 'existing' ? photo.originalName : photo.file.name }}
                </span>
                •
                <span>
                  {{ photo.kind === 'existing' ? formatFileSize(photo.size) : formatFileSize(photo.file.size) }}
                </span>
                <template v-if="photo.kind === 'existing' && photo.width && photo.height">
                  • {{ photo.width }}×{{ photo.height }} px
                </template>
              </p>
              <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="rounded-md border border-slate-700 px-3 py-1 font-semibold text-slate-200 transition hover:bg-slate-800/70 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="index === 0"
                    @click="movePhotoUp(index)"
                  >
                    Subir
                  </button>
                  <button
                    type="button"
                    class="rounded-md border border-slate-700 px-3 py-1 font-semibold text-slate-200 transition hover:bg-slate-800/70 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="index === photoItems.length - 1"
                    @click="movePhotoDown(index)"
                  >
                    Descer
                  </button>
                </div>
                <div class="flex items-center gap-3">
                  <button
                    type="button"
                    class="font-semibold text-indigo-300 transition hover:text-indigo-200"
                    @click="openPhotoPreview(photo)"
                  >
                    Ver maior
                  </button>
                  <button
                    v-if="photo.kind === 'existing'"
                    type="button"
                    class="font-semibold transition"
                    :class="photo.markedForRemoval ? 'text-emerald-300 hover:text-emerald-200' : 'text-rose-300 hover:text-rose-200'"
                    @click="toggleExistingPhotoRemovalFlag(photo)"
                  >
                    {{ photo.markedForRemoval ? 'Desfazer' : 'Remover' }}
                  </button>
                  <button
                    v-else
                    type="button"
                    class="font-semibold text-rose-300 transition hover:text-rose-200"
                    @click="removeNewPhoto(photo.id)"
                  >
                    Remover
                  </button>
                </div>
              </div>
            </div>
          </article>
        </div>
        <div
          v-else
          class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 px-6 py-8 text-center text-sm text-slate-400"
        >
          Nenhuma foto adicionada até o momento. Utilize o botão acima para enviar imagens do imóvel.
        </div>
      </section>

      <section class="space-y-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-5 shadow-inner shadow-black/20">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-3">
            <span class="h-6 w-1 rounded-full bg-cyan-500"></span>
            <div>
              <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Documentos anexos</h3>
              <p class="text-xs text-slate-400">
                Envie contratos, laudos ou imagens importantes para o imóvel (PDF, JPG, JPEG ou PNG).
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <input
              ref="fileInputRef"
              type="file"
              multiple
              accept=".pdf,.jpg,.jpeg,.png"
              class="hidden"
              @change="handleFilesSelected"
            />
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500"
              @click="openFilePicker"
            >
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              Anexar documento
            </button>
          </div>
        </header>
        <div v-if="hasAnyAttachments" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-900/70 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th scope="col" class="px-4 py-2 text-left font-semibold">Nome do arquivo</th>
                <th scope="col" class="px-4 py-2 text-left font-semibold">Formato</th>
                <th scope="col" class="px-4 py-2 text-left font-semibold">Data</th>
                <th scope="col" class="px-4 py-2 text-left font-semibold">Enviado por</th>
                <th scope="col" class="px-4 py-2 text-right font-semibold">Ações</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              <tr
                v-for="attachment in existingAttachments"
                :key="`existing-${attachment.id}`"
                :class="attachment.markedForRemoval ? 'opacity-60' : undefined"
              >
                <td class="px-4 py-3 align-top">
                  <input
                    v-model="attachment.displayName"
                    type="text"
                    :class="inputClass"
                    placeholder="Nome do arquivo"
                    :disabled="attachment.markedForRemoval"
                  />
                  <p class="mt-1 text-xs text-slate-500">Original: {{ attachment.originalName }}</p>
                  <p v-if="attachment.markedForRemoval" class="mt-1 text-xs font-semibold text-rose-300">
                    Remoção pendente
                  </p>
                </td>
                <td class="px-4 py-3 align-top text-slate-300">
                  {{ formatAttachmentType(attachment.mimeType, attachment.originalName) }}
                </td>
                <td class="px-4 py-3 align-top text-slate-300">
                  {{ formatAttachmentDate(attachment.uploadedAt) }}
                </td>
                <td class="px-4 py-3 align-top text-slate-300">
                  {{ attachment.uploadedBy?.name ?? '—' }}
                </td>
                <td class="px-4 py-3 align-top">
                  <div class="flex items-center justify-end gap-2">
                    <a
                      v-if="attachment.url"
                      :href="attachment.url"
                      target="_blank"
                      rel="noopener"
                      class="rounded-md border border-slate-700 px-3 py-1 text-xs text-slate-200 transition hover:bg-slate-800/70"
                    >
                      Baixar
                    </a>
                    <button
                      type="button"
                      class="text-xs font-semibold text-rose-300 transition hover:text-rose-200"
                      @click="toggleExistingAttachmentRemoval(attachment)"
                    >
                      {{ attachment.markedForRemoval ? 'Desfazer remoção' : 'Remover' }}
                    </button>
                  </div>
                </td>
              </tr>
              <tr
                v-for="attachment in newAttachments"
                :key="`new-${attachment.id}`"
                class="bg-slate-900/40"
              >
                <td class="px-4 py-3 align-top">
                  <input
                    v-model="attachment.displayName"
                    type="text"
                    :class="inputClass"
                    placeholder="Nome do arquivo"
                  />
                  <p class="mt-1 text-xs text-slate-500">Arquivo: {{ attachment.file.name }}</p>
                </td>
                <td class="px-4 py-3 align-top text-slate-300">
                  {{ formatAttachmentType(attachment.mimeType, attachment.file.name) }}
                </td>
                <td class="px-4 py-3 align-top text-slate-300">
                  {{ formatAttachmentDate(attachment.uploadedAt) }}
                </td>
                <td class="px-4 py-3 align-top text-slate-300">
                  {{ attachment.uploadedByName }}
                </td>
                <td class="px-4 py-3 align-top">
                  <div class="flex items-center justify-end">
                    <button
                      type="button"
                      class="text-xs font-semibold text-rose-300 transition hover:text-rose-200"
                      @click="removeNewAttachment(attachment.id)"
                    >
                      Remover
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div
          v-else
          class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 px-6 py-8 text-center text-sm text-slate-400"
        >
          Nenhum documento anexado até o momento. Utilize o botão acima para adicionar arquivos.
        </div>
      </section>

      <div class="flex items-center justify-end gap-3">
        <button
          type="button"
          class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800/60"
          @click="emit('cancel')"
          :disabled="saving"
        >
          Cancelar
        </button>
        <button
          type="submit"
          class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500"
          :disabled="saving"
        >
          {{ saving ? 'Salvando...' : 'Salvar' }}
        </button>
      </div>
    </form>
  </div>

  <transition name="fade">
    <div
      v-if="photoPreview"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 px-4 py-6 backdrop-blur"
      @click.self="closePhotoPreview"
    >
      <div class="relative w-full max-w-4xl rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl shadow-black/40">
        <button
          type="button"
          class="absolute right-4 top-4 rounded-full bg-slate-900/80 p-2 text-slate-300 transition hover:text-white"
          @click="closePhotoPreview"
        >
          <span class="sr-only">Fechar</span>
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <div class="rounded-2xl p-5">
          <img :src="photoPreview.url" alt="Pré-visualização da foto" class="mx-auto max-h-[70vh] w-full rounded-xl object-contain" />
          <div class="mt-4 space-y-1 text-center text-sm text-slate-300">
            <div class="font-semibold text-white">
              {{ photoPreview.legenda ?? photoPreview.originalName }}
            </div>
            <div class="text-xs text-slate-400">{{ photoPreview.originalName }}</div>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>
