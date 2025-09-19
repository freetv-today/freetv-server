import { useDebugLog } from '@/hooks/useDebugLog';
import { useConfig } from '@/context/ConfigContext';
import { useEffect } from 'preact/hooks';
import { useAdsense } from '@/hooks/useAdSense';
import { useLocalStorage } from '@/hooks/useLocalStorage';

export function AdBar() {
	
    const [showAdMsg, setShowAdMsg] = useLocalStorage('showAdMsg', 0);
	const log = useDebugLog();
	const { showads, gid } = useConfig();

    useAdsense(showads);

	useEffect(() => {
        if (showads && !showAdMsg) {
          log(`Ads are enabled. (Google ID: ${gid})`); 
		  setShowAdMsg(1);
        }
	}, [showads, showAdMsg]);

	return (
        <>
			{/* If 'showads' is true display ads... */}
			{showads && (
            <div className="container-fluid text-center mt-2">
                {/* Small screen (468 x 60) */}
                <div className="row d-md-none">
					<img src="/src/assets/sample_banner.png" className="smallAd" />
				</div>
                {/* Medium and above screens (728 x 90) */}
                <div className="row d-none d-md-block">
					<img src="/src/assets/sample_leaderboard.png" className="largeAd" />
				</div>
            </div>
			)}
            
        </>
	);
}