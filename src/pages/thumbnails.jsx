import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { ThumbnailManager } from '@/components/UI/ThumbnailManager';
import { playlistSignal } from '@signals/playlistSignal';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';

export function AdminThumbnails() {

    const log = useDebugLog();
    const { loading, error } = playlistSignal.value;

    useEffect(() => {
        document.title = "Free TV: Thumbnail Manager";
        log('Rendered Thumbnail Manager page (pages/thumbnails.jsx)');
    }, []);

    if (loading) return <SpinnerLoadingAppData />;
    if (error) return <div className="alert alert-danger mt-4">{error}</div>;

    return <ThumbnailManager />;
}    