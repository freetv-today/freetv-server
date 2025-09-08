import { capitalizeFirstLetter } from '@/utils';

/**
 * HeaderBlock component for page headers with responsive image and heading.
 * @param {Object} props
 * @param {string} props.img - Path to the image (used for both small and large)
 * @param {string} props.heading - Heading text (will be capitalized)
 * @param {string} props.desc - Description text below the heading
 * @param {string} props.alt - Alt text for the image
 * @returns {import('preact').JSX.Element}
 */

// used on Category, Favorites, and Recent pages
export function HeaderBlock({ img, heading, desc, alt }) {
  return (
    <>
      <div id="headerBlock" className="d-flex justify-content-center align-items-center mx-auto">
          <div className="me-3 d-lg-none"><img id="smallLogo" src={img} width="45" alt={alt} /></div>
          <div><h1 className="pt-1 mb-0">{capitalizeFirstLetter(heading)}</h1></div>
      </div>
      <p className="mt-3 mx-auto">{desc}</p>
      <img
        id="largeLogo"
        src={img}
        width="100"
        className="mb-3 mt-2 d-none d-lg-block mx-auto"
        alt={alt}
      />
    </>
  );
}
