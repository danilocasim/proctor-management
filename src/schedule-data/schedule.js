import { SheetAPI } from "../js/sheet-api.js";

export class Subjects {
  static async getSubjects() {
    return await SheetAPI.sheetNamesAPI("CS Subjects");
  }

  static async separateSubjects() {
    const firstYearSubject = [];
    const secondYearSubjects = [];
    const thirdYearSubjects = [];
    const fourthYearSubjects = [];

    const subjects = await this.getSubjects();

    subjects.forEach((subject) => {
      for (let sub in subject) {
        const proctor = "";
        if (sub == "1st Year") {
          const [name, time, day] = subject[sub].split(" | ");
          firstYearSubject.push({ name, time, day, proctor });
        }
        if (sub == "2nd Year") {
          const [name, time, day] = subject[sub].split(" | ");
          secondYearSubjects.push({ name, time, day, proctor });
        }
        if (sub == "3rd Year") {
          const [name, time, day] = subject[sub].split(" | ");
          thirdYearSubjects.push({ name, time, day, proctor });
        }
        if (sub == "4th Year") {
          const [name, time, day] = subject[sub].split(" | ");
          fourthYearSubjects.push({ name, time, day, proctor });
        }
      }
    });

    return [
      firstYearSubject,
      secondYearSubjects,
      thirdYearSubjects,
      fourthYearSubjects,
    ];
  }
}

export class Sections {
  static async getSection() {
    return await SheetAPI.sheetNamesAPI("CS Sections");
  }

  static async addSubjects() {
    const sections = await this.getSection();
    const subjects = await Subjects.separateSubjects();
    sections.map(async (section) => {
      if (section.Section[0] == "1") {
        section["Subjects"] = subjects[0];
      }
      if (section.Section[0] == "2") {
        section["Subjects"] = subjects[1];
      }
      if (section.Section[0] == "3") {
        section["Subjects"] = subjects[2];
      }
      if (section.Section[0] == "4") {
        section["Subjects"] = subjects[3];
      }
    });

    return sections;
  }
}
