import { useEffect, useState } from 'preact/hooks';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';
import { formatDateTime } from '@/utils/utils';

function snapshotDownloadUrl(snapshotName) {
  const path = `/api/admin/data-snapshot-download.php?snapshot=${encodeURIComponent(snapshotName)}`;
  const developmentApiOrigin = import.meta.env.DEV
    ? import.meta.env.VITE_API_PROXY_TARGET?.replace(/\/+$/, '')
    : '';

  return developmentApiOrigin ? `${developmentApiOrigin}${path}` : path;
}

function CountRow({ label, value }) {
  return (
    <div className="d-flex justify-content-between gap-3 border-bottom py-2">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

function ChangeSummary({ title, changes }) {
  return (
    <div className="col-12 col-md-6">
      <div className="border rounded p-3 h-100">
        <h4 className="h6">{title}</h4>
        <CountRow label="New" value={changes.new} />
        <CountRow label="Updated" value={changes.updated} />
      </div>
    </div>
  );
}

export function DataSnapshotController() {
  const [snapshot, setSnapshot] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [productionSnapshot, setProductionSnapshot] = useState(null);
  const [creatingSnapshot, setCreatingSnapshot] = useState(false);
  const [creationError, setCreationError] = useState(null);

  useEffect(() => {
    document.title = 'Free TV: Admin Dashboard - Data Snapshot Controller';
    const controller = new AbortController();

    async function loadSnapshotStatus() {
      try {
        const response = await fetch('/api/admin/data-snapshot-status.php', {
          credentials: 'include',
          signal: controller.signal,
        });
        const result = await response.json().catch(() => null);
        if (!result) {
          throw new Error('Data snapshot status returned an invalid response');
        }
        if (!response.ok || !result.success) {
          throw new Error(result.message || 'Could not load data snapshot status');
        }
        setSnapshot(result);
      } catch (requestError) {
        if (requestError.name !== 'AbortError') {
          setError(requestError.message || 'Could not load data snapshot status');
        }
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }

    void loadSnapshotStatus();
    return () => controller.abort();
  }, []);

  async function createProductionSnapshot() {
    if (creatingSnapshot) return;

    setCreatingSnapshot(true);
    setCreationError(null);
    setProductionSnapshot(null);
    try {
      const response = await fetch('/api/admin/data-snapshot-create.php', {
        method: 'POST',
        credentials: 'include',
      });
      const result = await response.json().catch(() => null);
      if (!result) {
        throw new Error('Snapshot creation returned an invalid response');
      }
      if (!response.ok || !result.success || result.status !== 'created') {
        throw new Error(result.message || 'Could not create the production content snapshot');
      }
      setProductionSnapshot(result.snapshot);
    } catch (requestError) {
      setCreationError(requestError.message || 'Could not create the production content snapshot');
    } finally {
      setCreatingSnapshot(false);
    }
  }

  if (loading) return <SpinnerLoadingAppData />;

  return (
    <div className="container py-4" style={{ maxWidth: 950 }}>
      <h2 className="text-center mb-4">Data Snapshot Controller</h2>

      {error && (
        <div className="alert alert-danger" role="alert">
          <h3 className="h6 alert-heading">Data snapshot status is unavailable</h3>
          <div>{error}</div>
        </div>
      )}

      <section className="bg-white border rounded p-4 mb-4" aria-labelledby="productionSnapshotHeading">
        <h3 id="productionSnapshotHeading" className="h5 mb-2">Production Content Snapshot</h3>
        <p className="text-muted mb-3">
          Create a private snapshot of the current production playlists, shows, and thumbnails.
        </p>

        <button
          type="button"
          className="btn btn-primary"
          disabled={creatingSnapshot}
          aria-busy={creatingSnapshot}
          onClick={() => void createProductionSnapshot()}
        >
          {creatingSnapshot && (
            <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" />
          )}
          {creatingSnapshot ? 'Creating Snapshot…' : 'Create Snapshot'}
        </button>

        {creationError && (
          <div className="alert alert-danger mt-3 mb-0" role="alert">
            <h4 className="h6 alert-heading">Snapshot creation failed</h4>
            <div>{creationError}</div>
          </div>
        )}

        {productionSnapshot && (
          <div className="alert alert-success mt-3 mb-0" role="status">
            <h4 className="h6 alert-heading">Snapshot created successfully</h4>
            <CountRow
              label="Production snapshot"
              value={formatDateTime(productionSnapshot.production_snapshot_at)}
            />
            <CountRow
              label="Capture completed"
              value={formatDateTime(productionSnapshot.capture_completed_at)}
            />
            <CountRow label="Playlists" value={productionSnapshot.counts.playlists} />
            <CountRow label="Shows" value={productionSnapshot.counts.shows} />
            <CountRow label="Thumbnails" value={productionSnapshot.counts.thumbnails} />
            {productionSnapshot.download_available && (
              <a
                className="btn btn-success mt-3"
                href={snapshotDownloadUrl(productionSnapshot.name)}
                download={`${productionSnapshot.name}.zip`}
              >
                Download Snapshot
              </a>
            )}
          </div>
        )}
      </section>

      {snapshot && (
        <>
          <div className="row g-4">
            <section className="col-12 col-lg-7" aria-labelledby="officialDatasetHeading">
              <div className="bg-white border rounded p-4 h-100">
                <h3 id="officialDatasetHeading" className="h5 mb-3">Official Dataset</h3>
                <CountRow
                  label="Production snapshot"
                  value={formatDateTime(snapshot.official_dataset.production_snapshot_at)}
                />
                <CountRow
                  label="Dataset generated"
                  value={formatDateTime(snapshot.official_dataset.generated_at)}
                />
                <CountRow label="Format version" value={snapshot.official_dataset.format_version} />
                <CountRow label="Playlists" value={snapshot.official_dataset.counts.playlists} />
                <CountRow label="Shows" value={snapshot.official_dataset.counts.shows} />
                <CountRow label="Thumbnails" value={snapshot.official_dataset.counts.thumbnails} />
              </div>
            </section>

            <section className="col-12 col-lg-5" aria-labelledby="currentProductionHeading">
              <div className="bg-white border rounded p-4 h-100">
                <h3 id="currentProductionHeading" className="h5 mb-3">Current Production</h3>
                <CountRow label="Playlists" value={snapshot.production.counts.playlists} />
                <CountRow label="Shows" value={snapshot.production.counts.shows} />
              </div>
            </section>
          </div>

          <section className="bg-white border rounded p-4 mt-4" aria-labelledby="snapshotChangesHeading">
            <h3 id="snapshotChangesHeading" className="h5 mb-3">Changes Since Official Snapshot</h3>
            <div className="row g-3">
              <ChangeSummary title="Playlists" changes={snapshot.changes_since_snapshot.playlists} />
              <ChangeSummary title="Shows" changes={snapshot.changes_since_snapshot.shows} />
            </div>
          </section>

          <div className="alert alert-info text-center mt-4 mb-0 w-75 m-auto" role="note">
            This status view does not detect deleted shows or thumbnail changes.<br/>Full snapshot comparison via the CLI does include thumbnails.
          </div>
        </>
      )}
    </div>
  );
}
