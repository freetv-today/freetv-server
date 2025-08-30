import { useLocation } from 'preact-iso';

export function NavbarSubNavAdmin({ onMetaClick, onInfoClick }) {
  const { route } = useLocation();
  return (
    <nav id="subnavbar" className="navbar mb-4">
      <div className="container-fluid p-0 m-0 d-flex justify-content-center align-items-center">
        <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm me-2 mt-2 fw-bold px-4 py-2">
            <span class="me-1">{"\u271A"}</span> Create New Playlist
        </button>
        <button
          type="button"
          class="btn btn-outline-secondary rounded-pill btn-sm me-2 mt-2 fw-bold px-4 py-2"
          onClick={() => route('/dashboard/add')}
        >
          <span class="me-1">{"\u271A"}</span> Add Video
        </button>
        <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm me-2 mt-2 fw-bold px-4 py-2" onClick={onMetaClick}> 
            Playlist Meta Data
        </button>
        <button type="button" class="infoBtnAdmin ms-3 mt-2" aria-label="Current Playlist Information" title="Current Playlist Information" onClick={onInfoClick}></button>
      </div>
    </nav>
  )
}