import { useEffect } from 'preact/hooks';
import { useAdminSession } from '@hooks/Admin/useAdminSession';
import { useDebugLog } from '@/hooks/useDebugLog';
import { ThumbnailManager } from '@/components/Admin/ThumbnailManager';

export function AdminThumbnails() {

    const log = useDebugLog();
    const user = useAdminSession();

    useEffect(() => {
        document.title = "Free TV: Thumbnail Manager";
        log('Rendered Thumbnail Manager page (pages/Admin/thumbnails.jsx)');
    }, []);

    if (!user) return null;

    return <ThumbnailManager />;
}    