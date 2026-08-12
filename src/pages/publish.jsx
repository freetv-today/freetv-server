import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';

export function AdminPublish() {
    const log = useDebugLog();

    useEffect(() => {
        document.title = 'Free TV: Admin Dashboard - Publish';
        log('Rendered Admin Publish page (pages/publish.jsx)');
    }, []);

    return (
        <div className="container py-4" style={{ maxWidth: 750 }}>
            <h2 className="text-center mb-4">Publish</h2>

            <div className="p-4 bg-white">
                <p className="mb-3">
                    Publication status will appear here.
                </p>
                <p className="mb-3">
                    Selected playlist publishing will appear here.
                </p>
                <p className="mb-0">
                    Publishing all changed content will appear here.
                </p>
            </div>
        </div>
    );
}
