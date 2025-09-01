import { useState } from 'preact/hooks';
import { capitalizeFirstLetter } from '@/utils';
import { useQueueVideo } from '@hooks/useQueueVideo';
import { DescriptionModal } from '@components/UI/DescriptionModal';
import { useDebugLog } from '@/hooks/useDebugLog';

export function SearchResults({ results }) {
  const log = useDebugLog();
  const { queueVideo } = useQueueVideo();
  const [showModal, setShowModal] = useState(false);
  const [selectedShow, setSelectedShow] = useState(null);

  if (!results) return null;
  let query = localStorage.getItem('searchQuery');
  if (results.length === 0) {
    log(`No search results found for query: ${query}`, 'warn');
    return <p class="fs-4 text-center text-danger fw-bold mt-5">No search results found</p>;
  } else {
    log(`Displaying ${results.length} results for query: ${query}`);
  }

  const handleShowInfo = (show) => {
    setSelectedShow(show);
    setShowModal(true);
  };

  // Sort results by year (start) ascending
  const sortedResults = [...results].sort((a, b) => {
    const yearA = parseInt(a.start, 10);
    const yearB = parseInt(b.start, 10);
    if (isNaN(yearA) && isNaN(yearB)) return 0;
    if (isNaN(yearA)) return 1;
    if (isNaN(yearB)) return -1;
    return yearA - yearB;
  });

  return (
    <div class="container-fluid my-4">
      <h2 class="fs-2 fw-bold mb-4 text-center">Search Results:</h2>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th class="w-auto text-nowrap">Category</th>
              <th class="w-auto text-nowrap pe-2">Title</th>
              <th class="w-auto text-nowrap ps-1 pe-2" style={{ minWidth: '60px', width: '1%' }}>Year</th>
              <th class="d-none d-md-table-cell flex-grow-1 ps-2" style={{ minWidth: '200px' }}>Description</th>
              <th style={{ width: '110px' }}></th>
            </tr>
          </thead>
          <tbody>
            {sortedResults.map(show => (
              <tr key={show.identifier}>
                <td class="w-auto text-nowrap">{capitalizeFirstLetter(show.category)}</td>
                <td class="w-auto text-nowrap pe-2">
                  <a
                    href="#"
                    onClick={e => { e.preventDefault(); handleShowInfo(show); }}
                    title={`More info about ${show.title}`}
                    style={{ cursor: 'pointer', textDecoration: 'underline' }}
                  >
                    {show.title}
                  </a>
                </td>
                <td class="w-auto text-nowrap ps-1 pe-2" style={{ minWidth: '60px', width: '1%' }}>{show.start}</td>
                <td
                  class="d-none d-md-table-cell flex-grow-1 ps-2"
                  style={{ minWidth: '200px', maxWidth: '600px', whiteSpace: 'normal' }}
                  title={show.desc}
                >
                  {/* Use Bootstrap class to truncate long text with ellipses (...) */}
                  <span class="d-inline-block text-truncate" style="max-width: 275px;">
                    {show.desc}
                  </span>
                </td>
                <td style={{ width: '110px' }}>
                  <button
                    class="btn btn-sm btn-primary fw-bold"
                    onClick={() => queueVideo({ category: show.category, identifier: show.identifier, title: show.title })}
                  >
                    Watch &#9654;
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {selectedShow && (
        <DescriptionModal
          show={showModal}
          onClose={() => setShowModal(false)}
          title={selectedShow.title}
          category={selectedShow.category}
          identifier={selectedShow.identifier}
          desc={selectedShow.desc}
          start={selectedShow.start}
          end={selectedShow.end}
          imdb={selectedShow.imdb}
        />
      )}
    </div>
  );
}