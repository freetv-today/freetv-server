// src/components/UI/DescriptionModal.jsx
import { useState, useEffect } from 'preact/hooks';

/**
 * @param {Object} props
 * @param {boolean} props.show
 * @param {() => void} props.onClose
 * @param {string} props.title
 * @param {string} props.category
 * @param {string} props.identifier
 * @param {string} props.desc
 * @param {string} props.start
 * @param {string} props.end
 * @param {string} props.imdb
 * @returns {import('preact').JSX.Element}
 */
export function DescriptionModal({ show, onClose, title, category, identifier, desc, start, end, imdb }) {
  const [thumbnailSrc, setThumbnailSrc] = useState('/src/assets/img/vintage-tv.png');

  useEffect(() => {
    if (imdb) {
      const img = new Image();
      img.src = `/src/assets/img/thumbs/${imdb}.jpg`;
      img.onload = () => setThumbnailSrc(img.src);
      img.onerror = () => setThumbnailSrc('/src/assets/img/vintage-tv.png');
    }
  }, [imdb]);

  // Format aired dates
  const airedText = start === end ? start : `${start} &ndash; ${end}`;

  // Generate links
  const links = (
    <ul>
      {imdb && (
        <li class="pb-1">
          <a href={`https://www.imdb.com/title/${imdb}/`} target="_blank" rel="noopener noreferrer">
            IMDB Page <img src="/src/assets/img/external-link.svg" width="14" class="ms-2" alt="External Link" />
          </a>
        </li>
      )}
      <li class="pb-1">
        <a href={`https://archive.org/details/${identifier}`} target="_blank" rel="noopener noreferrer">
          Archive.org <img src="/src/assets/img/external-link.svg" width="14" class="ms-2" alt="External Link" />
        </a>
      </li>
      <li class="pb-2">
        <a href={`https://archive.org/download/${identifier}`} target="_blank" rel="noopener noreferrer">
          Download Files <img src="/src/assets/img/external-link.svg" width="14" class="ms-2" alt="External Link" />
        </a>
      </li>
    </ul>
  );

  return (
    <div class={`modal fade ${show ? 'show d-block' : ''}`} tabIndex={-1} style={show ? { backgroundColor: 'rgba(0,0,0,0.5)' } : {}}>
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{title}</h5>
            <button type="button" class="btn-close" onClick={onClose} aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="d-flex flex-row gap-2 mb-4 justify-content-center align-middle">
              <div class="m-0">
                <img id="thumbnail" src={thumbnailSrc} width="100" alt={`${title} Thumbnail`} />
              </div>
              <div class="flex-grow-1 p-2">
                <div class="d-flex flex-column h-100 p-2">
                  <div class="flex-fill pb-3">{desc}</div>
                  <div class="flex-fill pb-3">Originally Aired: {airedText}</div>
                  <div class="flex-fill">{links}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}