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