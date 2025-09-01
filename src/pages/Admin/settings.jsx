import { useEffect } from 'preact/hooks';
import { useConfig } from '@/context/ConfigContext';
import { useDebugLog } from '@/hooks/useDebugLog';
import { useAdminSession } from '@hooks/Admin/useAdminSession';

export function AdminSettings() {
    const { name, version, author, email, link, lastupdated, offline, appdata, collector, showads, gid, modules, debugmode } = useConfig();
    const log = useDebugLog();
    const user = useAdminSession();

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - Settings";
        log('Rendered Admin Settings page (pages/Admin/settings.jsx)');
    }, []);

    if (!user) return null;

    log('Settings for /assets/config.json', 'group');
    log(`Name: ${name}`);
    log(`Version ${version}`);
    log(`Author: ${author}`);
    log(`Email: ${email}`);
    log(`Link: ${link}`);
    log(`Last upated: ${lastupdated}`);
    log(`Offline: ${offline}`);
    log(`App data: ${appdata}`);
    log(`Collector: ${collector}`);
    log(`Show ads: ${showads}`);
    log(`Google ID: ${gid}`);
    log(`Modules: ${modules}`);
    log(`Debug mode: ${debugmode}`);
    log('','end');

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