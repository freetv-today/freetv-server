import { capitalizeFirstLetter } from '@/utils';
import { AdminShowActions } from '@components/Admin/Navigation/AdminShowActions';

/**
 * AdminSearchResults - displays admin search results in a table with admin actions
 * Props:
 *   results: array of show objects
 *   onEdit: function(show) => void
 *   onDelete: function(show) => void
 *   onTest: function(show) => void
 *   onStatusToggle: function(show) => void
 */
export function AdminSearchResults({ results = [], onEdit, onDelete, onTest, onStatusToggle }) {
  if (!results) return null;
  if (results.length === 0) {
    return <p class="fs-4 text-center text-danger fw-bold mt-5">No search results found</p>;
  }

  return (
    <div className="table-responsive my-4">
      <table className="table table-striped table-hover align-middle mb-5">
        <thead>
          <tr>
            <th>Category</th>
            <th>Title</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {results.map((show, idx) => (
            <tr key={show.id || show.title + idx} className={show.status === 'disabled' ? 'disabled-item' : ''}>
              <td>{show.category ? capitalizeFirstLetter(show.category) : ''}</td>
              <td>{show.title}</td>
              <td>
                <button
                  className={`btn tinybtn ${show.status === 'disabled' ? 'btn-outline-danger' : 'btn-outline-success'}`}
                  onClick={() => onStatusToggle && onStatusToggle(show)}
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
    </div>
  );
}
