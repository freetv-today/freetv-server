import { useEffect } from 'preact/hooks';
import { useConfig } from '@/context/ConfigContext';
import { useAdminSession } from '@/hooks/useAdminSession';

export function AdminSettings() {
    const { debugmode } = useConfig();
    const user = useAdminSession();

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - Settings";
        if (debugmode) {
            console.log('Rendered Admin Settings page (pages/Admin/settings.jsx)');
        }
    }, [debugmode]);

    if (!user) return null;

    return (
        <>
            <h2 class="text-center mt-5">Admin Settings</h2>
            <hr class="w-50 mx-auto mb-5" />
            <ul class="list-group list-group-flush w-50 mx-auto">
                <li class="list-group-item">Config.JSON toggle settings (debugmode, appdata, modules, offline)</li>
                <li class="list-group-item">App Name</li>
                <li class="list-group-item">App Version</li>
                <li class="list-group-item">Default Database</li>
                <li class="list-group-item">Data Collector (Beacon)</li>
                <li class="list-group-item">Google Adsense ID</li>
                <li class="list-group-item">Last Updated Date (refresh datestamp)</li>
                <li class="list-group-item">Update Playlist Index (/playlists/index.json) Button</li>
            </ul>
        </>      
    );
}