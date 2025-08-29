
import { useState, useEffect, useContext } from 'preact/hooks';
import useSearchResults from '@/hooks/useSearchResults.js';
import { useConfig } from '@/context/ConfigContext.jsx';
import { useAdminSession } from '@/hooks/useAdminSession.js';
import { PlaylistContext } from '@/context/PlaylistContext.jsx';
import { SearchQueryComponent } from '@components/UI/SearchQueryComponent';
import { ImageLargeLogo } from "@components/UI/ImageLargeLogo";
import { AdminSearchResults } from '@components/Admin/AdminSearchResults.jsx';
import { AdminTestVideoModal } from '@/components/Admin/AdminTestVideoModal.jsx';
import { AdminDeleteShowModal } from '@/components/Admin/AdminDeleteShowModal.jsx';
import { useAdminShowActions } from '@/hooks/useAdminShowActions.js';

export function AdminSearch() {
    const { debugmode } = useConfig();
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
        if (debugmode) {
            console.log('Rendered Admin Search page (pages/Admin/search.jsx)');
        }
    }, [debugmode]);

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
        <h3 class="text-center mt-4">Admin Dashboard Search</h3>
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