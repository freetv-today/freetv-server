import { useMemo } from 'preact/hooks';
import { capitalizeFirstLetter } from '@/utils/utils';
import { createPath } from '@/utils/env';
import { useAdminAuth } from '@context/AdminSessionContext';

/**
 * AdminDashboardFilters - filter controls for dashboard table
 * Props:
 *   shows: array of show objects (for computing unique categories)
 *   filterCategory: string | null
 *   setFilterCategory: function
 *   hideDisabled: boolean
 *   setHideDisabled: function
 *   playlistName: string
 *   onMetaClick: function
 *   onInfoClick: function
 */
export function AdminDashboardFilters({
  shows = [],
  filterCategory,
  setFilterCategory,
  hideDisabled,
  setHideDisabled,
  playlistName = '',
  onMetaClick,
  onInfoClick,
}) {
  const { canEditContent } = useAdminAuth();
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
      <div className="row align-items-center mb-2 g-2 p-2 border border-1 border-dark">

        {/* Center: Current Playlist (on top for small screens) */}
        <div className="col-12 col-md-4 order-1 order-md-2 mb-2 border border-1 border-dark bg-info-subtle rounded-pill">
          <div className="d-flex align-items-center flex-md-column flex-xl-row gap-2 px-2 py-1">

            <div className="small flex-grow-1 text-center" style={{ minWidth: 0 }}>
              {playlistName && (
                <>
                  <span className="d-md-block d-xl-inline text-nowrap fw-bold">
                    Current Playlist:
                  </span>
                  <span className="d-md-block d-xl-inline font-monospace">
                    <span className="d-none d-xl-inline"> </span>
                    "{playlistName}"
                  </span>
                </>
              )}
            </div>

            <div className="d-flex align-items-center justify-content-center flex-shrink-0 gap-1">
              {canEditContent && (
                <button
                  type="button"
                  className="btn border-0 bg-transparent p-1 lh-1"
                  title="Edit Playlist Metadata"
                  aria-label="Edit Playlist Metadata"
                  onClick={onMetaClick}
                >
                  <img
                    src={createPath('/assets/gear-fill.svg')}
                    width="20"
                    height="20"
                    alt=""
                    aria-hidden="true"
                  />
                </button>
              )}

              <button
                type="button"
                className="btn border-0 bg-transparent p-1 lh-1"
                title="Current Playlist Information"
                aria-label="Current Playlist Information"
                onClick={onInfoClick}
              >
                <img
                  src={createPath('/assets/info-circle-black.svg')}
                  width="22"
                  height="22"
                  alt=""
                  aria-hidden="true"
                />
              </button>
            </div>

          </div>
        </div>

        {/* Left: Category Selector */}
        <div className="col-6 col-md-4 order-2 order-md-1 d-flex align-items-center border border-1 border-danger">
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
        <div className="col-6 col-md-4 order-3 order-md-3 d-flex justify-content-end align-items-center border border-1 border-danger">
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
