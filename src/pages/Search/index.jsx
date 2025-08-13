import { useState, useEffect } from 'preact/hooks';
import { useConfig } from '@/context/ConfigContext.jsx';
import { useContext } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext.jsx';
import { SearchQueryComponent } from '@components/UI/SearchQueryComponent';
import { SearchResults } from '@components/UI/SearchResults';
import { ImageLargeLogo } from "@components/UI/ImageLargeLogo";

export function Search() {
  const { debugmode } = useConfig();
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
  const [results, setResults] = useState(null);

  useEffect(() => {
    document.title = "Free TV: Search";
    if (debugmode) {
      console.log('Rendered Search page (pages/Search/index.jsx)');
    }
  }, [debugmode]);

  // Auto-run search if query exists on mount
  useEffect(() => {
    if (query && query.length >= 3) {
      handleSearch(query);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Handle search logic
  const handleSearch = (q) => {
    setQuery(q);
    if (!q || q.length < 3) {
      setResults(null);
      return;
    }
    const qLower = q.toLowerCase();
    const filtered = showData.filter(show =>
      show.title.toLowerCase().includes(qLower) ||
      show.category.toLowerCase().includes(qLower) ||
      (show.desc && show.desc.toLowerCase().includes(qLower)) ||
      (show.start && String(show.start).includes(qLower))
    );
    setResults(filtered);
  };

  return (
    <>
      <SearchQueryComponent onSearch={handleSearch} />
      {(!query || !results) ? (
        <ImageLargeLogo />
      ) : (
        <SearchResults results={results} />
      )}
    </>
  );
}