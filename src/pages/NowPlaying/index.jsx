import { useEffect, useState, useRef } from 'preact/hooks';
import { useLocalStorage } from '@hooks/useLocalStorage';
import { SpinnerLoadingVideo } from '@components/Loaders/SpinnerLoadingVideo.jsx';
import { Link } from '@components/UI/Link.jsx';
import { useDebugLog } from '@hooks/useDebugLog';

export function NowPlaying() {
  const [currentVid] = useLocalStorage('currentVid', null);
  const [loading, setLoading] = useState(true);
  const [timeoutError, setTimeoutError] = useState(false);
  const timeoutRef = useRef(null);
  const log = useDebugLog();

  // Effect for timeout logic (runs on loading/timeoutError change)
  useEffect(() => {
    if (loading && !timeoutError) {
      timeoutRef.current = setTimeout(() => {
        setTimeoutError(true);
        setLoading(false);
      }, 90000);  // default timeout is 90 seconds
    }
    return () => {
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
    };
  }, [loading, timeoutError]);

  // Effect to clear currentVid on unmount (e.g. leaving the page)
  useEffect(() => {
    return () => {
      // Remove key from local storage
      localStorage.removeItem('currentVid');
    };
  }, []);

  // if user returns to the route and there is no currentVid data
  if (!currentVid) {
    return (
      <div className="container text-center my-5">
        <h2 className="text-danger mb-4">No data for last-watched video</h2>
        <p>You can only return to, or reload this URL while the current video is playing.<br/>After you navigate away from the currently-playing video you'll have to load a new video again.</p>
        <p>Check out your <Link href="/recent" className="link-primary">recently-watched videos</Link>.</p>
        <p><img src="/src/assets/sadface.svg" width="80" /></p>
      </div>
    );
  }

  const { identifier, title } = currentVid;
  const archiveUrl = `https://archive.org/embed/${identifier}?playlist=1&list_height=250`;

  const handleIframeLoad = () => {
    if (timeoutRef.current) clearTimeout(timeoutRef.current);
    setLoading(false);
    setTimeoutError(false);
    // Log only once per load
    if (title) {
      log(`Video ${title} loaded`);
    }
  };

  return (
    <div className={loading ? "" : "timeoutMsg"}>
      {loading && !timeoutError && <SpinnerLoadingVideo title={title.replace(/_/g, ' ')} />}
      {timeoutError && (
        <div className="container text-center my-5">
          <h2 className="text-danger mb-4">Timeout: the video failed to load</h2>
          <p>The Internet Archive server did not respond within 90 seconds.<br/>Please check your internet connection and/or reload to try again.</p>
          <p>If you keep having trouble, check out the <Link href="/help" className="primary-link">Help</Link> page for troubleshooting tips.</p>
          <p><img src="/src/assets/sadface.svg" width="80" /></p>
        </div>
      )}
      {!timeoutError && (
        <iframe
          id="vidviewer"
          width="640" 
          height="480"
          src={archiveUrl}
          style={{ display: loading ? 'none' : 'block' }}
          allowFullScreen
          onLoad={handleIframeLoad}
          title={title}
        />
      )}
    </div>
  );
}