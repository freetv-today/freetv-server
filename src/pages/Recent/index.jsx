import { ShowListSidebar } from '@components/UI/ShowListSidebar';
import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';

export function Recent() {
  const log = useDebugLog();
  useEffect(() => {
    document.title = "Free TV: Recent";
    log('Rendered Recent page (pages/Recent/index.jsx)');
  }, []);

  return (
    <div class="container-fluid mt-3 mb-5" style="min-height: calc(100vh - 112px);">
      <div class="d-flex flex-column flex-lg-row">
        <ShowListSidebar context="recent" />
        <section class="flex-fill bg-white p-2 border rounded text-center">
          <h1>Recently Viewed</h1>
          <p class="my-4">This is a list of your recently viewed shows.<br/>Click on a show title button to continue watching more Free TV.</p>
          <img src="/src/assets/clock.svg" width="140" class="mt-2" alt="Recent Shows" />
        </section>
      </div>
    </div>
  );
}