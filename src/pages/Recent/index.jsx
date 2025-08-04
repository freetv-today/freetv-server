import { ButtonShowTitleNav } from "../../components/ButtonShowTitleNav";

export function Recent() {
	return (
	<div class="container-fluid mt-3 mb-5" style="min-height: calc(100vh - 112px);">
  		<div class="d-flex flex-column flex-lg-row">

			<aside class="sidebar-fixed-width p-1 mb-1 mb-lg-0">
				<ButtonShowTitleNav title="Show Title 1" />
				<ButtonShowTitleNav title="Show Title 2" />
				<ButtonShowTitleNav title="Show Title 3" />
				<ButtonShowTitleNav title="Show Title 4" />
				<ButtonShowTitleNav title="Show Title 5" />
				<ButtonShowTitleNav title="Show Title 6" />
				<ButtonShowTitleNav title="Show Title 7" />
				<ButtonShowTitleNav title="Show Title 8" />
				<ButtonShowTitleNav title="Show Title 9" />
				<ButtonShowTitleNav title="Show Title 10" />
				<ButtonShowTitleNav title="Show Title 11" />
				<ButtonShowTitleNav title="Show Title 12" />   
			</aside>

			<section class="flex-fill bg-white p-2 border rounded text-center">
				<h1>Recently Viewed</h1>
				<p>This is a list of your recently viewed shows.<br/>Click on a show title button to continue watching more Free TV.</p>
				<img src="/src/assets/clock.svg" width="140" class="mt-2" />
			</section>

		</div>
	</div>
	);
}