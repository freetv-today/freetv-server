import { useLocation } from 'preact-iso';
import { useDebugLog } from '@/hooks/useDebugLog';

/**
 * useAdminLogout - Custom hook that returns a logout handler for the admin dashboard.
 * @returns {Function} Logout handler function.
 * Usage: const handleLogout = useAdminLogout();
 */

export function useAdminLogout() {

	const log = useDebugLog();
	const { route } = useLocation();
	
	return async function handleLogout(e) {
		if (e && e.preventDefault) e.preventDefault();
		log('The log out button was clicked');
		if (!window.confirm('Are you sure you want to log out?')) {
			log('The log out operation was cancelled');
			return;
		} else {
			try {
				await fetch('/api/admin/logout.php', {
					method: 'POST',
					credentials: 'include',
					headers: { 'Content-Type': 'application/json' },
				});
			} catch {
				// ignore error, just redirect
			}
			log('Logging out of Admin Dashboard');
			route('/admin?loggedout=1');			
		}
	};
}
