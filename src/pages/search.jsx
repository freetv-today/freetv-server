import { useState, useEffect } from 'preact/hooks';
import { playlistSignal } from '@signals/playlistSignal';
import { SearchQueryComponent } from '@components/UI/SearchQueryComponent';
import { ImageLargeLogo } from '@components/UI/ImageLargeLogo';
import { AdminSearchResults } from '@components/UI/AdminSearchResults';
import { AdminTestVideoModal } from '@/components/Modals/AdminTestVideoModal';
import { AdminDeleteShowModal } from '@/components/Modals/AdminDeleteShowModal';
import { useAdminShowActions } from '@hooks/useAdminShowActions';
import { useDebugLog } from '@/hooks/useDebugLog';
import { AdminMessage } from '@/components/UI/AdminMessage';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';


export function AdminSearch() {
    const log = useDebugLog();
    const { showData, currentPlaylist, loading: playlistLoading, error: playlistError } = playlistSignal.value;

    // Show loading spinner when playlist is loading
    if (playlistLoading) return <SpinnerLoadingAppData />;
    if (playlistError) return <div className="alert alert-danger mt-4">{playlistError}</div>;

    // Restore query from localStorage
    const getInitialQuery = () => {
      try {
        const val = window.localStorage.getItem('searchQuery');
        return val ? JSON.parse(val) : '';
      } catch {
        return '';
      }
    };
    const [query, setQuery] = useState(getInitialQuery());

    // Track which show is being updated for status
    const [statusUpdatingImdb, setStatusUpdatingImdb] = useState(null);

    // Callback to re-run search after data changes
    const rerunSearch = () => {
      setQuery(q => q); // triggers re-render, re-filters results
    };

    // Custom status toggle handler to show spinner
    const handleStatusToggleWithSpinner = async (show) => {
      setStatusUpdatingImdb(show.imdb);
      try {
        await handleStatusToggle(show);
      } finally {
        setStatusUpdatingImdb(null);
      }
    };

    // Use new hook with currentPlaylist, local message setter, and rerunSearch
    const {
      handleEdit,
      handleDelete,
      handleTest,
      handleStatusToggle,
      showDeleteModal,
      showToDelete,
      deleting,
      deleteError,
      handleDeleteConfirm,
      closeDeleteModal,
      showTestModal,
      testShow,
      closeTestModal,
    } = useAdminShowActions(currentPlaylist, undefined, rerunSearch);

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - Search";
        log('Rendered Admin Search page (pages/search.jsx)');
    }, []);

    // Save query to localStorage
    useEffect(() => {
      window.localStorage.setItem('searchQuery', JSON.stringify(query));
    }, [query]);

    // Filter results based on query
    const filteredResults = (!query || query.length < 3)
      ? []
      : (showData || []).filter(show => {
          const q = query.toLowerCase();
          return (
            (show.title && show.title.toLowerCase().includes(q)) ||
            (show.category && show.category.toLowerCase().includes(q)) ||
            (show.desc && show.desc.toLowerCase().includes(q)) ||
            (show.start && String(show.start).includes(q))
          );
        });

    const handleSearch = (q) => setQuery(q);

    return (
      <>
        <h3 className="text-center mt-4">Admin Dashboard Search</h3>
        <SearchQueryComponent onSearch={handleSearch} />
        {(!query || query.length < 3) ? (
          <ImageLargeLogo />
        ) : (
          <>
            <AdminMessage />
            <AdminSearchResults
              results={filteredResults}
              onEdit={handleEdit}
              onDelete={handleDelete}
              onTest={handleTest}
              onStatusToggle={handleStatusToggleWithSpinner}
              statusUpdatingImdb={statusUpdatingImdb}
            />
            <AdminTestVideoModal
              show={showTestModal}
              onClose={closeTestModal}
              showData={testShow}
            />
            <AdminDeleteShowModal
              show={showDeleteModal}
              onClose={closeDeleteModal}
              showData={showToDelete}
              deleting={deleting}
              error={deleteError}
              onDeleteConfirm={handleDeleteConfirm}
            />
          </>
        )}
      </>
    );
}