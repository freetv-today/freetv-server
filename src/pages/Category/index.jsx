// src/pages/Category/index.jsx
import { useRoute } from 'preact-iso';
import { useEffect } from 'preact/hooks';
import { ShowListSidebar } from '@components/UI/ShowListSidebar.jsx';
import { capitalizeFirstLetter } from '@/utils.js';

/**
 * @returns {import('preact').JSX.Element}
 */
export function Category() {
  const { params } = useRoute(); // Use useRoute for dynamic route params
  const category = params.name;

  useEffect(() => {
    document.title = `Free TV: ${capitalizeFirstLetter(category)}`;
  }, [category]);

  return (
    <div class="container-fluid mt-3 mb-5" style="min-height: calc(100vh - 112px);">
      <div class="d-flex flex-column flex-lg-row">
        <ShowListSidebar context="category" category={category} />
        <section class="flex-fill bg-white p-2 border rounded text-center">
          <h1>{capitalizeFirstLetter(category)}</h1>
          <p>Select a show from the sidebar to watch Free TV.</p>
          <img src="/src/assets/img/freetv.png" width="140" class="mt-2" alt="Free TV Logo" />
        </section>
      </div>
    </div>
  );
}