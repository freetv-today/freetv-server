// src/signals/adminMessageSignal.js
import { signal } from '@preact/signals';

// The global admin message signal
// { type: 'success'|'danger'|'info', text: string } | null
export const adminMsgSignal = signal(null);

// Set the admin message (and sync to localStorage)
export function setAdminMsg(msg) {
  if (msg && typeof msg === 'object' && msg.text) {
    adminMsgSignal.value = msg;
    try {
      localStorage.setItem('adminMsg', JSON.stringify(msg));
  } catch { /* ignore */ }
  }
}

// Clear the admin message (and remove from localStorage)
export function clearAdminMsg() {
  adminMsgSignal.value = null;
  try {
    localStorage.removeItem('adminMsg');
  } catch { /* ignore */ }
}

// Listen for localStorage changes (multi-tab support)
if (typeof window !== 'undefined') {
  window.addEventListener('storage', e => {
    if (e.key === 'adminMsg') {
      try {
        adminMsgSignal.value = e.newValue ? JSON.parse(e.newValue) : null;
      } catch {
        adminMsgSignal.value = null; // ignore parse error
      }
    }
  });
}
