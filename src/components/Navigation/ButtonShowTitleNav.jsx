import { useState } from 'preact/hooks';
import { DescriptionModal } from '@components/UI/DescriptionModal';
import { ReportProblemModal } from '@components/UI/ReportProblemModal';
import { useConfig } from '@/context/ConfigContext';
import { useQueueVideo } from '@hooks/useQueueVideo';
import { useFavoritesList } from '@hooks/useFavoritesList';
import { triggerToast } from '@/signals/toastSignal';

/**
 * @param {Object} props
 * @param {string} props.title
 * @param {string} props.category
 * @param {string} props.identifier
 * @param {string} props.desc
 * @param {string} props.start
 * @param {string} props.end
 * @param {string} props.imdb
 * @returns {import('preact').JSX.Element}
 */


export function ButtonShowTitleNav({ title, category, identifier, desc, start, end, imdb }) {
  const { modules } = useConfig();
  const [showModal, setShowModal] = useState(false);
  const [showReportModal, setShowReportModal] = useState(false);
  const { queueVideo } = useQueueVideo();
  const { favorites, addToFavorites, removeFromFavorites } = useFavoritesList();

  // Use global toast signal for feedback

  const isFavorite = Array.isArray(favorites?.title) && favorites.title.includes(title);

  // Main function to queue video and save to recent
  const handleMainClick = () => {
    queueVideo({ category, identifier, title });
  };

  return (
    <>
      <div className="btn-group mb-1" style={{ width: '98%' }}>
        <button
          className="btn btn-sm btn-outline-dark"
          style={{ width: 'calc(100% - 3rem)' }}
          title={`Watch ${title}`}
          aria-label={`Watch: ${title}`}
          onClick={handleMainClick}
        >
          {title}
        </button>
        <button
          type="button"
          className="btn btn-sm btn-outline-dark dropdown-toggle dropdown-toggle-split"
          data-bs-toggle="dropdown"
          aria-expanded="false"
          style={{ width: '3rem' }}
        >
          <span className="visually-hidden">Toggle Dropdown</span>
        </button>
        <ul className="dropdown-menu p-2">
          <li>
            <a
              className="dropdown-item moreoptions"
              href="#"
              title={`About ${title}`}
              onClick={e => { e.preventDefault(); setShowModal(true); }}
            >
              About this show
            </a>
          </li>
          {/* ---- These buttons are controlled by config setting 'modules' ----  */}
          {modules && (
            <div className="moduleBtns" style={{ display: 'block' }}>
              <li>
                {isFavorite ? (
                  <a
                    className="dropdown-item moreoptions"
                    href="#"
                    title={`Remove ${title} from favorites`}
                    onClick={e => {
                      e.preventDefault();
                      removeFromFavorites(title);
                      triggerToast('Show was removed from Favorites', 'dark');
                    }}
                  >
                    Remove from favorites
                  </a>
                ) : (
                  <a
                    className="dropdown-item moreoptions"
                    href="#"
                    title={`Add ${title} to favorites`}
                    onClick={e => {
                      e.preventDefault();
                      addToFavorites(title);
                      triggerToast('Show was added to Favorites', 'dark');
                    }}
                  >
                    Add to favorites
                  </a>
                )}
              </li>
              <li>
                <a
                  className="dropdown-item moreoptions text-danger"
                  href="#"
                  title={`Report a problem with ${title}`}
                  onClick={e => { e.preventDefault(); setShowReportModal(true); }}
                >
                  Report a problem
                </a>
              </li>
              <li>
                <a
                  className="dropdown-item moreoptions"
                  href={`https://archive.org/download/${identifier}`}
                  title={`Download files for ${title}`}
                  target="_blank"
                >
                  Download files
                  <img src="/src/assets/external-link.svg" width="15" className="ms-2 pb-1" title="Opens in a new tab or window" alt="External Link" />
                </a>
              </li>
            </div>
          )}
        </ul>
      </div>
      <DescriptionModal
        show={showModal}
        onClose={() => setShowModal(false)}
        title={title}
        category={category}
        identifier={identifier}
        desc={desc}
        start={start}
        end={end}
        imdb={imdb}
      />
      {showReportModal && (
        <ReportProblemModal
          show={showReportModal}
          onClose={() => setShowReportModal(false)}
          title={title}
          category={category}
          identifier={identifier}
          desc={desc}
          start={start}
          end={end}
          imdb={imdb}
          playlist={typeof window !== 'undefined' ? localStorage.getItem('playlist') : ''}
        />
      )}
    </>
  );
}