// ------------------------------
// Javascript Utilities
// ------------------------------

// Generate random tokens
// accepts 1 argument: length of token to create
// Usage: generateToken(32) - creates 32 char alphanumerical token
export function generateToken(length) {
  const a = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890'.split('');
  const b = [];
  for (let i = 0; i < length; i++) {
    const j = Math.floor(Math.random() * a.length);
    b[i] = a[j];
  }
  return b.join('');
}

// Generate unique code (used for session tracking)
// Tokens are pseudo-random uppercase & alphanumeric w/ dashes
// Example: 3M4L-X5FX-BB9P-DQXV-O4NM-TLPD
export function generateNewCode() {
  const pt1 = generateToken(4).toUpperCase();
  const pt2 = generateToken(4).toUpperCase();
  const pt3 = generateToken(4).toUpperCase();
  const pt4 = generateToken(4).toUpperCase();
  const pt5 = generateToken(4).toUpperCase();
  const pt6 = generateToken(4).toUpperCase();
  return `${pt1}-${pt2}-${pt3}-${pt4}-${pt5}-${pt6}`;
}

// Uppercase first letter
// accepts 1 argument: string to modify
// Usage: capitalizeFirstLetter('foobar') -- returns 'Foobar'
export function capitalizeFirstLetter(string) {
  if (!string || typeof string !== 'string') return '';
  return string.charAt(0).toUpperCase() + string.slice(1);
}

// Determines whether date in local storage matches config date
// used by AppLoader.jsx
export function shouldUpdateData(storedData, newData) {
  const storedDate = storedData?.lastupdated ? new Date(storedData.lastupdated) : null;
  const newDate = newData?.lastupdated ? new Date(newData.lastupdated) : null;
  return !storedData || !storedDate || (newDate && newDate > storedDate);
}

// Shows SpinnerLoadingAppData for min of 1.2 seconds
// used by AppLoader.jsx (gives user time to see spinner)
export async function enforceMinLoadingTime(startTime, minTime = 1200) {
  const elapsedTime = Date.now() - startTime;
  const remainingTime = minTime - elapsedTime;
  if (remainingTime > 0) {
    await new Promise((resolve) => setTimeout(resolve, remainingTime));
  }
}

// Used by ButtonVideoNav.jsx to confirm page reload
export function confirmPlaylistReload() {
  return window.confirm(
    'To show or hide the Episode Playlist you\'ll have to reload the page. Do you wish to proceed?'
  );
}

// Show alert and focus text input field
// accepts 2 arguments: message, and id of form text input to focus
// used by SearchQueryComponent.jsx
export function showAlert(message, inputId) {
  alert(message);
  if (inputId) {
    setTimeout(() => {
      const el = document.getElementById(inputId);
      if (el) el.focus();
    }, 0);
  }
}

// Remove extra parameters from the URL
// Example: changes URL from /help#version to /help
// used on Help page
export function resetUrl() {
  const baseUrl = window.location.origin + window.location.pathname;
  history.replaceState({}, document.title, baseUrl);
}

// Format JSON timestamp to user-friendly date/time
// Usage: formatDateTime('2025-08-31T13:03:05.608Z')
// Returns formatted date like: '8/31/25, 09:03 AM'
export function formatDateTime(date, options = {}) {
  if (!date) return '-';
  const d = typeof date === 'string' || typeof date === 'number' ? new Date(date) : date;
  if (isNaN(d)) return '00/00/00, 00:00';
  // Default options can be overridden
  const defaultOptions = { year: '2-digit', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' };
  return d.toLocaleString(undefined, { ...defaultOptions, ...options });
}

// Returns a random category 
// Accepts 1 argument: an array of categories
export function getRandomCategory(categories) {
  if (!Array.isArray(categories) || categories.length === 0) return null;
  const idx = Math.floor(Math.random() * categories.length);
  return categories[idx];
}

// Handle keypress events
export function handleKeyPress(event) {
  // Ignore if focus is in an input, textarea, or contenteditable element
  const tag = document.activeElement && document.activeElement.tagName;
  const isInput = tag === 'INPUT' || tag === 'TEXTAREA' ||
    (typeof HTMLElement !== 'undefined' && document.activeElement && document.activeElement instanceof HTMLElement && document.activeElement.isContentEditable);
  if (isInput) return;
  // Otherwise, check to see if SHIFT + A is pressed
  if (event.shiftKey && event.key === 'A') {
    event.preventDefault();
    // And direct user to Admin Dashboard
    window.location.href = '/admin';
  }
}

// Get app info from local storage
export function getAppInfo() {
  let d = localStorage.getItem('appInfo');
  if (d) {
    let info = JSON.parse(d);
    return info;
  }
}

// --------------------------------------
// Analytics: App Data (Usage Tracking)
// --------------------------------------

/**
 * logShowView
 *
 * Usage: logShowView(imdbId, category)
 *
 * This function records show/category views for analytics.
 *
 * @param {string} imdbId - The IMDB ID of the show/movie
 * @param {string} [category] - The category or genre (optional)
 */

export function logShowView(imdbId, category) {
  if (!imdbId) return;
  try {
    const visitData = JSON.parse(localStorage.getItem('visitData') || '{}');
    if (!visitData) return;
    if (!Array.isArray(visitData.recentShows)) visitData.recentShows = [];
    // Only keep the last 20 entries for privacy and size
    visitData.recentShows.push({
      imdbId,
      category: category || null,
      start: new Date().toISOString(),
    });
    if (visitData.recentShows.length > 20) visitData.recentShows = visitData.recentShows.slice(-20);
    localStorage.setItem('visitData', JSON.stringify(visitData));
  } catch {
    // Ignore errors for now
  }
}

/**
 * logShowEnd - Sets the end time for the most recent matching show in visitData.recentShows
 *
 * Usage: logShowEnd(imdbId)
 *
 * Finds the most recent entry for the given imdbId in visitData.recentShows and sets its end time.
 * If no matching entry is found, does nothing. This is a placeholder for future analytics.
 *
 * @param {string} imdbId - The IMDB ID of the show/movie
 */

export function logShowEnd(imdbId) {
  if (!imdbId) return;
  try {
    const visitData = JSON.parse(localStorage.getItem('visitData') || '{}');
    if (!visitData || !Array.isArray(visitData.recentShows)) return;
    // Find the most recent matching entry (search from end)
    for (let i = visitData.recentShows.length - 1; i >= 0; i--) {
      const entry = visitData.recentShows[i];
      if (entry.imdbId === imdbId && !entry.end) {
        entry.end = new Date().toISOString();
        break;
      }
    }
    localStorage.setItem('visitData', JSON.stringify(visitData));
  } catch {
    // Ignore errors for now
  }
}