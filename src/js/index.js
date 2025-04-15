import schedule from "../schedule-data/first-sem-schedule.json" with { type: "json" };
import { SheetAPI } from "./sheet-api.js";

// Show loading indicator
const scheduleContent = document.getElementById('schedule-content');
scheduleContent.innerHTML = "<p>Loading...</p>";

/**
 * Fetch and display schedule names from SheetAPI.
 */
async function displaySchedule() {
  try {
    const names = await SheetAPI.getNames();
    if (names && names.length > 0) {
      scheduleContent.innerHTML = names.map(name => `<p>${name}</p>`).join('');
    } else {
      scheduleContent.innerHTML = "<p>No schedule data found.</p>";
    }
  } catch (error) {
    scheduleContent.innerHTML = "<p>Error loading schedule. Please try again later.</p>";
    console.error("Failed to load schedule:", error);
  }
}
displaySchedule();