// src/pages/Category/index.jsx
import { useRoute } from 'preact-iso';
import { useEffect } from 'preact/hooks';
import { useConfig } from '@/context/ConfigContext';
import { ShowListSidebar } from '@components/UI/ShowListSidebar';
import { capitalizeFirstLetter } from '@/utils';

export function Category() {
  
  const { params } = useRoute(); // Use useRoute for dynamic route params
  const category = params.name;
  const { debugmode } = useConfig();
  
  useEffect(() => {
    document.title = `Free TV: ${capitalizeFirstLetter(category)}`;
    if (debugmode) {
		  console.log('Rendered Category page (pages/Category/index.jsx)');
      console.log(`Selected Category: ${capitalizeFirstLetter(category)}`);
	  }
  }, [debugmode, category]);

  return (
    <div class="container-fluid mt-3 mb-5" style="min-height: calc(100vh - 112px);">
      <div class="d-flex flex-column flex-lg-row">
        <ShowListSidebar context="category" category={category} />
        <section class="flex-fill bg-white p-2 border rounded text-center">
          <h1>{capitalizeFirstLetter(category)}</h1>
          <p class="my-4">Click on a show title button to watch some Free TV.</p>
          <img src="/src/assets/freetv.png" width="140" title="Free TV" />
        </section>
      </div>
    </div>
  );
}