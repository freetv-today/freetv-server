import { useEffect, useState } from 'preact/hooks';
import { useLocation } from 'preact-iso';

export function AdminLogin() {

    const { url, route } = useLocation();
    const params = new URLSearchParams(url.split('?')[1] || '');
    // Log when Admin page mounts
    console.log('[Admin] page mount, url:', url);
    const loggedOut = params.get('loggedout') === '1';

    const [error, setError] = useState('');
    const [success, setSuccess] = useState(loggedOut ? 'You have been logged out of your account.' : '');
    const [loading, setLoading] = useState(false);
    const [checkingSession, setCheckingSession] = useState(true);

    // Check for valid admin session on mount
    useEffect(() => {
        let isMounted = true;
        fetch('/api/admin/session.php', { credentials: 'include' })
            .then(res => res.json())
            .then(data => {
                if (isMounted && data.loggedIn) {
                    route('/dashboard');
                } else {
                    setCheckingSession(false);
                }
            })
            .catch(() => {
                if (isMounted) setCheckingSession(false);
            });
        return () => { isMounted = false; };
    }, [route]);

    useEffect(() => {
        document.title = "Free TV: Admin";
        // add the css link if it doesn't already exist
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


    // Remove ?loggedout=1 from URL after showing the alert
    useEffect(() => {
        if (loggedOut) {
            const baseUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, baseUrl);
        }
    }, [loggedOut]);

    // Auto-dismiss the success alert after 4 seconds
    useEffect(() => {
        if (success) {
            const timer = setTimeout(() => setSuccess(''), 4000);
            return () => clearTimeout(timer);
        }
    }, [success]);    

    // Auto-dismiss the error alert after 4 seconds
    useEffect(() => {
        if (error) {
            const timer = setTimeout(() => setError(''), 4000);
            return () => clearTimeout(timer);
        }
    }, [error]);
        
    async function handleLogin(e) {
        e.preventDefault();
        setError('');
        setSuccess('');
        setLoading(true);
        const form = e.target;
        const user = form.user.value;
        const pass = form.pass.value;
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
            setError(data.message || 'Login failed.');
        }
    }

    function dismissError() {
        setError('');
    }
    function dismissSuccess() {
        setSuccess('');
    }

    if (checkingSession) {
        return null;
    }

    return (
        <div id="admintarg" className="container-fluid outerwrapper">
            <div className="d-flex flex-column">
                <div id="loginform" className="form-signin shadow-lg loginform">
                    <div className="d-flex flex-row flex-nowrap justify-content-between align-middle">
                        <div className="flex-grow-1 p-2">
                            {success && (
                                <div className="alert alert-success alert-dismissible fade show my-4 mx-auto text-center" style="width: 80%;" role="alert">
                                    {success}
                                    <button type="button" className="btn-close" aria-label="Close" onClick={dismissSuccess}></button>
                                </div>
                            )}
                            {error && (
                                <div className="alert alert-danger alert-dismissible fade show my-4 mx-auto text-center" style="width: 80%;" role="alert">
                                    {error}
                                    <button type="button" className="btn-close" aria-label="Close" onClick={dismissError}></button>
                                </div>
                            )}
                        </div>
                    </div>
                    <form onSubmit={handleLogin} className="w-75 mx-auto">
                        <div className="d-flex flex-column nowrap justify-content-center align-items-center mb-3">
                            <div className="d-flex align-items-center">
                                <img
                                    src="/src/assets/freetv.png"
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