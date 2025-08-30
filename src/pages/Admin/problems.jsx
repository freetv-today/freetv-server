import { useEffect } from 'preact/hooks';
import { useConfig } from '@/context/ConfigContext';
import { useAdminSession } from '@/hooks/useAdminSession';

export function AdminProblems() {
    const { debugmode } = useConfig();
    const user = useAdminSession();

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - Problems";
        if (debugmode) {
            console.log('Rendered Admin Problems page (pages/Admin/problems.jsx)');
        }
    }, [debugmode]);

    if (!user) return null;

    return (
        <>
            <h2 class="text-center mt-5">Problems Which Need To Be Fixed</h2>
            <hr class="w-50 mx-auto mb-5" />
            <ul class="list-group list-group-flush w-50 mx-auto">
                <li class="list-group-item">Playlist info data block</li>
                <li class="list-group-item">Shows marked as Disabled</li>
                <li class="list-group-item">Problem Report Logs</li>
                <li class="list-group-item">Items Waiting to be Approved</li>
                <li class="list-group-item">Other Errors or Server Logs</li>
                <li class="list-group-item">Uptime Robot Monitoring</li>
                <li class="list-group-item">Etc, Etc, Etc...</li>
            </ul>
        </>  
    );
}