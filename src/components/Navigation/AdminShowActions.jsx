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
    <div className="d-flex flex-lg-row flex-column gap-1 w-100">
      <button
        className="btn tinybtn btn-primary w-100"
        title={`Edit \"${show.title}\"`}
        onClick={() => onEdit && onEdit(show)}
      >
        Edit
      </button>
      <button
        className="btn tinybtn btn-danger w-100"
        title={`Delete \"${show.title}\"`}
        onClick={() => onDelete && onDelete(show)}
      >
        Delete
      </button>
      <button
        className="btn tinybtn btn-warning w-100"
        title={`Test \"${show.title}\"`}
        onClick={() => onTest && onTest(show)}
      >
        Test
      </button>
    </div>
  );
}
