import { SelectSmall } from '@components/Navigation/SelectSmall';
import { useAdminLogout } from '@hooks/useAdminLogout';
import { Link } from '@components/Navigation/Link';
import { createPath } from '@/utils/env';
import { useAdminAuth } from '@context/AdminSessionContext';
import { hasUnpublishedChangesSignal } from '@signals/publicationStatusSignal';

export function AdminToggleDropDownMenu() {
  const handleLogout = useAdminLogout();
  const { isAdmin, canManageReports, canManageThumbnails } = useAdminAuth();
  const hasUnpublishedChanges = hasUnpublishedChangesSignal.value;
  return (
    <ul className="dropdown-menu dropdown-menu-dark dropdown-menu-custom p-2 pb-3">
      <li className="pt-1 px-1">
        Playlist:
        <SelectSmall />
        <hr/>
      </li>
      <li>
        <Link className="dropdown-item-custom" href={createPath('/dashboard')}>
          <span className="icon-sm castle-icon"></span>Dashboard
        </Link>
      </li>
      <li>
        <Link className="dropdown-item-custom" href={createPath('/dashboard/search')}>
          <span className="icon-sm search-icon"></span>Search
        </Link>
      </li>
      {canManageThumbnails && <li>
        <Link className="dropdown-item-custom" href={createPath('/dashboard/thumbnails')}>
          <span className="icon-sm thumbs-icon"></span>Thumbnails
        </Link>
      </li>}
      {canManageReports && <li>
        <Link className="dropdown-item-custom" href={createPath('/dashboard/problems')}>
          <span className="icon-sm problems-icon"></span>Problems
          <span className="position-absolute start-100 translate-middle badge rounded-pill bg-danger" style={{ top: '17%' }}></span>
        </Link>
      </li>}
      {isAdmin && <li>
        <Link className="dropdown-item-custom" href={createPath('/dashboard/users')}>
          <span className="icon-sm users-icon"></span>User Manager
        </Link>
      </li>}
      {isAdmin && <li>
        <Link className="dropdown-item-custom" href={createPath('/dashboard/publish')}>
          <span className="icon-sm publish-icon"></span>Publish
          {hasUnpublishedChanges && (
            <span
              className="badge rounded-pill bg-danger ms-2 p-1"
              title="There are unpublished changes"
            >
              <span className="visually-hidden">There are unpublished changes</span>
            </span>
          )}
        </Link>
      </li>}
      {isAdmin && <li>
        <Link className="dropdown-item-custom" href={createPath('/dashboard/settings')}>
          <span className="icon-sm settings-icon"></span>Settings
        </Link>
      </li>}
      <hr/>
      <li>
        <Link className="dropdown-item-custom" href="#" onClick={handleLogout}>
          <span className="icon-sm logout-icon"></span>Log Out
        </Link>
      </li>
    </ul>
  );
}
