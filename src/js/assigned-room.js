import schedule from "../schedule-data/first-sem-schedule.json" with { type: "json" };
import { SheetAPI } from "./sheet-api.js";

class Proctors {
    static async getSortedProctors() {
        const proctors = await SheetAPI.getNames();

        const sortedProctors =  proctors.sort((a, b) =>
            a.Status === "Part-time" ? -1 : 1
          );
        return sortedProctors
    }
}

export class AssignmentRoom {
  static unassignedSubjects() {
    const unassignedSubjects = [];
    schedule.forEach((dept) => {
      dept.courses.forEach((course) => {
        course.sections.forEach((section) => {
          section.subjects.forEach((subject) => {
            if (!subject.proctor || subject.proctor.trim() === "") {
              unassignedSubjects.push(subject);
            }
          });
        });
      });
    });
    return unassignedSubjects
  }

  static async assignmentCount() {
    const sortedProctors = await Proctors.getSortedProctors()

    const assignmentCount = {};
    sortedProctors.forEach((p) => (assignmentCount[p.Name] = 0));
    return assignmentCount  
  }

  static async getAssignedSchedule () {
    const sortedProctors = await Proctors.getSortedProctors()

    let proctorIndex = 0;
    this.unassignedSubjects().forEach(async (subject) => {
    const proctor = sortedProctors[proctorIndex % sortedProctors.length];
    subject.proctor = proctor.Name;
    await this.assignmentCount()[proctor.Name]++;
    proctorIndex++;
});
    return schedule
}
}
