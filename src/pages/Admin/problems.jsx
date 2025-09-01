import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { useAdminSession } from '@hooks/Admin/useAdminSession';

export function AdminProblems() {
    const log = useDebugLog();
    const user = useAdminSession();

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - Problems";
        log('Rendered Admin Problems page (pages/Admin/problems.jsx)');
    }, []);

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