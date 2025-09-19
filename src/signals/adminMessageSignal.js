import { signal } from '@preact/signals';

/**
 * adminMsgSignal - Global signal for admin messages.
 * @type {import('@preact/signals').Signal<{ type: 'success'|'danger'|'info', text: string }|null>}
 * Used to display admin notifications across the app.
 */

export const adminMsgSignal = signal(null);

/**
 * setAdminMsg - Set the admin message and sync to localStorage.
 * @param {{ type: 'success'|'danger'|'info', text: string }} msg - The admin message object.
 */

export function setAdminMsg(msg) {
  if (msg && typeof msg === 'object' && msg.text) {
    adminMsgSignal.value = msg;
    try {
      localStorage.setItem('adminMsg', JSON.stringify(msg));
  } catch { /* ignore */ }
  }
}

/**
 * clearAdminMsg - Clear the admin message and remove from localStorage.
 */

export function clearAdminMsg() {
  adminMsgSignal.value = null;
  try {
    localStorage.removeItem('adminMsg');
  } catch { /* ignore */ }
}

/**
 * Listens for localStorage changes to sync admin messages across tabs.
 */

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
