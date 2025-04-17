import schedule from "../schedule-data/first-sem-schedule.json" with { type: "json" };
import { SheetAPI } from "./sheet-api.js";
import {  isTimeWithinRange } from "./time-utils.js";

class Proctors {
  static async getSortedProctors() {
    const proctors = await SheetAPI.getNames();
    return proctors.sort((a, b) => (a.Status === "Part-time" ? -1 : 1));
  }
}

export class AssignmentRoom {
  static unassignedSubjects() {
    const unassignedSubjects = [];
    schedule.forEach((dept) =>
      dept.courses.forEach((course) =>
        course.sections.forEach((section) =>
          section.subjects.forEach((subject) => {
            if (!subject.proctor || subject.proctor.trim() === "") {
              unassignedSubjects.push(subject);
            }
          })
        )
      ) 
    );
    return unassignedSubjects;
  }

  static async getAssignedSchedule() {
    const sortedProctors = await Proctors.getSortedProctors();
    const assignmentCount = {};
    sortedProctors.forEach((p) => (assignmentCount[p.Name] = 0));

    const subjects = this.unassignedSubjects();

    for (const subject of subjects) {
      const { day, time } = subject.exam;

      const availableProctors = sortedProctors.filter((proctor) => {
        const dayAvail = proctor["Day Availability"]?.[day];
        const timeAvail = proctor["Time Availability"];
        return (
          dayAvail &&
          isTimeWithinRange(time, timeAvail)
        );
      });

      if (availableProctors.length === 0) {
        console.warn(`⚠️ No available proctor for ${subject.name} on ${day} at ${time}`);
        continue;
      }

      // Sort by part-time first, then by fewest assignments
      availableProctors.sort((a, b) => {
        const countA = assignmentCount[a.Name];
        const countB = assignmentCount[b.Name];
        if (a.Status === b.Status) return countA - countB;
        return a.Status === "Part-time" ? -1 : 1;
      });

      const selectedProctor = availableProctors[0];
      subject.proctor = selectedProctor.Name;
      assignmentCount[selectedProctor.Name]++;
    }

    console.log("✅ Proctor assignment counts:", assignmentCount);
    return schedule;
  }
}
