import { MainNavIcons } from '@components/Navigation/MainNavIcons';
import { ImageSmallLogo } from '@components/UI/ImageSmallLogo';
import { ToggleDropDownMenu } from '@components/Navigation/ToggleDropDownMenu';
import { SelectLarge } from '@components/Navigation/SelectLarge';

// this navbar is displayed on all pages except video playback (/nowplaying)
export function NavbarMain() {
  return (
    <nav id="navbar" className="navbar navbar-dark bg-dark fixed-top">
      <div className="container-fluid p-0 m-0 d-flex justify-content-between">

        <MainNavIcons />

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
          <ToggleDropDownMenu />
        </nav>

        <div id="lg_logoblock" className="d-none d-sm-flex flex-row align-items-center order-sm-2">
          <ImageSmallLogo />
        </div>

        <div id="playlistSelector" className="order-2 order-sm-3 d-flex flex-row flex-nowrap align-items-center pe-1">
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