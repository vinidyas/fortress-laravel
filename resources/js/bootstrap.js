import axios from 'axios';

const hasImportMeta = typeof import.meta !== 'undefined' && import.meta && import.meta.env;
const backendUrl = hasImportMeta && import.meta.env.VITE_BACKEND_URL
  ? import.meta.env.VITE_BACKEND_URL
  : window.location.origin;

axios.defaults.baseURL = backendUrl;
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

let csrfRequest = null;

const ensureCsrfCookie = () => {
  if (!csrfRequest) {
    csrfRequest = axios.get('/sanctum/csrf-cookie', {
      withCredentials: true,
    }).finally(() => {
      csrfRequest = null;
    });
  }

  return csrfRequest;
};

const getCookieValue = (name) => {
  if (typeof document === 'undefined') {
    return null;
  }

  const cookies = document.cookie ? document.cookie.split('; ') : [];

  for (const cookie of cookies) {
    if (cookie.startsWith(`${name}=`)) {
      return decodeURIComponent(cookie.substring(name.length + 1));
    }
  }

  return null;
};

axios.interceptors.request.use(async (config) => {
  const method = (config.method || 'get').toLowerCase();

  if (['post', 'put', 'patch', 'delete'].includes(method)) {
    if (!config.url?.includes('/sanctum/csrf-cookie')) {
      await ensureCsrfCookie();
    }

    const token = getCookieValue('XSRF-TOKEN');

    if (token) {
      config.headers = config.headers ?? {};
      config.headers['X-XSRF-TOKEN'] = token;
    }
  }

  return config;
});

export default axios;
