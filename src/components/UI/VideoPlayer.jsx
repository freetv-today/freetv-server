import { useState, useEffect } from 'preact/hooks';
import { useLocalStorage } from '@/hooks/useLocalStorage';
import { SpinnerLoadingVideo } from '@components/Loaders/SpinnerLoadingVideo.jsx';

export function VideoPlayer() {
  const [currentVid] = useLocalStorage('currentVid', null);
  const [embedPlaylist] = useLocalStorage('embedPlaylist', true);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true); // Show spinner when playlist toggle changes
  }, [embedPlaylist, currentVid]);

  if (!currentVid) {
    return (
      <div class="container text-center my-5">
        <h2 class="text-danger">No video selected</h2>
      </div>
    );
  }

  const { identifier, title } = currentVid;
  const archiveUrl = `https://archive.org/embed/${identifier}${embedPlaylist ? '?playlist=1' : ''}`;

  return (
    <div class="container-fluid p-0 m-0" style={{ minHeight: '80vh' }}>
      {loading && <SpinnerLoadingVideo title={title.replace(/_/g, ' ')} />}
      <iframe
        id="vidviewer"
        src={archiveUrl}
        style={{ display: loading ? 'none' : 'block' }}
        allowFullScreen
        onLoad={() => setLoading(false)}
        title={title}
      />
    </div>
  );
}