import { useMemo } from 'preact/hooks';
import { capitalizeFirstLetter } from '@/utils/utils';
import { AdminShowActions } from '@components/Navigation/AdminShowActions';

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
      if (sortBy === 'title') {
        valA = valA.replace(/^the\s+/i, '').toLowerCase();
        valB = valB.replace(/^the\s+/i, '').toLowerCase();
      } else {
        if (typeof valA === 'string') valA = valA.toLowerCase();
        if (typeof valB === 'string') valB = valB.toLowerCase();
      }
      if (valA < valB) return sortOrder === 'asc' ? -1 : 1;
      if (valA > valB) return sortOrder === 'asc' ? 1 : -1;
      return 0;
    });
    return data;
  }, [shows, sortBy, sortOrder, filterCategory, hideDisabled]);

  // Determine grouping information for visual indication
  const groupInfo = useMemo(() => {
    const groupPositions = {};
    const groups = {};
    
    // First, identify all groups and their positions
    filteredShows.forEach((show, index) => {
      if (show.group) {
        if (!groups[show.group]) {
          groups[show.group] = [];
        }
        groups[show.group].push(index);
      }
    });

    // Determine which rows should have group styling
    Object.keys(groups).forEach(groupName => {
      const positions = groups[groupName];
      if (positions.length >= 2) { // Only groups with 2+ items
        positions.forEach((pos, idx) => {
          const isContiguous = positions.length > 1 && 
            (idx === 0 ? positions[idx + 1] === pos + 1 : 
             idx === positions.length - 1 ? positions[idx - 1] === pos - 1 :
             positions[idx - 1] === pos - 1 || positions[idx + 1] === pos + 1);
          
          groupPositions[pos] = {
            groupName,
            isGrouped: true,
            isFirst: idx === 0,
            isLast: idx === positions.length - 1,
            isContiguous,
            totalInGroup: positions.length
          };
        });
      }
    });

    return groupPositions;
  }, [filteredShows]);

  // Helper to render carat for a column
  function renderSortCarat(column) {
    if (sortBy !== column) return null;
    return (
      <span style={{ fontSize: '0.9em', marginLeft: 4, userSelect: 'none' }}>
        {sortOrder === 'asc' ? ' ▲' : ' ▼'}
      </span>
    );
  }

  return (
    <table className="table table-striped table-hover align-middle mt-5 mb-5">
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
        {filteredShows.length === 0 ? (
          <tr>
            <td colSpan={4} className="text-center text-muted py-4">No shows to display.</td>
          </tr>
        ) : (
          filteredShows.map((show, idx) => {
            const groupData = groupInfo[idx];
            let rowClass = show.status === 'disabled' ? 'disabled-item' : '';
            
            if (groupData && groupData.isGrouped) {
              rowClass += ' grouped-item';
              if (groupData.isContiguous) {
                if (groupData.isFirst) rowClass += ' group-first';
                if (groupData.isLast) rowClass += ' group-last';
                if (!groupData.isFirst && !groupData.isLast) rowClass += ' group-middle';
              } else {
                rowClass += ' group-individual';
              }
            }

            return (
              <tr key={show.id || show.title + idx} className={rowClass.trim()}>
                <td>{show.category ? capitalizeFirstLetter(show.category) : ''}</td>
                <td>
                  {show.title}
                  {groupData && groupData.isGrouped && (
                    <small className="text-muted ms-2" title={`Part of group: ${groupData.groupName}`}>
                      📁
                    </small>
                  )}
                </td>
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
            );
          })
        )}
      </tbody>
    </table>
  );
}
