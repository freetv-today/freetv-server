import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { ThumbnailManager } from '@/components/UI/ThumbnailManager';

export function AdminThumbnails() {

    const log = useDebugLog();

    useEffect(() => {
        document.title = "Free TV: Thumbnail Manager";
        log('Rendered Thumbnail Manager page (pages/thumbnails.jsx)');
    }, []);

    return <ThumbnailManager />;
}    