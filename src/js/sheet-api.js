export class SheetAPI {
  static handleResponse(csvText) {
    let sheetObjects = this.csvToObjects(csvText);

    // Transform each row to bundle day availability
    sheetObjects = sheetObjects.map((row) => {
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

  static csvToObjects(csv) {
    const csvRows = csv.split("\n");
    const propertyNames = this.csvSplit(csvRows[0]);
    let objects = [];
    for (let i = 1, max = csvRows.length; i < max; i++) {
      let thisObject = {};
      let row = this.csvSplit(csvRows[i]);
      for (let j = 0, max = row.length; j < max; j++) {
        thisObject[propertyNames[j]] = row[j];
      }
      objects.push(thisObject);
    }
    return objects;
  }

  static csvSplit(row) {
    return row.split(",").map((val) => val.substring(1, val.length - 1));
  }

  static async sheetNamesAPI() {
    try {
      const sheetId = "1Pmbx5h6gPFWzsRBaIhd8NoTd3mAU5gDbufd-4rPRzlk";
      const sheetName = encodeURIComponent("Prof Data");
      const sheetURL = `https://docs.google.com/spreadsheets/d/${sheetId}/gviz/tq?tqx=out:csv&sheet=${sheetName}`;

      const response = await fetch(sheetURL);
      const csvText = await response.text();
      const objects = await this.handleResponse(csvText);

      return objects;
    } catch (e) {
      console.error(new Error(e));
    }
  }

  static async getNames() {
    return await this.sheetNamesAPI();
  }
}
