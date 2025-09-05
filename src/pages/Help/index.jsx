import { useEffect } from 'preact/hooks';
import { HelpAnnouncement } from '@components/Help/HelpAnnouncement';
import { HelpAbout } from '@components/Help/HelpAbout';
import { HelpIndex } from '@components/Help/HelpIndex';
import { HelpFAQ } from '@components/Help/HelpFAQ';
import { HelpHowToUse } from '@components/Help/HelpHowToUse';
import { HelpTroubleshooting } from '@components/Help/HelpTroubleshooting';
import { HelpVersionInfo } from '@components/Help/HelpVersionInfo';
import { HelpLicenseInfo } from '@components/Help/HelpLicenseInfo';
import { useDebugLog } from '@/hooks/useDebugLog';
import { resetUrl } from '@/utils';

export function Help() {
    const log = useDebugLog();

    useEffect(() => {
        document.title = "Free TV: Help";
        log('Rendered Help page (pages/Help/index.jsx)');
    }, []);

    return (
        <div id="top" className="mt-5">
            <div className="container mt-0 mb-5">
                <h1 className="fs-1 fw-bold mb-4">Free TV Help</h1>
                <HelpAnnouncement />
                <div className="text-center my-5">
                    <a href="https://downdetector.com/status/internetarchive/" target="_blank" className="btn btn-lg btn-danger shadow border border-1 border-dark" title="Open new tab or window showing Internet Archive Network Status">
                        <img src="/src/assets/help/health-white.svg" height="40" className="me-2" />
                        Internet Archive Network Status
                    </a>
                </div>
                <HelpAbout />
                <HelpIndex />
                <hr />
                <HelpHowToUse />
                <hr />
                <HelpTroubleshooting />
                <hr />
                <HelpFAQ />
                <hr />
                <HelpVersionInfo />
                <HelpLicenseInfo />

                <div className="my-5 text-center">
                    <h1 className="display-4 bruno-ace">Free TV</h1>
                    <a className="fw-bold" href="/"><img src="/src/assets/freetv.png" width="185" title="Free TV" alt="Free TV Logo" /></a>
                </div>
                <div className="my-5 fs-4 fw-bold text-center">
                    <a href="https://archive.org" className="fw-bold link-dark text-decoration-none" target="_blank" title="Powered by the Internet Archive">
                        Powered by the Internet Archive
                        <br/>
                        <img src="/src/assets/help/internet_archive-black.svg" width="65" title="The Internet Archive" alt="The Internet Archive" className="mt-3" />
                    </a>
                </div>
                
                <p className="text-center my-4">
                    <a
                      href="#top"
                      className="fw-bold btn btn-sm btn-primary"
                      onClick={() => setTimeout(resetUrl, 1000)}
                    >
                    Back to Top &#9650;
                    </a>
                </p>
            </div>
        </div>
    );
}