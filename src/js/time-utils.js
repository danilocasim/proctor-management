// time-utils.js

export function parseTime(timeStr) {
  const match = timeStr.match(/(\d{1,2}):(\d{2})\s*(AM|PM)?/i);
  if (!match) throw new Error("Invalid time format: " + timeStr);
  let [_, h, m, ap] = match;
  h = parseInt(h, 10);
  m = parseInt(m, 10);
  if (ap) {
    ap = ap.toUpperCase();
    if (ap === "PM" && h < 12) h += 12;
    if (ap === "AM" && h === 12) h = 0;
  }
  return h * 60 + m;
}

export function minutesToTimeString(mins) {
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  const ampm = h >= 12 ? "PM" : "AM";
  const h12 = h % 12 === 0 ? 12 : h % 12;
  return `${h12}:${m.toString().padStart(2, "0")} ${ampm}`;
}