import { useContext } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext';

/**
 * useCategories - Custom hook to extract and sort unique categories from show data.
 * @returns {string[]} - Sorted array of unique categories.
 */

export function useCategories() {
  
  const { showData } = useContext(PlaylistContext);

  return [
    ...new Set(
      showData?.filter(item => item.category.trim() !== '').map(item => item.category)
    ),
  ].sort((a, b) => a.localeCompare(b));
}
