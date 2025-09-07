
export function ToggleDropDownMenu() {
  return (
    <ul className="dropdown-menu dropdown-menu-dark dropdown-menu-custom pt-0 mt-0">
		<li><a className="dropdown-item-custom" href="/"><span className="icon-sm home-icon"></span>Home</a></li>
		<li><a className="dropdown-item-custom" href="/recent"><span className="icon-sm recent-icon"></span>Recent</a></li>
		<li><a className="dropdown-item-custom" href="/search"><span className="icon-sm search-icon"></span>Search</a></li>
		<li><a className="dropdown-item-custom" href="/favorites"><span className="icon-sm favorites-icon"></span>Favorites</a></li>
		<li><a className="dropdown-item-custom" href="/help"><span className="icon-sm help-icon"></span>Help</a></li>
	</ul>
    );
}