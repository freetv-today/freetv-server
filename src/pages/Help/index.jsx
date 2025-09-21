import { useEffect } from 'preact/hooks';
import { HelpAnnouncement } from '@components/Help/HelpAnnouncement';
import { HelpAbout } from '@components/Help/HelpAbout';
import { HelpIndex } from '@components/Help/HelpIndex';
import { HelpFAQ } from '@components/Help/HelpFAQ';
import { HelpHowToUse } from '@components/Help/HelpHowToUse';
import { HelpTroubleshooting } from '@components/Help/HelpTroubleshooting';
import { HelpVersionInfo } from '@components/Help/HelpVersionInfo';
import { HelpLicenseInfo } from '@components/Help/HelpLicenseInfo';
import { HelpDeveloperInfo } from '@/components/Help/HelpDeveloperInfo';
import { useDebugLog } from '@/hooks/useDebugLog';
import { ImageLargeLogo } from '@/components/UI/ImageLargeLogo';
import { AdBar } from '@/components/UI/AdBar';
import { resetUrl } from '@/utils';


export function Help() {

    const log = useDebugLog();

    useEffect(() => {
        document.title = "Free TV: Help";
        log('Rendered Help page (pages/Help/index.jsx)');
    }, []);

    return (
        <div id="top" className="mt-5">
            <div className="container">
                <AdBar/>
                <h1 className="fs-1 fw-bold my-4 text-center">Free TV Help</h1>
                <HelpAnnouncement />
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
                <hr />
                <HelpDeveloperInfo />
                <hr />
                <HelpLicenseInfo />
                <hr />
                <ImageLargeLogo />
                <div className="my-5 fs-4 fw-bold text-center">
                    <a href="https://archive.org" className="fw-bold link-dark text-decoration-none" target="_blank" title="Powered by the Internet Archive">
                        Powered by the Internet Archive<br/>
                        <img src="/assets/help/internet_archive-black.svg" width="65" title="The Internet Archive" alt="The Internet Archive" className="mt-3" />
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