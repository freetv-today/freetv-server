import { Link } from '@components/Navigation/Link';
import { createPath } from '@/utils/env';

export function ButtonAdminThumbsNav() {
  return (
    <Link href={createPath('/dashboard/thumbnails')} className="btn btn-sm icon-btn thumbs-icon me-1 me-lg-2" title="Thumbnail Manager">
      &nbsp;
    </Link>
  );
}