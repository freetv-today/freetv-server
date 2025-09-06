// src/components/UI/ShowListSidebar.jsx
import { useContext } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext';
import { ButtonShowTitleNav } from '@components/Navigation/ButtonShowTitleNav';
import { useLocalStorage } from '@hooks/useLocalStorage';
import { useSignalEffect } from '@preact/signals';
import { favoritesSignal } from '@hooks/useFavoritesList';
import { useDebugLog } from '@/hooks/useDebugLog';
import { capitalizeFirstLetter } from '@/utils';

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
  const [favoritesList] = useLocalStorage('favoritesList', { title: [] });

  let shows = [];

  // Force rerender when favoritesSignal changes
  useSignalEffect(() => {
    favoritesSignal.value;
  });

  log(`ShowListSidebar context: ${context}`);
  log('recentTitles:', recentTitles);
  log('favoritesList:', favoritesList);

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
    log('Shows for recent:', shows);
  } else if (context === 'favorites') {
    shows = favoritesList.title
      .map(title => showData?.find(show => show.title === title))
      .filter(Boolean); // Remove any not found
    log('Shows for favorites:', shows);
  }
  if (category) {
    log(`Selected category: ${capitalizeFirstLetter(category)}`);
  }
  log(`There are ${shows.length} titles in this category`);

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
        <p className="text-center my-3 text-danger fw-bold">No recently watched shows available.</p>
      )}
    </aside>
  );
}