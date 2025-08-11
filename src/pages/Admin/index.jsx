import { useEffect } from 'preact/hooks';

export function Admin() {

	useEffect(() => {
		document.title = "Free TV: Admin";
	}, []);

	return (
		<h1 class="mt-5 text-center">Admin Page</h1>
	);
}