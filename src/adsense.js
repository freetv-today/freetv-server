export async function loadAdsense() {
  try {
    const res = await fetch('/config.json');
    const config = await res.json();
    if (config.showads && config.gid) {
      const script = document.createElement('script');
      script.async = true;
      script.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${encodeURIComponent(config.gid)}`;
      script.crossOrigin = 'anonymous';
      document.head.appendChild(script);
    }
  } catch {
     console.error('Config file could not be loaded');
  }
}