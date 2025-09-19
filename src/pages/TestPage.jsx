import { useEffect } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { generateNewCode } from '@/utils';

// DEV USE:
// THIS PAGE IS FOR DEVELOPER USE TO TEST NEW FEATURES
// DELETE THIS PAGE AND ROUTE BEFORE MOVING TO PRODUCTION!

export function TestPage() { 
    const log = useDebugLog();

    useEffect(() => {
        document.title = "Free TV: Test Page";
        log('Rendered Test page (pages/TestPage.jsx)');
    }, []);

    return (
        <div className="container my-5">
            <h2 className="text-center">Codes</h2>
            <ul>
                <li>{generateNewCode()}</li>
                <li>{generateNewCode()}</li>
                <li>{generateNewCode()}</li>
                <li>{generateNewCode()}</li>
            </ul>
        </div>
    );
}