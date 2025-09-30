import { useEffect, useState, useRef } from 'preact/hooks';
import { SpinnerLoadingVideo } from '@components/Loaders/SpinnerLoadingVideo';
import { Link } from '@components/Navigation/Link';

/**
 * VideoLoader - Generic video loader with spinner, timeout, and error message
 * Props:
 *   src: string (iframe src)
 *   title: string (video title)
 *   showHelpLink: boolean (show help link on timeout)
 *   onLoad: function (optional, called when iframe loads)
 *   iframeProps: object (optional, extra props for iframe)
 *   timeoutMs: number (timeout in ms, default 90000)
 */

export function VideoLoader({
  src,
  title,
  showHelpLink = false,
  onLoad: onLoadProp,
  iframeProps = {},
  timeoutMs = 90000,
}) {
  const [loading, setLoading] = useState(true);
  const [timeoutError, setTimeoutError] = useState(false);
  const timeoutRef = useRef(null);

  useEffect(() => {
    if (loading && !timeoutError) {
      timeoutRef.current = setTimeout(() => {
        setTimeoutError(true);
        setLoading(false);
      }, timeoutMs);
    }
    return () => {
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
    };
  }, [loading, timeoutError, timeoutMs]);

  const handleIframeLoad = () => {
    if (timeoutRef.current) clearTimeout(timeoutRef.current);
    setLoading(false);
    setTimeoutError(false);
    if (onLoadProp) onLoadProp();
  };

  if (timeoutError) {
    return (
      <div className="container text-center my-5">
        <h2 className="text-danger mb-4">Timeout: the video failed to load</h2>
        <p>
          The Internet Archive server did not respond within {timeoutMs / 1000} seconds.<br />
          Please check your internet connection and/or reload to try again.
        </p>
        {showHelpLink && (
          <p>
            If you keep having trouble, check out the <Link href="/help" className="primary-link">Help</Link> page for troubleshooting tips.
          </p>
        )}
        <p><img src="/assets/sadface.svg" width="80" /></p>
      </div>
    );
  }

  return (
    <div style={{ position: 'relative', width: '100%', height: '100%' }}>
      <div
        style={{
          position: 'absolute',
          top: 0,
          left: 0,
          width: '100%',
          height: '100%',
          background: '#fff',
          visibility: loading ? 'visible' : 'hidden',
          pointerEvents: loading ? 'auto' : 'none',
          zIndex: 2,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
        }}
      >
        <SpinnerLoadingVideo title={title.replace(/_/g, ' ')} />
      </div>
      <iframe
        src={src}
        frameBorder="0"
        allowFullScreen
        onLoad={handleIframeLoad}
        title={title}
        style={{
          position: 'absolute',
          top: 0,
          left: 0,
          width: '100%',
          height: '100%',
          visibility: loading ? 'hidden' : 'visible',
          pointerEvents: loading ? 'none' : 'auto',
          zIndex: 1,
        }}
        {...iframeProps}
      />
    </div>
  );
}
