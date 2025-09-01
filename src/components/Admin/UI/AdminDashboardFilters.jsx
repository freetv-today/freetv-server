import { useMemo } from 'preact/hooks';
import { capitalizeFirstLetter } from '@/utils.js';

/**
 * AdminDashboardFilters - filter controls for dashboard table
 * Props:
 *   shows: array of show objects (for computing unique categories)
 *   filterCategory: string | null
 *   setFilterCategory: function
 *   hideDisabled: boolean
 *   setHideDisabled: function
 */
export function AdminDashboardFilters({
  shows = [],
  filterCategory,
  setFilterCategory,
  hideDisabled,
  setHideDisabled,
  playlistName = '',
}) {
  // Compute unique categories
  const categories = useMemo(() => {
    const cats = new Set();
    shows.forEach(show => {
      if (show.category) cats.add(show.category);
    });
    return Array.from(cats).sort();
  }, [shows]);

  return (
    <>
      <div className="row align-items-center mb-5 g-2">
        {/* Left: Category Selector */}
        <div className="col-12 col-md-4 d-flex align-items-center">
          <label className="form-label me-2 mb-0 small">Category:</label>
          <select
            className="form-select form-select-sm d-inline-block w-auto small"
            value={filterCategory || ''}
            onChange={e => setFilterCategory(e.currentTarget.value || null)}
          >
            <option value="">All</option>
            {categories.map(cat => (
              <option key={cat} value={cat}>{capitalizeFirstLetter(cat)}</option>
            ))}
          </select>
          {filterCategory && (
            <button className="btn btn-link btn-sm ms-2" onClick={() => setFilterCategory(null)}>
              Clear
            </button>
          )}
        </div>
        {/* Center: Current Playlist */}
        <div className="col-12 col-md-4 text-center">
          {playlistName && (
            <div className="small"><span className="text-nowrap fw-bold">Current Playlist: </span><br/><span className="font-monospace customBlue">"{playlistName}"</span></div>
          )}
        </div>
        {/* Right: Hide Disabled */}
        <div className="col-12 col-md-4 d-flex justify-content-md-end align-items-center">
          <div className="form-check">
            <input
              className="form-check-input pt-2"
              type="checkbox"
              id="hideDisabledCheckbox"
              checked={hideDisabled}
              onChange={e => setHideDisabled(e.currentTarget.checked)}
            />
            <label className="form-check-label small" htmlFor="hideDisabledCheckbox">
              Hide disabled items
            </label>
          </div>
        </div>
      </div>
    </>
  );
}
