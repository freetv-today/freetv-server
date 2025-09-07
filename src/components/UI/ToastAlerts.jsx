import { toastSignal } from '@/signals/toastSignal';
import { useEffect } from 'preact/hooks';

export function ShowToastAlert() {
    const { color, msg, show } = toastSignal.value;

    useEffect(() => {
        if (show) {
            setTimeout(() => {
                const toast = document.getElementById('app-toast');
                if (toast) {
                    // @ts-ignore
                    const bootstrap = window.bootstrap;
                    if (bootstrap && toast['toastInstance']) {
                        toast['toastInstance'].show();
                    } else if (bootstrap) {
                        toast['toastInstance'] = new bootstrap.Toast(toast, {delay: 3000}); // 3 second dela
                        toast['toastInstance'].show();
                    }
                }
            }, 10);
        }
    }, [show, msg, color]);

    // Use white close button for dark backgrounds, default for light/warning
    const closeBtnClass =
        color === 'warning' || color === 'light'
            ? 'btn-close me-2 m-auto'
            : 'btn-close btn-close-white me-2 m-auto';

    return (
        <div
            className={`
                toast align-items-center text-bg-${color} border-0 position-fixed bottom-0
                w-100 start-50 translate-middle-x
                w-md-auto end-md-0 start-md-auto translate-middle-x-md-none
                m-0 m-md-3
            `}
            id="app-toast"
            role="alert"
            aria-live="assertive"
            aria-atomic="true"
            style={{ zIndex: 9999 }}
        >
            <div className="d-flex">
                <div className="toast-body">
                    {msg}
                </div>
                <button
                    type="button"
                    className={closeBtnClass}
                    data-bs-dismiss="toast"
                    aria-label="Close"
                ></button>
            </div>
        </div>
    );
}