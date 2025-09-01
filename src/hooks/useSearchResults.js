import { useMemo } from 'preact/hooks';

/**
 * useSearchResults - shared hook for filtering and searching show data
 * @param {Array} showData - The array of shows to search/filter
 * @param {string} query - The search query string
 * @param {Object} [options] - Optional config (e.g., custom filter function)
 * @returns {Array} filteredResults - The filtered search results
 */

export function useSearchResults(showData, query, options = {}) {
  const { filterFn } = options;

  // Memoize filtered results for performance
  const filteredResults = useMemo(() => {
    if (!query || !showData) return [];
    const q = query.trim().toLowerCase();
    if (!q) return [];
    // Default filter: match title or id
    return showData.filter(show => {
      if (filterFn) return filterFn(show, q);
      return (
        (show.title && show.title.toLowerCase().includes(q)) ||
        (show.id && show.id.toLowerCase().includes(q))
      );
    });
  }, [showData, query, filterFn]);

  return filteredResults;
}
