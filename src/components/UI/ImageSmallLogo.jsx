import { Link } from '@components/UI/Link';

export function ImageSmallLogo() {
  return (
    <Link href="/" className="navbar-brand d-flex align-items-center">
	    <img src="/src/assets/freetv.png" className="d-inline-block me-2 pb-1" height="40" title="Free TV" alt="Free TV" />
	    <span className="bruno-ace">Free TV</span>
	  </Link>
  );
}