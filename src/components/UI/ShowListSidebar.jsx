import { useContext, useEffect } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext';
import { ButtonShowTitleNav } from '@components/Navigation/ButtonShowTitleNav';
import { useLocalStorage } from '@hooks/useLocalStorage';
import { useSignalEffect } from '@preact/signals';
import { favoritesSignal } from '@hooks/useFavoritesList';
import { useDebugLog } from '@/hooks/useDebugLog';
import { capitalizeFirstLetter, getRandomCategory } from '@/utils';
import { useCategories } from '@/hooks/useCategories';


/**
 * @param {Object} props
 * @param {'category' | 'recent' | 'favorites'} props.context
 * @param {string} [props.category]
 * @returns {import('preact').JSX.Element}
 */

export function ShowListSidebar({ context, category }) {

  const log = useDebugLog();
  const { showData } = useContext(PlaylistContext);
  const [recentTitles] = useLocalStorage('recentTitles', { title: [] });
  const [favoritesList, setFavoritesList] = useLocalStorage('favoritesList', { title: [] });
  const categories = useCategories();
  const randomCategory = getRandomCategory(categories);

  let shows = [];


  // Force rerender and sync favoritesList from localStorage when favoritesSignal changes (only for favorites context)
  useSignalEffect(() => {
    if (context === 'favorites') {
      favoritesSignal.value;
      // Always re-read the latest value from localStorage
      try {
        const item = window.localStorage.getItem('favoritesList');
        setFavoritesList(item ? JSON.parse(item) : { title: [] });
      } catch (err) {
        // fallback: do nothing
      }
    }
  });

  if (context === 'category' && category) {
    // Filter shows by category, sort alphabetically (ignoring "The")
    shows = showData?.filter(item => item.category.toLowerCase() === category.toLowerCase() && item.status === 'active')
      .sort((a, b) => {
        const titleA = a.title.replace(/^The\s+/i, '');
        const titleB = b.title.replace(/^The\s+/i, '');
        return titleA.localeCompare(titleB);
      }) || [];
  } else if (context === 'recent') {
    shows = recentTitles.title
      .map(title => showData?.find(show => show.title === title))
      .filter(Boolean); // Remove any not found
  } else if (context === 'favorites') {
    shows = favoritesList.title
      .map(title => showData?.find(show => show.title === title))
      .filter(Boolean); // Remove any not found
  }
  if (category) {
    log(`Selected category: ${capitalizeFirstLetter(category)}`);
    log(`There are ${shows.length} titles in this category`);
  }
  
  return (
    <aside className="sidebar-fixed-width p-1 mb-1 mb-lg-0">
      {shows.length > 0 ? (
        shows.map(show => (
          <ButtonShowTitleNav
            key={show.identifier}
            title={show.title}
            category={show.category}
            identifier={show.identifier}
            desc={show.desc}
            start={show.start}
            end={show.end}
            imdb={show.imdb}
          />
        ))
      ) : (
        <>
          <p className="text-center mt-3 text-danger fw-bold">
            {context === 'favorites'
              ? `You haven't added any shows to your Favorites yet`
              : context === 'recent'
                ? 'No recently watched shows available'
                : 'No shows available in this category'}
          </p>
          <p className="text-center">
            <img src="/assets/sadface.svg" alt="😢" width="40" />
          </p>
          <p className="text-center">
            <a
              href={randomCategory ? `/category/${randomCategory}` : '#'}
              className="small fw-bold link-dark link-offset-3 link-underline link-underline-opacity-50"
              title="Click to visit a random category"
              onClick={e => {
                if (!randomCategory) e.preventDefault();
              }}
            >
              Go watch some Free TV!
            </a>  
          </p>        
        </>

        
      )}
    </aside>
  );
}