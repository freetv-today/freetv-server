import { useLocation } from 'preact-iso';
import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';

export function NotFound() {

    const { url } = useLocation();
    const log = useDebugLog();

    useEffect(() => {
        document.title = "Free TV: Not Found";
        log(`The requested route (${url}) does not exist`,'error');
        log('Rendered 404 page (pages/_404.jsx)');
    }, [url]);

    return (
        <div className="text-center">
            <h1 className="mt-5 text-danger">404: Not Found</h1>
            <p className="mt-3 text-danger">
                The page you are looking for (<span className="text-monospace">{url}</span>) was not found.<br />
                Please check the URL and try again.
            </p>
            <img src="/src/assets/sadface.svg" width="140" title="Not Found" />
        </div>
    );
}