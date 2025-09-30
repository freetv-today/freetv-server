/**
 * ConfirmDeleteModal - Modal for confirming user deletion
 * @param {Object} props
 * @param {boolean} props.show - Whether the modal is visible
 * @param {(reason: 'cancel' | 'save') => void} props.onClose - Function to close the modal, with reason
 * @param {function(Object):void} props.onConfirm - Function called when user confirms delete
 * @param {Object} props.user - The user object to delete
 */
export function ConfirmDeleteModal({ show, onClose, onConfirm, user }) {
  if (!show) return null;
  return (
    <div className="modal d-block" tabIndex={-1} style={{ background: 'rgba(0,0,0,0.3)' }}>
      <div className="modal-dialog">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Delete User</h5>
            <button type="button" className="btn-close" onClick={() => onClose('cancel')}></button>
          </div>
          <div className="modal-body">
            <p>Are you sure you want to delete user <strong>{user?.username}</strong>?</p>
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={() => onClose('cancel')}>Cancel</button>
            <button type="button" className="btn btn-danger" onClick={() => onConfirm(user)}>Delete</button>
          </div>
        </div>
      </div>
    </div>
  );
}
