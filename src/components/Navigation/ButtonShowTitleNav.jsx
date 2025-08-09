// src/components/Navigation/ButtonShowTitleNav.jsx
import { useState } from 'preact/hooks';
import { DescriptionModal } from '@components/UI/DescriptionModal.jsx';

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
  const [showModal, setShowModal] = useState(false);

  const handleMainClick = () => {
    // Placeholder for video playback
    console.log(`Queue video: ${title} (${identifier})`);
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
              href="javascript:void(0)"
              title="About this show"
              onClick={() => setShowModal(true)}
            >
              About this show
            </a>
          </li>
          <div class="moduleBtns">
            <li>
              <a
                class="dropdown-item moreoptions text-danger"
                href="javascript:void(0)"
                title="Report a problem"
              >
                Report a problem
              </a>
            </li>
            <li>
              <a
                class="dropdown-item moreoptions text-secondary"
                href="javascript:void(0)"
                title="Share this video"
              >
                Share this video
              </a>
            </li>
            <li>
              <a
                class="dropdown-item moreoptions text-secondary"
                href="javascript:void(0)"
                title="Add to favorites"
              >
                Add to favorites
              </a>
            </li>
          </div>
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
    </>
  );
}