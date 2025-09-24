import { signal } from '@preact/signals';

// Signal to trigger ad reloads
export const adReloadSignal = signal(0);

// Function to trigger ad reload
export function triggerAdReload() {
    adReloadSignal.value = Date.now();
}