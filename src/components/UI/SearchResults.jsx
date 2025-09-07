import { useState } from 'preact/hooks';
import { capitalizeFirstLetter } from '@/utils';
import { useQueueVideo } from '@hooks/useQueueVideo';
import { DescriptionModal } from '@components/Modals/DescriptionModal';
import { useDebugLog } from '@/hooks/useDebugLog';
import { Link } from '@components/Navigation/Link';

export function SearchResults({ results }) {
  const log = useDebugLog();
  const { queueVideo } = useQueueVideo();
  const [showModal, setShowModal] = useState(false);
  const [selectedShow, setSelectedShow] = useState(null);

  // Only include shows with status 'active'
  const activeResults = Array.isArray(results)
    ? results.filter(show => show.status === 'active')
    : [];

  if (!results) return null;
  let query = localStorage.getItem('searchQuery');
  if (activeResults.length === 0) {
    log(`No search results found for query: ${query}`, 'warn');
    return <p className="fs-4 text-center text-danger fw-bold mt-5">No search results found</p>;
  } else {
    log(`Displaying ${activeResults.length} results for query: ${query}`);
  }

  const handleShowInfo = (show) => {
    setSelectedShow(show);
    setShowModal(true);
  };

  // Sort results by year (start) ascending
  const sortedResults = [...activeResults].sort((a, b) => {
    const yearA = parseInt(a.start, 10);
    const yearB = parseInt(b.start, 10);
    if (isNaN(yearA) && isNaN(yearB)) return 0;
    if (isNaN(yearA)) return 1;
    if (isNaN(yearB)) return -1;
    return yearA - yearB;
  });

  return (
    <div className="container-fluid my-4">
      <h2 className="fs-2 fw-bold mb-5 text-center">Search Results:</h2>
      <div className="table-responsive">
        <table className="table align-middle">
          <thead>
            <tr>
              <th className="w-auto text-nowrap">Category</th>
              <th className="w-auto text-nowrap pe-2">Title</th>
              <th className="d-none d-sm-table-cell w-auto text-nowrap ps-1 pe-2" style={{ minWidth: '60px', width: '1%' }}>Year</th>
              <th className="d-none d-md-table-cell flex-grow-1 ps-2" style={{ minWidth: '200px' }}>Description</th>
              <th style={{ width: '110px' }}></th>
            </tr>
          </thead>
          <tbody>
            {sortedResults.map(show => (
              <tr key={show.identifier}>
                <td className="w-auto text-nowrap">
                  <Link href={`/category/${show.category}`} className="text-decoration-none link-dark">{capitalizeFirstLetter(show.category)}</Link>
                </td>
                <td className="w-auto text-nowrap text-truncate pe-2" style={{ minWidth: '200px', maxWidth: '300px'}}>
                  <a
                    href="#"
                    onClick={e => { e.preventDefault(); handleShowInfo(show); }}
                    title={`More info about ${show.title}`}
                  >
                    {show.title}
                  </a>
                </td>
                <td className="d-none d-sm-table-cell w-auto text-nowrap ps-1 pe-2" style={{ minWidth: '60px' }}>
                  {show.start}
                </td>
                <td
                  className="d-none d-md-table-cell flex-grow-1 ps-2"
                  style={{ minWidth: '200px', maxWidth: '600px', whiteSpace: 'normal' }}
                  title={show.desc}
                >
                  {/* Use Bootstrap class to truncate long text with ellipses (...) */}
                  <span className="d-inline-block text-truncate" style={{maxWidth: '275px'}}>
                    {show.desc}
                  </span>
                </td>
                <td>
                  <button
                    className="btn btn-sm btn-primary fw-bold"
                    onClick={() => queueVideo({ category: show.category, identifier: show.identifier, title: show.title })}
                    title={`Watch ${show.title}`}
                  >
                    <span className="d-none d-md-block">Watch</span> &#9654;
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