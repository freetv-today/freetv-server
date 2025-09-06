import { useDebugLog } from '@/hooks/useDebugLog';
import { useConfig } from '@/context/ConfigContext';
import { useEffect } from 'preact/hooks';
import { useAdsense } from '@/hooks/useAdSense';

export function AdBar() {

	const log = useDebugLog();
	const { showads } = useConfig();
    useAdsense(showads);

	useEffect(() => {
        if (showads) {
          log('Advertising is enabled');  
        }
	}, []);

	return (
        <>
			{/* If 'showads' is true display ads... */}
			{showads && (
            <div className="container-fluid text-center mt-2">
                {/* Small screen */}
                <div className="row d-md-none">
					<img src="/src/assets/sample_banner.png" className="smallAd" />
				</div>
                {/* Medium and above screens */}
                <div className="row d-none d-md-block">
					<img src="/src/assets/sample_leaderboard.png" className="largeAd" />
				</div>
            </div>
			)}
            
        </>
	);
}