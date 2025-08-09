// src/components/UI/ShowListSidebar.jsx
import { useShowData } from '@/context/ShowDataContext.jsx';
import { ButtonShowTitleNav } from '@components/Navigation/ButtonShowTitleNav';

/**
 * @param {Object} props
 * @param {'category' | 'recent'} props.context
 * @param {string} [props.category]
 * @returns {import('preact').JSX.Element}
 */
export function ShowListSidebar({ context, category }) {
  const showData = useShowData();

  let shows = [];

  if (context === 'category' && category) {
    // Filter shows by category, sort alphabetically (ignoring "The")
    shows = showData?.shows
      ?.filter(item => item.category.toLowerCase() === category.toLowerCase() && item.status === 'active')
      .sort((a, b) => {
        const titleA = a.title.replace(/^The\s+/i, '');
        const titleB = b.title.replace(/^The\s+/i, '');
        return titleA.localeCompare(titleB);
      }) || [];
  } else if (context === 'recent') {
    // Placeholder for recent titles
    shows = [];
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
        <p class="text-center">No shows available.</p>
      )}
    </aside>
  );
}