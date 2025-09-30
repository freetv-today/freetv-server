import { useEffect, useState } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { useDebugLog } from '@/hooks/useDebugLog';
import { AdminMessage } from '@/components/UI/AdminMessage';
import { setAdminMsg } from '@/signals/adminMessageSignal';

export function AdminLogin() {

    const log = useDebugLog();
    const { route } = useLocation();
    const [loading, setLoading] = useState(false);
    const [checkingSession, setCheckingSession] = useState(true);

    // Check for valid admin session on mount
    useEffect(() => {
        let isMounted = true;
        fetch('/api/admin/session.php', { credentials: 'include' })
            .then(res => res.json())
            .then(data => {
                if (isMounted && data.loggedIn) {
                    log('You are already logged in. Redirected to Admin Dashboard');
                    route('/dashboard');
                } else {
                    log('Rendered Admin Dashboard login (pages/index.jsx)');
                    log('Please log in to your Admin Dashboard account');
                    setCheckingSession(false);
                }
            })
            .catch(() => {
                if (isMounted) setCheckingSession(false);
            });
        return () => { isMounted = false; };
    }, [route]);

    useEffect(() => {
        // add admin.css link if it doesn't already exist
        if (!document.getElementById('admin-css')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = '/assets/admin.css';
            link.id = 'admin-css';
            document.head.appendChild(link);
        }
        // Remove admin.css on unmount
        return () => {
            const el = document.getElementById('admin-css');
            if (el) el.parentNode.removeChild(el);
        };
    }, []);

    useEffect(() => {
		document.title = "Free TV: Admin Dashboard";
	}, []);
        
    async function handleLogin(e) {
        e.preventDefault();
        setLoading(true);
        const form = e.target;
        const user = form.user.value;
        const pass = form.pass.value;
        try {
            const res = await fetch('/api/admin/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ user, pass })
            });
            const data = await res.json();
            setLoading(false);
            if (data.success) {
                route('/dashboard');
            } else {
                setAdminMsg({ type: 'danger', text: data.message || 'Login failed.' });
            }
        } catch {
            setLoading(false);
            setAdminMsg({ type: 'danger', text: 'Network error. Please try again.' });
        }
    }


    if (checkingSession) {
        return null;
    }

    return (
        <div id="admintarg" className="container-fluid outerwrapper">
            <AdminMessage />
            <div className="d-flex flex-column">
                <div id="loginform" className="form-signin shadow-lg loginform">
                    <form onSubmit={handleLogin} className="w-75 mx-auto">
                        <div className="d-flex flex-column nowrap justify-content-center align-items-center mb-3">
                            <div className="d-flex align-items-center">
                                <img
                                    src="/assets/freetv.png"
                                    alt="Free TV Logo"
                                    title="Free TV"
                                    width="125"
                                />
                            </div>
                            <div
                                id="dashhead"
                                className="fw-bold font-monospace fs-1 text-nowrap"
                            >
                                Admin Dashboard
                            </div>
                        </div>
                        <div className="form-floating mb-3">
                            <input
                                type="text"
                                id="user"
                                name="user"
                                className="form-control"
                                placeholder="Username"
                                autoFocus
                                disabled={loading}
                                required
                            />
                            <label htmlFor="user">Username</label>
                        </div>
                        <div className="form-floating mb-3">
                            <input
                                type="password"
                                id="pass"
                                name="pass"
                                className="form-control"
                                placeholder="Password"
                                disabled={loading}
                                required
                            />
                            <label htmlFor="pass">Password</label>
                        </div>
                        <div className="text-center my-5">
                            <button
                                type="submit"
                                className="btn btn-primary py-3 px-5 shadow border border-2 border-dark fw-bold fs-5"
                                disabled={loading}
                            >
                                {loading ? 'Signing In...' : 'Sign In'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}