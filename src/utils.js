
export function generateToken(length) {
  const a = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890".split("");
  const b = [];
  for (let i = 0; i < length; i++) {
    const j = Math.floor(Math.random() * a.length);
    b[i] = a[j];
  }
  return b.join("");
}

export function generateNewCode() {
  const pt1 = generateToken(4).toUpperCase();
  const pt2 = generateToken(4).toUpperCase();
  const pt3 = generateToken(4).toUpperCase();
  const pt4 = generateToken(4).toUpperCase();
  const pt5 = generateToken(4).toUpperCase();
  const pt6 = generateToken(4).toUpperCase();
  return `${pt1}-${pt2}-${pt3}-${pt4}-${pt5}-${pt6}`;
}

export function capitalizeFirstLetter(string) {
  return string.charAt(0).toUpperCase() + string.slice(1);
}

export function shouldUpdateData(storedData, newData) {
  const storedDate = storedData?.lastupdated ? new Date(storedData.lastupdated) : null;
  const newDate = newData?.lastupdated ? new Date(newData.lastupdated) : null;
  return !storedData || !storedDate || (newDate && newDate > storedDate);
}

export async function enforceMinLoadingTime(startTime, minTime = 1200) {
  const elapsedTime = Date.now() - startTime;
  const remainingTime = minTime - elapsedTime;
  if (remainingTime > 0) {
    await new Promise((resolve) => setTimeout(resolve, remainingTime));
  }
}

export function confirmPlaylistReload() {
  return window.confirm(
    "To show or hide the Episode Playlist you'll have to reload the page. Do you wish to proceed?"
  );
}