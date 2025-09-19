import { signal } from '@preact/signals';

/**
 * showVidNavBtnsSignal - Signal to control visibility of video navigation buttons.
 * @type {import('@preact/signals').Signal<boolean>}
 * Set to true to show nav buttons after video loads.
 */

export const showVidNavBtnsSignal = signal(false);