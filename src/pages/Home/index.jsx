import { ImageLargeLogo } from "@components/UI/ImageLargeLogo";
import { useConfig } from '@/context/ConfigContext.jsx';

export function Home() {

	const { debugmode, offline, showadmin } = useConfig();
	if (debugmode) {
		console.log('Config values -- debugmode:', debugmode, ' | offline:', offline, ' | showadmin:', showadmin);
	}
	return (
		<ImageLargeLogo />
	);
}