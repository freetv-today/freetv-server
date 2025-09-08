import { useLocation } from 'preact-iso';

export function NavbarSubNavAdmin({ onMetaClick, onInfoClick }) {
  const { route } = useLocation();
  return (
    <nav id="subnavbar" className="navbar mb-4">
      <div className="container-fluid p-0 m-0 d-flex justify-content-center align-items-center">

        <button 
          type="button" 
          className="btn btn-outline-secondary rounded-pill btn-sm me-2 mt-2 fw-bold px-4 py-2" 
          title="Create New Playlist" 
          aria-label="Create New Playlist"
          onClick={() => route('/dashboard/playlist')}
        >
          <span className="me-1">{"\u271A"}</span> Create New Playlist
        </button>

        <button
          type="button"
          className="btn btn-outline-secondary rounded-pill btn-sm me-2 mt-2 fw-bold px-4 py-2"
          title="Add Video"
          aria-label="Add Video"
          onClick={() => route('/dashboard/add')}
        >
          <span className="me-1">{"\u271A"}</span> Add Video
        </button>

        <button 
          type="button" 
          className="btn btn-outline-secondary rounded-pill btn-sm me-2 mt-2 fw-bold px-4 py-2" 
          onClick={onMetaClick} 
          title="Edit Playlist Meta Data" 
          aria-label="Edit Playlist Meta Data"
        > 
          Meta Data
        </button>

        <button 
          type="button" 
          className="infoBtnAdmin ms-2 mt-2" 
          title="Current Playlist Information"
          aria-label="Current Playlist Information"  
          onClick={onInfoClick}
        >
          {/* Info Icon */}
        </button>

      </div>
    </nav>
  )
}