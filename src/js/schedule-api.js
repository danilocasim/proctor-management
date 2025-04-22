// schedule-api.js

const SHEET_ID = "1Pmbx5h6gPFWzsRBaIhd8NoTd3mAU5gDbufd-4rPRzlk";
const TAB_NAMES = ["IoSW", "IoPPaG", "CoTEd", "CoA", "CITCS", "CoCJ", "CoBA", "CoAaS"];

async function fetchTab(tabName) {
  const url = `https://docs.google.com/spreadsheets/d/${SHEET_ID}/gviz/tq?tqx=out:csv&sheet=${encodeURIComponent(tabName)}`;
  const response = await fetch(url);
  if (!response.ok) throw new Error(`Failed to fetch tab: ${tabName}`);
  const csv = await response.text();
  return csvToArray(csv);
}

function csvToArray(csv) {
  const [header, ...rows] = csv.trim().split('\n');
  const keys = header.split(',').map(k => k.trim());
  return rows.map(row => {
    const values = row.split(',').map(v => v.trim());
    return Object.fromEntries(keys.map((k, i) => [k, values[i]]));
  });
}

export async function getAllSchedules() {
  let allData = [];
  for (const tab of TAB_NAMES) {
    try {
      const data = await fetchTab(tab);
      allData = allData.concat(data);
    } catch (e) {
      console.warn(`Could not fetch tab ${tab}:`, e.message);
    }
  }
  return allData;
}