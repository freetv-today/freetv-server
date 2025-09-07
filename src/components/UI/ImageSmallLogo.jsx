import { Link } from '@components/Navigation/Link';

export function ImageSmallLogo() {
  return (
    <Link href="/" title="Free TV" className="navbar-brand d-flex align-items-center">
	    <img src="/src/assets/freetv.png" className="d-inline-block me-2 pb-1" height="40" alt="Logo" />
	    <span className="bruno-ace">Free TV</span>
	  </Link>
  );
}