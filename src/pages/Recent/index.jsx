import { ShowListSidebar } from '@components/UI/ShowListSidebar';
import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { AdBar } from '@/components/UI/AdBar';
import { HeaderBlock } from '@/components/UI/HeaderBlock';

export function Recent() {
  const log = useDebugLog();
  useEffect(() => {
    document.title = "Free TV: Recent Shows";
    log('Rendered Recent page (pages/Recent/index.jsx)');
  }, []);

  return (
    <div className="container-fluid mt-3 mb-5" style="min-height: calc(100vh - 112px);">
      <div className="d-flex flex-column flex-lg-row">

        <section className="order-2 order-lg-1">
          <ShowListSidebar context="recent" />
        </section>

        <section className="flex-fill bg-white p-2 border rounded text-center order-1 order-lg-2">
          <AdBar/>
          <HeaderBlock
            img="/src/assets/clock.svg"
            heading="Recent Shows"
            desc="This is a list of your recently-watched shows. Click on a show title button to continue watching more Free TV."
            alt="Recent Shows"
          />
        </section>

      </div>
    </div>
  );
}