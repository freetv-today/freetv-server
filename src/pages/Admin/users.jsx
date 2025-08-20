import { useEffect } from 'preact/hooks';
import { useConfig } from '@/context/ConfigContext.jsx';
import { useAdminSession } from '@/hooks/useAdminSession.js';

export function AdminUsers() {
    const { debugmode } = useConfig();
    const user = useAdminSession();

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - User Manager";
        if (debugmode) {
            console.log('Rendered Admin User Manager page (pages/Admin/users.jsx)');
        }
    }, [debugmode]);

    if (!user) return null;

    return (
        <>
            <h2 class="text-center mt-5">User Manager</h2>
            <hr class="w-50 mx-auto mb-5" />
            <ul class="list-group list-group-flush w-50 mx-auto">
                <li class="list-group-item">List of existing Users and Roles</li>
                <li class="list-group-item">Add New User</li>
                <li class="list-group-item">Delete User</li>
                <li class="list-group-item">Edit Users</li>
                <li class="list-group-item">Logging for user-management operations</li>
            </ul>
        </>  
    );
}