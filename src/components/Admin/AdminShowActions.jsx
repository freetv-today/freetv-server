/**
 * AdminShowActions - renders Edit, Delete, and Test buttons for a show row
 * Props:
 *   show: the show object
 *   onEdit: function(show) => void
 *   onDelete: function(show) => void
 *   onTest: function(show) => void
 */
export function AdminShowActions({ show, onEdit, onDelete, onTest }) {
  return (
    <>
      <button
        className="btn tinybtn btn-primary p-1 me-2 mt-1"
        title={`Edit \"${show.title}\"`}
        onClick={() => onEdit && onEdit(show)}
      >
        Edit
      </button>
      <button
        className="btn tinybtn btn-danger p-1 me-2 mt-1"
        title={`Delete \"${show.title}\"`}
        onClick={() => onDelete && onDelete(show)}
      >
        Delete
      </button>
      <button
        className="btn tinybtn btn-warning p-1 me-2 mt-1"
        title={`Test \"${show.title}\"`}
        onClick={() => onTest && onTest(show)}
      >
        Test
      </button>
    </>
  );
}
