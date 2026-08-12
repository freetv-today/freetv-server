import { Link } from '@components/Navigation/Link';
import { createPath } from '@/utils/env';

export function ButtonAdminPublishNav() {
  return (
    <Link href={createPath('/dashboard/publish')} className="btn btn-sm icon-btn publish-icon me-1 me-lg-2" title="Publish">
      &nbsp;
    </Link>
  );
}
