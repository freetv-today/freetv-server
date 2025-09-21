
/**
 * AdBar component displays a Google AdSense ad if enabled in config.
 * - Loads ad slot for medium+ screens, sample banner for small screens.
 * - Uses localStorage to show a one-time log message when ads are enabled.
 * - Relies on useAdsense hook to trigger AdSense rendering after slot is mounted.
 *
 * @component
 * @returns {JSX.Element}
 */
import { useDebugLog } from '@/hooks/useDebugLog';
import { useConfig } from '@/context/ConfigContext';
import { useEffect, useRef } from 'preact/hooks';
import { useAdsense } from '@/hooks/useAdSense';
import { useLocalStorage } from '@/hooks/useLocalStorage';

export function AdBar() {
    /**
     * showAdMsg: 0 if ad message not shown, 1 if shown
     * setShowAdMsg: setter for showAdMsg
     */
    const [showAdMsg, setShowAdMsg] = useLocalStorage('showAdMsg', 0);
    /**
     * log: debug logger
     */
    const log = useDebugLog();
    /**
     * showads: boolean, whether ads are enabled
     * gid: Google AdSense publisher ID
     */
	const { showads, gid, 'sm-ad-slot': smAdSlot, 'lg-ad-slot': lgAdSlot } = useConfig();
    /**
     * adRef: ref to the ad slot element
     */
    const adRef = useRef(null);

    // Only call useAdsense when the adRef is present
    useAdsense(showads && adRef.current);

    useEffect(() => {
        if (showads && !showAdMsg) {
            log(`Ads are enabled. (Google ID: ${gid})`);
            setShowAdMsg(1);
        }
    }, [showads, showAdMsg]);

    return (
        <>
            {showads && (
                <div className="container-fluid text-center mt-2">
                    <div className="row d-md-none">
						<ins
                            class="adsbygoogle"
							style="display:inline-block;width:728px;height:90px"
							data-ad-client={gid}
							data-ad-slot={smAdSlot}
							ref={adRef}
                        ></ins>
                    </div>
                    <div className="row d-none d-md-block">
                        <ins
                            class="adsbygoogle"
							style="display:inline-block;width:728px;height:90px"
							data-ad-client={gid}
							data-ad-slot={lgAdSlot}
							ref={adRef}
                        ></ins>
                    </div>
                </div>
            )}
        </>
    );
}