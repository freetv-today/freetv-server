import { useState, useEffect } from 'preact/hooks';
import { useSearchResults } from '@/hooks/useSearchResults';
import { useContext } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext';
import { SearchQueryComponent } from '@components/UI/SearchQueryComponent';
import { SearchResults } from '@components/UI/SearchResults';
import { ImageLargeLogo } from '@components/UI/ImageLargeLogo';
import { useDebugLog } from '@/hooks/useDebugLog';
import { AdBar } from '@/components/UI/AdBar';

export function Search() {
  const log = useDebugLog();
  const { showData } = useContext(PlaylistContext);

  // Try to restore query from localStorage
  const getInitialQuery = () => {
    try {
      const val = window.localStorage.getItem('searchQuery');
      return val ? JSON.parse(val) : '';
    } catch {
      return '';
    }
  };

  const [query, setQuery] = useState(getInitialQuery());
  // Use shared hook for search results
  const results = useSearchResults(showData, query, {
    filterFn: (show, q) =>
      (show.title && show.title.toLowerCase().includes(q)) ||
      (show.category && show.category.toLowerCase().includes(q)) ||
      (show.desc && show.desc.toLowerCase().includes(q)) ||
      (show.start && String(show.start).includes(q))
  });

  useEffect(() => {
    document.title = "Free TV: Search";
    log('Rendered Search page (pages/Search/index.jsx)');
  }, []);

  // Auto-run search if query exists on mount
  useEffect(() => {
    if (query && query.length >= 3) {
      setQuery(query);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Only update query state; results are derived from hook
  const handleSearch = (q) => {
    setQuery(q);
  };

  return (
    <>
      <SearchQueryComponent onSearch={handleSearch} />
      {(!query || !results) ? (
        <>
          <AdBar/>
          <ImageLargeLogo />
        </>
      ) : (
        <SearchResults results={results} />
      )}
    </>
  );
}