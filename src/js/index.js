import { getAllSchedules, getRooms, getProctors } from "./sheet-api.js";
import { generateExamSchedule, getProctorWorkloadReport } from "./generate-exam-schedule.js";

let lastScheduleData = [];

function renderOutput(data, isError = false, filter = "scheduled") {
  const outputDiv = document.getElementById("output");
  outputDiv.innerHTML = "";

  if (isError) {
    outputDiv.innerHTML = `<div style="color: red;">${data}</div>`;
    return;
  }

  if (!Array.isArray(data) || data.length === 0) {
    outputDiv.innerHTML = "<div>No schedule data available.</div>";
    return;
  }

  let filteredData;
  if (filter === "scheduled") {
    filteredData = data.filter(row =>
      row.Day && row.Day !== "Unscheduled" &&
      row.Time && row.Room && row.Proctor
    );
  } else if (filter === "unscheduled") {
    filteredData = data.filter(row =>
      !row.Day || row.Day === "Unscheduled" ||
      !row.Time || !row.Room || !row.Proctor
    );
  } else {
    filteredData = data;
  }

  if (filteredData.length === 0) {
    outputDiv.innerHTML = "<div>No schedule data found for this filter.</div>";
    return;
  }

  // Render the summary before the table
  renderSchedulingSummary(data);

  const preferredOrder = [
    "Course Code", "Year", "Section", "Subject", "Day", "Time", "Room", "Proctor"
  ];
  const columns = [...preferredOrder, "Reason", "_tab"];

  // Normalize data keys
  const normalizedData = filteredData.map(row => {
    const normalizedRow = {};
    columns.forEach(col => {
      const key = Object.keys(row).find(
        k => k.trim().toLowerCase() === col.trim().toLowerCase()
      );
      normalizedRow[col] = key ? row[key] : "";
    });
    return normalizedRow;
  });

  let html = "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse;'>";
  html += "<thead><tr>";
  columns.forEach(col => {
    html += `<th>${col}</th>`;
  });
  html += "</tr></thead><tbody>";

  normalizedData.forEach(row => {
    html += "<tr>";
    columns.forEach(col => {
      if (col === "Day" || col === "Time") {
        html += `<td><b>${row[col]}</b></td>`;
      } else if (col === "Reason" && row[col]) {
        html += `<td style="color: #8B0000; font-style: italic;">${row[col]}</td>`;
      } else {
        html += `<td>${row[col]}</td>`;
      }
    });
    html += "</tr>";
  });

  html += "</tbody></table>";
  outputDiv.innerHTML = html;
}

function renderProctorSummary(report) {
  const div = document.getElementById("proctor-summary");
  if (!div) return;
  if (!Array.isArray(report) || report.length === 0) {
    div.innerHTML = "<div>No proctor workload data available.</div>";
    return;
  }
  let html = "<h3>Proctor Workload Summary</h3><table border='1' cellpadding='5' cellspacing='0' style='border-collapse:collapse;'>";
  html += "<thead><tr><th>Name</th><th>Type</th><th>Required Hours</th><th>Assigned Hours</th><th>% Fulfilled</th></tr></thead><tbody>";
  report.forEach(row => {
    html += `<tr>
      <td>${row.Name}</td>
      <td>${row.facultyType}</td>
      <td>${row.RequiredHours}</td>
      <td>${row.AssignedHours}</td>
      <td>${row.PercentFulfilled}</td>
    </tr>`;
  });
  html += "</tbody></table>";
  div.innerHTML = html;
}

async function main() {
  renderOutput("Loading schedule...");
  try {
    // Fetch and normalize data
    const subjectsRaw = await getAllSchedules();
    const roomsRaw = await getRooms();
    const proctorsRaw = await getProctors();

    console.log("Raw Subjects Data:", subjectsRaw);
    console.log("Raw Rooms Data:", roomsRaw);
    console.log("Raw Proctors Data:", proctorsRaw);

    const subjects = subjectsRaw.map(normalizeKeys);
    const rooms = roomsRaw.map(normalizeKeys);
    const proctors = proctorsRaw.map(normalizeKeys);

    console.log("Normalized Subjects:", subjects);
    console.log("Normalized Rooms:", rooms);
    console.log("Normalized Proctors:", proctors);

    const schedule = generateExamSchedule(subjects, rooms, proctors);
    console.log("Generated Schedule:", schedule);

    renderOutput(schedule);

    // Render proctor workload summary
    const proctorReport = getProctorWorkloadReport(proctors);
    renderProctorSummary(proctorReport);

    // Enable export button if present
    const exportBtn = document.getElementById("exportExcelBtn");
    if (exportBtn) {
      exportBtn.disabled = false;
      exportBtn.onclick = () => exportScheduleToExcel(schedule);
    }
  } catch (error) {
    renderOutput("Failed to load schedule: " + error.message, true);
  }
}

function normalizeKeys(obj) {
  const keyMap = {
    "Student Count": "studentCount",
    "studentCount": "studentCount",
    "Room Name": "name",
    "name": "name",
    "Capacity": "capacity",
    "capacity": "capacity",
    "Name": "name",
    "Faculty Type": "facultyType",
    "facultyType": "facultyType",
    "Required Hours": "requiredHours",
    "requiredHours": "requiredHours",
    "Academic Load": "academicLoad",
    "academicLoad": "academicLoad",
    // Add more mappings as needed for your exact sheet columns
  };
  const newObj = {};
  for (const key in obj) {
    const normKey = keyMap[key] || key;
    let value = obj[key];
    // Remove surrounding quotes if present
    if (typeof value === "string" && value.startsWith('"') && value.endsWith('"')) {
      value = value.slice(1, -1);
    }
    // Convert certain fields to numbers
    if (
      normKey === "studentCount" ||
      normKey === "capacity" ||
      normKey === "requiredHours" ||
      normKey === "academicLoad"
    ) {
      newObj[normKey] = value ? Number(value) : 0;
    } else {
      newObj[normKey] = value;
    }
  }
  return newObj;
}

main();

document.getElementById("scheduleFilter").addEventListener("change", function() {
  const filterType = this.value;
  renderOutput(lastScheduleData, filterType);
});

function exportScheduleToExcel(schedule) {
  const grouped = {};
  schedule.forEach(row => {
    const dept = row._tab || "Other";
    if (!grouped[dept]) grouped[dept] = [];
    grouped[dept].push(row);
  });

  const wb = XLSX.utils.book_new();

  Object.entries(grouped).forEach(([dept, rows]) => {
    const exportRows = rows.map(({["Schedule Summary"]: _, ...rest}) => rest);
    const ws = XLSX.utils.json_to_sheet(exportRows);
    XLSX.utils.book_append_sheet(wb, ws, dept.substring(0, 31));
  });

  XLSX.writeFile(wb, "Exam-Schedule.xlsx");
}


function renderSchedulingSummary(data) {
  const summaryDiv = document.getElementById("scheduling-summary");
  if (!summaryDiv) return;

  const summary = {
    totalSubjects: data.length,
    scheduled: data.filter(row => row.Day && row.Day !== "Unscheduled").length,
    unscheduled: data.filter(row => !row.Day || row.Day === "Unscheduled").length,
    reasons: {}
  };

  // Collect reasons for unscheduled exams
  data.filter(row => !row.Day || row.Day === "Unscheduled")
    .forEach(row => {
      const reason = row.Reason || "Unknown reason";
      summary.reasons[reason] = (summary.reasons[reason] || 0) + 1;
    });

  // Generate summary HTML
  let html = `<div class="scheduling-summary" style="margin: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">`;
  
  // Overall Summary
  html += `<h3>Scheduling Summary</h3>`;
  html += `<div class="summary-overview">`;
  html += `<div class="summary-item">`;
  html += `<span class="summary-label">Total Subjects:</span>`;
  html += `<span class="summary-value">${summary.totalSubjects}</span>`;
  html += `</div>`;
  html += `<div class="summary-item">`;
  html += `<span class="summary-label">Successfully Scheduled:</span>`;
  html += `<span class="summary-value" style="color: #28a745;">${summary.scheduled}</span>`;
  html += `</div>`;
  html += `<div class="summary-item">`;
  html += `<span class="summary-label">Unscheduled:</span>`;
  html += `<span class="summary-value" style="color: #dc3545;">${summary.unscheduled}</span>`;
  html += `</div>`;
  html += `</div>`;

  // Detailed Reason Analysis
  if (Object.keys(summary.reasons).length > 0) {
    html += `<h4 style="margin-top: 20px;">Unscheduled Exam Reasons:</h4>`;
    html += `<div class="reasons-list">`;
    Object.entries(summary.reasons).sort((a, b) => b[1] - a[1]).forEach(([reason, count]) => {
      const percentage = ((count / summary.unscheduled) * 100).toFixed(1);
      html += `<div class="reason-item">`;
      html += `<span class="reason-count">${count}</span>`;
      html += `<span class="reason-label">${reason}</span>`;
      html += `<span class="reason-percentage">(${percentage}%)</span>`;
      html += `</div>`;
    });
    html += `</div>`;
  }

  // Department-wise Summary
  const deptSummary = {};
  data.forEach(row => {
    const dept = row["Course Code"]?.split("-")[0] || "Unknown";
    deptSummary[dept] = deptSummary[dept] || {
      total: 0,
      scheduled: 0,
      unscheduled: 0
    };
    deptSummary[dept].total++;
    if (!row.Day || row.Day === "Unscheduled") {
      deptSummary[dept].unscheduled++;
    } else {
      deptSummary[dept].scheduled++;
    }
  });

  if (Object.keys(deptSummary).length > 1) { // Only show if there are multiple departments
    html += `<h4 style="margin-top: 20px;">Department-wise Summary:</h4>`;
    html += `<div class="dept-summary">`;
    Object.entries(deptSummary).sort((a, b) => b[1].total - a[1].total).forEach(([dept, stats]) => {
      const scheduledPct = ((stats.scheduled / stats.total) * 100).toFixed(1);
      html += `<div class="dept-item">`;
      html += `<span class="dept-name">${dept}</span>`;
      html += `<span class="dept-stats">Total: ${stats.total}, Scheduled: ${stats.scheduled} (${scheduledPct}%)</span>`;
      html += `</div>`;
    });
    html += `</div>`;
  }

  html += `</div>`;
  summaryDiv.innerHTML = html;
}