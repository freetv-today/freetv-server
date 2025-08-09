// src/components/NavbarMain.jsx
import { ButtonHomeNav } from '@components/Navigation/ButtonHomeNav';
import { ButtonRecentNav } from '@components/Navigation/ButtonRecentNav';
import { ButtonSearchNav } from '@components/Navigation/ButtonSearchNav';
import { ButtonHelpNav } from '@components/Navigation/ButtonHelpNav';
import { ButtonAdminNav } from '@components/Navigation/ButtonAdminNav';
import { ButtonsVidViewer } from '@components/Navigation/ButtonsVidViewer';
import { ImageSmallLogo } from '@components/UI/ImageSmallLogo';
import { ToggleDropDownMenu } from '@components/UI/ToggleDropDownMenu';
import { SelectLarge } from '@components/UI/SelectLarge';
import { useConfig } from '@/context/ConfigContext.jsx';

export function NavbarMain() {
  // Provide fallback for showadmin
  let showadmin = true; // Default to true for offline mode
  try {
    const config = useConfig();
    showadmin = config.showadmin;
  } catch (e) {
    console.warn('ConfigProvider not available, defaulting showadmin to true');
  }

  return (
    <nav id="navbar" className="navbar navbar-dark bg-dark fixed-top">
      <div className="container-fluid p-0 m-0">
        <div id="iconmenu" className="d-none d-md-flex flex-row justify-content-between align-items-center ms-2 me-auto order-md-1">
          <ButtonHomeNav />
          <ButtonRecentNav />
          <ButtonSearchNav />
          <ButtonHelpNav />
          {showadmin && <ButtonAdminNav />}
        </div>
        <nav id="smallToggle" className="dropdown d-md-none order-1 ms-2">
          <button
            className="navbar-toggler"
            type="button"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            title="Page Navigation Menu"
          >
            <span className="navbar-toggler-icon"></span>
          </button>
          <ToggleDropDownMenu />
        </nav>
        <div id="btnWrapper" className="order-2 ms-auto ms-md-0 me-md-auto d-none btnWrapper me-2">
          <ButtonsVidViewer />
        </div>
        <div id="playlistSelector" className="order-3 order-md-4 d-none d-sm-none d-md-block ms-auto me-2">
          <div className="d-flex flex-row flex-nowrap justify-content-center align-middle">
            <div className="pt-1 pe-2">
              <span className="navbar-text fw-bold">Current Playlist: </span>
            </div>
            <div>
              <SelectLarge />
            </div>
          </div>
        </div>
        <div id="sm_logoblock" className="order-4 d-flex d-md-none flex-row align-items-center ms-auto text-nowrap">
          <ImageSmallLogo />
        </div>
        <div id="lg_logoblock" className="d-none d-md-flex flex-row align-items-center align-middle order-md-3">
          <ImageSmallLogo />
        </div>
      </div>
    </nav>
  );
}