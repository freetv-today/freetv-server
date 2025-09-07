import { ButtonAdminHomeNav } from '@components/Admin/Navigation/ButtonAdminHomeNav';
import { ButtonAdminSearchNav } from '@components/Admin/Navigation/ButtonAdminSearchNav';
import { ButtonAdminProblemsNav } from '@components/Admin/Navigation/ButtonAdminProblemsNav';
import { ButtonAdminUsersNav } from '@components/Admin/Navigation/ButtonAdminUsersNav';
import { ButtonAdminSettingsNav } from '@components/Admin/Navigation/ButtonAdminSettingsNav';
import { ButtonAdminThumbsNav } from './ButtonAdminThumbsNav';
import { ImageSmallLogo } from '@components/UI/ImageSmallLogo';
import { AdminToggleDropDownMenu } from '@components/Admin/Navigation/AdminToggleDropDownMenu';
import { SelectLarge } from '@components/Navigation/SelectLarge';
import { useAdminLogout } from '@hooks/Admin/useAdminLogout';

// Accept problemCount as a prop

export function NavbarAdmin({ problemCount }) {

  const handleLogout = useAdminLogout();

  return (
    <nav id="navbar" className="navbar navbar-dark bg-dark fixed-top">
      <div className="container-fluid p-0 m-0 d-flex justify-content-between">

        <div id="iconmenu" className="d-none d-md-flex flex-row align-items-center order-1">
          <ButtonAdminHomeNav />
          <ButtonAdminSearchNav />
          <ButtonAdminThumbsNav />
          <ButtonAdminProblemsNav count={problemCount} />
          <ButtonAdminUsersNav />
          <ButtonAdminSettingsNav />
        </div>

        <nav id="smallToggle" className="d-md-none order-1 ms-2">
          <button
            className="navbar-toggler"
            type="button"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            title="Page Navigation Menu"
          >
            <span className="navbar-toggler-icon"></span>
          </button>
          <AdminToggleDropDownMenu />
        </nav>

        <div id="sm_logoblock" className="d-md-none order-3 flex-row align-items-center text-nowrap">
          {/* <!-- small screen logo --> */}
          <ImageSmallLogo />
        </div>

        <div id="lg_logoblock" className="d-none d-md-flex flex-row align-items-center order-md-2">
          {/* <!-- medium and large screen logo --> */}
          <ImageSmallLogo />
        </div>

        {/* Logout button (desktop only) */}
        <div className="d-none d-md-flex align-items-center order-md-3">
          <button
            className="btn btn-outline-light btn-sm px-3"
            type="button"
            title="Log Out"
            onClick={handleLogout}
          >          
          Log Out
          </button>
        </div>

        <div id="playlistSelector" className="d-none d-md-flex order-md-4 flex-row flex-nowrap align-items-center pe-1">
          <div className="pe-1">
            <span className="navbar-text fw-bold">Playlist:</span>
          </div>
          <div>
            <SelectLarge />
          </div>
        </div>

      </div>
    </nav>
  )
}