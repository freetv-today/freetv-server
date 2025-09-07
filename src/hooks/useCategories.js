// src/hooks/useCategories.js
import { useContext } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext';

export function useCategories() {
  const { showData } = useContext(PlaylistContext);

  return [
    ...new Set(
      showData?.filter(item => item.category.trim() !== '').map(item => item.category)
    ),
  ].sort((a, b) => a.localeCompare(b));
}
