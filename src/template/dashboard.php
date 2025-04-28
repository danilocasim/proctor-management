<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Proctor Management System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" href="../assets/logo.png" type="image/png">
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <nav class="sidebar">
      <div class="dashboard-logo">
        <img src="../../assets/image/plmun-logo.png" alt="PLMUN Logo" class="sidebar-logo" />
      </div>
      <div class="sidebar-search">
        <div for="search">
          <div class="burger">
            <div class="line-1"></div>
            <div class="line-2"></div>
            <div class="line-3"></div>
          </div>
        </div>
      </div>
      <ul class="sidebar-nav">
        <li>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <title>home</title>
            <path d="M10,20V14H14V20H19V12H22L12,3L2,12H5V20H10Z" />
          </svg>
          <h3>Dashboard</h3>
        </li>
        <li>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <title>account-group</title>
            <path
              d="M12,5.5A3.5,3.5 0 0,1 15.5,9A3.5,3.5 0 0,1 12,12.5A3.5,3.5 0 0,1 8.5,9A3.5,3.5 0 0,1 12,5.5M5,8C5.56,8 6.08,8.15 6.53,8.42C6.38,9.85 6.8,11.27 7.66,12.38C7.16,13.34 6.16,14 5,14A3,3 0 0,1 2,11A3,3 0 0,1 5,8M19,8A3,3 0 0,1 22,11A3,3 0 0,1 19,14C17.84,14 16.84,13.34 16.34,12.38C17.2,11.27 17.62,9.85 17.47,8.42C17.92,8.15 18.44,8 19,8M5.5,18.25C5.5,16.18 8.41,14.5 12,14.5C15.59,14.5 18.5,16.18 18.5,18.25V20H5.5V18.25M0,20V18.5C0,17.11 1.89,15.94 4.45,15.6C3.86,16.28 3.5,17.22 3.5,18.25V20H0M24,20H20.5V18.25C20.5,17.22 20.14,16.28 19.55,15.6C22.11,15.94 24,17.11 24,18.5V20Z"
            />
          </svg>
          <h3>All Schedule</h3>
        </li>
        <li>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <title>cog</title>
            <path
              d="M12,15.5A3.5,3.5 0 0,1 8.5,12A3.5,3.5 0 0,1 12,8.5A3.5,3.5 0 0,1 15.5,12A3.5,3.5 0 0,1 12,15.5M19.43,12.97C19.47,12.65 19.5,12.33 19.5,12C19.5,11.67 19.47,11.34 19.43,11L21.54,9.37C21.73,9.22 21.78,8.95 21.66,8.73L19.66,5.27C19.54,5.05 19.27,4.96 19.05,5.05L16.56,6.05C16.04,5.66 15.5,5.32 14.87,5.07L14.5,2.42C14.46,2.18 14.25,2 14,2H10C9.75,2 9.54,2.18 9.5,2.42L9.13,5.07C8.5,5.32 7.96,5.66 7.44,6.05L4.95,5.05C4.73,4.96 4.46,5.05 4.34,5.27L2.34,8.73C2.21,8.95 2.27,9.22 2.46,9.37L4.57,11C4.53,11.34 4.5,11.67 4.5,12C4.5,12.33 4.53,12.65 4.57,12.97L2.46,14.63C2.27,14.78 2.21,15.05 2.34,15.27L4.34,18.73C4.46,18.95 4.73,19.03 4.95,18.95L7.44,17.94C7.96,18.34 8.5,18.68 9.13,18.93L9.5,21.58C9.54,21.82 9.75,22 10,22H14C14.25,22 14.46,21.82 14.5,21.58L14.87,18.93C15.5,18.67 16.04,18.34 16.56,17.94L19.05,18.95C19.27,19.03 19.54,18.95 19.66,18.73L21.66,15.27C21.78,15.05 21.73,14.78 21.54,14.63L19.43,12.97Z"
            />
          </svg>
          <h3>Logout</h3>
        </li>
      </ul>
    </nav>
    <!-- Main Content -->
    <main class="main-content">
      <!-- Header Bar -->
      <header class="main-header">
        <div class="header-left">
          <img src="../../assets/image/default-profile.png" alt="Profile" class="profile-pic" />
          <span class="user-label">Proctor:</span>
          <span class="user-name">
            <?php echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']); ?>
          </span>
        </div>
        <div class="header-center">
          <label for="viewTypeSelect" style="margin-right:6px;">Show Schedule:</label>
          <select id="viewTypeSelect" style="margin-right:10px;">
            <option value="all">All</option>
            <option value="scheduled">Scheduled</option>
            <option value="unscheduled">Unscheduled</option>
          </select>
          <label for="viewTypeSelect">View:</label>
          <select id="viewTypeSelect">
            <option value="time">Time</option>
            <option value="assign">Assign Type</option>
          </select>
        </div>
        <div class="header-right">
          <button type="button" id="updateBtn">Update</button>
          <button type="button" id="exportExcelBtn">Export to Excel</button>
        </div>
      </header>
      <!-- Filter and Table -->
      <section class="schedule-section">
        <div class="table-responsive">
          <table id="scheduleTable" border="1" cellpadding="5" style="border-collapse:collapse;width:100%;">
            <thead>
              <tr id="filterRow">
                <th><select id="courseCodeFilter"><option value="all">All</option></select></th>
                <th><select id="departmentFilter"><option value="all">All</option></select></th>
                <th><select id="yearFilter"><option value="all">All</option></select></th>
                <th><select id="sectionFilter"><option value="all">All</option></select></th>
                <th><!-- Subject: no filter --></th>
                <th><select id="dayFilter"><option value="all">All</option></select></th>
                <th><!-- Time: no filter --></th>
                <th><select id="roomFilter"><option value="all">All</option></select></th>
                <th><select id="proctorFilter"><option value="all">All</option></select></th>
                <th id="reasonFilterHeader"></th>
              </tr>
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
                <th id="reasonHeader">Reason</th>
              </tr>
            </thead>
            <tbody id="scheduleTableBody">
              <tr><td colspan="10">Loading schedule...</td></tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="10" id="scheduleSummary">MY TOTAL TIME: 00 hrs and 00 mins</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
      <!-- Reports Section and Forecasting (as previously added) -->
      <div id="proctor-summary" style="margin-top:40px;"></div>
      <section id="reports-section" style="margin-top: 2rem;">
        <h2>Reporting &amp; Monitoring</h2>
        <div class="report-controls">
          <label for="reportType">Generate Report By:</label>
          <select id="reportType">
            <option value="room">Room</option>
            <option value="section">Section</option>
            <option value="professor">Professor</option>
            <option value="floor">Floor</option>
          </select>
          <button id="generateReportBtn">Generate Report</button>
          <button id="exportReportBtn">Export to Excel</button>
        </div>
        <div id="report-output" style="margin-top: 1rem;"></div>
      </section>
      <section id="forecast-section" style="margin-top: 2rem;">
        <h2>Resource Forecasting</h2>
        <div class="forecast-controls">
          <label for="pagesPerStudent">Bond Paper Pages per Student:</label>
          <input type="number" id="pagesPerStudent" value="1" min="1" style="width: 60px;">
          <label for="scantronsPerStudent" style="margin-left: 1rem;">Scantrons per Student:</label>
          <input type="number" id="scantronsPerStudent" value="1" min="0" style="width: 60px;">
          <button id="forecastBtn">Forecast</button>
        </div>
        <div id="forecast-output" style="margin-top: 1rem;"></div>
      </section>
    </main>
  </div>
  <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
  <script src="../js/index.js" type="module"></script>
</body>
</html>
