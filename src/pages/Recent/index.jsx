import { ShowListSidebar } from '@components/UI/ShowListSidebar';
import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { AdBar } from '@/components/UI/AdBar';

export function Recent() {
  const log = useDebugLog();
  useEffect(() => {
    document.title = "Free TV: Recent";
    log('Rendered Recent page (pages/Recent/index.jsx)');
  }, []);

  return (
    <div className="container-fluid mt-3 mb-5" style="min-height: calc(100vh - 112px);">
      <div className="d-flex flex-column flex-lg-row">
        <ShowListSidebar context="recent" />
        <section className="flex-fill bg-white p-2 border rounded text-center">
          <AdBar/>
          <h1 className="mt-3">Recently Viewed</h1>
          <p className="my-4">This is a list of your recently viewed shows.<br/>Click on a show title button to continue watching more Free TV.</p>
          <img src="/src/assets/clock.svg" width="140" className="mt-2" alt="Recent Shows" />
        </section>
      </div>
    </div>
  );
}