import { useLocation } from 'preact-iso';

/**
 * useAdminLogout - returns a logout handler for admin dashboard
 * Usage: const handleLogout = useAdminLogout();
 */
export function useAdminLogout() {
	const { route } = useLocation();
	return async function handleLogout(e) {
		if (e && e.preventDefault) e.preventDefault();
		if (!window.confirm('Are you sure you want to log out?')) return;
		try {
			await fetch('/api/admin/logout.php', {
				method: 'POST',
				credentials: 'include',
				headers: { 'Content-Type': 'application/json' },
			});
		   } catch {
			   // ignore error, just redirect
		   }
		route('/admin?loggedout=1');
	};
}
