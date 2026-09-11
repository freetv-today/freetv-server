// ------------------------------
// Javascript Utilities
// ------------------------------

/**
 * Uppercase first letter of a string
 * @param {string} string - String to modify
 * @returns {string} String with first letter capitalized
 * @example capitalizeFirstLetter('foobar') // returns 'Foobar'
 */
export function capitalizeFirstLetter(string) {
  if (!string || typeof string !== 'string') return '';
  return string.charAt(0).toUpperCase() + string.slice(1);
}

/**
 * Shows SpinnerLoadingAppData for minimum duration
 * Used by AppLoader.jsx to ensure user sees spinner for adequate time
 * @param {number} startTime - Timestamp when loading started
 * @param {number} [minTime=1200] - Minimum time in milliseconds
 * @returns {Promise<void>} Promise that resolves after minimum time
 */
export async function enforceMinLoadingTime(startTime, minTime = 1200) {
  const elapsedTime = Date.now() - startTime;
  const remainingTime = minTime - elapsedTime;
  if (remainingTime > 0) {
    await new Promise((resolve) => setTimeout(resolve, remainingTime));
  }
}

/**
 * Show alert and focus text input field
 * Used by SearchQueryComponent.jsx for user feedback
 * @param {string} message - Alert message to display
 * @param {string} [inputId] - ID of form text input to focus after alert
 */
export function showAlert(message, inputId) {
  alert(message);
  if (inputId) {
    setTimeout(() => {
      const el = document.getElementById(inputId);
      if (el) el.focus();
    }, 0);
  }
}

/**
 * Format JSON timestamp to user-friendly date/time
 * @param {string|number|Date} date - Date to format
 * @param {Object} [options={}] - Intl.DateTimeFormat options to override defaults
 * @returns {string} Formatted date like '8/31/25, 09:03 AM' or '-' if invalid
 * @example formatDateTime('2025-08-31T13:03:05.608Z') // returns '8/31/25, 09:03 AM'
 */
export function formatDateTime(date, options = {}) {
  if (!date) return '-';
  const d = typeof date === 'string' || typeof date === 'number' ? new Date(date) : date;
  if (isNaN(d.getTime())) return '00/00/00, 00:00';
  // Default options can be overridden
  const defaultOptions = { year: '2-digit', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' };
  return d.toLocaleString(undefined, { ...defaultOptions, ...options });
}