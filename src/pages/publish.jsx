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

    useEffect(() => {
        document.title = 'Free TV: Admin Dashboard - Publish';
        log('Rendered Admin Publish page (pages/publish.jsx)');
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
        } catch (error) {
            setConfigFeedback({
                type: 'danger',
                text: error instanceof Error ? error.message : 'Viewer settings publication failed',
            });
        } finally {
            setConfigPublishing(false);
        }
    }

    return (
        <div className="container py-4" style={{ maxWidth: 750 }}>
            <h2 className="text-center mb-4">Publish</h2>

            {feedback && (
                <div className={`alert alert-${feedback.type}`} role="alert">
                    {feedback.text}
                </div>
            )}

            <form className="p-4 bg-white" onSubmit={handlePublish}>
                <label className="form-label fw-bold" htmlFor="publishPlaylist">
                    Playlist
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
                        className="btn btn-primary"
                        disabled={publishing || selectedFilename === ''}
                    >
                        {publishing ? 'Publishing...' : 'Publish Selected Playlist'}
                    </button>
                </div>
            </form>

            {configFeedback && (
                <div className={`alert alert-${configFeedback.type} mt-4`} role="alert">
                    {configFeedback.text}
                </div>
            )}

            <div className="p-4 bg-white mt-4 text-center">
                <button
                    type="button"
                    className="btn btn-primary"
                    onClick={handleConfigPublish}
                    disabled={configPublishing}
                >
                    {configPublishing ? 'Publishing...' : 'Publish Viewer Settings'}
                </button>
            </div>
        </div>
    );
}
