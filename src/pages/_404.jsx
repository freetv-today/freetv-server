import { useConfig } from '@/context/ConfigContext.jsx';
import { useLocation } from 'preact-iso';
import { useEffect } from 'preact/hooks';

export function NotFound() {
    const { debugmode } = useConfig();
    const { url } = useLocation();

    useEffect(() => {
        document.title = "Free TV: Not Found";
        if (debugmode) {
            console.error(`The requested route (${url}) does not exist`);
            console.log('Rendered 404 page (pages/_404.jsx)');
        }
    }, [debugmode, url]);

    return (
        <div class="text-center">
            <h1 class="mt-5 text-danger">404: Not Found</h1>
            <p class="mt-3 text-danger">
                The page you are looking for (<span class="text-monospace">{url}</span>) was not found.<br />
                Please check the URL and try again.
            </p>
            <img src="/src/assets/sadface.svg" width="140" title="Not Found" />
        </div>
    );
}