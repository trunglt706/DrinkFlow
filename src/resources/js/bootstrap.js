import axios from 'axios';
import { initDateInputs } from './shared/date-input';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Shared date inputs (<x-date-input>) of every page: dd/mm/yyyy with a calendar dropdown.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDateInputs);
} else {
    initDateInputs();
}
