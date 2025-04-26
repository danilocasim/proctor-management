// schedule-api.js

const SHEET_ID = "1fAhT2o5FsHRW_fAUKexj5SpN7a4U1EnWEqhzbc4XnS0";
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

// Scheduling Logic
function canSchedule(subject, day) {
  // Implement logic to check if a subject can be scheduled on a given day
  return true; // Placeholder
}

function findAvailableRoom(subject, rooms) {
  // Implement logic to find an available room for a subject
  return rooms[0]; // Placeholder
}

function findAvailableProctor(subject, proctors) {
  // Implement logic to find an available proctor for a subject
  return proctors[0]; // Placeholder
}

function allocateDays(subjects, days) {
  const schedule = [];
  subjects.forEach(subject => {
    for (const day of days) {
      if (canSchedule(subject, day)) {
        schedule.push({ subject, day });
        break;
      }
    }
  });
  return schedule;
}

function assignRooms(schedule, rooms) {
  schedule.forEach(entry => {
    const room = findAvailableRoom(entry.subject, rooms);
    if (room) {
      entry.room = room;
    } else {
      entry.reason = "No available room";
    }
  });
}

function assignProctors(schedule, proctors) {
  schedule.forEach(entry => {
    const proctor = findAvailableProctor(entry.subject, proctors);
    if (proctor) {
      entry.proctor = proctor;
    } else {
      entry.reason = "No available proctor";
    }
  });
}

// Main Function
async function generateSchedule() {
  const subjects = await getAllSchedules();
  const rooms = await fetchTab("Room Data");
  const proctors = await fetchTab("Prof Data");

  const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
  let schedule = allocateDays(subjects, days);
  assignRooms(schedule, rooms);
  assignProctors(schedule, proctors);

  console.log("Final Schedule:", schedule);
}

generateSchedule().catch(console.error);