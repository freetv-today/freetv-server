import { NavbarAdmin } from '@components/Navigation/NavbarAdmin';
import { useProblemCount } from '@hooks/useProblemCount';
import { useAdminSession } from '@hooks/useAdminSession';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { useEffect } from 'preact/hooks';

export function LayoutAdmin({ children }) {

  const problemCount = useProblemCount();
  const user = useAdminSession();

  useEffect(() => {
    if (user === false) {
      setAdminMsg({ type: 'danger', text: 'Your session has expired!' });
    }
  }, [user]);

  if (user === null || user === false) return null;

  return (
    <>
      <NavbarAdmin problemCount={problemCount} />
      {children}
    </>
  );
}