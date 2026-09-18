import { useEffect } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { adminMsgSignal, clearAdminMsg } from '@/signals/adminMessageSignal';

/**
 * AdminMessage - Displays a dismissible alert for admin actions.
 * Usage: Place <AdminMessage /> at the top of your admin dashboard page.
 * Set messages from anywhere using setAdminMsg({ type: 'success'|'danger'|'info', text: '...' })
 */

export function AdminMessage() {
  const { url } = useLocation();
  const adminMsg = adminMsgSignal.value;
  const currentPath = url.split(/[?#]/, 1)[0];
  const isTargetPage = !adminMsg?.targetPath || adminMsg.targetPath === currentPath;

  useEffect(() => {
    if (!adminMsg || !isTargetPage) return undefined;

    const timer = setTimeout(() => {
      if (adminMsgSignal.value === adminMsg) clearAdminMsg();
    }, 4000);

    return () => {
      clearTimeout(timer);
      if (adminMsgSignal.value === adminMsg) clearAdminMsg();
    };
  }, [adminMsg, isTargetPage]);

  if (!adminMsg || !isTargetPage) return null;
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
