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
        <p><img src="/src/assets/sadface.svg" width="80" /></p>
      </div>
    );
  }

  return (
    <>
      {loading && <SpinnerLoadingVideo title={title.replace(/_/g, ' ')} />}
      <iframe
        src={src}
        width="100%"
        height="480"
        frameBorder="0"
        allowFullScreen
        style={{ display: loading ? 'none' : 'block', width: '100%', minHeight: 400 }}
        onLoad={handleIframeLoad}
        title={title}
        {...iframeProps}
      />
    </>
  );
}
