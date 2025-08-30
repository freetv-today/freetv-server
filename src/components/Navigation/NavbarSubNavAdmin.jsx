import { useLocation } from 'preact-iso';

export function NavbarSubNavAdmin({ onMetaClick }) {
  const { route } = useLocation();
  return (
    <nav id="subnavbar" className="navbar mb-4">
      <div className="container-fluid p-0 m-0 d-flex justify-content-center">
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
      </div>
    </nav>
  )
}