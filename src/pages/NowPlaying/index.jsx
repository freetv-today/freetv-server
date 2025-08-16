import { VideoPlayer } from '@components/UI/VideoPlayer.jsx';
import { useEffect } from 'preact/hooks';

export function NowPlaying() {
  useEffect(() => {
    return () => {
      // Remove currentVid from localStorage when leaving NowPlaying
      localStorage.removeItem('currentVid');
    };
  }, []);
  return <VideoPlayer />;
}