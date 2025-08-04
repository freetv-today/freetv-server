
export function ButtonsVidViewer() {
  return (
    <span>
        <button id="backBtn" class="btn btn-sm btn-outline-warning fw-bold ms-2 me-2" title="Go back to the previous page">&larr; Back</button>
	    <input id="playlistBtn" type="checkbox" class="btn-check" autocomplete="off" />
	    <label class="btn btn-sm btn-outline-warning fw-bold" for="playlistBtn" title="Episode Playlist">Episode Playlist</label>  
    </span>
    );
}