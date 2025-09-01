import { useLocalStorage } from '@/hooks/useLocalStorage';
import { useEffect } from 'preact/hooks';

/**
 * AdminMessage - Displays a dismissible alert for admin actions using localStorage.
 * Usage: Place <AdminMessage /> at the top of your admin dashboard page.
 * Set messages from anywhere using setAdminMsg({ type: 'success'|'danger'|'info', text: '...' })
 */
export function AdminMessage() {
  const [adminMsg, setAdminMsg] = useLocalStorage('adminMsg', null);

  // Listen for storage events to update adminMsg if changed in another tab or by code
  useEffect(() => {
    function handleStorage(e) {
      if (e.key === 'adminMsg') {
        try {
          setAdminMsg(e.newValue ? JSON.parse(e.newValue) : null);
        } catch {
          setAdminMsg(null);
        }
      }
    }
    window.addEventListener('storage', handleStorage);
    return () => window.removeEventListener('storage', handleStorage);
  }, [setAdminMsg]);

  if (!adminMsg) return null;

  function handleDismiss() {
    setAdminMsg(null);
    localStorage.removeItem('adminMsg');
  }

  return (
    <div className={`alert alert-${adminMsg.type || 'info'} mt-2`} role="alert">
      {adminMsg.text}
      <button
        type="button"
        className="btn-close float-end"
        aria-label="Close"
        onClick={handleDismiss}
      ></button>
    </div>
  );
}
