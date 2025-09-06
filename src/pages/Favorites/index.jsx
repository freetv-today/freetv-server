import { ShowListSidebar } from '@components/UI/ShowListSidebar';
import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { AdBar } from '@/components/UI/AdBar';

export function Favorites() {
	const log = useDebugLog();
	
	useEffect(() => {
		document.title = "Free TV: Favorites";
		log('Rendered Favorites page (pages/Favorites/index.jsx)');
	}, []);

	return (
		<div className="container-fluid mt-3 mb-5" style="min-height: calc(100vh - 112px);">
		<div className="d-flex flex-column flex-lg-row">
			<ShowListSidebar context="favorites" />
			<section className="flex-fill bg-white p-2 border rounded text-center">
			<AdBar/>
			<h1 className="mt-3">Favorite Shows</h1>
			<p className="my-4">This is a list of shows you have added to your favorites.<br/>Click on a show title button to continue watching more Free TV.</p>
			<img src="/src/assets/heart.svg" width="140" className="mt-2" alt="Favorite Shows" />
			</section>
		</div>
		</div>   
	);
}