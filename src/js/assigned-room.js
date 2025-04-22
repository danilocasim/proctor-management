// assigned-room.js

import { getAllSchedules } from "./schedule-api.js";
import { SheetAPI } from "./sheet-api.js";
import { isTimeWithinRange } from "./time-utils.js";

/** Constant for part-time status */
const PART_TIME_STATUS = "Part-time";

/**
 * Proctors utility class for fetching and sorting proctors.
 */
class Proctors {
  /**
   * Fetches proctor data and sorts by status (part-time first).
   * @returns {Promise<Array>} Sorted proctor list
   */
  static async getSortedProctors() {
    try {
      const proctors = await SheetAPI.getNames();
      return proctors.sort((a, b) => (a.Status === PART_TIME_STATUS ? -1 : 1));
    } catch (error) {
      console.error("Failed to fetch or sort proctors:", error);
      return [];
    }
  }
}

/**
 * AssignmentRoom handles subject-proctor assignments.
 */
export class AssignmentRoom {
  /**
   * Returns a list of subjects without assigned proctors.
   * @param {Array} schedule - The schedule data array.
   * @returns {Array} Unassigned subjects
   */
  static unassignedSubjects(schedule) {
    // This assumes each row is a subject (flattened structure from getAllSchedules)
    return schedule.filter(subject =>
      !subject.proctor || subject.proctor.trim() === ""
    );
  }

  /**
   * Assigns proctors to unassigned subjects based on availability and load.
   * @returns {Promise<Array>} Updated schedule with assignments
   */
  static async getAssignedSchedule() {
    try {
      const schedule = await getAllSchedules(); // Fetch dynamic schedule data
      const sortedProctors = await Proctors.getSortedProctors();
      const assignmentCount = {};
      sortedProctors.forEach((p) => (assignmentCount[p.Name] = 0));

      const subjects = this.unassignedSubjects(schedule);

      for (const subject of subjects) {
        const day = subject.exam_day || subject["exam_day"] || subject["day"] || subject["Day"];
        const time = subject.exam_time || subject["exam_time"] || subject["time"] || subject["Time"];

        // Find all proctors available for this day and time
        const availableProctors = sortedProctors.filter((proctor) => {
          const dayAvail = proctor["Day Availability"]?.[day] || proctor[day];
          const timeAvail = proctor["Time Availability"];
          return dayAvail && isTimeWithinRange(time, timeAvail);
        });

        if (availableProctors.length === 0) {
          // User-friendly warning
          console.warn(
            `⚠️ No available proctor for "${subject.name || subject["Subject"]}" on ${day} at ${time}`
          );
          continue;
        }

        // Sort by part-time first, then by fewest assignments
        availableProctors.sort((a, b) => {
          const countA = assignmentCount[a.Name];
          const countB = assignmentCount[b.Name];
          if (a.Status === b.Status) return countA - countB;
          return a.Status === PART_TIME_STATUS ? -1 : 1;
        });

        const selectedProctor = availableProctors[0];
        subject.proctor = selectedProctor.Name;
        assignmentCount[selectedProctor.Name]++;
      }

      console.log("✅ Proctor assignment counts:", assignmentCount);
      return schedule;
    } catch (error) {
      console.error("Failed to assign proctors:", error);
      return [];
    }
  }
}