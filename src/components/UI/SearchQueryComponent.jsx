import { useState, useRef, useEffect } from 'preact/hooks';
import { useLocalStorage } from '@hooks/useLocalStorage';
import { showAlert } from '@/utils/utils';
import { useDebugLog } from '@/hooks/useDebugLog';

const IGNORED_WORDS = ['a', 'and', 'the', 'or', 'but'];

/**
 * SearchQueryComponent - Search input field with validation and submit handling
 * @param {Object} props
 * @param {function(string): void} props.onSearch - Called when search is submitted with valid query
 */
export function SearchQueryComponent({ onSearch }) {
  const [searchQuery, setsearchQuery] = useLocalStorage('adminSearchQuery', '');
  const [query, setQuery] = useState(searchQuery || '');
  const inputRef = useRef(null);
  const log = useDebugLog();

  useEffect(() => {
    if (inputRef.current) {
      inputRef.current.focus();
    }
  }, []);

  // Update localStorage whenever query changes
  const handleInput = (e) => {
    const val = e.target.value;
    setQuery(val);
    setsearchQuery(val);
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const trimmed = query.trim();
    if (trimmed.length < 3) {
      showAlert("Your search query must be at least 3 characters long", "searchquery");
      return;
    }
    // Remove punctuation and split into words
    const filtered = trimmed
      .replace(/[&,:\-.]/g, '')
      .split(/\s+/)
      .filter(word => !IGNORED_WORDS.includes(word.toLowerCase()));
    if (filtered.length === 0) {
      showAlert(
        "Your search query only contains words that are ignored in the search (such as 'the', 'and', 'a', 'or', and 'but'). Please include more specific search terms.",
        "searchquery"
      );
      return;
    }
    setsearchQuery(trimmed);
    // Pass the query as-is so the parent can match substrings in all fields, including year
    onSearch(trimmed);
  };

  const handleClear = () => {
    setQuery('');
    setsearchQuery('');
    localStorage.removeItem('adminSearchQuery');
    onSearch('');
    log('Clearing admin search query from local storage');
  };

  return (
    <div className="container-fluid">
      <div className="row justify-content-center border-bottom border-2 border-dark p-2">
        <div className="col-12 col-sm-9 col-lg-6">
          <form className="input-group" onSubmit={handleSubmit}>
            <input 
              id="searchquery"
              type="text"
              ref={inputRef}
              className="form-control rounded fw-bold fs-5 ps-2"
              title="Type your keywords here"
              placeholder="Search..."
              style={{ minWidth: '200px' }}
              value={query}
              onInput={handleInput}
            />
            <button
              id="searchbutton"
              type="submit"
              title="Run the search"
              className="ms-2 mt-1 p-0 py-1 px-2 rounded fw-bold btn btn-sm btn-success gobtn"
            >
              GO
            </button>
            <button
              type="button"
              className="ms-2 mt-1 p-0 py-1 px-2 rounded fw-bold btn btn-sm btn-danger"
              title="Clear previous search queries"
              onClick={handleClear}
            >
              CLEAR
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}