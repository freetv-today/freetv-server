import { ButtonHomeNav } from '@components/Navigation/ButtonHomeNav';
import { ButtonRecentNav } from '@components/Navigation/ButtonRecentNav';
import { ButtonSearchNav } from '@components/Navigation/ButtonSearchNav';
import { ButtonFavoritesNav } from '@components/Navigation/ButtonFavoritesNav';
import { ButtonHelpNav } from '@components/Navigation/ButtonHelpNav';
import { ButtonVideoNav } from '@components/Navigation/ButtonVideoNav';
import { ButtonAddFavoritesNav } from '@components/Navigation/ButtonAddFavoritesNav';
import { ImageSmallLogo } from '@components/UI/ImageSmallLogo';
import { ToggleDropDownMenu } from '@components/UI/ToggleDropDownMenu';
import { showVidNavBtnsSignal } from '@signals/showVidNavBtns';

export function NavbarVideoPlayback() {

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
            className="navbar-toggler pb-0 mb-0"
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

        {showVidNavBtnsSignal.value && (
        <div id="vidNavBtns" className="order-2 order-md-3 me-2">
          <ButtonAddFavoritesNav/>
          <ButtonVideoNav />
        </div>
        )}

      </div>
    </nav>
  )
}