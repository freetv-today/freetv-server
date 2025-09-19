import { signal } from '@preact/signals';

/**
 * toastSignal - Global signal for displaying toast notifications.
 * @type {import('@preact/signals').Signal<{ color: string, msg: string, show: boolean }>}
 * Usage: toastSignal.value = { color: 'success', msg: 'Hello!', show: true }
 */

export const toastSignal = signal({ color: 'primary', msg: '', show: false });

/**
 * triggerToast - Helper to trigger a toast notification from anywhere in the app.
 * @param {string} msg - The message to display.
 * @param {string} [color='primary'] - The color/type of the toast (e.g., 'success', 'danger').
 */

export function triggerToast(msg, color = 'primary') {
  // Toggle show to false first to retrigger the effect if the same toast is shown again
  toastSignal.value = { ...toastSignal.value, show: false };
  setTimeout(() => {
    toastSignal.value = { color, msg, show: true };
  }, 10);
}
