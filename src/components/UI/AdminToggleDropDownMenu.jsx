import { SelectSmall } from '@components/UI/SelectSmall';
import { useAdminLogout } from '@/hooks/useAdminLogout';

export function AdminToggleDropDownMenu() {
	const handleLogout = useAdminLogout();
	return (
		<ul class="dropdown-menu dropdown-menu-dark dropdown-menu-custom p-2 pb-3">
			<li class="pt-1 px-1">
				Playlist:
				<SelectSmall />
				<hr/>
			</li>
			<li><a class="dropdown-item-custom" href="/dashboard"><span class="icon-sm castle-icon"></span>Home</a></li>
			<li><a class="dropdown-item-custom" href="/dashboard/search"><span class="icon-sm search-icon"></span>Search</a></li>
			<li><a class="dropdown-item-custom" href="/dashboard/problems"><span class="icon-sm problems-icon"></span>Problems</a></li>
			<li><a class="dropdown-item-custom" href="/dashboard/users"><span class="icon-sm users-icon"></span>Users</a></li>
			<li><a class="dropdown-item-custom" href="/dashboard/settings"><span class="icon-sm settings-icon"></span>Settings</a></li>
			<hr/>
			<li>
				<a class="dropdown-item-custom" href="#" onClick={handleLogout}>
					<span class="icon-sm logout-icon"></span>Log Out
				</a>
			</li>
		</ul>
	);
}