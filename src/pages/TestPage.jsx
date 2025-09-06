import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { triggerToast } from '@/signals/toastSignal';

// DEV USE:
// THIS PAGE IS FOR DEVELOPER USE TO TEST NEW FEATURES
// DELETE THIS PAGE AND ROUTE BEFORE MOVING TO PRODUCTION!

export function TestPage() { 
    const log = useDebugLog();

    useEffect(() => {
        document.title = "Free TV: Toast Test";
        log('Rendered Test page (pages/TestPage.jsx)');
    }, []);

    return (
        <div className="container text-center my-5">
            <h2>Toast Test Page</h2>
            <button className="btn btn-success m-2" onClick={() => triggerToast('Added to Favorites!', 'success')}>Show Success Toast</button>
            <button className="btn btn-danger m-2" onClick={() => triggerToast('Removed from Favorites!', 'danger')}>Show Danger Toast</button>
            <button className="btn btn-warning m-2" onClick={() => triggerToast('Warning Toast!', 'warning')}>Show Warning Toast</button>
            <button className="btn btn-light m-2" onClick={() => triggerToast('Light Toast!', 'light')}>Show Light Toast</button>
        </div>
    );
}