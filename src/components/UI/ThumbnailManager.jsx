import { useThumbnail } from '@/hooks/useThumbnail';
import { useEffect, useState, useRef } from 'preact/hooks';
import { createPath } from '@/utils/env';

export function ThumbnailManager() {

  const {
    shows,
    thumbnails,
    missingThumbnailShows,
    selectedShow,
    setSelectedShow,
    loading,
    error,
    success,
    fetchThumbnail,
    saveThumbnail,
    totalShows,
    totalThumbnails,
    missingThumbnails,
    showsSharingThumbnail,
    sharedImdbs,
    searching,
    searchResults,
    searchError,
    searchThumbnails,
  } = useThumbnail();

  const [listMode, setListMode] = useState('existing'); // 'existing' or 'missing'
  const [fetching, setFetching] = useState(false);
  const [fetchedImage, setFetchedImage] = useState(null); // URL of temp image
  const [canSave, setCanSave] = useState(false);
  // For auto-dismiss alerts
  const [showSuccess, setShowSuccess] = useState(true);
  const [showError, setShowError] = useState(true);
  // Search UI state
  const [searchQuery, setSearchQuery] = useState('');
  const [searchActive, setSearchActive] = useState(false);
  const searchInputRef = useRef(null);

  // Handle fetch thumbnail logic
  async function handleFetchThumbnail() {
    if (!selectedShow) return;
    setFetching(true);
    setFetchedImage(null);
    setCanSave(false);
    setShowSuccess(true);
    setShowError(true);
    // Show spinner in preview
    try {
      const imageUrl = await fetchThumbnail(selectedShow.imdb, true);
      if (imageUrl) {
        setFetchedImage(imageUrl); // e.g. /temp/ttxxxxxx.jpg
        setCanSave(true);
      }
    } finally {
      setFetching(false);
    }
  }

  // Handle save thumbnail logic (dummy, to be implemented)
  async function handleSaveThumbnail() {
    if (!selectedShow || !fetchedImage) return;
    setCanSave(false);
    setShowSuccess(true);
    setShowError(true);
    await saveThumbnail(selectedShow.imdb);
    setFetchedImage(null);
    // After save, the UI will refresh via hook state update
    // Auto-select next item in list
    setTimeout(() => {
      let nextShow = null;
      if (listMode === 'missing') {
        // Find index of removed item
        const idx = missingThumbnailShows.findIndex(s => s.imdb === selectedShow.imdb);
        if (idx !== -1 && missingThumbnailShows.length > 1) {
          // Try next item, else previous
          nextShow = missingThumbnailShows[idx + 1] || missingThumbnailShows[idx - 1] || null;
        }
      } else {
        const idx = thumbnails.findIndex(t => t.replace('.jpg','') === selectedShow.imdb);
        if (idx !== -1 && thumbnails.length > 1) {
          nextShow = shows.find(s => s.imdb === (thumbnails[idx + 1]?.replace('.jpg','') || thumbnails[idx - 1]?.replace('.jpg','')));
        }
      }
      setSelectedShow(nextShow);
    }, 200); // slight delay to allow state update
  }

  // Determine preview image
  let previewImg = createPath('/assets/vintage-tv.png');
  if (fetching) {
    previewImg = null; // Show spinner
  } else if (fetchedImage) {
    previewImg = fetchedImage;
  } else if (selectedShow && thumbnails.find(t => t.replace('.jpg','') === selectedShow.imdb)) {
    previewImg = `/thumbs/${selectedShow.imdb}.jpg`;
  }

  // Reset preview and selection when toggling infoblock buttons
  function handleListModeChange(mode) {
    setListMode(mode);
    setSelectedShow(null);
    setFetchedImage(null);
    setCanSave(false);
    setSearchActive(false);
    setSearchQuery('');
    if (searchInputRef.current) searchInputRef.current.value = '';
  }

  // Handle search
  async function handleSearch(e) {
    e.preventDefault();
    if (!searchQuery.trim()) return;
    setSearchActive(true);
    setSelectedShow(null);
    await searchThumbnails(searchQuery.trim());
  }

  // Auto-dismiss alerts after 4 seconds
  useEffect(() => {
    if (success && showSuccess) {
      const timer = setTimeout(() => setShowSuccess(false), 4000);
      return () => clearTimeout(timer);
    }
    return undefined;
  }, [success, showSuccess]);
  useEffect(() => {
    if (error && showError) {
      const timer = setTimeout(() => setShowError(false), 4000);
      return () => clearTimeout(timer);
    }
  }, [error, showError]);
  return (
    <>
      <h2 className="my-4 text-center">Thumbnail Manager</h2>

      <div className="row m-2 mx-auto p-3 align-items-center rounded-3 border border-1 border-tertiary" style={{ width: '95%' }}>
        {/* Responsive stacking for md and below */}
        <div className="d-block d-lg-none w-100">
          {/* Row 1: Infoblock */}
          <ul className="list-group list-group-flush mb-3">
            <li className="list-group-item">Number of shows: {totalShows}</li>
            <li className="list-group-item list-group-item-action" style={{cursor:'pointer'}} onClick={() => handleListModeChange('existing')}>Number of thumbnails: {totalThumbnails}</li>
            <li className="list-group-item list-group-item-action" style={{cursor:'pointer'}} onClick={() => handleListModeChange('missing')}>Missing thumbnails: {missingThumbnails}</li>
            <li className="list-group-item list-group-item-action" style={{cursor:'pointer'}} onClick={() => handleListModeChange('shared')}>Shows sharing a thumbnail: {showsSharingThumbnail}</li>
          </ul>
          {/* Row 2: Search */}
          <form className="input-group mb-3" onSubmit={handleSearch}>
            <input
              id="query"
              type="text"
              className="form-control"
              placeholder="Search for Thumbnail Image by Show Title or IMDB ID"
              value={searchQuery}
              onInput={e => setSearchQuery(e.currentTarget.value)}
              ref={searchInputRef}
              autoComplete="off"
              disabled={searching}
            />
            <button className="btn btn-outline-secondary" type="submit" disabled={searching || !searchQuery.trim()}>
              Search
            </button>
            {searchActive && (
              <button className="btn btn-outline-danger ms-2" type="button" onClick={() => { setSearchActive(false); setSearchQuery(''); if (searchInputRef.current) searchInputRef.current.value = ''; }}>Clear</button>
            )}
          </form>
          {/* Row 3: Alerts */}
          {success && showSuccess && <div className="alert alert-success alert-dismissible fade show mx-auto" role="alert">{success}<button type="button" className="btn-close" data-bs-dismiss="alert" onClick={() => setShowSuccess(false)}></button></div>}
          {error && showError && <div className="alert alert-danger alert-dismissible fade show mx-auto" role="alert">{error}<button type="button" className="btn-close" data-bs-dismiss="alert" onClick={() => setShowError(false)}></button></div>}
        </div>
        {/* Large screen: 2 columns as before */}
        <div className="col-4 d-none d-lg-block">
          <ul className="list-group list-group-flush ms-3 mb-3">
            <li className="list-group-item">Number of shows: {totalShows}</li>
            <li className="list-group-item list-group-item-action" style={{cursor:'pointer'}} onClick={() => handleListModeChange('existing')}>Number of thumbnails: {totalThumbnails}</li>
            <li className="list-group-item list-group-item-action" style={{cursor:'pointer'}} onClick={() => handleListModeChange('missing')}>Missing thumbnails: {missingThumbnails}</li>
            <li className="list-group-item list-group-item-action" style={{cursor:'pointer'}} onClick={() => handleListModeChange('shared')}>Shows sharing a thumbnail: {showsSharingThumbnail}</li>
          </ul>
        </div>
        <div className="col-8 d-none d-lg-block">
          {/* Alerts */}
          {success && showSuccess && <div className="alert alert-success alert-dismissible fade show mx-auto" role="alert">{success}<button type="button" className="btn-close" data-bs-dismiss="alert" onClick={() => setShowSuccess(false)}></button></div>}
          {error && showError && <div className="alert alert-danger alert-dismissible fade show mx-auto" role="alert">{error}<button type="button" className="btn-close" data-bs-dismiss="alert" onClick={() => setShowError(false)}></button></div>}
          {/* Search */}
          <form className="input-group mb-3 mx-auto" onSubmit={handleSearch}>
            <input
              id="query"
              type="text"
              className="form-control"
              placeholder="Search for Thumbnail Image by Show Title or IMDB ID"
              value={searchQuery}
              onInput={e => setSearchQuery(e.currentTarget.value)}
              ref={searchInputRef}
              autoComplete="off"
              disabled={searching}
            />
            <button className="btn btn-outline-secondary" type="submit" disabled={searching || !searchQuery.trim()}>
              Search
            </button>
            {searchActive && (
              <button className="btn btn-outline-danger ms-2" type="button" onClick={() => { setSearchActive(false); setSearchQuery(''); if (searchInputRef.current) searchInputRef.current.value = ''; }}>Clear</button>
            )}
          </form>
        </div>
      </div>

      <div className="row mb-3 mb-lg-5 mx-auto p-0 rounded-3 border border-1 border-dark" style={{width: '98%', minWidth: '548px'}}>

        {/* Left Column: Thumbnail List - wider on md and below */}
        <div className="col-3 col-lg-2 m-0 p-1 tm-leftcol">
          <ul className="list-group list-group-flush m-0">
            {/* Search results take priority if active */}
            {searchActive ? (
              searchError ? (
                <li className="list-group-item text-danger">Error!</li>
              ) : searching ? (
                <li className="list-group-item">Searching...</li>
              ) : searchResults.length === 0 ? (
                <li className="list-group-item text-danger">No results</li>
              ) : (
                searchResults.map(result => (
                  <li
                    key={result.imdb}
                    className={`list-group-item list-group-item-action${result.has_thumbnail ? '' : ' list-group-item-danger'}${selectedShow && selectedShow.imdb === result.imdb ? ' active' : ''}`}
                    onClick={() => setSelectedShow(result)}
                    style={{ cursor: 'pointer' }}
                    title={result.has_thumbnail ? 'Thumbnail exists' : 'This file does not exist'}
                  >
                    {result.imdb}.jpg
                  </li>
                ))
              )
            ) : error && error.includes('thumbnails') ? (
              <li className="list-group-item text-danger">Error!</li>
            ) : listMode === 'existing' ? (
              thumbnails.map(t => {
                const imdb = t.replace('.jpg','');
                const show = shows.find(s => s.imdb === imdb);
                return show ? (
                  <li
                    key={imdb}
                    className={`list-group-item list-group-item-action${selectedShow && selectedShow.imdb === imdb ? ' active' : ''}`}
                    onClick={() => setSelectedShow(show)}
                    style={{ cursor: 'pointer' }}
                  >
                    {imdb}.jpg
                  </li>
                ) : null;
              })
            ) : listMode === 'shared' ? (
              sharedImdbs.length === 0 ? (
                <li className="list-group-item text-danger">No shared IMDBs</li>
              ) : (
                sharedImdbs.map(imdb => {
                  const hasThumb = thumbnails.some(t => t.replace('.jpg','') === imdb);
                  // Determine if this shared IMDB is missing a thumbnail by checking missingThumbnailShows
                  const isMissing = missingThumbnailShows.some(show => show.imdb === imdb);
                  // Find the first show with this IMDB for details panel
                  const showObj = shows.find(s => s.imdb === imdb);
                  return (
                    <li
                      key={imdb}
                      className={`list-group-item list-group-item-action${isMissing ? ' list-group-item-danger' : ''}${selectedShow && selectedShow.imdb === imdb ? ' active' : ''}`}
                      onClick={() => setSelectedShow(showObj || { imdb })}
                      style={{ cursor: 'pointer' }}
                      title={isMissing ? 'This file does not exist' : 'Thumbnail exists'}
                    >
                      {imdb}.jpg
                    </li>
                  );
                })
              )
            ) : (
              missingThumbnailShows
                .filter(show => show.imdb && show.imdb.trim())
                .map(show => (
                  <li
                    key={show.imdb}
                    className={`list-group-item list-group-item-action list-group-item-danger${selectedShow && selectedShow.imdb === show.imdb ? ' active' : ''}`}
                    onClick={() => setSelectedShow(show)}
                    style={{ cursor: 'pointer' }}
                    title="This file does not exist"
                  >
                    {show.imdb}.jpg
                  </li>
                ))
            )}
            {thumbnails.length === 0 && !searchActive && (
              <li className="list-group-item text-danger nothumbs">No thumbnail images to display</li>
            )}
          </ul>
        </div>

        {/* Right Column: Details & Actions */}
        <div className="col-9 col-lg-10 p-2">
          <div className="row p-2">
            <div className="col">
              <label htmlFor="title" className="form-label fw-bold">Show Title</label>
              <div className="mb-3">
                <input type="text" className="form-control" id="title" value={selectedShow ? selectedShow.title : ''} readOnly disabled />
              </div>
              <label htmlFor="imdb" className="form-label fw-bold">IMDB ID</label>
              <div className="mb-3">
                <input type="text" className="form-control" id="imdb" value={selectedShow ? selectedShow.imdb : ''} readOnly disabled />
                <button className="btn btn-sm btn-secondary mt-1" title="Check IMDB Page" onClick={() => selectedShow && window.open(`https://www.imdb.com/title/${selectedShow.imdb}`,'checkWindow','width=640,height=480')}>Check IMDB Page</button>
              </div>
              {/* Add margin below IMDB field on md and below */}
              <div className="d-block d-lg-none mb-2"></div>
              <div className="my-4 fw-bold">Fetch Thumbnail from IMDB</div>
              <div>
                <button className="btn btn-warning me-2" disabled={loading || !selectedShow || fetching} onClick={handleFetchThumbnail}>Fetch Thumbnail</button>
                <button className={`mt-sm-2 mt-md-0 btn btn-primary${canSave ? '' : ' disabled'}`} disabled={!canSave} onClick={handleSaveThumbnail}>Save File</button>
              </div>
            </div>
            {/* Thumbnail Preview */}
            <div className="col text-center mt-sm-5">
              <figure className="figure">
                {fetching ? (
                  <div className="d-flex flex-column align-items-center justify-content-center" style={{height:'350px'}}>
                    <div className="spinner-border text-dark mb-2" role="status" style={{width:'4rem',height:'4rem'}}>
                      <span className="visually-hidden">Fetching thumbnail...</span>
                    </div>
                    <div className="mt-2">Fetching thumbnail...</div>
                  </div>
                ) : (
                  <img
                    id="thumbPreview"
                    src={previewImg}
                    height={window.innerWidth < 992 ? 220 : 350}
                    style={{ border: '2px dashed black', borderRadius: '12px', marginTop: window.innerWidth < 992 ? 12 : 0, marginBottom: window.innerWidth < 992 ? 12 : 0 }}
                  />
                )}
                <figcaption className="mt-2 figure-caption">Thumbnail Image Preview</figcaption>
              </figure>
            </div>
          </div>
        </div>

      </div>
    </>
  );
}