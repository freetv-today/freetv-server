import { Link } from '@components/Navigation/Link';
import freetvLogo from '/assets/freetv-small.png';
import { createPath } from '@/utils/env';

export function ImageSmallLogo() {
  return (
    <Link href={createPath('/')} title="Free TV" className="navbar-brand d-flex align-items-center">
	    <img src={freetvLogo} className="d-inline-block me-2 pb-1" height="40" title="Free TV" alt="Free TV logo" />
	    <span className="bruno-ace">Free TV</span>
	  </Link>
  );
}