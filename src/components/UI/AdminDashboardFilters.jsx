import { useMemo } from 'preact/hooks';
import { capitalizeFirstLetter } from '@/utils';

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
      <div className="row align-items-center mb-2 g-2 p-2">

        {/* Center: Current Playlist (on top for small screens) */}
        <div className="col-12 col-md-4 order-1 order-md-2 text-center mb-2 border border-1 border-dark bg-info-subtle rounded-pill">
          {playlistName && (
            <div className="small">
              <span className="text-nowrap fw-bold">Current Playlist: </span>
              <br />
              <span className="font-monospace">"{playlistName}"</span>
            </div>
          )}
        </div>

        {/* Left: Category Selector */}
        <div className="col-6 col-md-4 order-2 order-md-1 d-flex align-items-center">
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

        {/* Right: Hide Disabled */}
        <div className="col-6 col-md-4 order-3 order-md-3 d-flex justify-content-end align-items-center">
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
