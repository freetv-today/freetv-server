import { useEffect } from 'preact/hooks';

// DEV USE:
// THIS PAGE IS FOR DEVELOPER USE TO TEST NEW FEATURES
// DELETE THIS PAGE AND ROUTE BEFORE MOVING TO PRODUCTION!

export function TestPage() { 
    return (
    <>
        <h1 class="mt-5 text-center">Test Page</h1>
        {/* INSERT TEST CODE HERE */}
        <div class="text-center mt-4">
            <button class="btn btn-primary">Test Button</button>
        </div> 
    </>    
    );
}