// sheet-api.js

// Google Sheet constants
const SHEET_ID = "1Pmbx5h6gPFWzsRBaIhd8NoTd3mAU5gDbufd-4rPRzlk";
const SCHEDULE_TAB_NAMES = [
  "IoSW", "IoPPaG", "CoTEd", "CoA", "CITCS", "CoCJ", "CoBA", "CoAaS"
];
const PROF_DATA_SHEET_NAME = "Prof Data";

/**
 * SheetAPI provides methods to fetch and parse proctor data from Google Sheets.
 */
export class SheetAPI {
  /**
   * Converts CSV text to an array of objects, bundling day availability.
   * @param {string} csvText
   * @returns {Array<Object>}
   */
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
          delete row[key];
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
    try {
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
    } catch (error) {
      console.error("CSV parsing failed:", error);
      return [];
    }
  }

  static csvSplit(row) {
    return row.split(",").map((val) => val.substring(1, val.length - 1));
  }

  /**
   * Fetches proctor names from the Google Sheet.
   * @returns {Promise<Array<Object>>}
   */
  static async sheetNamesAPI() {
    try {
      const sheetURL = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq?tqx=out:csv&sheet=${encodeURIComponent(PROF_DATA_SHEET_NAME)}`;
      const response = await fetch(sheetURL);
      if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
      const csvText = await response.text();
      return this.handleResponse(csvText);
    } catch (error) {
      console.error("Failed to fetch or parse sheet data:", error);
      return [];
    }
  }

  static async getNames() {
    return this.sheetNamesAPI();
  }
}

// --- Schedule fetching functions ---

/**
 * Fetches CSV data from a specific tab in the Google Sheet.
 * @param {string} tabName
 * @returns {Promise<Array<Object>>}
 */
async function fetchTab(tabName) {
  const url = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq?tqx=out:csv&sheet=${encodeURIComponent(tabName)}`;
  const response = await fetch(url);
  if (!response.ok) throw new Error(`Failed to fetch tab: ${tabName}`);
  const csv = await response.text();
  return csvToArray(csv);
}

/**
 * Converts CSV text to an array of objects.
 * @param {string} csv
 * @returns {Array<Object>}
 */
function csvToArray(csv) {
  const [header, ...rows] = csv.trim().split('\n');
  const keys = header.split(',').map(k => k.trim());
  return rows
    .filter(row => row.trim().length > 0)
    .map(row => {
      const values = row.split(',').map(v => v.trim());
      return Object.fromEntries(keys.map((k, i) => [k, values[i] || ""]));
    });
}

/**
 * Fetches and merges schedule data from all relevant tabs.
 * @returns {Promise<Array<Object>>}
 */
export async function getAllSchedules() {
  let allData = [];
  for (const tab of SCHEDULE_TAB_NAMES) {
    try {
      const data = await fetchTab(tab);
      data.forEach(row => row._tab = tab);
      allData = allData.concat(data);
    } catch (e) {
      console.warn(`Could not fetch tab ${tab}:`, e.message);
    }
  }
  return allData;
}