// time-utils.js

/**
 * Parses a time string (e.g., "3:00PM") into minutes since midnight.
 * @param {string} timeStr
 * @returns {number|null} Minutes since midnight, or null if invalid
 */
export function parseTime(timeStr) {
  try {
    const normalized = timeStr.trim().toUpperCase().replace(/\s+/g, ""); // e.g., "3:00pm" -> "3:00PM"
    const match = normalized.match(/^(\d{1,2}):(\d{2})(AM|PM)$/);
    if (!match) throw new Error("Invalid time format: " + timeStr);

    let [_, hours, minutes, meridian] = match;
    hours = parseInt(hours);
    minutes = parseInt(minutes);

    if (meridian === "PM" && hours !== 12) hours += 12;
    if (meridian === "AM" && hours === 12) hours = 0;

    return hours * 60 + minutes;
  } catch (error) {
    console.error("parseTime error:", error.message);
    return null;
  }
}

/**
 * Parses a time range string (e.g., "9:00AM - 11:00AM") into start and end minutes.
 * @param {string} range
 * @returns {[number, number]|null} Array of [start, end] minutes, or null if invalid
 */
export function parseTimeRange(range) {
  try {
    if (range.trim().toLowerCase() === "anytime") return [0, 24 * 60];
    const [startStr, endStr] = range.split(" - ").map((s) => s.trim());
    const start = parseTime(startStr);
    const end = parseTime(endStr);
    if (start === null || end === null) throw new Error("Invalid time range: " + range);
    return [start, end];
  } catch (error) {
    console.error("parseTimeRange error:", error.message);
    return null;
  }
}

/**
 * Checks if a target time range is within an availability range.
 * @param {string} targetRange
 * @param {string} availabilityRange
 * @returns {boolean} True if target is within availability, false otherwise
 */
export function isTimeWithinRange(targetRange, availabilityRange) {
  const target = parseTimeRange(targetRange);
  const avail = parseTimeRange(availabilityRange);
  if (!target || !avail) return false;
  const [targetStart, targetEnd] = target;
  const [availStart, availEnd] = avail;
  return targetStart >= availStart && targetEnd <= availEnd;
}