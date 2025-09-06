import { useRoute } from 'preact-iso';
import { useEffect } from 'preact/hooks';
import { ShowListSidebar } from '@components/UI/ShowListSidebar';
import { capitalizeFirstLetter } from '@/utils';
import { useDebugLog } from '@/hooks/useDebugLog';
import { AdBar } from '@/components/UI/AdBar';

export function Category() {

  const { params } = useRoute(); // useRoute for dynamic route params
  const category = params.name;
  const log = useDebugLog();
 
  useEffect(() => {
    document.title = `Free TV: ${capitalizeFirstLetter(category)}`;
    log('Rendered Category page (pages/Category/index.jsx)');
  }, [category]);

  return (
    <div className="container-fluid mt-3 mb-5" style="min-height: calc(100vh - 112px);">
      <div className="d-flex flex-column flex-lg-row">

        <section className="order-2 order-lg-1">
          <ShowListSidebar context="category" category={category} />
        </section>

        <section className="flex-fill bg-white p-2 border rounded text-center order-1 order-lg-2">

          <AdBar />
          
          <h1 className="mt-3">{capitalizeFirstLetter(category)}</h1>

          <p className="my-3">Click on a show title button to watch some Free TV.</p>

          <img src="/src/assets/freetv.png" width="140" title="Free TV" className="d-none d-lg-block mx-auto" />
        
        </section>

      </div>
    </div>
  );
}