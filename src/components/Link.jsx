// src/components/Link.jsx
import { useLocation } from 'preact-iso';

export function Link({ href, class: className, activeClass = 'active', children, ...props }) {
  const { url } = useLocation();
  // Normalize URLs for comparison (remove trailing slashes)
  const cleanUrl = url.replace(/\/$/, '');
  const cleanHref = href.replace(/\/$/, '');
  // Check if the link is active
  const isActive = cleanUrl === cleanHref;
  const classes = isActive ? `${className || ''} ${activeClass}`.trim() : className || '';

  const handleClick = (e) => {
    e.preventDefault();
    // Handle path navigation
    window.history.pushState({}, '', href);
    window.dispatchEvent(new PopStateEvent('popstate'));
  };

  return (
    <a href={href} class={classes} onClick={handleClick} {...props}>
      {children}
    </a>
  );
}