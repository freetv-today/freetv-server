import { ButtonHomeNav } from '@components/Navigation/ButtonHomeNav';
import { ButtonRecentNav } from '@components/Navigation/ButtonRecentNav';
import { ButtonSearchNav } from '@components/Navigation/ButtonSearchNav';
import { ButtonFavoritesNav } from '@components/Navigation/ButtonFavoritesNav';
import { ButtonHelpNav } from '@components/Navigation/ButtonHelpNav';
import { ImageSmallLogo } from '@components/UI/ImageSmallLogo';
import { ToggleDropDownMenu } from '@components/UI/ToggleDropDownMenu';
import { SelectLarge } from '@components/UI/SelectLarge';

// this navbar is displayed on all pages except video playback (/nowplaying)
export function NavbarMain() {
  return (
    <nav id="navbar" className="navbar navbar-dark bg-dark fixed-top">
      <div className="container-fluid p-0 m-0 d-flex justify-content-between">

        <div id="iconmenu" className="d-none d-md-flex flex-row align-items-center order-md-1">
          <ButtonHomeNav />
          <ButtonRecentNav />
          <ButtonSearchNav />
          <ButtonFavoritesNav />
          <ButtonHelpNav />
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
          <ToggleDropDownMenu />
        </nav>

        <div id="lg_logoblock" className="d-none d-md-flex flex-row align-items-center order-md-2">
          <ImageSmallLogo />
        </div>

        <div id="playlistSelector" className="order-2 order-md-3 d-flex flex-row flex-nowrap align-items-center pe-1">
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