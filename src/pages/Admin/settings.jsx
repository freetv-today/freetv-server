import { useLocation } from 'preact-iso';
import { useEffect, useState, useRef } from 'preact/hooks';
import { useConfig } from '@/context/ConfigContext';
import { useDebugLog } from '@/hooks/useDebugLog';
import { useAdminSession } from '@hooks/Admin/useAdminSession';

export function AdminSettings() {

    /** @type {import('preact').RefObject<Object>} */
    const initialFormRef = useRef();
    const log = useDebugLog();
    const configData = useConfig();
    const user = useAdminSession();
    const { route } = useLocation();

    // Form state
    const [form, setForm] = useState({
        collector: configData.collector || '',
        gid: configData.gid || '',
        offline: !!configData.offline,
        appdata: !!configData.appdata,
        showads: !!configData.showads,
        modules: !!configData.modules,
        debugmode: !!configData.debugmode
    });
    // Set the initial form state on mount
    useEffect(() => {
        initialFormRef.current = {
            collector: configData.collector || '',
            gid: configData.gid || '',
            offline: !!configData.offline,
            appdata: !!configData.appdata,
            showads: !!configData.showads,
            modules: !!configData.modules,
            debugmode: !!configData.debugmode
        };
    }, []);

    // Helper: shallow compare form to initialFormRef
    function isFormChanged() {
        const initial = initialFormRef.current;
        if (!initial) return false;
        for (const key in form) {
            if (form[key] !== initial[key]) return true;
        }
        return false;
    }

    const [lastUpdated, setLastUpdated] = useState(configData.lastupdated || '');
    const [success, setSuccess] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - Settings";
        log('Rendered Admin Settings page (pages/Admin/settings.jsx)');
    }, []);

    if (!user) return null;

    // Handlers
    function handleInput(e) {
        const { name, value, type, checked } = e.currentTarget;
        setForm(f => ({
            ...f,
            [name]: type === 'checkbox' ? checked : value
        }));
    }

    function handleCancel() {
        route('/dashboard');
    }

    async function handleSave(e) {
        e.preventDefault();
        setSaving(true);
        setSuccess('');
        setError('');
        try {
            const res = await fetch('/api/admin/edit-config.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(form)
            });
            const data = await res.json();
            if (data.success) {
                setSuccess('Settings saved!');
                setLastUpdated(data.lastupdated);
                // Update config context and localStorage
                const newConfig = { ...form, lastupdated: data.lastupdated };
                try {
                    localStorage.setItem('configData', JSON.stringify(newConfig));
                } catch {}
                // Reset initial form state after save
                initialFormRef.current = { ...form };
                // Scroll to top so user sees the alert
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                setError(data.message || 'Failed to save settings.');
            }
        } catch (err) {
            setError('Network error.');
        } finally {
            setSaving(false);
        }
    }
    // Auto-dismiss success alert after 4 seconds
    useEffect(() => {
        if (success) {
            const timer = setTimeout(() => {
                setSuccess('');
            }, 4000);
            return () => clearTimeout(timer);
        }
    }, [success]);

    async function handleRefreshDate() {
        setError('');
        setSuccess('');
        try {
            const res = await fetch('/api/admin/edit-config.php?action=refresh');
            const data = await res.json();
            if (data.success) {
                setLastUpdated(data.lastupdated);
                setSuccess('Timestamp refreshed!');
            } else {
                setError(data.message || 'Failed to refresh timestamp.');
            }
        } catch (err) {
            setError('Network error.');
        }
    }

    return (
        <div className="container py-4" style={{ maxWidth: 650 }}>
            <h2 className="text-center mt-4 mb-4">Application Settings</h2>

            {success && (
                <div className="alert alert-success alert-dismissible fade show mx-auto" role="alert" style={{maxWidth: 400}}>
                    {success}
                    <button type="button" className="btn-close" data-bs-dismiss="alert" aria-label="Close" onClick={() => setSuccess('')}></button>
                </div>
            )}
            {error && (
                <div className="alert alert-danger alert-dismissible fade show mx-auto" role="alert" style={{maxWidth: 400}}>
                    {error}
                    <button type="button" className="btn-close" data-bs-dismiss="alert" aria-label="Close" onClick={() => setError('')}></button>
                </div>
            )}

            <form className="p-3 border border-primary rounded bg-white" onSubmit={handleSave}>
                {/* Last Updated Row */}
                <div className="row mb-3 align-items-center">
                    <div className="col-12 col-md-4 mb-1 mb-md-0">
                        <label className="form-label mb-0">Last Updated</label>
                    </div>
                    <div className="col-12 col-md-6 mb-1 mb-md-0">
                        <input type="text" className="form-control form-control-sm" value={lastUpdated} readOnly disabled />
                    </div>
                    <div className="col-12 col-md-2">
                        <button type="button" className="btn btn-outline-secondary w-100 tinybtn" onClick={handleRefreshDate} disabled={saving}>Refresh</button>
                    </div>
                </div>

                {/* Main Fields */}
                {[ 
                    {label: 'Collector', name: 'collector', type: 'text'},
                    {label: 'Google ID', name: 'gid', type: 'text'}
                ].map(field => (
                    <div className="row mb-3 align-items-center" key={field.name}>
                        <div className="col-12 col-md-4 mb-1 mb-md-0">
                            <label className="form-label mb-0" htmlFor={field.name}>{field.label}</label>
                        </div>
                        <div className="col-12 col-md-8">
                            <input
                                type={field.type}
                                className="form-control form-control-sm"
                                id={field.name}
                                name={field.name}
                                value={form[field.name]}
                                onInput={handleInput}
                                disabled={saving}
                            />
                        </div>
                    </div>
                ))}

                {/* Options Switches */}
                <div className="my-4 text-center">
                    <hr/>
                    <h4 className="my-4">Configuration Options:</h4>
                    <hr/>
                    <div className="mx-auto w-100 w-md-auto" style={{maxWidth: 250}}>
                        {[{
                            id: 'offline', label: 'Offline Mode', checked: form.offline
                        }, {
                            id: 'appdata', label: 'App Data', checked: form.appdata
                        }, {
                            id: 'showads', label: 'Show Ads', checked: form.showads
                        }, {
                            id: 'modules', label: 'Modules', checked: form.modules
                        }, {
                            id: 'debugmode', label: 'Debug Mode', checked: form.debugmode
                        }].map(sw => (
                            <div className="mb-3" key={sw.id}>
                                <div className="d-flex justify-content-between align-items-center flex-wrap">
                                    <label className="form-label mb-0" htmlFor={sw.id} style={{minWidth: 120}}>{sw.label}</label>
                                    <div className="form-check form-switch" style={{fontSize: '1.15em'}}>
                                        <input
                                            className="form-check-input"
                                            type="checkbox"
                                            role="switch"
                                            id={sw.id}
                                            name={sw.id}
                                            checked={sw.checked}
                                            onChange={handleInput}
                                            disabled={saving}
                                            style={{transform: 'scale(1.15)'}}
                                        />
                                        <label className="form-check-label visually-hidden" htmlFor={sw.id}>{sw.label}</label>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <hr/>

                <div className="row my-5">
                    <div className="col-12 d-flex justify-content-center gap-2">
                        <button type="button" className="btn btn-secondary" onClick={handleCancel} disabled={saving}>Cancel</button>
                        <button type="submit" className="btn btn-primary" disabled={saving || !isFormChanged()}>Save</button>
                    </div>
                </div>
            </form>
        </div>
    );
}