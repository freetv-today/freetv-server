import { useEffect, useState } from 'preact/hooks';
import { useFavoritesList } from '@hooks/useFavoritesList';
import { useLocalStorage } from '@hooks/useLocalStorage';
import { triggerToast } from '@signals/toastSignal';

// Add to Favorites icon button for NavbarVideoPlayback
export function ButtonAddFavoritesNav() {

  const [currentVid] = useLocalStorage('currentVid', null);
  const { favorites, addToFavorites, removeFromFavorites } = useFavoritesList();
  const [isFavorite, setIsFavorite] = useState(false);

  // Sync favorite state with currentVid and favorites
  useEffect(() => {
    if (!currentVid || !currentVid.title) {
      setIsFavorite(false);
      return;
    }
    setIsFavorite(Array.isArray(favorites?.title) && favorites.title.includes(currentVid.title));
  }, [currentVid, favorites]);

  if (!currentVid || !currentVid.title) return null;
  const title = currentVid.title;

  const handleToggle = (e) => {
    e.preventDefault();
    if (isFavorite) {
      removeFromFavorites(title);
      triggerToast('Show was removed from Favorites', 'dark');
    } else {
      addToFavorites(title);
      triggerToast('Show was added to Favorites', 'dark');
    }
    // Optimistically update UI
    setIsFavorite(!isFavorite);
  };

  return (
    <>
      <input
        type="checkbox"
        id="addFavoritesBtn"
        className="btn-check"
        autoComplete="off"
        checked={isFavorite}
        onChange={handleToggle}
        style={{ display: 'none' }}
      />
      <label
        htmlFor="addFavoritesBtn"
        className={`btn btn-sm icon-btn addfavorites-icon me-2${isFavorite ? ' active' : ''}`}
        title={isFavorite ? `Remove ${currentVid.title} from Favorites` : `Add ${currentVid.title} to Favorites`}
        tabIndex={0}
        aria-pressed={isFavorite}
      >
        &nbsp;
      </label>
    </>
  );
}