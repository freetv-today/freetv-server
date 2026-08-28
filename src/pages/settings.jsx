import { useLocation } from 'preact-iso';
import { useEffect, useRef, useState } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { playlistSignal } from '@signals/playlistSignal';
import { AdminMessage } from '@/components/UI/AdminMessage';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';
import { createPath } from '@/utils/env';
import { refreshPublicationStatus } from '@signals/publicationStatusSignal';

export function AdminSettings() {
    /** @type {import('preact').RefObject<Object>} */
    const initialFormRef = useRef();
    const log = useDebugLog();
    const { route } = useLocation();
    const { loading: playlistLoading, error: playlistError } = playlistSignal.value;

    // Show loading spinner when playlist is loading
    if (playlistLoading) return <SpinnerLoadingAppData />;
    if (playlistError) return <div className="alert alert-danger mt-4">{playlistError}</div>;

    const [loading, setLoading] = useState(true);
    const [form, setForm] = useState({ show_ads: false });
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        async function loadSettings() {
            try {
                const response = await fetch('/api/admin/edit-config.php');
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to load settings');
                }
                if (typeof data.settings?.show_ads !== 'boolean') {
                    throw new Error('Invalid settings response');
                }

                const newForm = { show_ads: data.settings.show_ads };
                setForm(newForm);
                initialFormRef.current = newForm;
            } catch (error) {
                log('Error loading settings:', error);
                setAdminMsg({
                    type: 'danger',
                    text: error.message || 'Failed to load settings'
                });
            } finally {
                setLoading(false);
            }
        }

        document.title = 'Free TV: Admin Dashboard - Settings';
        log('Rendered Admin Settings page (pages/settings.jsx)');
        loadSettings();
    }, []);

    function isFormChanged() {
        return initialFormRef.current?.show_ads !== form.show_ads;
    }

    function handleInput(event) {
        setForm({ show_ads: event.currentTarget.checked });
    }

    function handleCancel() {
        route(createPath('/dashboard'));
    }

    async function handleSave(event) {
        event.preventDefault();
        setSaving(true);

        try {
            const response = await fetch('/api/admin/edit-config.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ show_ads: form.show_ads })
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to save settings.');
            }
            void refreshPublicationStatus();

            if (typeof data.settings?.show_ads !== 'boolean') {
                throw new Error('Invalid settings response');
            }

            const normalizedForm = { show_ads: data.settings.show_ads };
            setForm(normalizedForm);
            initialFormRef.current = normalizedForm;
            setAdminMsg({ type: 'success', text: 'Settings saved!' });
            window.scrollTo({ top: 0, behavior: 'auto' });
        } catch (error) {
            setAdminMsg({
                type: 'danger',
                text: error.message || 'Network error.'
            });
        } finally {
            setSaving(false);
        }
    }

    if (loading) {
        return (
            <div className="container py-4" style={{ maxWidth: 650 }}>
                <h2 className="text-center mb-4">Configuration Settings</h2>
                <div className="text-center mt-5">
                    <div className="spinner-border text-primary" role="status">
                        <span className="visually-hidden">Loading...</span>
                    </div>
                    <div className="mt-2">Loading settings...</div>
                </div>
            </div>
        );
    }

    return (
        <div className="container py-4" style={{ maxWidth: 650 }}>
            <h2 className="text-center mb-4">Configuration Settings</h2>

            <AdminMessage />

            <form className="p-3 bg-white" onSubmit={handleSave}>
                <div className="mb-4 text-center settingsAppWrapper">
                    <h4 className="mb-4">Application Options:</h4>
                    <div className="mx-auto w-100 w-md-auto" style={{ maxWidth: 250 }}>
                        <div className="mb-3">
                            <div className="d-flex justify-content-between align-items-center flex-wrap">
                                <label className="form-label mb-0" htmlFor="show_ads" style={{ minWidth: 120 }}>
                                    Show Ads
                                </label>
                                <div className="form-check form-switch" style={{ fontSize: '1.15em' }}>
                                    <input
                                        className="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="show_ads"
                                        name="show_ads"
                                        checked={form.show_ads}
                                        onChange={handleInput}
                                        disabled={saving}
                                        style={{ transform: 'scale(1.15)' }}
                                    />
                                    <label className="form-check-label visually-hidden" htmlFor="show_ads">
                                        Show Ads
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="row mt-5 mb-3">
                    <div className="col-12 d-flex justify-content-center gap-2">
                        <button type="button" className="btn btn-secondary" onClick={handleCancel} disabled={saving}>
                            Cancel
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={saving || !isFormChanged()}>
                            {saving ? 'Saving...' : 'Save'}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    );
}
