import { useLocation } from 'preact-iso';
import { createPath } from '@/utils/env';

/**
 * ErrorPage - Reusable error page component
 * @param {Object} props
 * @param {string} [props.type='Error'] - Error type/title
 * @param {string} [props.message] - Error message
 * @param {boolean} [props.showReload=false] - Show reload button
 * @param {function} [props.onReload] - Reload button callback
 * @param {boolean} [props.showHome=true] - Show home button
 */

export function ErrorPage({ 
  type = 'Error', 
  message = 'Something went wrong. Please try again later.',
  showReload = false,
  onReload,
  showHome = true
}) {
  const { route } = useLocation();

  return (
    <div className="text-center text-danger fw-bold p-4" style={{ marginTop: '100px' }}>
      <h3 className="display-5">{type}</h3>
      <p className="mb-4">{message}</p>
      <img src={createPath('/assets/sadface.svg')} alt="😢" width="100" className="mb-4" /> 
      
      <div className="d-flex justify-content-center gap-2">
        {showReload && onReload && (
          <button className="btn btn-primary" onClick={() => onReload()}>
            Try Again
          </button>
        )}
        {showHome && (
          <button className="btn btn-outline-secondary" onClick={() => route(createPath('/'))}>
            Back to Login
          </button>
        )}
      </div>
    </div>
  );
}