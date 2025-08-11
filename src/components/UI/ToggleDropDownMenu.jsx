import { SelectSmall } from "@components/UI/SelectSmall";

export function ToggleDropDownMenu() {
  return (
        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-custom p-2 mt-1 pb-3">
			<li class="pt-1 px-1">
				Current Playlist:
				<SelectSmall />
				<hr/>
			</li>
			<li><a class="dropdown-item-custom" href="/"><span class="icon-sm home-icon"></span>Home</a></li>
			<li><a class="dropdown-item-custom" href="/recent"><span class="icon-sm recent-icon"></span>Recent</a></li>
			<li><a class="dropdown-item-custom" href="/search"><span class="icon-sm search-icon"></span>Search</a></li>
			<li><a class="dropdown-item-custom" href="/help"><span class="icon-sm help-icon"></span>Help</a></li>
			<li><a id="aiconSm" href="/admin/" class="dropdown-item-custom"><span class="icon-sm admin-icon"></span>Admin</a></li>
		</ul>  
    );
}