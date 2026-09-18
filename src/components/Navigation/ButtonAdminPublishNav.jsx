import { Link } from '@components/Navigation/Link';
import { createPath } from '@/utils/env';
import { hasUnpublishedChangesSignal } from '@signals/publicationStatusSignal';

export function ButtonAdminPublishNav() {
  const hasUnpublishedChanges = hasUnpublishedChangesSignal.value;

  return (
    <Link
      href={createPath('/dashboard/publish')}
      className="btn btn-sm icon-btn publish-icon me-1 me-lg-2 position-relative"
      title="Publish"
    >
      {hasUnpublishedChanges && (
        <span
          className="position-absolute start-100 translate-middle rounded-circle bg-danger p-1"
          style={{ top: '17%' }}
          title="There are unpublished changes"
        >
          <span className="visually-hidden">There are unpublished changes</span>
        </span>
      )}
      &nbsp;
    </Link>
  );
}
