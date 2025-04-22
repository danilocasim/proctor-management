// index.js

import { AssignmentRoom } from "./assigned-room.js";

/**
 * Renders the schedule or error message to the DOM.
 * @param {Object|Array} data - The schedule data or error message.
 * @param {boolean} isError - Whether the message is an error.
 */
function renderOutput(data, isError = false) {
  let outputDiv = document.getElementById("output");
  if (!outputDiv) {
    outputDiv = document.createElement("div");
    outputDiv.id = "output";
    document.body.appendChild(outputDiv);
  }
  outputDiv.innerHTML = "";

  if (isError) {
    outputDiv.innerHTML = `<div style="color: red;">${data}</div>`;
    return;
  }

  // Render the schedule as a simple JSON (customize as needed)
  outputDiv.innerHTML = `<pre>${JSON.stringify(data, null, 2)}</pre>`;
}

/**
 * Main function to load and display the assigned schedule.
 */
async function main() {
  renderOutput("Loading schedule...");
  try {
    const schedule = await AssignmentRoom.getAssignedSchedule();
    renderOutput(schedule);
  } catch (error) {
    renderOutput("Failed to load schedule: " + error.message, true);
  }
}

main();