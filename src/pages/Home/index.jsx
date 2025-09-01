import { ImageLargeLogo } from '@components/UI/ImageLargeLogo';
import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';

export function Home() {
	const log = useDebugLog();
	
	useEffect(() => {
		document.title = "Free TV: Home";
		log('Rendered Home page (pages/Home/index.jsx)');
	}, []);

	return (
		<ImageLargeLogo />
	);
}