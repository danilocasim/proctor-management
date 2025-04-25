import { Sections } from "../schedule-data/schedule.js";
import { SheetAPI } from "./sheet-api.js";
import { isTimeWithinRange } from "./time-utils.js";

class Proctors {
  static async getSortedProctors() {
    const proctors = await SheetAPI.sheetNamesAPI("Prof Data");

    proctors.sort((a, b) => (a.Status === "Part-time" ? -1 : 1));
    // Transform each row to bundle day availability
    const sheetObjects = proctors.map((row) => {
      const dayAvailability = {};

      for (const key of Object.keys(row)) {
        const cleanKey = key.trim();
        if (
          ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"].includes(
            cleanKey
          )
        ) {
          dayAvailability[cleanKey] = row[key].trim().toUpperCase() === "TRUE";
          delete row[key]; // Remove individual day from root
        }
      }

      // Normalize "Monday " with trailing space
      if ("Monday " in row) {
        dayAvailability["Monday"] =
          row["Monday "].trim().toUpperCase() === "TRUE";
        delete row["Monday "];
      }

      row["Day Availability"] = dayAvailability;
      return row;
    });
    return sheetObjects;
  }
}

export class AssignmentRoom {
  static async unassignedSubjects() {
    const sections = await Sections.addSubjects();

    const unassignedSubjects = [];
    sections.forEach((section) =>
      section.Subjects.forEach((subject) => {
        if (!subject.proctor || subject.proctor.trim() === "") {
          unassignedSubjects.push(subject);
        }
      })
    );

    return unassignedSubjects;
  }

  static async getAssignedSchedule() {
    const sortedProctors = await Proctors.getSortedProctors();
    const assignmentCount = {};
    sortedProctors.forEach((p) => (assignmentCount[p.Name] = 0));

    const subjects = await this.unassignedSubjects();

    // Separate proctors by status
    const partTimeProctors = sortedProctors.filter(
      (p) => p.Status === "Part-time"
    );
    const fullTimeProctors = sortedProctors.filter(
      (p) => p.Status === "Full-time"
    );

    let ptIndex = 0;
    let ftIndex = 0;
    let usePartTime = true;

    for (const subject of subjects) {
      const { day, time } = subject;
      let assigned = false;

      for (let i = 0; i < sortedProctors.length; i++) {
        const proctorGroup = usePartTime ? partTimeProctors : fullTimeProctors;
        if (proctorGroup.length === 0) {
          usePartTime = !usePartTime;
          continue;
        }

        const currentIndex = usePartTime
          ? ptIndex % proctorGroup.length
          : ftIndex % proctorGroup.length;
        const proctor = proctorGroup[currentIndex];
        const dayAvail = proctor["Day Availability"]?.[day];
        const timeAvail = proctor["Time Availability"];

        if (dayAvail && isTimeWithinRange(time, timeAvail)) {
          subject.proctor = proctor.Name;
          assignmentCount[proctor.Name]++;
          assigned = true;

          if (usePartTime) ptIndex++;
          else ftIndex++;

          usePartTime = !usePartTime; // Alternate for next round
          break;
        } else {
          // If the current proctor can't take the subject, try the other group on next round
          usePartTime = !usePartTime;
        }
      }

      if (!assigned) {
        console.warn(
          `⚠️ No available proctor for ${subject.name} on ${day} at ${time}`
        );
      }
    }

    console.log("✅ Proctor assignment counts:", assignmentCount);
    return subjects;
  }
}
