import { useLocation } from 'preact-iso';

export function Link({ href, class: className, activeClass = 'active', children, ...props }) {
  const { url, route } = useLocation();
  // Normalize URLs for comparison
  const cleanUrl = url ? url.replace(/\/$/, '') : '';
  const cleanHref = href ? href.replace(/\/$/, '') : '';
  // Check if the link is active
  const isActive = cleanUrl === cleanHref;
  const classes = isActive ? `${className || ''} ${activeClass}`.trim() : className || '';

  const handleClick = (e) => {
    e.preventDefault();
    if (cleanUrl !== cleanHref) {
      route(href); // Only route if the URL is different
    }
  };

  return (
    <a href={href} class={classes} onClick={handleClick} {...props}>
      {children}
    </a>
  );
}