import { useState } from 'preact/hooks';
import { useLocalStorage } from '@hooks/useLocalStorage';

export function SearchQueryComponent({ onSearch }) {
  const [searchQuery, setsearchQuery] = useLocalStorage('searchQuery', '');
  const [query, setQuery] = useState(searchQuery || '');

  // Update localStorage whenever query changes
  const handleInput = (e) => {
    const val = e.target.value;
    setQuery(val);
    setsearchQuery(val);
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    setsearchQuery(query.trim());
    onSearch(query.trim());
  };

  const handleClear = () => {
    setQuery('');
    setsearchQuery('');
    onSearch('');
  };

  return (
    <div class="w-100 border-bottom border-2 border-dark p-3">
      <form class="w-75 input-group mx-auto" onSubmit={handleSubmit}>
        <input
          id="searchquery"
          type="text"
          class="form-control rounded fw-bold fs-5 ps-2"
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
          class="ms-2 mt-1 p-0 py-1 px-2 rounded fw-bold btn btn-sm btn-success gobtn"
        >
          GO
        </button>
        <button
          type="button"
          class="ms-2 mt-1 p-0 py-1 px-2 rounded fw-bold btn btn-sm btn-danger"
          title="Clear previous search queries"
          onClick={handleClear}
        >
          CLEAR
        </button>
      </form>
    </div>
  );
}