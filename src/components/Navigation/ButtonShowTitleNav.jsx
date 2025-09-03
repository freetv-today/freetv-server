import { useState } from 'preact/hooks';
import { DescriptionModal } from '@components/UI/DescriptionModal';
import { ReportProblemModal } from '@components/UI/ReportProblemModal';
import { useConfig } from '@/context/ConfigContext';
import { useQueueVideo } from '@hooks/useQueueVideo';

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

  // Main function to queue video and save to recent
  const handleMainClick = () => {
    queueVideo({ category, identifier, title });
  };

  return (
    <>
      <div class="btn-group mb-1" style={{ width: '98%' }}>
        <button
          class="btn btn-sm btn-outline-dark"
          style={{ width: 'calc(100% - 3rem)' }}
          title={`Watch ${title}`}
          aria-label={`Watch: ${title}`}
          onClick={handleMainClick}
        >
          {title}
        </button>
        <button
          type="button"
          class="btn btn-sm btn-outline-dark dropdown-toggle dropdown-toggle-split"
          data-bs-toggle="dropdown"
          aria-expanded="false"
          style={{ width: '3rem' }}
        >
          <span class="visually-hidden">Toggle Dropdown</span>
        </button>
        <ul class="dropdown-menu p-2">
          <li>
            <a
              class="dropdown-item moreoptions"
              href="#"
              title="About this show"
              onClick={e => { e.preventDefault(); setShowModal(true); }}
            >
              About this show
            </a>
          </li>
          {modules && (
            <div className="moduleBtns" style={{ display: 'block' }}>
              <li>
                <a
                  class="dropdown-item moreoptions text-danger"
                  href="#"
                  title="Report a problem"
                  onClick={e => { e.preventDefault(); setShowReportModal(true); }}
                >
                  Report a problem
                </a>
              </li>
              {/* 
              <li>
                <a
                  class="dropdown-item moreoptions text-secondary"
                  href="#"
                  title="Share this video"
                  onClick={e => e.preventDefault()}
                >
                  Share this video
                </a>
              </li>
              <li>
                <a
                  class="dropdown-item moreoptions text-secondary"
                  href="#"
                  title="Add to favorites"
                  onClick={e => e.preventDefault()}
                >
                  Add to favorites
                </a>
              </li> 
              */}
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
        />
      )}
    </>
  );
}