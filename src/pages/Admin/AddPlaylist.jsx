import { useLocation } from 'preact-iso';
import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { useAdminSession } from '@hooks/Admin/useAdminSession';

export function AddPlaylist() {

  const { route } = useLocation();
  const user = useAdminSession();
  const log = useDebugLog();

  useEffect(() => {
    document.title = "Free TV: Admin Dashboard - Add Playlist";
    log('Rendered Add Playlist page (pages/Admin/AddPlaylist.jsx)');
  }, []);

  if (!user) return null;

  return (
    <div className="container mt-3">
        <h1 className="my-4 text-center">Add A New Playlist</h1>
        <form className="mt-4 w-75 mx-auto">
            <div className="mb-3">
                <label className="form-label">Playlist Title</label>
                <input type="text" className="form-control form-control-sm" name="dbtitle" placeholder="My New Playlist" value="" />
            </div>
            <div className="mb-3">
                <label className="form-label">Playlist Version</label>
                <input type="text" className="form-control form-control-sm" name="dbversion" placeholder="1.0" value="" />
            </div>
            <div class="mb-3">
                <label htmlFor="filename" className="form-label">File Name</label>
                <div class="input-group">
                    <input type="text" className="form-control form-control-sm" id="filename" name="filename" placeholder="myplaylist" value="" />
                    <span class="input-group-text" title="">
                        <a href="#" className="text-decoration-none" title="Open a modal with more info about file names">❔</a>
                    </span>
                </div>
            </div>
            <div className="mb-3">
                <label className="form-label">Author</label>
                <input type="text" className="form-control form-control-sm" name="author" placeholder="Full Name" value="" />
            </div>
            <div className="mb-3">
                <label className="form-label">Email</label>
                <input type="email" className="form-control form-control-sm" name="email" placeholder="you@example.com" value="" />
            </div>
            <div className="mb-5">
                <label className="form-label">Link</label>
                <input type="text" className="form-control form-control-sm" name="link" placeholder="https://example.com" value="" />
            </div>
            <div className="text-center mb-5">
                <button 
                    type="button" 
                    className="btn btn-outline-secondary me-2" 
                    title="Return to Dashboard"
                    onClick={() => route('/dashboard')}
                >
                    Cancel
                </button>
                <button 
                    type="submit" 
                    className="btn btn-outline-primary me-2" 
                    title="Create an empty playlist with no shows and return to Dashboard"
                >
                    Save As Draft
                </button>
                <button 
                    type="submit" 
                    className="btn btn-outline-success" 
                    title="Create this playlist, switch to it, and start adding shows"
                >
                    Save and Add Shows
                </button>
            </div>
        </form>
    </div>
  );
}