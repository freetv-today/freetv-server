import { useLocation } from 'preact-iso';
import { createPath } from '@/utils/env';

export function NavbarSubNavAdmin({ onMetaClick, onInfoClick, onSortClick }) {

  const { route } = useLocation();
  
  return (
    <nav id="subnavbar" className="navbar mb-2">
      <div className="container-fluid p-0 m-0 d-flex flex-wrap flex-md-nowrap justify-content-center align-items-center admin-nav-flex">
        {/* Row 1: New Playlist, Add Video */}
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
            title="Add Video"
            aria-label="Add Video"
            onClick={() => route(createPath('/dashboard/add'))}
          >
            <span className="me-1">{"\u271A"}</span> Add Video
          </button>
        </div>  
        {/* Row 2: Meta, Sort, Info */}
        <div className="admin-nav-row2 d-flex flex-wrap">
          <button 
            type="button" 
            className="btn btn-outline-secondary rounded-pill btn-sm mt-2 fw-bold px-4 py-2" 
            onClick={onMetaClick} 
            title="Edit Playlist Meta Data" 
            aria-label="Edit Playlist Meta Data"
          > 
            Meta Data
          </button>
          <button 
            type="button" 
            className="btn btn-outline-secondary rounded-pill btn-sm mt-2 fw-bold px-4 py-2" 
            title="Database Sort" 
            aria-label="Database Sort"
            onClick={onSortClick}
          > 
            Database Sort
          </button>
          <button 
            type="button" 
            className="infoBtnAdmin ms-2 mt-2" 
            title="Current Playlist Information"
            aria-label="Current Playlist Information"  
            onClick={onInfoClick}
          >
            {/* Info icon loaded via admin.css */}
          </button>
        </div>
      </div>
    </nav>
  )
}