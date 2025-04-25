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

    // Track total assignments for each proctor
    const assignmentCount = {};
    sortedProctors.forEach((p) => (assignmentCount[p.Name] = 0));

    // Track daily assignments for each proctor
    const dailyAssignmentCount = {};
    sortedProctors.forEach((p) => {
      dailyAssignmentCount[p.Name] = {
        Monday: 0,
        Tuesday: 0,
        Wednesday: 0,
        Thursday: 0,
        Friday: 0,
      };
    });

    const subjects = await this.unassignedSubjects();

    // Sort subjects by day to process them together
    subjects.sort((a, b) => {
      if (a.day !== b.day) return a.day.localeCompare(b.day);
      return a.time.localeCompare(b.time);
    });

    // First pass: Try to assign all subjects while respecting daily limits
    for (const subject of subjects) {
      const { day, time } = subject;

      // Find available proctors who haven't reached daily limit
      let availableProctors = sortedProctors.filter((proctor) => {
        const dayAvail = proctor["Day Availability"]?.[day];
        const timeAvail = proctor["Time Availability"];
        const dailyLimit = dailyAssignmentCount[proctor.Name][day] < 3;

        return dayAvail && isTimeWithinRange(time, timeAvail) && dailyLimit;
      });

      if (availableProctors.length > 0) {
        // Sort by total assignments first for global fairness
        availableProctors.sort(
          (a, b) => assignmentCount[a.Name] - assignmentCount[b.Name]
        );

        // Assign to the proctor with the least assignments
        const selectedProctor = availableProctors[0];
        subject.proctor = selectedProctor.Name;

        // Update counts
        assignmentCount[selectedProctor.Name]++;
        dailyAssignmentCount[selectedProctor.Name][day]++;
      }
    }

    // Second pass: For any unassigned subjects, try to find proctors who still have capacity
    let unassignedSubjects = subjects.filter((s) => !s.proctor);

    while (unassignedSubjects.length > 0) {
      let assignedInThisPass = false;

      for (const subject of unassignedSubjects) {
        const { day, time } = subject;

        // Try one more time with focus on daily balance
        let availableProctors = sortedProctors.filter((proctor) => {
          const dayAvail = proctor["Day Availability"]?.[day];
          const timeAvail = proctor["Time Availability"];
          const dailyLimit = dailyAssignmentCount[proctor.Name][day] < 3;

          return dayAvail && isTimeWithinRange(time, timeAvail) && dailyLimit;
        });

        if (availableProctors.length > 0) {
          // Sort by daily assignments for this specific day
          availableProctors.sort(
            (a, b) =>
              dailyAssignmentCount[a.Name][day] -
              dailyAssignmentCount[b.Name][day]
          );

          // Assign to the proctor with the least assignments on this day
          const selectedProctor = availableProctors[0];
          subject.proctor = selectedProctor.Name;

          // Update counts
          assignmentCount[selectedProctor.Name]++;
          dailyAssignmentCount[selectedProctor.Name][day]++;
          assignedInThisPass = true;
        } else {
          // Check if there are ANY available proctors regardless of daily limit
          availableProctors = sortedProctors.filter((proctor) => {
            const dayAvail = proctor["Day Availability"]?.[day];
            const timeAvail = proctor["Time Availability"];

            return dayAvail && isTimeWithinRange(time, timeAvail);
          });

          if (availableProctors.length === 0) {
            console.error(
              `❌ No proctors at all available for ${subject.name} on ${day} at ${time}!`
            );
          }
        }
      }

      // Check if we made progress in this pass
      if (!assignedInThisPass) {
        break; // If we couldn't assign any subjects in this pass, exit the loop
      }

      // Update unassigned subjects and continue if we made progress
      unassignedSubjects = subjects.filter((s) => !s.proctor);
    }

    // Final pass with relaxed constraints if needed
    unassignedSubjects = subjects.filter((s) => !s.proctor);

    if (unassignedSubjects.length > 0) {
      console.warn(
        `⚠️ ${unassignedSubjects.length} subjects couldn't be assigned within daily limits. Attempting with relaxed constraints...`
      );

      for (const subject of unassignedSubjects) {
        const { day, time } = subject;

        // Find ANY available proctors regardless of daily limit
        const availableProctors = sortedProctors.filter((proctor) => {
          const dayAvail = proctor["Day Availability"]?.[day];
          const timeAvail = proctor["Time Availability"];

          return dayAvail && isTimeWithinRange(time, timeAvail);
        });

        if (availableProctors.length > 0) {
          // Sort by total assignments for overall fairness
          availableProctors.sort(
            (a, b) => assignmentCount[a.Name] - assignmentCount[b.Name]
          );

          // Assign to the proctor with the least total assignments
          const selectedProctor = availableProctors[0];
          subject.proctor = selectedProctor.Name;

          // Update counts
          assignmentCount[selectedProctor.Name]++;
          dailyAssignmentCount[selectedProctor.Name][day]++;

          console.warn(
            `⚠️ Daily limit exceeded for ${
              selectedProctor.Name
            } on ${day} with ${
              dailyAssignmentCount[selectedProctor.Name][day]
            } assignments`
          );
        } else {
          console.error(
            `❌ No proctors available for ${subject.name} on ${day} at ${time}!`
          );
        }
      }
    }

    // Calculate statistics to verify fairness
    const assignedProctors = sortedProctors.filter(
      (p) => assignmentCount[p.Name] > 0
    );
    const minAssignments =
      assignedProctors.length > 0
        ? Math.min(...assignedProctors.map((p) => assignmentCount[p.Name]))
        : 0;
    const maxAssignments =
      assignedProctors.length > 0
        ? Math.max(...assignedProctors.map((p) => assignmentCount[p.Name]))
        : 0;
    const totalAssigned = subjects.filter((s) => s.proctor).length;
    const unassigned = subjects.filter((s) => !s.proctor);

    if (!unassigned) console.warn(unassigned);
    console.log("✅ Proctor assignment counts:", assignmentCount);
    console.log(
      `✅ Assignment distribution: min=${minAssignments}, max=${maxAssignments}, spread=${
        maxAssignments - minAssignments
      }`
    );
    console.log(`✅ Assigned subjects: ${totalAssigned}/${subjects.length}`);
  }
}
