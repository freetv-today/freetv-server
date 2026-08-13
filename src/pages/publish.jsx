import { useEffect, useState } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { playlistSignal } from '@signals/playlistSignal';

export function AdminPublish() {
    const log = useDebugLog();
    const { playlists, currentPlaylist } = playlistSignal.value;
    const [selectedFilename, setSelectedFilename] = useState(currentPlaylist || '');
    const [publishing, setPublishing] = useState(false);
    const [feedback, setFeedback] = useState(null);
    const [configPublishing, setConfigPublishing] = useState(false);
    const [configFeedback, setConfigFeedback] = useState(null);
    const [undoStatus, setUndoStatus] = useState({ available: false, operation: null, target: null });
    const [undoing, setUndoing] = useState(false);
    const [undoFeedback, setUndoFeedback] = useState(null);

    useEffect(() => {
        document.title = 'Free TV: Admin Dashboard - Publish';
        log('Rendered Admin Publish page (pages/publish.jsx)');
    }, []);

    useEffect(() => {
        refreshUndoStatus();
    }, []);

    useEffect(() => {
        const selectionExists = playlists.some(playlist => playlist.filename === selectedFilename);
        if (!selectionExists) {
            setSelectedFilename(currentPlaylist || playlists[0]?.filename || '');
        }
    }, [playlists, currentPlaylist, selectedFilename]);

    async function handlePublish(event) {
        event.preventDefault();
        if (!selectedFilename || publishing) return;

        setPublishing(true);
        setFeedback(null);
        try {
            const response = await fetch('/api/admin/publication/publish-playlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ filename: selectedFilename }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Playlist publication failed');
            }

            setFeedback({
                type: 'success',
                text: `${selectedFilename} published successfully at ${result.publication.lastupdated}.`,
            });
            await refreshUndoStatus();
        } catch (error) {
            setFeedback({
                type: 'danger',
                text: error instanceof Error ? error.message : 'Playlist publication failed',
            });
        } finally {
            setPublishing(false);
        }
    }

    async function handleConfigPublish() {
        if (configPublishing) return;

        setConfigPublishing(true);
        setConfigFeedback(null);
        try {
            const response = await fetch('/api/admin/publication/publish-config.php', {
                method: 'POST',
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Viewer settings publication failed');
            }

            setConfigFeedback({
                type: 'success',
                text: `Viewer settings published successfully at ${result.publication.lastupdated}.`,
            });
            await refreshUndoStatus();
        } catch (error) {
            setConfigFeedback({
                type: 'danger',
                text: error instanceof Error ? error.message : 'Viewer settings publication failed',
            });
        } finally {
            setConfigPublishing(false);
        }
    }

    async function refreshUndoStatus() {
        try {
            const response = await fetch('/api/admin/publication/undo-status.php');
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Could not load publication Undo status');
            }
            setUndoStatus({
                available: result.available === true,
                operation: result.operation || null,
                target: result.target || null,
            });
        } catch (error) {
            setUndoFeedback({
                type: 'danger',
                text: error instanceof Error ? error.message : 'Could not load publication Undo status',
            });
        }
    }

    async function handleUndo() {
        if (undoing || !undoStatus.available) return;

        setUndoing(true);
        setUndoFeedback(null);
        try {
            const response = await fetch('/api/admin/publication/undo-last.php', { method: 'POST' });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Could not undo the last publication');
            }
            setUndoFeedback({
                type: 'success',
                text: `Restored the previous ${result.undo.operation} publication.`,
            });
            await refreshUndoStatus();
        } catch (error) {
            setUndoFeedback({
                type: 'danger',
                text: error instanceof Error ? error.message : 'Could not undo the last publication',
            });
        } finally {
            setUndoing(false);
        }
    }

    return (
        <div className="container py-4" style={{ maxWidth: 750 }}>
            <h2 className="text-center mb-4">Publish</h2>

            <p className="pb-4">
                Changes that you make in the Admin Dashboard have to be published before the
                front end client (FreeTV Viewer) will see them. Use the buttons below to publish
                the changes you've made.
            </p>

            <hr/>

            {feedback && (
                <div className={`alert alert-${feedback.type} alert-dismissible fade show`} role="alert">
                    {feedback.text}
                    <button type="button" className="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            )}

            <form className="p-4 bg-white" onSubmit={handlePublish}>
                <label className="form-label fw-bold" htmlFor="publishPlaylist">
                    Publish One Playlist:
                </label>
                <select
                    id="publishPlaylist"
                    className="form-select mb-4"
                    value={selectedFilename}
                    onChange={event => {
                        setSelectedFilename(event.currentTarget.value);
                        setFeedback(null);
                    }}
                    disabled={publishing || playlists.length === 0}
                >
                    {playlists.map(playlist => (
                        <option value={playlist.filename} key={playlist.filename}>
                            {playlist.dbtitle}
                        </option>
                    ))}
                </select>

                <div className="text-center">
                    <button
                        type="submit"
                        className="btn btn-outline-primary"
                        title="If you modified show or playlist data you can publish your changes here"
                        disabled={publishing || selectedFilename === ''}
                    >
                        {publishing ? 'Publishing Playlist...' : 'Publish The Selected Playlist'}
                    </button>
                </div>
            </form>

            <hr/>

            {configFeedback && (
                <div className={`alert alert-${configFeedback.type} alert-dismissible fade show mt-4`} role="alert">
                    {configFeedback.text}
                    <button type="button" className="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            )}

            <div className="p-4 bg-white mt-4 text-center">
                <button
                    type="button"
                    className="btn btn-outline-primary"
                    onClick={handleConfigPublish}
                    title="If you modified configuration settings you can publish your changes here"
                    disabled={configPublishing}
                >
                    {configPublishing ? 'Publishing...' : 'Publish Config Settings'}
                </button>
            </div>

            <hr/>

            {undoFeedback && (
                <div className={`alert alert-${undoFeedback.type} alert-dismissible fade show mt-4`} role="alert">
                    {undoFeedback.text}
                    <button type="button" className="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            )}
            {undoStatus.available && (
            <>
                <div className="p-4 bg-white mt-4 text-center">
                    <p className="mb-3">
                        Last publication: {undoStatus.operation === 'config' ? 'Config Settings' : undoStatus.target}
                    </p>
                    <button
                        type="button"
                        className="btn btn-outline-secondary"
                        onClick={handleUndo}
                        disabled={undoing || !undoStatus.available}
                    >
                        {undoing ? 'Restoring...' : 'Undo Last Publish'}
                    </button>
                </div>
                <hr/>
            </>
            )}
        </div>
    );
}
