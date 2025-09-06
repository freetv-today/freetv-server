import { useLocalStorage } from '@hooks/useLocalStorage';
import { signal } from '@preact/signals';

// Signal to force update components using favorites
export const favoritesSignal = signal(0);

export function useFavoritesList() {
  const [favorites, setFavorites] = useLocalStorage('favoritesList', { title: [] });

  const addToFavorites = (title) => {
    setFavorites(prev => {
      let arr = Array.isArray(prev?.title) ? [...prev.title] : [];
      if (!arr.includes(title)) arr.unshift(title);
      favoritesSignal.value++;
      return { title: arr };
    });
  };

  const removeFromFavorites = (title) => {
    setFavorites(prev => {
      let arr = Array.isArray(prev?.title) ? prev.title : [];
      arr = arr.filter(t => t !== title);
      favoritesSignal.value++;
      return { title: arr };
    });
  };

  return { favorites, addToFavorites, removeFromFavorites };
}
