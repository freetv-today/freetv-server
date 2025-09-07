import { useRoute } from 'preact-iso';
import { useEffect } from 'preact/hooks';
import { ShowListSidebar } from '@components/UI/ShowListSidebar';
import { capitalizeFirstLetter } from '@/utils';
import { useDebugLog } from '@/hooks/useDebugLog';
import { AdBar } from '@/components/UI/AdBar';
import { HeaderBlock } from '@/components/UI/HeaderBlock';
import { useCategories } from '@/hooks/useCategories';
import { NotFound } from '@pages/_404';

export function Category() {
  const { params } = useRoute(); // useRoute for dynamic route params
  const category = params.name;
  const log = useDebugLog();
  const categories = useCategories();

  // Check if the category exists (case-insensitive)
  const categoryExists = categories.some(
    c => c.toLowerCase() === category.toLowerCase()
  );

  if (!categoryExists) {
    return <NotFound />;
  }

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
          <HeaderBlock
            img="/src/assets/freetv.png"
            heading={category}
            desc="Click on a show title button to watch some Free TV."
            alt="Free TV"
          />
        </section>
      </div>
    </div>
  );
}