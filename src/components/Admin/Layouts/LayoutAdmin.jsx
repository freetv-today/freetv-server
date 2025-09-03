import { useEffect } from 'preact/hooks';
import { NavbarAdmin } from '@components/Admin/Navigation/NavbarAdmin';
import { useProblemCount } from '@hooks/Admin/useProblemCount';

export function LayoutAdmin ({ children }) {

  const problemCount = useProblemCount();
  useEffect(() => {
    // Only add the link if it doesn't already exist
    if (!document.getElementById('admin-css')) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = '/assets/admin.css';
      link.id = 'admin-css';
      document.head.appendChild(link);
    }
    // Remove on unmount
    return () => {
      const el = document.getElementById('admin-css');
      if (el) el.remove();
    };
  }, []);

  return (
    <>
      <NavbarAdmin problemCount={problemCount} />
      {children}
    </>
  );
}