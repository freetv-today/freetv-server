import { ImageLargeLogo } from "@components/UI/ImageLargeLogo";
import { useEffect } from 'preact/hooks';
import { useConfig } from '@/context/ConfigContext.jsx';

export function Search() {
	const { debugmode } = useConfig();

	useEffect(() => {
		document.title = "Free TV: Search";
		if (debugmode) {
			console.log('Rendered Search page (pages/Search/index.jsx)');
		}
	}, [debugmode]);	

	return (
		<ImageLargeLogo />
	);
}