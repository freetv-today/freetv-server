import { signal } from '@preact/signals';

// Usage: toastSignal.value = { color: 'success', msg: 'Hello!', show: true }
export const toastSignal = signal({ color: 'primary', msg: '', show: false });

// Helper to trigger a toast from anywhere
export function triggerToast(msg, color = 'primary') {
  // Toggle show to false first to retrigger the effect if the same toast is shown again
  toastSignal.value = { ...toastSignal.value, show: false };
  setTimeout(() => {
    toastSignal.value = { color, msg, show: true };
  }, 10);
}
