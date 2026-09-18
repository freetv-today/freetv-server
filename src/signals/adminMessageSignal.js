import { signal } from '@preact/signals';

/**
 * adminMsgSignal - Global signal for admin messages.
 * @type {import('@preact/signals').Signal<{ type: 'success'|'danger'|'info'|'warning', text: string, targetPath: string|null }|null>}
 * Used to display admin notifications across the app.
 */

export const adminMsgSignal = signal(null);

/**
 * setAdminMsg - Set a message scoped to the current page.
 * @param {{ type: 'success'|'danger'|'info', text: string }} msg - The admin message object.
 */

export function setAdminMsg(msg) {
  if (msg && typeof msg === 'object' && msg.text) {
    adminMsgSignal.value = { ...msg, targetPath: null };
  }
}

/**
 * setAdminFlashMsg - Set a message intended for a specific destination page.
 * @param {{ type: 'success'|'danger'|'info', text: string }} msg - The admin message object.
 * @param {string} targetPath - The route where the message should be displayed.
 */

export function setAdminFlashMsg(msg, targetPath) {
  if (msg && typeof msg === 'object' && msg.text && targetPath) {
    adminMsgSignal.value = { ...msg, targetPath };
  }
}

/**
 * clearAdminMsg - Clear the admin message.
 */

export function clearAdminMsg() {
  adminMsgSignal.value = null;
}
