import { useEffect } from 'preact/hooks';

/**
 * AdminTestVideoModal - Modal for testing a video via archive.org embed
 * @param {Object} props
 * @param {boolean} props.show - Whether the modal is visible
 * @param {() => void} props.onClose - Function to close the modal
 * @param {Object} props.showData - The show object (must have identifier, title)
 */
export function AdminTestVideoModal({ show, onClose, showData }) {
  useEffect(() => {
    if (!show) return;
    // Prevent background scroll when modal is open
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = ''; };
  }, [show]);

  if (!show || !showData) return null;
  const { identifier, title } = showData;
  const src = `https://archive.org/embed/${identifier}?playlist=1`;

  return (
    <div class={`modal fade${show ? ' show d-block' : ''}`} tabIndex={-1} style={show ? { backgroundColor: 'rgba(0,0,0,0.5)' } : {}}>
      <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Test Video: {title}</h5>
            <button type="button" class="btn-close" aria-label="Close" onClick={onClose}></button>
          </div>
          <div class="modal-body p-0" style={{ minHeight: 400 }}>
            <iframe
              src={src}
              width="100%"
              height="480"
              frameBorder="0"
              allowFullScreen
              style={{ display: 'block', width: '100%', minHeight: 400 }}
              title={`Test Video: ${title}`}
            ></iframe>
          </div>
        </div>
      </div>
    </div>
  );
}
