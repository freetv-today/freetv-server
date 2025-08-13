// src/components/UI/ShowListSidebar.jsx
import { useContext } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext.jsx';
import { ButtonShowTitleNav } from '@components/Navigation/ButtonShowTitleNav';
import { useLocalStorage } from '@hooks/useLocalStorage';

/**
 * @param {Object} props
 * @param {'category' | 'recent'} props.context
 * @param {string} [props.category]
 * @returns {import('preact').JSX.Element}
 */
export function ShowListSidebar({ context, category }) {

  const { showData } = useContext(PlaylistContext);
  const [recentTitles] = useLocalStorage('recentTitles', { title: [] });

  let shows = [];

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
      .map(title =>
        showData?.find(show => show.title === title)
      )
      .filter(Boolean); // Remove any not found
  }

  return (
    <aside class="sidebar-fixed-width p-1 mb-1 mb-lg-0">
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
        <p class="text-center my-3 text-danger fw-bold">No recently watched shows available.</p>
      )}
    </aside>
  );
}