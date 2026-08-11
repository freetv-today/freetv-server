import { useThumbnail } from '@/hooks/useThumbnail';
import { useEffect, useState } from 'preact/hooks';
import { createPath } from '@/utils/env';

function formatCount(count, singular) {
  return `${count} ${singular}${count === 1 ? '' : 's'}`;
}

export function ThumbnailManager() {
  const {
    currentPlaylist,
    existing,
    missing,
    shared,
    selectedShow,
    setSelectedShow,
    loading,
    error,
    totalShows,
    totalThumbnails,
    missingThumbnails,
    sharedThumbnails,
    searching,
    searchResults,
    searchError,
    searchThumbnails,
  } = useThumbnail();

  const [listMode, setListMode] = useState('existing');
  const [searchQuery, setSearchQuery] = useState('');
  const [searchActive, setSearchActive] = useState(false);

  useEffect(() => {
    setListMode('existing');
    setSearchQuery('');
    setSearchActive(false);
    setSelectedShow(null);
  }, [currentPlaylist, setSelectedShow]);

  function handleListModeChange(mode) {
    setListMode(mode);
    setSelectedShow(null);
    setSearchActive(false);
    setSearchQuery('');
  }

  async function handleSearch(event) {
    event.preventDefault();
    const query = searchQuery.trim();
    if (!query) return;

    setSearchActive(true);
    setSelectedShow(null);
    await searchThumbnails(query);
  }

  function clearSearch() {
    setSearchActive(false);
    setSearchQuery('');
    setSelectedShow(null);
  }

  const modeItems = {
    existing,
    missing,
    shared,
  };
  const displayedItems = searchActive ? searchResults : modeItems[listMode];
  const previewImage = selectedShow?.has_thumbnail
    ? `/thumbs/${selectedShow.imdb}.jpg`
    : createPath('/assets/vintage-tv.png');
  const globalUsage = selectedShow?.global_usage;
  const selectedPlaylistShowCount = selectedShow?.selected_playlist_show_count || 0;
  const showSelectedPlaylistUsage = selectedPlaylistShowCount > 1;
  const showGlobalUsage = globalUsage?.playlist_count > 1;

  function renderSummaryList() {
    return <ul className="list-group list-group-flush mb-3">
      <li className="list-group-item">Number of shows: {totalShows}</li>
      <li
        className="list-group-item list-group-item-action"
        style={{ cursor: 'pointer' }}
        onClick={() => handleListModeChange('existing')}
      >
        Number of thumbnails: {totalThumbnails}
      </li>
      <li
        className="list-group-item list-group-item-action"
        style={{ cursor: 'pointer' }}
        onClick={() => handleListModeChange('missing')}
      >
        Missing thumbnails: {missingThumbnails}
      </li>
      <li
        className="list-group-item list-group-item-action"
        style={{ cursor: 'pointer' }}
        onClick={() => handleListModeChange('shared')}
      >
        Shared thumbnails: {shared.length} ({formatCount(sharedThumbnails, 'reused show')})
      </li>
    </ul>;
  }

  function renderSearchForm() {
    return <form className="input-group mb-3 mx-auto" onSubmit={handleSearch}>
      <input
        type="text"
        className="form-control"
        placeholder="Search by Show Title or IMDb ID"
        value={searchQuery}
        onInput={event => setSearchQuery(event.currentTarget.value)}
        autoComplete="off"
        disabled={searching || !currentPlaylist}
      />
      <button className="btn btn-outline-secondary" type="submit" disabled={searching || !searchQuery.trim()}>
        Search
      </button>
      {searchActive && (
        <button className="btn btn-outline-danger ms-2" type="button" onClick={clearSearch}>
          Clear
        </button>
      )}
    </form>;
  }

  return (
    <>
      <h2 className="my-4 text-center">Thumbnail Manager</h2>

      <div
        className="row m-2 mx-auto p-3 align-items-center rounded-3 border border-1 border-tertiary"
        style={{ width: '95%' }}
      >
        <div className="d-block d-lg-none w-100">
          {renderSummaryList()}
          {renderSearchForm()}
          {error && <div className="alert alert-danger mx-auto" role="alert">{error}</div>}
        </div>
        <div className="col-4 d-none d-lg-block">
          {renderSummaryList()}
        </div>
        <div className="col-8 d-none d-lg-block">
          {error && <div className="alert alert-danger mx-auto" role="alert">{error}</div>}
          {renderSearchForm()}
        </div>
      </div>

      <div
        className="row mb-3 mb-lg-5 mx-auto p-0 rounded-3 border border-1 border-dark"
        style={{ width: '98%', minWidth: '548px' }}
      >
        <div className="col-3 col-lg-2 m-0 p-1 tm-leftcol">
          <ul className="list-group list-group-flush m-0">
            {searchActive && searchError ? (
              <li className="list-group-item text-danger">{searchError}</li>
            ) : loading ? (
              <li className="list-group-item">Loading...</li>
            ) : searching ? (
              <li className="list-group-item">Searching...</li>
            ) : displayedItems.length === 0 ? (
              <li className="list-group-item text-danger">
                {searchActive ? 'No results' : `No ${listMode} thumbnails`}
              </li>
            ) : (
              displayedItems.map(item => (
                <li
                  key={item.imdb}
                  className={`list-group-item list-group-item-action${item.has_thumbnail ? '' : ' list-group-item-danger'}${selectedShow?.imdb === item.imdb ? ' active' : ''}`}
                  onClick={() => setSelectedShow(item)}
                  style={{ cursor: 'pointer' }}
                  title={item.has_thumbnail ? 'Thumbnail exists' : 'This file does not exist'}
                >
                  {item.imdb}.jpg
                </li>
              ))
            )}
          </ul>
        </div>

        <div className="col-9 col-lg-10 p-2">
          <div className="row p-2">
            <div className="col">
              <label htmlFor="thumbnail-title" className="form-label fw-bold">Show Title</label>
              <div className="mb-3">
                <input
                  type="text"
                  className="form-control"
                  id="thumbnail-title"
                  value={selectedShow?.title || ''}
                  readOnly
                  disabled
                />
              </div>

              <label htmlFor="thumbnail-imdb" className="form-label fw-bold">IMDb ID</label>
              <div className="mb-3">
                <input
                  type="text"
                  className="form-control"
                  id="thumbnail-imdb"
                  value={selectedShow?.imdb || ''}
                  readOnly
                  disabled
                />
                <button
                  type="button"
                  className="btn btn-sm btn-secondary mt-1"
                  title="Check IMDb Page"
                  disabled={!selectedShow}
                  onClick={() => selectedShow && window.open(
                    `https://www.imdb.com/title/${selectedShow.imdb}`,
                    'checkWindow',
                    'width=640,height=480'
                  )}
                >
                  Check IMDb Page
                </button>
              </div>

              {selectedShow && (
                showSelectedPlaylistUsage
                || showGlobalUsage
                || !selectedShow.has_thumbnail
              ) && (
                <div className="small text-muted mt-4">
                  {showSelectedPlaylistUsage && (
                    <div>
                      Used by {formatCount(selectedPlaylistShowCount, 'show')} in this playlist
                    </div>
                  )}
                  {showGlobalUsage && (
                    <div>
                      Used by {formatCount(globalUsage.show_count, 'show')} across{' '}
                      {formatCount(globalUsage.playlist_count, 'playlist')}
                    </div>
                  )}
                  {!selectedShow.has_thumbnail && (
                    <div className="text-danger mt-2">No thumbnail file currently exists.</div>
                  )}
                </div>
              )}
            </div>

            <div className="col text-center mt-sm-5">
              <figure className="figure">
                <img
                  id="thumbPreview"
                  src={previewImage}
                  alt={selectedShow ? `Thumbnail preview for ${selectedShow.title}` : 'Default thumbnail placeholder'}
                  height={window.innerWidth < 992 ? 220 : 350}
                  style={{
                    border: '2px dashed black',
                    borderRadius: '12px',
                    marginTop: window.innerWidth < 992 ? 12 : 0,
                    marginBottom: window.innerWidth < 992 ? 12 : 0,
                  }}
                />
                <figcaption className="mt-2 figure-caption">Thumbnail Image Preview</figcaption>
              </figure>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
