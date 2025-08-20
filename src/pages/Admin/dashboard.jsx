import { useEffect, useState, useContext } from 'preact/hooks';
import { useAdminSession } from '@/hooks/useAdminSession.js';
import { PlaylistContext } from '@/context/PlaylistContext.jsx';
import { AdminDashboardTable } from '@/components/Admin/AdminDashboardTable.jsx';
import { AdminDashboardFilters } from '@/components/Admin/AdminDashboardFilters.jsx';
import { NavbarSubNavAdmin } from '@/components/Navigation/NavbarSubNavAdmin.jsx';

export function Dashboard() {
    const user = useAdminSession();
    const { showData, playlists, currentPlaylist } = useContext(PlaylistContext);

    // Get current playlist meta info
    const playlistMeta = playlists && currentPlaylist
        ? playlists.find(p => p.filename === currentPlaylist)
        : null;
    const playlistName = playlistMeta ? playlistMeta.dbtitle : '';


    // State for sorting/filtering
    const [sortBy, setSortBy] = useState('title');
    const [sortOrder, setSortOrder] = useState('asc');
    const [filterCategory, setFilterCategory] = useState(null);
    const [hideDisabled, setHideDisabled] = useState(false);

    // Handle sorting when header is clicked
    function handleSort(column) {
        if (sortBy === column) {
            setSortOrder(prev => (prev === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortBy(column);
            setSortOrder('asc');
        }
    }

    function handleEdit(show) {
        // TODO: navigate to /dashboard/edit/:id or open edit modal
        alert('Edit: ' + show.title);
    }
    function handleDelete(show) {
        // TODO: show confirmation and call API to delete
        if (window.confirm(`Are you sure you want to delete "${show.title}"?`)) {
            alert('Delete: ' + show.title);
        }
    }
    function handleStatusToggle(show) {
        // TODO: call API to toggle status
        alert('Toggle status: ' + show.title);
    }

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard";
    }, []);

    if (!user) return <div className="container mt-5">Loading...</div>;

    return (
        <div className="container mt-3">
            <h1 class="text-center mb-2">Admin Dashboard</h1>
            <NavbarSubNavAdmin />
            <AdminDashboardFilters
                shows={showData || []}
                filterCategory={filterCategory}
                setFilterCategory={setFilterCategory}
                hideDisabled={hideDisabled}
                setHideDisabled={setHideDisabled}
                playlistName={playlistName}
            />
            <AdminDashboardTable
                shows={showData || []}
                onEdit={handleEdit}
                onDelete={handleDelete}
                onStatusToggle={handleStatusToggle}
                sortBy={sortBy}
                sortOrder={sortOrder}
                filterCategory={filterCategory}
                hideDisabled={hideDisabled}
                onSort={handleSort}
            />
        </div>
    );
}