import { ButtonHomeNav } from './ButtonHomeNav';
import { ButtonRecentNav } from './ButtonRecentNav';
import { ButtonSearchNav } from './ButtonSearchNav';
import { ButtonHelpNav } from './ButtonHelpNav';
import { ButtonAdminNav } from './ButtonAdminNav';
import { ImageSmallLogo } from './ImageSmallLogo';
import { ToggleDropDownMenu } from './ToggleDropDownMenu';
import { SelectLarge } from './SelectLarge';
import { ButtonsVidViewer } from './ButtonsVidViewer';
import { useConfig } from '../context/ConfigContext.jsx';

export function NavbarMain() {

	// check config for Admin status
	const { showadmin } = useConfig();
	
	return (
		<nav id="navbar" class="navbar navbar-dark bg-dark fixed-top">
			<div class="container-fluid p-0 m-0">
				<div id="iconmenu" class="d-none d-md-flex flex-row justify-content-between align-items-center ms-2 me-auto order-md-1">
					<ButtonHomeNav />
					<ButtonRecentNav />
					<ButtonSearchNav />
					<ButtonHelpNav />
					{showadmin && (<ButtonAdminNav />)}
				</div>
				<nav id="smallToggle" class="dropdown d-md-none order-1 ms-2">
					<button class="navbar-toggler" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Page Navigation Menu">
					<span class="navbar-toggler-icon"></span>
					</button>
					<ToggleDropDownMenu />
				</nav>
				<div id="btnWrapper" class="order-2 ms-auto ms-md-0 me-md-auto d-none btnWrapper me-2">
					<ButtonsVidViewer />
				</div>
				<div id="playlistSelector" class="order-3 order-md-4 d-none d-sm-none d-md-block ms-auto me-2">
					<div class="d-flex flex-row flex-nowrap justify-content-center align-middle">
						<div class="pt-1 pe-2"><span class="navbar-text fw-bold">Current Playlist: </span></div>
						<div>
							<SelectLarge />
						</div>
					</div>
				</div>
				<div id="sm_logoblock" class="order-4 d-flex d-md-none flex-row align-items-center ms-auto text-nowrap">
					<ImageSmallLogo />
				</div>
				<div id="lg_logoblock" class="d-none d-md-flex flex-row align-items-center align-middle order-md-3">
					<ImageSmallLogo />
				</div>
			</div>
		</nav>
	);
}