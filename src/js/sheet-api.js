// sheet-api.js

const SHEET_ID = "1fAhT2o5FsHRW_fAUKexj5SpN7a4U1EnWEqhzbc4XnS0";
const SCHEDULE_TAB_NAMES = [
  "IoSW", "IoPPaG", "CoTEd", "CoA", "CITCS", "CoCJ", "CoBA", "CoAaS"
];

const ROOMS_SHEET_NAME = "Room Data";
const PROCTORS_SHEET_NAME = "Prof Data";

// Utility for CSV parsing
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

export async function getAllSchedules() {
  let allData = [];
  for (const tab of SCHEDULE_TAB_NAMES) {
    try {
      const url = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq?tqx=out:csv&sheet=${encodeURIComponent(tab)}`;
      const response = await fetch(url);
      if (!response.ok) throw new Error(`Failed to fetch tab: ${tab}`);
      const csv = await response.text();
      const data = csvToArray(csv);
      data.forEach(row => row._tab = tab);
      allData = allData.concat(data);

      // Debug logs for each tab
      console.log(`[${tab}] Fetched CSV:`, csv);
      console.log(`[${tab}] Parsed array:`, data);
    } catch (e) {
      console.warn(`Could not fetch tab ${tab}:`, e.message);
    }
  }
  return allData;
}

export async function getRooms() {
  const url = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq?tqx=out:csv&sheet=${encodeURIComponent(ROOMS_SHEET_NAME)}`;
  const response = await fetch(url);
  if (!response.ok) throw new Error(`Failed to fetch rooms`);
  const csv = await response.text();
  return csvToArray(csv);
}

export async function getProctors() {
  const url = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq?tqx=out:csv&sheet=${encodeURIComponent(PROCTORS_SHEET_NAME)}`;
  const response = await fetch(url);
  if (!response.ok) throw new Error(`Failed to fetch proctors`);
  const csv = await response.text();
  return csvToArray(csv);
}