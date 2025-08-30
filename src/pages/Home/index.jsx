import { ImageLargeLogo } from '@components/UI/ImageLargeLogo';
import { useEffect } from 'preact/hooks';
import { useConfig } from '@/context/ConfigContext';

export function Home() {
	const { debugmode } = useConfig();
	
	useEffect(() => {
		document.title = "Free TV: Home";
		if (debugmode) {
			console.log('Rendered Home page (pages/Home/index.jsx)');
		}
	}, [debugmode]);

	return (
		<ImageLargeLogo />
	);
}