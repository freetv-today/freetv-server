import { useEffect, useState, useRef } from 'preact/hooks';
import { useLocalStorage } from '@hooks/useLocalStorage';
import { VideoLoader } from '@components/Loaders/VideoLoader';
import { Link } from '@components/Navigation/Link';
import { useDebugLog } from '@hooks/useDebugLog';
import { showVidNavBtnsSignal } from '@signals/showVidNavBtns';
import { logShowEnd } from '@/utils';

export function NowPlaying() {

  const [currentVid] = useLocalStorage('currentVid', null);
  const [embedPlaylist] = useLocalStorage('embedPlaylist', true);
  const log = useDebugLog();

  // Effect to log show end and clear currentVid on unmount (e.g. leaving the page)
  useEffect(() => {
    return () => {
      if (currentVid && currentVid.imdb) {
        logShowEnd(currentVid.imdb);
      }
      localStorage.removeItem('currentVid');
      showVidNavBtnsSignal.value = false;
    };
  }, []);

  // if user returns to the route and there is no currentVid data
  if (!currentVid) {
    return (
      <div className="container text-center my-5">
        <h2 className="text-danger mb-4">No data for last-watched video</h2>
        <p>You can only return to, or reload this URL while the current video is playing. After you navigate away from this page you have to reload a new video again. Try checking your <Link href="/recent" className="link-primary">recently-watched videos</Link>.</p>
        <p><img src="/src/assets/sadface.svg" width="80" /></p>
      </div>
    );
  }

  const { identifier, title } = currentVid;

  useEffect(() => {
    if (title) { document.title = `Free TV: ${title}`; } 
    else { document.title = "Free TV"; }
  }, [title]);

  const archiveUrl = embedPlaylist
  ? `https://archive.org/embed/${identifier}?playlist=1&list_height=300`
  : `https://archive.org/embed/${identifier}`;

  log('Archive URL is: ' + archiveUrl);

  return (
    <div>
      <VideoLoader
        src={archiveUrl}
        title={title}
        showHelpLink={true}
        onLoad={() => {
          showVidNavBtnsSignal.value = true;
          if (title) log(`Video ${title} loaded`);
        }}
        iframeProps={{ id: 'vidviewer' }}
      />
    </div>
  );
}