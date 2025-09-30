import { useEffect } from 'preact/hooks';
import { adminMsgSignal, clearAdminMsg } from '@/signals/adminMessageSignal';

/**
 * AdminMessage - Displays a dismissible alert for admin actions using localStorage.
 * Usage: Place <AdminMessage /> at the top of your admin dashboard page.
 * Set messages from anywhere using setAdminMsg({ type: 'success'|'danger'|'info', text: '...' })
 */

export function AdminMessage() {
  
  const adminMsg = adminMsgSignal.value;

  // Auto-dismiss after 4 seconds
  useEffect(() => {
    if (!adminMsg) return;
    const timer = setTimeout(() => {
      clearAdminMsg();
    }, 4000);
    return () => clearTimeout(timer);
  }, [adminMsg]);

  if (!adminMsg) return null;
  return (
    <div className={`alert alert-${adminMsg.type || 'info'} mt-2`} role="alert">
      {adminMsg.text}
      <button
        type="button"
        className="btn-close float-end"
        aria-label="Close"
        onClick={clearAdminMsg}
      ></button>
    </div>
  );
}
