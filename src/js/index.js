import { getAllSchedules, getRooms, getProctors } from "./sheet-api.js";
import { generateExamSchedule, getProctorWorkloadReport } from "./generate-exam-schedule.js";

let lastScheduleData = [];

// Helper to clean and normalize values
function cleanValue(val) {
  if (typeof val === "string") {
    // Remove all double quotes and trim
    const cleaned = val.replace(/"/g, '').trim();
    return cleaned === "" ? "" : cleaned;
  }
  return val;
}

// Helper to check for truly non-empty, non-quote values
function isNonEmpty(val) {
  return val && cleanValue(val) !== "";
}

// Normalize a row object
function normalizeRow(row) {
  const norm = {};
  Object.keys(row).forEach(key => {
    norm[key] = cleanValue(row[key]);
  });
  return norm;
}

function getUniqueCourseDept(data) {
  const set = new Set();
  data.forEach(row => {
    if (isNonEmpty(row['Course Code'])) set.add(cleanValue(row['Course Code']));
    if (isNonEmpty(row['_tab'])) set.add(cleanValue(row['_tab']));
  });
  return Array.from(set);
}

function populateFilters(data) {
  const columns = [
    { id: "courseCodeFilter", key: "Course Code" },
    { id: "departmentFilter", key: "_tab" },
    { id: "yearFilter", key: "Year" },
    { id: "sectionFilter", key: "Section" },
    { id: "dayFilter", key: "Day" },
    { id: "roomFilter", key: "Room" },
    { id: "proctorFilter", key: "Proctor" }
  ];
  columns.forEach(col => {
    const select = document.querySelector(`#${col.id}`);
    if (!select) return;
    const unique = Array.from(new Set(data.map(row => cleanValue(row[col.key])).filter(isNonEmpty)));
    // Save the current value so we can try to preserve it
    const prevVal = select.value;
    select.innerHTML = `<option value=\"all\">All</option>` + unique.map(val => `<option value=\"${val}\">${val}</option>`).join('');
    // Try to restore previous selection if possible
    if (unique.includes(prevVal)) select.value = prevVal;
    else select.value = 'all';
  });
}

function renderOutput(data, isError = false, scheduleFilter = "scheduled") {
  console.log('Rendering output with data:', data, 'isError:', isError, 'scheduleFilter:', scheduleFilter);
  // Hide/show the Reason column header
  const showReason = scheduleFilter !== "scheduled";
  const reasonHeader = document.querySelector('#reasonHeader');
  if (reasonHeader) reasonHeader.style.display = showReason ? "" : "none";
  const reasonFilterHeader = document.querySelector('#reasonFilterHeader');
  if (reasonFilterHeader) reasonFilterHeader.style.display = showReason ? "" : "none";

  const tbody = document.querySelector("#scheduleTableBody");
  tbody.innerHTML = "<tr><td colspan='10' style='color: green;'>renderOutput called! Data length: " + (Array.isArray(data) ? data.length : 'N/A') + "</td></tr>";

  if (isError) {
    tbody.innerHTML = `<tr><td colspan=\"10\" style=\"color: red;\">${data}</td></tr>`;
    return;
  }

  if (!Array.isArray(data) || data.length === 0) {
    tbody.innerHTML = `<tr><td colspan=\"10\">No schedule data found for the selected filter(s).</td></tr>`;
    return;
  }

  // Remove test row before rendering real data
  tbody.innerHTML = "";

  data.forEach(row => {
    const tr = document.createElement('tr');
    // For mobile: add data-labels to each cell
    const cells = [
      {label: 'Course Code', value: cleanValue(row['Course Code'])},
      {label: 'Department', value: isNonEmpty(row['_tab']) ? cleanValue(row['_tab']) : "N/A"},
      {label: 'Year', value: cleanValue(row['Year'])},
      {label: 'Section', value: cleanValue(row['Section'])},
      {label: 'Subject', value: cleanValue(row['Subject'])},
      {label: 'Day', value: cleanValue(row['Day'])},
      {label: 'Time', value: cleanValue(row['Time'])},
      {label: 'Room', value: cleanValue(row['Room'])},
      {label: 'Proctor', value: cleanValue(row['Proctor'])}
    ];
    let tds = cells.map(cell => `<td data-label=\"${cell.label}\">${cell.value}</td>`).join('');
    if (showReason) {
      tds += `<td data-label=\"Reason\">${cleanValue(row['Reason'])}</td>`;
    }
    tr.innerHTML = tds;
    if (!showReason && tr.children[9]) tr.children[9].style.display = "none";
    tbody.appendChild(tr);
  });
}

function filterSchedule() {
  console.log("filterSchedule called, lastScheduleData:", lastScheduleData);
  const scheduleFilter = document.querySelector('#viewTypeSelect'); 
  if (!scheduleFilter) { console.warn('Missing #viewTypeSelect'); return; }
  const courseCode = document.querySelector('#courseCodeFilter');
  if (!courseCode) { console.warn('Missing #courseCodeFilter'); return; }
  const department = document.querySelector('#departmentFilter');
  if (!department) { console.warn('Missing #departmentFilter'); return; }
  const year = document.querySelector('#yearFilter');
  if (!year) { console.warn('Missing #yearFilter'); return; }
  const section = document.querySelector('#sectionFilter');
  if (!section) { console.warn('Missing #sectionFilter'); return; }
  const day = document.querySelector('#dayFilter');
  if (!day) { console.warn('Missing #dayFilter'); return; }
  const room = document.querySelector('#roomFilter');
  if (!room) { console.warn('Missing #roomFilter'); return; }
  const proctor = document.querySelector('#proctorFilter');
  if (!proctor) { console.warn('Missing #proctorFilter'); return; }

  let filtered = lastScheduleData;

  // Scheduled/Unscheduled/All filter
  if (scheduleFilter.value === 'scheduled') {
    filtered = filtered.filter(e =>
      isNonEmpty(e['Course Code']) &&
      isNonEmpty(e['Year']) &&
      isNonEmpty(e['Section']) &&
      isNonEmpty(e['Subject']) &&
      e.Day && e.Day !== "Unscheduled" &&
      e.Time && e.Room && e.Proctor
    );
  } else if (scheduleFilter.value === 'unscheduled') {
    filtered = filtered.filter(e =>
      !isNonEmpty(e['Course Code']) ||
      !isNonEmpty(e['Year']) ||
      !isNonEmpty(e['Section']) ||
      !isNonEmpty(e['Subject']) ||
      !e.Day || e.Day === "Unscheduled" ||
      !e.Time || !e.Room || !e.Proctor
    );
  }
  // Multi-column filters
  if (courseCode.value !== "all") filtered = filtered.filter(e => cleanValue(e['Course Code']) === courseCode.value);
  if (department.value !== "all") filtered = filtered.filter(e => cleanValue(e['_tab']) === department.value);
  if (year.value !== "all") filtered = filtered.filter(e => cleanValue(e['Year']) === year.value);
  if (section.value !== "all") filtered = filtered.filter(e => cleanValue(e['Section']) === section.value);
  if (day.value !== "all") filtered = filtered.filter(e => cleanValue(e['Day']) === day.value);
  if (room.value !== "all") filtered = filtered.filter(e => cleanValue(e['Room']) === room.value);
  if (proctor.value !== "all") filtered = filtered.filter(e => cleanValue(e['Proctor']) === proctor.value);

  renderOutput(filtered, false, scheduleFilter.value);
  renderSchedulingSummary(filtered);
}

function renderSchedulingSummary(filteredData) {
  const summaryDiv = document.querySelector('#proctor-summary');
  if (!summaryDiv) return;
  summaryDiv.innerHTML = `<div>Total exams shown: ${filteredData.length}</div>`;
}

// --- Reporting & Monitoring ---
function groupScheduleData(data, type) {
  // type: 'room', 'section', 'professor', 'floor'
  const keyMap = {
    room: row => row['Room'],
    section: row => row['Section'],
    professor: row => row['Proctor'],
    floor: row => {
      // Example: extract floor from room name like '3F-201'
      if (row['Room'] && row['Room'].match(/\dF/)) {
        return row['Room'].match(/\dF/)[0];
      }
      return 'Unknown';
    }
  };
  const getKey = keyMap[type];
  const grouped = {};
  data.forEach(row => {
    const key = getKey(row) || 'Unknown';
    if (!grouped[key]) grouped[key] = [];
    grouped[key].push(row);
  });
  return grouped;
}

function renderReport(type) {
  const grouped = groupScheduleData(lastScheduleData, type);
  let html = '';
  Object.keys(grouped).forEach(group => {
    html += `<h3>${type.charAt(0).toUpperCase() + type.slice(1)}: ${group}</h3>`;
    html += `<table border="1" cellpadding="5" style="border-collapse:collapse; margin-bottom: 1rem;">
      <thead>
        <tr>
          <th>Course Code</th>
          <th>Department</th>
          <th>Year</th>
          <th>Section</th>
          <th>Subject</th>
          <th>Day</th>
          <th>Time</th>
          <th>Room</th>
          <th>Proctor</th>
        </tr>
      </thead>
      <tbody>`;
    grouped[group].forEach(row => {
      html += `<tr>
        <td>${cleanValue(row['Course Code'])}</td>
        <td>${isNonEmpty(row['_tab']) ? cleanValue(row['_tab']) : "N/A"}</td>
        <td>${cleanValue(row['Year'])}</td>
        <td>${cleanValue(row['Section'])}</td>
        <td>${cleanValue(row['Subject'])}</td>
        <td>${cleanValue(row['Day'])}</td>
        <td>${cleanValue(row['Time'])}</td>
        <td>${cleanValue(row['Room'])}</td>
        <td>${cleanValue(row['Proctor'])}</td>
      </tr>`;
    });
    html += '</tbody></table>';
  });
  const reportOutput = document.querySelector('#report-output');
  if (reportOutput) reportOutput.innerHTML = html;
}

// --- Resource Forecasting ---
function forecastResources(data, pagesPerStudent, scantronsPerStudent) {
  let totalStudents = 0;
  data.forEach(row => {
    if (row['Student Count']) {
      totalStudents += parseInt(row['Student Count'], 10) || 0;
    }
  });
  return {
    totalStudents,
    bondPapers: totalStudents * pagesPerStudent,
    scantrons: totalStudents * scantronsPerStudent
  };
}

document.addEventListener('DOMContentLoaded', function() {
  // --- Reporting & Monitoring ---
  const generateReportBtn = document.querySelector('#generateReportBtn');
  if (generateReportBtn) {
    generateReportBtn.addEventListener('click', () => {
      const reportType = document.querySelector('#reportType');
      if (reportType) {
        const type = reportType.value;
        renderReport(type);
      }
    });
  }
  const exportReportBtn = document.querySelector('#exportReportBtn');
  if (exportReportBtn) {
    exportReportBtn.addEventListener('click', () => {
      const reportType = document.querySelector('#reportType');
      if (reportType) {
        const type = reportType.value;
        const grouped = groupScheduleData(lastScheduleData, type);
        const wb = XLSX.utils.book_new();
        Object.keys(grouped).forEach(group => {
          const ws = XLSX.utils.json_to_sheet(grouped[group]);
          XLSX.utils.book_append_sheet(wb, ws, group);
        });
        XLSX.writeFile(wb, `schedule-report-by-${type}.xlsx`);
      }
    });
  }
  const forecastBtn = document.querySelector('#forecastBtn');
  if (forecastBtn) {
    forecastBtn.addEventListener('click', () => {
      const pagesPerStudent = document.querySelector('#pagesPerStudent');
      const scantronsPerStudent = document.querySelector('#scantronsPerStudent');
      if (pagesPerStudent && scantronsPerStudent) {
        const pages = parseInt(pagesPerStudent.value, 10) || 1;
        const scantrons = parseInt(scantronsPerStudent.value, 10) || 0;
        const forecast = forecastResources(lastScheduleData, pages, scantrons);
        const forecastOutput = document.querySelector('#forecast-output');
        if (forecastOutput) forecastOutput.innerHTML = `
          <div><strong>Total Students:</strong> ${forecast.totalStudents}</div>
          <div><strong>Bond Paper Pages Needed:</strong> ${forecast.bondPapers}</div>
          <div><strong>Scantrons Needed:</strong> ${forecast.scantrons}</div>
        `;
      }
    });
  }
  const scheduleFilter = document.querySelector('#viewTypeSelect'); 
  if (scheduleFilter) {
    scheduleFilter.addEventListener('change', () => {
      [
        'courseCodeFilter', 'departmentFilter', 'yearFilter',
        'sectionFilter', 'dayFilter', 'roomFilter', 'proctorFilter'
      ].forEach(id => {
        const sel = document.querySelector(`#${id}`);
        if (sel) sel.value = 'all';
      });
      const scheduleFilterVal = scheduleFilter.value;
      if (scheduleFilterVal === 'all') {
        populateFilters(lastScheduleData);
      } else {
        let filtered = lastScheduleData;
        if (scheduleFilterVal === 'scheduled') {
          filtered = filtered.filter(e =>
            isNonEmpty(e['Course Code']) &&
            isNonEmpty(e['Year']) &&
            isNonEmpty(e['Section']) &&
            isNonEmpty(e['Subject']) &&
            e.Day && e.Day !== "Unscheduled" &&
            e.Time && e.Room && e.Proctor
          );
        } else if (scheduleFilterVal === 'unscheduled') {
          filtered = filtered.filter(e =>
            !isNonEmpty(e['Course Code']) ||
            !isNonEmpty(e['Year']) ||
            !isNonEmpty(e['Section']) ||
            !isNonEmpty(e['Subject']) ||
            !e.Day || e.Day === "Unscheduled" ||
            !e.Time || !e.Room || !e.Proctor
          );
        }
        populateFilters(filtered);
      }
      filterSchedule();
    });
  }
  [
    'courseCodeFilter', 'departmentFilter', 'yearFilter',
    'sectionFilter', 'dayFilter', 'roomFilter', 'proctorFilter'
  ].forEach(id => {
    const filterEl = document.querySelector(`#${id}`);
    if (filterEl) {
      filterEl.addEventListener('change', filterSchedule);
    }
  });

  // Initial data load
  async function loadAndRender() {
    try {
      // Load your data here
      const [subjects, rooms, proctors] = await Promise.all([
        getAllSchedules(),
        getRooms(),
        getProctors()
      ]);
      // Normalize all rows
      const normSubjects = subjects.map(normalizeRow);
      const normRooms = rooms.map(normalizeRow);
      const normProctors = proctors.map(normalizeRow);

      console.log('Subjects:', normSubjects);
      console.log('Rooms:', normRooms);
      console.log('Proctors:', normProctors);

      lastScheduleData = generateExamSchedule(normSubjects, normRooms, normProctors);
      console.log('Generated schedule:', lastScheduleData);

      populateFilters(lastScheduleData);
      filterSchedule(); // Initial render
    } catch (err) {
      renderOutput("Failed to load schedule: " + err, true);
      console.error('Error in loadAndRender:', err);
    }
  }

  loadAndRender();
});