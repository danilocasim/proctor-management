export function parseTime(timeStr) {
  const normalized = timeStr.trim().toUpperCase().replace(/\s+/g, ""); // e.g., "3:00pm" -> "3:00PM"
  const match = normalized.match(/^(\d{1,2}):(\d{2})(AM|PM)$/);
  if (!match) throw new Error("Invalid time format: " + timeStr);

  let [_, hours, minutes, meridian] = match;
  hours = parseInt(hours);
  minutes = parseInt(minutes);

  if (meridian === "PM" && hours !== 12) hours += 12;
  if (meridian === "AM" && hours === 12) hours = 0;

  return hours * 60 + minutes;
}

export function parseTimeRange(range) {
  if (range.trim().toLowerCase() === "anytime") return [0, 24 * 60];
  const [startStr, endStr] = range.split(" - ").map((s) => s.trim());
  return [parseTime(startStr), parseTime(endStr)];
}

export function isTimeWithinRange(targetRange, availabilityRange) {
  const [targetStart, targetEnd] = parseTimeRange(targetRange);
  const [availStart, availEnd] = parseTimeRange(availabilityRange);
  return targetStart >= availStart && targetEnd <= availEnd;
}
