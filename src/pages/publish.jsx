import { useEffect, useState } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { playlistSignal } from '@signals/playlistSignal';
import {
    publicationStatusSignal,
    refreshPublicationStatus,
} from '@signals/publicationStatusSignal';

export function AdminPublish() {
    const log = useDebugLog();
    const { playlists, currentPlaylist } = playlistSignal.value;
    const [selectedFilename, setSelectedFilename] = useState(currentPlaylist || '');
    const [publishing, setPublishing] = useState(false);
    const [feedback, setFeedback] = useState(null);
    const [allPublishing, setAllPublishing] = useState(false);
    const [allFeedback, setAllFeedback] = useState(null);
    const [configPublishing, setConfigPublishing] = useState(false);
    const [configFeedback, setConfigFeedback] = useState(null);
    const [undoStatus, setUndoStatus] = useState({ available: false, operation: null, target: null });
    const [undoing, setUndoing] = useState(false);
    const [undoFeedback, setUndoFeedback] = useState(null);
    const {
        status: publicationStatus,
        loading: statusLoading,
        error: statusError,
    } = publicationStatusSignal.value;

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
            await Promise.all([refreshUndoStatus(), refreshPublicationStatus()]);
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
            await Promise.all([refreshUndoStatus(), refreshPublicationStatus()]);
        } catch (error) {
            setConfigFeedback({
                type: 'danger',
                text: error instanceof Error ? error.message : 'Viewer settings publication failed',
            });
        } finally {
            setConfigPublishing(false);
        }
    }

    async function handlePublishAllPlaylists() {
        if (allPublishing) return;

        setAllPublishing(true);
        setAllFeedback(null);
        try {
            const response = await fetch('/api/admin/publication/publish-all-playlists.php', {
                method: 'POST',
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Show and playlist content publication failed');
            }

            setAllFeedback({
                type: result.publication.no_op ? 'info' : 'success',
                text: result.message,
            });
            await Promise.all([refreshUndoStatus(), refreshPublicationStatus()]);
        } catch (error) {
            setAllFeedback({
                type: 'danger',
                text: error instanceof Error ? error.message : 'Show and playlist content publication failed',
            });
        } finally {
            setAllPublishing(false);
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
                text: result.undo.operation === 'playlist_all'
                    ? 'Restored the previous Publish All Shows and Playlist Content operation.'
                    : `Restored the previous ${result.undo.operation} publication.`,
            });
            await Promise.all([refreshUndoStatus(), refreshPublicationStatus()]);
        } catch (error) {
            setUndoFeedback({
                type: 'danger',
                text: error instanceof Error ? error.message : 'Could not undo the last publication',
            });
        } finally {
            setUndoing(false);
        }
    }

    function renderStatusBadge(item) {
        if (item?.error) {
            return <span className="badge text-bg-danger" title={item.error}>Error</span>;
        }
        if (item?.changed) {
            return <span className="badge text-bg-warning">Unpublished changes</span>;
        }
        return <span className="badge text-bg-success">Published</span>;
    }

    function renderPlaylistDelta(playlist) {
        if (!playlist?.changed || !playlist.delta) return null;

        const details = [];
        if (playlist.delta.shows_added > 0) {
            details.push(`+${playlist.delta.shows_added} ${playlist.delta.shows_added === 1 ? 'show' : 'shows'}`);
        }
        if (playlist.delta.shows_edited > 0) {
            details.push(`~${playlist.delta.shows_edited} edited`);
        }
        if (playlist.delta.shows_removed > 0) {
            details.push(`-${playlist.delta.shows_removed} ${playlist.delta.shows_removed === 1 ? 'show' : 'shows'}`);
        }
        if (playlist.delta.order_changed) {
            details.push('Show order changed');
        }
        if (playlist.delta.metadata_changed) {
            details.push(`Playlist metadata: ${playlist.delta.metadata_fields.join(', ')}`);
        }

        return details.length > 0
            ? <div className="small text-body-secondary mt-1">{details.join(' · ')}</div>
            : null;
    }

    function renderConfigDelta(config) {
        if (!config?.changed || !config.delta?.fields?.length) return null;

        return (
            <div className="small text-body-secondary mt-1">
                {config.delta.fields.map(field => `${field} changed`).join(' · ')}
            </div>
        );
    }

    function renderDefaultDelta(defaultPlaylist) {
        if (!defaultPlaylist?.changed || defaultPlaylist.error
            || !defaultPlaylist.published || !defaultPlaylist.database) return null;

        return (
            <div className="small text-body-secondary mt-1">
                {defaultPlaylist.published} → {defaultPlaylist.database}
            </div>
        );
    }

    return (
        <div className="container py-4" style={{ maxWidth: 750 }}>
            <h2 className="text-center mb-4">Publish</h2>

            <p className="pb-4">
                Changes that you make in the Admin Dashboard have to be published before the
                front end client (FreeTV Viewer) will see them. Use the buttons below to publish
                the changes you've made.
            </p>

            <section className="p-4 bg-white mb-4" aria-labelledby="publicationStatusHeading">
                <h3 id="publicationStatusHeading" className="h5 mb-3">Publication Status</h3>

                {statusLoading && (
                    <div className="text-center py-3">
                        <div className="spinner-border spinner-border-sm text-primary" role="status">
                            <span className="visually-hidden">Loading publication status...</span>
                        </div>
                    </div>
                )}

                {statusError && <div className="alert alert-danger mb-0">{statusError}</div>}

                {!statusLoading && publicationStatus && (
                    <div className="table-responsive">
                        <table className="table table-sm align-middle mb-0">
                            <tbody>
                                {publicationStatus.playlists.map(playlist => (
                                    <tr key={playlist.filename}>
                                        <th scope="row" className="fw-normal">
                                            {playlist.dbtitle}
                                            {renderPlaylistDelta(playlist)}
                                        </th>
                                        <td className="text-end align-top">{renderStatusBadge(playlist)}</td>
                                    </tr>
                                ))}
                                <tr>
                                    <th scope="row" className="fw-normal">
                                        Config Settings
                                        {renderConfigDelta(publicationStatus.config)}
                                    </th>
                                    <td className="text-end align-top">{renderStatusBadge(publicationStatus.config)}</td>
                                </tr>
                                <tr>
                                    <th scope="row" className="fw-normal">
                                        Default Playlist
                                        {renderDefaultDelta(publicationStatus.default_playlist)}
                                    </th>
                                    <td className="text-end align-top">
                                        {renderStatusBadge(publicationStatus.default_playlist)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        {[...publicationStatus.playlists,
                            publicationStatus.config,
                            publicationStatus.default_playlist]
                            .filter(item => item.error)
                            .map((item, index) => (
                                <div className="small text-danger mt-2" key={`${item.filename || 'status'}-${index}`}>
                                    {item.filename ? `${item.filename}: ` : ''}{item.error}
                                </div>
                            ))}
                    </div>
                )}
            </section>

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
                        disabled={publishing || allPublishing || selectedFilename === ''}
                    >
                        {publishing ? 'Publishing Playlist...' : 'Publish The Selected Playlist'}
                    </button>
                </div>
            </form>

            {allFeedback && (
                <div className={`alert alert-${allFeedback.type} alert-dismissible fade show mt-4`} role="alert">
                    {allFeedback.text}
                    <button type="button" className="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            )}

            <div className="p-4 bg-white mt-4 text-center">
                <button
                    type="button"
                    className="btn btn-outline-primary"
                    onClick={handlePublishAllPlaylists}
                    disabled={allPublishing || publishing}
                >
                    {allPublishing
                        ? 'Publishing All Shows and Playlist Content...'
                        : 'Publish All Shows and Playlist Content'}
                </button>
            </div>

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
