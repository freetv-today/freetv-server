import { useEffect, useState } from 'preact/hooks';
import { useLocation } from 'preact-iso';

/**
 * useAdminSession - Custom hook to check for a valid admin session and redirect if not logged in.
 * @returns {Object|null} User object if logged in, or null if not.
 */

export function useAdminSession() {
  const [user, setUser] = useState(null);
  const { route } = useLocation();

  useEffect(() => {
    let isMounted = true;
    fetch('/api/admin/session.php', { credentials: 'include' })
      .then(res => res.json())
      .then(data => {
        if (!data.loggedIn) {
          route('/admin');
        } else if (isMounted) {
          setUser(data.user);
        }
      });
    return () => { isMounted = false; };
  }, [route]);

  return user;
}
