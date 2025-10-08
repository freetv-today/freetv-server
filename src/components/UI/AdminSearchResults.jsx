import { capitalizeFirstLetter } from '@/utils/utils';
import { AdminShowActions } from '@components/Navigation/AdminShowActions';
import { useAdminPlaylistData } from '@hooks/useAdminPlaylistData';

/**
 * AdminSearchResults - displays admin search results in a table with admin actions
 * @param {Object} props
 * @param {Array<Object>} [props.results=[]] - Array of show objects to display
 * @param {function(Object): void} props.onEdit - Called when edit button is clicked
 * @param {function(Object): void} props.onDelete - Called when delete button is clicked
 * @param {function(Object): void} props.onTest - Called when test button is clicked
 * @param {function(Object): void} props.onStatusToggle - Called when status toggle is clicked
 * @param {string} [props.statusUpdatingIdentifier] - Identifier of show currently being updated
 */

export function AdminSearchResults({ results = [], onEdit, onDelete, onTest, onStatusToggle, statusUpdatingIdentifier }) {

  if (!results) return null;
  if (results.length === 0) {
    return <p className="fs-4 text-center text-danger fw-bold mt-5">No search results found</p>;
  }

  const { getCurrentPlaylistTitle } = useAdminPlaylistData();

  return (
    <>
      <h3 className="text-center fs-5 my-5">Search results from playlist: "{getCurrentPlaylistTitle()}"</h3>
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
                  {statusUpdatingIdentifier === show.identifier ? (
                    <button className={`btn tinybtn btn-outline-secondary pt-1`} type="button" disabled>
                      <span className="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    </button>
                  ) : (
                    <button
                      className={`btn tinybtn ${show.status === 'disabled' ? 'btn-outline-danger' : 'btn-outline-success'}`}
                      onClick={() => onStatusToggle && onStatusToggle(show)}
                      title={show.status === 'disabled' ? 'Enable' : 'Disable'}
                    >
                      {show.status === 'disabled' ? 'Disabled' : 'Active'}
                    </button>
                  )}
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
    </>
  );
}
