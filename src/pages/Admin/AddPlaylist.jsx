
import { useContext, useEffect, useState, useRef } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { PlaylistContext } from '@/context/PlaylistContext';
import { useDebugLog } from '@/hooks/useDebugLog';
import { useAdminSession } from '@hooks/Admin/useAdminSession';
import { AdminFilenameInfoModal } from '@/components/Admin/Modals/AdminFilenameInfoModal';

export function AddPlaylist() {

    const { route } = useLocation();
    const user = useAdminSession();
    const log = useDebugLog();
    const { changePlaylist } = useContext(PlaylistContext);

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
    const [success, setSuccess] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [showFilenameModal, setShowFilenameModal] = useState(false);
    const [filenameWarned, setFilenameWarned] = useState(false);
    const filenameInputRef = useRef(null);

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - Create Playlist";
        log('Rendered Create Playlist page (pages/Admin/AddPlaylist.jsx)');
    }, []);

    if (!user) return null;

    // Regex for valid filename (no extension)
    const validFilenameRegex = /^[a-zA-Z0-9_-]+$/;

    // Handle input changes
    function handleChange(e) {
        const { name, value } = e.target;
        setForm(f => ({ ...f, [name]: value }));
        if (name === 'filename') {
            // Warn if user types a dot followed by a letter (non-json extension)
            if (/\.[a-zA-Z]+$/.test(value) && !/\.json$/i.test(value) && !filenameWarned) {
                setShowFilenameModal(true);
                setFilenameWarned(true);
            }
        }
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
            return false;
        }
        if (base.toLowerCase() === 'index') {
            setError('File name index.json is not allowed.');
            return false;
        }
        return true;
    }

    // Handle form submit
    async function handleSubmit(e, action) {
        e.preventDefault();
        setError(null);
        setSuccess(null);
        if (!validateForm()) return;
        setSubmitting(true);
        try {
            const res = await fetch('/api/admin/add-playlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(form)
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                setError(data && data.message ? data.message : 'Failed to create playlist.');
                setSubmitting(false);
                return;
            }
            // Success
            setSuccess('Playlist created successfully!');
            // Save as Draft: go to dashboard, show admin message
            if (action === 'draft') {
                localStorage.setItem('adminMsg', JSON.stringify({ type: 'success', text: 'Playlist created successfully.' }));
                route('/dashboard');
            } else if (action === 'addshows') {
                // Save and Add Shows: switch playlist, then go to add show
                await changePlaylist(data.filename, true, false);
                localStorage.setItem('adminMsg', JSON.stringify({ type: 'success', text: 'Playlist created and switched. You can now add shows.' }));
                route('/dashboard/add');
            }
        } catch (err) {
            setError('Failed to create playlist.');
        } finally {
            setSubmitting(false);
        }
    }


    return (
        <div className="container mt-3">
            <h1 className="my-4 text-center">Create A New Playlist</h1>
            {/* Alert for error/success */}
            {error && <div className="alert alert-danger mb-3">{error}</div>}
            {success && <div className="alert alert-success mb-3">{success}</div>}
            <form className="mt-4 w-75 mx-auto" onSubmit={e => handleSubmit(e, 'draft')}>
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
                        <span className="input-group-text" title="">
                            <a href="#" className="text-decoration-none" title="Open a modal with more info about file names" onClick={e => { e.preventDefault(); setShowFilenameModal(true); }}>❔</a>
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