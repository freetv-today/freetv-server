import { useMemo } from 'preact/hooks';
import { capitalizeFirstLetter } from '@/utils';
import { AdminShowActions } from '@components/Admin/Navigation/AdminShowActions';

/**
 * AdminDashboardTable - displays a table of shows with per-row controls (Edit, Delete, Status toggle)
 * Props:
 *   shows: array of show objects
 *   onEdit: function(show) => void
 *   onDelete: function(show) => void
 *   onStatusToggle: function(show) => void
 *   onTest: function(show) => void
 *   sortBy: string (column)
 *   sortOrder: 'asc' | 'desc'
 *   filterCategory: string | null
 *   hideDisabled: boolean
 *   onSort: function(column: string) => void
 */
export function AdminDashboardTable({
  shows = [],
  onEdit,
  onDelete,
  onStatusToggle,
  onTest,
  sortBy = 'title',
  sortOrder = 'asc',
  filterCategory = null,
  hideDisabled = false,
  onSort,
}) {
  // Filter and sort shows
  const filteredShows = useMemo(() => {
    let data = [...shows];
    if (filterCategory) {
      data = data.filter(show => show.category === filterCategory);
    }
    if (hideDisabled) {
      data = data.filter(show => show.status !== 'disabled');
    }
    data.sort((a, b) => {
      let valA = a[sortBy] || '';
      let valB = b[sortBy] || '';
      if (typeof valA === 'string') valA = valA.toLowerCase();
      if (typeof valB === 'string') valB = valB.toLowerCase();
      if (valA < valB) return sortOrder === 'asc' ? -1 : 1;
      if (valA > valB) return sortOrder === 'asc' ? 1 : -1;
      return 0;
    });
    return data;
  }, [shows, sortBy, sortOrder, filterCategory, hideDisabled]);

  // Helper to render carat for a column
  function renderSortCarat(column) {
    if (sortBy !== column) return null;
    return (
      <span style={{ fontSize: '0.9em', marginLeft: 4, userSelect: 'none' }}>
        {sortOrder === 'asc' ? '▲' : '▼'}
      </span>
    );
  }

  return (
    <table className="table table-striped table-hover align-middle mb-5">
      <thead>
        <tr>
          <th
            style={{ cursor: 'pointer', color: '#111', fontWeight: 'bold', userSelect: 'none' }}
            onClick={onSort ? () => onSort('category') : undefined}
          >
            Category{renderSortCarat('category')}
          </th>
          <th
            style={{ cursor: 'pointer', color: '#111', fontWeight: 'bold', userSelect: 'none' }}
            onClick={onSort ? () => onSort('title') : undefined}
          >
            Title{renderSortCarat('title')}
          </th>
          <th
            style={{ cursor: 'pointer', color: '#111', fontWeight: 'bold', userSelect: 'none' }}
            onClick={onSort ? () => onSort('status') : undefined}
          >
            Status{renderSortCarat('status')}
          </th>
          <th style={{ color: '#111', fontWeight: 'bold' }}>Actions</th>
        </tr>
      </thead>
      <tbody>
        {filteredShows.map((show, idx) => (
          <tr key={show.id || show.title + idx} className={show.status === 'disabled' ? 'disabled-item' : ''}>
            <td>{show.category ? capitalizeFirstLetter(show.category) : ''}</td>
            <td>{show.title}</td>
            <td>
              <button
                className={`btn tinybtn ${show.status === 'disabled' ? 'btn-outline-danger' : 'btn-outline-success'}`}
                onClick={() => onStatusToggle(show)}
                title={show.status === 'disabled' ? 'Enable' : 'Disable'}
              >
                {show.status === 'disabled' ? 'Disabled' : 'Active'}
              </button>
            </td>
            <td>
              <AdminShowActions
                show={show}
                onEdit={onEdit}
                onDelete={onDelete}
                onTest={onTest}
              />
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
