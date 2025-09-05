import { useState, useEffect, useContext } from 'preact/hooks';
import { useSearchResults } from '@/hooks/useSearchResults';
import { useAdminSession } from '@hooks/Admin/useAdminSession';
import { PlaylistContext } from '@/context/PlaylistContext';
import { SearchQueryComponent } from '@components/UI/SearchQueryComponent';
import { ImageLargeLogo } from '@components/UI/ImageLargeLogo';
import { AdminSearchResults } from '@components/Admin/UI/AdminSearchResults';
import { AdminTestVideoModal } from '@/components/Admin/UI/AdminTestVideoModal';
import { AdminDeleteShowModal } from '@/components/Admin/UI/AdminDeleteShowModal';
import { useAdminShowActions } from '@hooks/Admin/useAdminShowActions';
import { useDebugLog } from '@/hooks/useDebugLog';

export function AdminSearch() {
    const log = useDebugLog();
    const user = useAdminSession();
    const { showData } = useContext(PlaylistContext);
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
    } = useAdminShowActions();

    // Try to restore query from localStorage
    const getInitialQuery = () => {
      try {
        const val = window.localStorage.getItem('searchQuery');
        return val ? JSON.parse(val) : '';
      } catch {
        return '';
      }
    };
    const [query, setQuery] = useState(getInitialQuery());
    // Use shared hook for search results
    const results = useSearchResults(showData, query, {
      filterFn: (show, q) =>
        (show.title && show.title.toLowerCase().includes(q)) ||
        (show.category && show.category.toLowerCase().includes(q)) ||
        (show.desc && show.desc.toLowerCase().includes(q)) ||
        (show.start && String(show.start).includes(q))
    });

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - Search";
        log('Rendered Admin Search page (pages/Admin/search.jsx)');
    }, []);

    // Auto-run search if query exists on mount
    useEffect(() => {
      if (query && query.length >= 3) {
        setQuery(query);
      }
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Only update query state; results are derived from hook
    const handleSearch = (q) => {
      setQuery(q);
    };

    if (!user) return null;

    return (
      <>
        <h3 className="text-center mt-4">Admin Dashboard Search</h3>
        <SearchQueryComponent onSearch={handleSearch} />
        {(!query || !results) ? (
          <ImageLargeLogo />
        ) : (
          <>
            <AdminSearchResults
              results={results}
              onEdit={handleEdit}
              onDelete={handleDelete}
              onTest={handleTest}
              onStatusToggle={handleStatusToggle}
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