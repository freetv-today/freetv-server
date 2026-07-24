import { useLocation } from 'preact-iso';
import { createPath } from '@/utils/env';

export function NavbarSubNavAdmin() {

  const { route } = useLocation();
  
  return (
    <nav id="subnavbar" className="navbar mb-2">
      <div className="container-fluid p-0 m-0 d-flex flex-wrap flex-md-nowrap justify-content-center align-items-center admin-nav-flex">
        {/* Admin actions: New Playlist, New Video, Export JSON Data */}
        <div className="admin-nav-row1 d-flex flex-wrap mb-2 mb-md-0">
          <button 
            type="button" 
            className="btn btn-outline-secondary rounded-pill btn-sm mt-2 fw-bold px-4 py-2" 
            title="New Playlist" 
            aria-label="New Playlist"
            onClick={() => route(createPath('/dashboard/playlist'))}
          >
            <span className="me-1">{"\u271A"}</span> New Playlist
          </button>
          <button
            type="button"
            className="btn btn-outline-secondary rounded-pill btn-sm mt-2 fw-bold px-4 py-2"
            title="New Video"
            aria-label="New Video"
            onClick={() => route(createPath('/dashboard/add'))}
          >
            <span className="me-1">{"\u271A"}</span> New Video
          </button>
          <button
            type="button"
            className="btn btn-outline-secondary rounded-pill btn-sm mt-2 fw-bold px-4 py-2"
            title="Export JSON Data — not yet available"
            aria-label="Export JSON Data — not yet available"
            disabled
          >
            Export JSON Data
          </button>
        </div>
      </div>
    </nav>
  )
}
