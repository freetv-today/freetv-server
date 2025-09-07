import { ShowListSidebar } from '@components/UI/ShowListSidebar';
import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { AdBar } from '@/components/UI/AdBar';
import { HeaderBlock } from '@/components/UI/HeaderBlock';

export function Favorites() {
	const log = useDebugLog();
	
	useEffect(() => {
		document.title = "Free TV: Favorites";
		log('Rendered Favorites page (pages/Favorites/index.jsx)');
	}, []);

	return (
		<div className="container-fluid mt-3 mb-5" style="min-height: calc(100vh - 112px);">
			<div className="d-flex flex-column flex-lg-row">

				<section className="order-2 order-lg-1">
					<ShowListSidebar context="favorites" />
				</section>

				<section className="flex-fill bg-white p-2 border rounded text-center order-1 order-lg-2">
					<AdBar/>
					<HeaderBlock
						img="/src/assets/heart.svg"
						heading="Favorite Shows"
						desc="This is a list of shows you've added to Favorites. Click a show title button to continue watching more Free TV."
						alt="Favorite Shows"
					/>
				</section>

			</div>
		</div>   
	);
}