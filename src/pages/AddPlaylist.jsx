import { useEffect, useState, useRef } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { useDebugLog } from '@/hooks/useDebugLog';
import { loadPlaylists, switchPlaylist } from '@signals/playlistSignal';
import { AdminFilenameInfoModal } from '@/components/Modals/AdminFilenameInfoModal';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { createPath } from '@/utils/env';

export function AddPlaylist() {

    const { route } = useLocation();
    const log = useDebugLog();

    // Form state
    const [form, setForm] = useState({
        dbtitle: '',
        dbversion: '',
        filename: '',
        author: '',
        email: '',
        link: ''
    });
    const [error, setError] = useState(null);
    // Remove local success state; use signal-based admin message
    const [submitting, setSubmitting] = useState(false);
    const [showFilenameModal, setShowFilenameModal] = useState(false);
    const filenameInputRef = useRef(null);

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - Create Playlist";
        log('Rendered Create Playlist page (pages/AddPlaylist.jsx)');
    }, []);

    // Regex for valid filename (no extension)
    const validFilenameRegex = /^[a-zA-Z0-9_-]+$/;

    // Handle input changes
    function handleChange(e) {
        const { name, value } = e.target;
        setForm(f => ({ ...f, [name]: value }));
    }

    // Validate form fields
    function validateForm() {
        if (!form.dbtitle.trim() || !form.filename.trim() || !form.author.trim() || !form.email.trim()) {
            setError('Please fill in all required fields.');
            return false;
        }
        // Remove .json if present for validation
        let base = form.filename.trim();
        if (/\.json$/i.test(base)) base = base.replace(/\.json$/i, '');
        if (!validFilenameRegex.test(base)) {
            setError('Invalid file name. Only letters, numbers, dashes, and underscores are allowed.');
            // Focus the filename input
            setTimeout(() => {
                if (filenameInputRef.current) filenameInputRef.current.focus();
            }, 0);
            return false;
        }
        if (base.toLowerCase() === 'index') {
            setError('File name index.json is not allowed.');
            setTimeout(() => {
                if (filenameInputRef.current) filenameInputRef.current.focus();
            }, 0);
            return false;
        }
        return true;
    }

    // Handle form submit
    async function handleSubmit(e, action) {
        e.preventDefault();
        setError(null);
        if (!validateForm()) {
            // Scroll to top if error
            window.scrollTo({ top: 0, behavior: 'auto' });
            return;
        }
        setSubmitting(true);
        try {
            const res = await fetch('/api/admin/add-playlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(form)
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                const msg = data && data.message ? data.message : 'Failed to create playlist.';
                setError(msg);
                // Focus filename input if error is about file already existing
                if (msg && msg.toLowerCase().includes('already exists')) {
                    setTimeout(() => {
                        if (filenameInputRef.current) filenameInputRef.current.focus();
                    }, 0);
                }
                setSubmitting(false);
                // Scroll to top if error
                window.scrollTo({ top: 0, behavior: 'auto' });
                return;
            }
            // Always reload playlists so select list updates
            await loadPlaylists();
            if (action === 'draft') {
                setAdminMsg({ type: 'success', text: 'Playlist created successfully.' });
                route(createPath('/dashboard'));
            } else if (action === 'addshows') {
                // Switch to new playlist, then go to AddShow page
                if (data.filename) {
                    await switchPlaylist(data.filename);
                }
                setAdminMsg({ type: 'success', text: 'Playlist created and switched. You can now add shows.' });
                route(createPath('/dashboard/add'));
            }
        } catch (err) {
            setError('Failed to create playlist.');
            // Scroll to top if error
            window.scrollTo({ top: 0, behavior: 'auto' });
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div id="top" className="container mt-3 w-75">

            <h1 className="my-4 text-center">Create A New Playlist</h1>

            {/* Alert for error/success */}
            {error && (
                <div className="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    {error}
                    <button type="button" className="btn-close" aria-label="Close" onClick={() => setError(null)}></button>
                </div>
            )}
            {/* Success alert removed; use AdminMessage signal-based component instead */}

            <form className="mt-4 mx-auto" onSubmit={e => handleSubmit(e, 'draft')}>
                <div className="mb-3">
                    <label className="form-label">Playlist Title <span className="text-danger">*</span></label>
                    <input type="text" className="form-control form-control-sm" name="dbtitle" placeholder="My New Playlist" value={form.dbtitle} onInput={handleChange} required />
                </div>
                <div className="mb-3">
                    <label className="form-label">Playlist Version</label>
                    <input type="text" className="form-control form-control-sm" name="dbversion" placeholder="1.0" value={form.dbversion} onInput={handleChange} />
                </div>
                <div className="mb-3">
                    <label htmlFor="filename" className="form-label">File Name <span className="text-danger">*</span></label>
                    <div className="input-group">
                        <input
                            type="text"
                            className="form-control form-control-sm"
                            id="filename"
                            name="filename"
                            placeholder="myplaylist"
                            value={form.filename}
                            onInput={handleChange}
                            ref={filenameInputRef}
                            required
                            autoComplete="off"
                        />
                        <span
                            className="input-group-text"
                            title="See more info about naming files (opens in a new window)"
                            style={{ cursor: 'pointer', userSelect: 'none' }}
                            onClick={e => { e.preventDefault(); setShowFilenameModal(true); }}
                        >
                            <a
                                href="#"
                                className="text-decoration-none"
                                tabIndex={-1}
                                style={{ pointerEvents: 'none', color: 'inherit', textDecoration: 'none' }}
                                aria-hidden="true"
                            >
                                ❔
                            </a>
                        </span>
                    </div>
                </div>
                <div className="mb-3">
                    <label className="form-label">Author <span className="text-danger">*</span></label>
                    <input type="text" className="form-control form-control-sm" name="author" placeholder="Full Name" value={form.author} onInput={handleChange} required />
                </div>
                <div className="mb-3">
                    <label className="form-label">Email <span className="text-danger">*</span></label>
                    <input type="email" className="form-control form-control-sm" name="email" placeholder="you@example.com" value={form.email} onInput={handleChange} required />
                </div>
                <div className="mb-5">
                    <label className="form-label">Link</label>
                    <input type="text" className="form-control form-control-sm" name="link" placeholder="https://example.com" value={form.link} onInput={handleChange} />
                </div>
                <div className="text-center mb-5">
                    <button
                        type="button"
                        className="btn btn-outline-secondary me-2"
                        title="Return to Dashboard"
                        onClick={() => route('/dashboard')}
                        disabled={submitting}
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        className="btn btn-outline-primary me-2"
                        title="Create an empty playlist with no shows and return to Dashboard"
                        disabled={submitting}
                    >
                        Save As Draft
                    </button>
                    <button
                        type="button"
                        className="btn btn-outline-success"
                        title="Create this playlist, switch to it, and start adding shows"
                        disabled={submitting}
                        onClick={e => handleSubmit(e, 'addshows')}
                    >
                        Save and Add Shows
                    </button>
                </div>
            </form>
            <AdminFilenameInfoModal show={showFilenameModal} onClose={() => setShowFilenameModal(false)} />
        </div>
    );
}