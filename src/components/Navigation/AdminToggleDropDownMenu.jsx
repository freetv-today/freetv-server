import { SelectSmall } from '@components/Navigation/SelectSmall';
import { useSignal } from '@preact/signals';
import { playlistSignal, switchPlaylist } from '@signals/playlistSignal';
import { useAdminLogout } from '@hooks/useAdminLogout';
import { Link } from '@components/Navigation/Link';

export function AdminToggleDropDownMenu() {
  const handleLogout = useAdminLogout();
  const playlist = useSignal(playlistSignal).value;
  return (
    <ul className="dropdown-menu dropdown-menu-dark dropdown-menu-custom p-2 pb-3">
      <li className="pt-1 px-1">
        Playlist:
        <SelectSmall />
        <hr/>
      </li>
      <li>
        <Link className="dropdown-item-custom" href="/dashboard">
          <span className="icon-sm castle-icon"></span>Dashboard
        </Link>
      </li>
      <li>
        <Link className="dropdown-item-custom" href="/dashboard/search">
          <span className="icon-sm search-icon"></span>Search
        </Link>
      </li>
      <li>
        <Link className="dropdown-item-custom" href="/dashboard/thumbnails">
          <span className="icon-sm thumbs-icon"></span>Thumbnails
        </Link>
      </li>
      <li>
        <Link className="dropdown-item-custom" href="/dashboard/problems">
          <span className="icon-sm problems-icon"></span>Problems
          <span className="position-absolute start-100 translate-middle badge rounded-pill bg-danger" style={{ top: '17%' }}></span>
        </Link>
      </li>
      <li>
        <Link className="dropdown-item-custom" href="/dashboard/users">
          <span className="icon-sm users-icon"></span>User Manager
        </Link>
      </li>
      <li>
        <Link className="dropdown-item-custom" href="/dashboard/settings">
          <span className="icon-sm settings-icon"></span>Settings
        </Link>
      </li>
      <hr/>
      <li>
        <Link className="dropdown-item-custom" href="#" onClick={handleLogout}>
          <span className="icon-sm logout-icon"></span>Log Out
        </Link>
      </li>
    </ul>
  );
}