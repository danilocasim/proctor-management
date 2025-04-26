// generate-exam-schedule.js

// Constants
const BREAK = 30; // minutes
const MAX_SUBJECTS_PER_DAY = 3;
const EXAM_DURATION = 90; // minutes
const EXTENDED_EXAM_DURATION = 180; // minutes for special needs
const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

// Custom fixed time slots
const TIME_SLOTS = [
  { start: "08:30", end: "10:00" },
  { start: "10:00", end: "11:30" },
  { start: "13:00", end: "14:30" },
  { start: "14:30", end: "16:00" },
  { start: "16:00", end: "17:30" },
  { start: "17:30", end: "19:00" }
];

// Helper: Convert "HH:MM" to minutes
function timeToMinutes(timeStr) {
  const [hours, minutes] = timeStr.split(":").map(Number);
  return hours * 60 + minutes;
}

// Helper: Convert minutes to "HH:MM"
function minutesToTime(mins) {
  const hours = Math.floor(mins / 60);
  const minutes = mins % 60;
  return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
}

// Helper: Normalize object keys
function normalizeKeys(obj) {
  const newObj = {};
  Object.keys(obj).forEach(key => {
    const cleanKey = key.replace(/['"]/g, '').trim();
    newObj[cleanKey] = obj[key];
  });
  return newObj;
}

// Subject clustering and prioritization
function clusterAndPrioritizeSubjects(subjects) {
  const subjectMap = {};
  subjects.forEach(s => {
    const key = (s.Subject || "").trim();
    if (!key) return;
    if (!subjectMap[key]) subjectMap[key] = [];
    subjectMap[key].push(s);
  });
  return Object.values(subjectMap).sort((a, b) => b.length - a.length);
}

// --- MAIN SCHEDULER ---
export function generateExamSchedule(subjects, rooms, proctors) {
  // --- NORMALIZATION ---
  proctors = proctors
    .filter(p => p && Object.keys(p).length > 0)
    .map(p => normalizeKeys(p))
    .map(p => {
      const name = typeof p.name === 'string' ? p.name.replace(/['"]/g, '').replace(/\\"/g, '').trim() : '';
      const facultyType = typeof p.facultyType === 'string' ? p.facultyType.replace(/['"]/g, '').replace(/\\"/g, '').trim() : '';
      const requiredHours = typeof p.requiredHours === 'string' ? p.requiredHours.replace(/['"]/g, '').replace(/\\"/g, '').trim() : '';
      const academicLoad = typeof p.academicLoad === 'string' ? p.academicLoad.replace(/['"]/g, '').replace(/\\"/g, '').trim() : '';
      const dayAvailability = typeof p['Day Availability'] === 'string' ? p['Day Availability'].replace(/['"]/g, '').replace(/\\"/g, '').trim() : '';
      const timeAvailability = typeof p['Time Availability'] === 'string' ? p['Time Availability'].replace(/['"]/g, '').replace(/\\"/g, '').trim() : '';
      return {
        ...p,
        name,
        facultyType,
        requiredHours,
        academicLoad,
        'Day Availability': dayAvailability,
        'Time Availability': timeAvailability,
      };
    });

  rooms = rooms
    .filter(r => r && Object.keys(r).length > 0)
    .map(r => normalizeKeys(r))
    .map(r => {
      const name = typeof r.name === 'string' ? r.name.replace(/['"]/g, '').trim() : '';
      const capacity = typeof r.capacity === 'string' ? r.capacity.replace(/['"]/g, '').trim() : '';
      const type = typeof r.type === 'string' ? r.type.replace(/['"]/g, '').trim() : '';
      const specialNeeds = typeof r.specialNeeds === 'string' ? r.specialNeeds.replace(/['"]/g, '').trim() : '';
      const capacityNum = Number(capacity);
      return {
        name,
        capacity: capacityNum,
        type: type || null,
        specialNeeds: specialNeeds || false
      };
    })
    .filter(r => {
      if (!r.name || !r.capacity || isNaN(r.capacity) || r.capacity <= 0) {
        console.log(`Invalid room data:`, r);
        return false;
      }
      return true;
    });

  subjects = subjects
    .filter(s => s && Object.keys(s).length > 0)
    .map(s => normalizeKeys(s))
    .filter(s => {
      if (!s.Subject || !s.studentCount) {
        console.log(`Invalid subject data:`, s);
        return false;
      }
      return true;
    })
    .map(s => ({
      ...s,
      studentCount: Number(s.studentCount) || 0,
      specialNeeds: s.specialNeeds ? String(s.specialNeeds).trim() : false,
      roomType: s.roomType ? String(s.roomType).trim() : null
    }));

  // --- DEBUG: Normalized Data ---
  console.log("Normalized proctors:", proctors);
  console.log("Normalized rooms:", rooms);
  console.log("Normalized subjects:", subjects);

  // --- CLUSTERING & PRIORITIZATION ---
  const clusteredSubjects = clusterAndPrioritizeSubjects(subjects);

  // --- SCHEDULING ---
  const schedule = [];
  const sectionDayCount = {};
  const sectionLastExamEnd = {};
  const proctorLastEnd = {};
  const roomBookings = {};
  const sectionRoomLast = {};

  for (const day of DAYS) {
    for (const slotObj of TIME_SLOTS) {
      const slotStart = timeToMinutes(slotObj.start);
      const slotEnd = timeToMinutes(slotObj.end);

      for (const subjectGroup of clusteredSubjects) {
        const unscheduledSections = subjectGroup.filter(s => !schedule.find(e => e.Section === s.Section && e.Subject === s.Subject));
        if (unscheduledSections.length === 0) continue;

        // Track used rooms for this slot to avoid double-booking in the cluster
        let usedRooms = new Set();
        let canScheduleAll = true;
        let candidateRooms = [];

        for (const subj of unscheduledSections) {
          const sectionKey = `${subj.Section}-${day}`;
          const examsToday = sectionDayCount[sectionKey] || 0;
          const lastEnd = sectionLastExamEnd[sectionKey] || 0;
          if (examsToday >= MAX_SUBJECTS_PER_DAY) { canScheduleAll = false; break; }
          if (slotStart - lastEnd < BREAK && lastEnd > 0) { canScheduleAll = false; break; }

          // Find available room not already used in this cluster for this slot
          let availableRooms = rooms.filter(r =>
            !usedRooms.has(r.name) &&
            r.capacity >= subj.studentCount &&
            (!subj.specialNeeds || r.specialNeeds) &&
            (!subj.roomType || r.type === subj.roomType) &&
            !(roomBookings[`${r.name}-${day}`] || []).some(b => (slotStart < b.end && slotEnd > b.start))
          );
          if (sectionRoomLast[sectionKey]) {
            availableRooms = availableRooms.sort((a, b) => (a.name === sectionRoomLast[sectionKey] ? -1 : 1));
          } else {
            availableRooms = availableRooms.sort((a, b) => a.capacity - b.capacity);
          }
          if (availableRooms.length === 0) {
            canScheduleAll = false;
            console.log(`No available rooms for ${subj.Subject} Section ${subj.Section} on ${day} at ${slotObj.start}`);
            break;
          }
          const assignedRoom = availableRooms[0];
          candidateRooms.push(assignedRoom);
          usedRooms.add(assignedRoom.name);
        }
        if (!canScheduleAll) continue;

        // Assign proctors and finalize scheduling for all these sections in this slot
        for (let i = 0; i < unscheduledSections.length; i++) {
          const subj = unscheduledSections[i];
          const room = candidateRooms[i];
          const sectionKey = `${subj.Section}-${day}`;
          const availableProctors = proctors.filter(p => {
            const dayAvailRaw = p["Day Availability"] || "";
            const dayAvailList = dayAvailRaw.split(',').map(d => d.trim().toLowerCase()).filter(Boolean);
            const isAvailableToday = dayAvailList.length === 0 || dayAvailList.includes(day.toLowerCase());
            const hasBreak = !proctorLastEnd[p.name] || slotStart - proctorLastEnd[p.name] >= BREAK;
            return isAvailableToday && hasBreak;
          }).sort((a, b) => (a.assignedMinutes || 0) - (b.assignedMinutes || 0));
          if (availableProctors.length < 1) {
            schedule.push({
              ...subj, Day: day, Time: "", Room: "", Proctor: "", Reason: "No available proctor for this slot"
            });
            console.log(`No available proctor for ${subj.Subject} Section ${subj.Section} on ${day} at ${slotObj.start}`);
            continue;
          }
          const assignedProctor = availableProctors[0];
          assignedProctor.assignedMinutes = (assignedProctor.assignedMinutes || 0) + EXAM_DURATION;
          proctorLastEnd[assignedProctor.name] = slotEnd;

          schedule.push({
            ...subj,
            Day: day,
            Time: `${slotObj.start} - ${slotObj.end}`,
            Room: room.name,
            Proctor: assignedProctor.name
          });
          sectionDayCount[sectionKey] = (sectionDayCount[sectionKey] || 0) + 1;
          sectionLastExamEnd[sectionKey] = slotEnd;
          sectionRoomLast[sectionKey] = room.name;
          roomBookings[`${room.name}-${day}`] = (roomBookings[`${room.name}-${day}`] || []).concat({start: slotStart, end: slotEnd});
        }
        // After scheduling in this slot, break to next subject group
        break;
      }
    }
  }

  // Mark any unscheduled subjects
  for (const subj of subjects) {
    if (!schedule.find(e => e.Section === subj.Section && e.Subject === subj.Subject)) {
      schedule.push({
        ...subj,
        Day: "Unscheduled",
        Time: "",
        Room: "",
        Proctor: "",
        Reason: "Could not be scheduled under current constraints"
      });
      console.log("UNSCHEDULED:", subj, "Reason: Could not be scheduled under current constraints");
    }
  }

  console.log("Final generated schedule:", schedule);
  return schedule;
}
export function getProctorWorkloadReport(proctors) {
  return proctors.map(p => ({
    Name: p.name,
    facultyType: p.facultyType,
    RequiredHours: (p.requiredMinutes || 0) / 60,
    AssignedHours: (p.assignedMinutes || 0) / 60,
    PercentFulfilled: p.requiredMinutes
      ? Math.round(100 * (p.assignedMinutes / p.requiredMinutes)) + "%"
      : "N/A"
  }));
}