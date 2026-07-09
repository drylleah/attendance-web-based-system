<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RFID Attendance Scanner</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
</head>
<body>

  <!-- ===== TOP BAR ===== -->
  <header class="topbar">
    <div class="topbar-left">
      <span class="topbar-title">Lorma Colleges Attendance System</span>
    </div>
    <div class="topbar-right">
      <button class="topbar-manual-btn" id="openManualLog" title="Manual Log">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
        Manual Log
      </button>
      <button class="topbar-manage-btn" id="openCardManager" title="Manage ID's">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
        Manage ID's
      </button>
      <div class="topbar-clock">
        <div class="clock-time" id="clockTime">--:-- --</div>
        <div class="clock-date" id="clockDate">--- --, ----</div>
      </div>
    </div>
  </header>

  <!-- ===== MAIN STAGE ===== -->
  <main class="stage">

    <!-- IDLE STATE -->
    <div class="panel" id="panelIdle">
      <div class="idle-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3" />
        </svg>
      </div>
      <h1 class="idle-title">Tap Your Lorma ID</h1>
      <p class="idle-sub">Place your id on the reader to record attendance</p>

      <div class="sim-input-row">
        <input type="text" id="simCardInput" class="sim-input" placeholder="Enter School ID" maxlength="50" autocomplete="off" spellcheck="false">
        <button class="sim-btn" id="simScanBtn">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" /></svg>
          Scan
        </button>
      </div>
    </div>

    <!-- SUCCESS STATE -->
    <div class="panel panel--hidden" id="panelSuccess">
      <div class="result-badge result-badge--in" id="resultBadge">
        <svg id="badgeIconIn" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <svg id="badgeIconOut" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" style="display:none;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
        </svg>
      </div>
      <div class="result-action" id="resultAction">TIME IN</div>
      <div class="result-avatar" id="resultAvatar">A</div>
      <div class="result-name"  id="resultName">—</div>
      <div class="result-id"    id="resultId">ID: —</div>
      <div class="result-time-block">
        <div class="result-time" id="resultTime">--:-- --</div>
        <div class="result-date" id="resultDate">--- --, ----</div>
      </div>
      <div class="result-progress-wrap">
        <div class="result-progress-bar" id="resultProgressBar"></div>
      </div>
      <p class="result-return-hint">Returning to standby…</p>
    </div>

    <!-- ERROR STATE -->
    <div class="panel panel--hidden" id="panelError">
      <div class="error-icon">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
      </div>
      <h2 class="error-title" id="errorTitle">Card Not Recognised</h2>
      <p  class="error-msg"   id="errorMsg">This card is not registered in the system.</p>
      <div class="result-progress-wrap">
        <div class="result-progress-bar result-progress-bar--error" id="errorProgressBar"></div>
      </div>
      <p class="result-return-hint">Returning to standby…</p>
    </div>

  </main>

  <!-- ===== FOOTER ===== -->
  <footer class="footer">
    <span>Lorma Colleges &mdash; Attendance System</span>
    <span id="footerCardId"></span>
  </footer>

  <!-- ===== MANUAL LOG MODAL ===== -->
  <div class="ml-overlay" id="manualLogOverlay">
    <div class="ml-modal">

      <div class="ml-header">
        <div class="ml-header-left">
          <span>Manual Attendance Log</span>
        </div>
        <button class="ml-close" id="closeManualLog" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
        </button>
      </div>

<div class="ml-body">

        <!-- Log Type Toggle -->
        <div class="ml-toggle-row">
          <button class="ml-toggle ml-toggle--active" id="mlToggleIn"  data-type="time_in">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            Time In
          </button>
          <button class="ml-toggle" id="mlToggleOut" data-type="time_out">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" /></svg>
            Time Out
          </button>
        </div>

        <!-- Name Row -->
        <div class="ml-form-row ml-form-row--three">
          <div class="ml-field">
            <label>Last Name <span class="ml-required">*</span></label>
            <input type="text" id="ml_last_name" maxlength="100" placeholder="e.g. Dela Cruz">
          </div>
          <div class="ml-field">
            <label>First Name <span class="ml-required">*</span></label>
            <input type="text" id="ml_first_name" maxlength="100" placeholder="e.g. Juan">
          </div>
          <div class="ml-field ml-field--mi">
            <label>M.I.</label>
            <input type="text" id="ml_mi" maxlength="5" placeholder="A">
          </div>
        </div>

        <!-- ID Row -->
        <div class="ml-form-row">
          <div class="ml-field">
            <label>School ID Number <span class="ml-required">*</span></label>
            <input type="text" id="ml_id_number" maxlength="50" autocomplete="off" placeholder="e.g. 2024-00123">
          </div>
        </div>

        <div class="ml-form-error" id="mlError"></div>

        <button class="ml-submit-btn" id="mlSubmitBtn">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
          Save Time In
        </button>

      </div><!-- /.ml-body -->

      <div class="ml-toast" id="mlToast"></div>
    </div>
  </div>

  <!-- ===== CARD MANAGER MODAL ===== -->
  <div class="cm-overlay" id="cardManagerOverlay">
    <div class="cm-modal">

      <div class="cm-header">
        <div class="cm-header-left">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
          <span>Manage ID's</span>
        </div>
        <button class="cm-close" id="closeCardManager" aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
        </button>
      </div>

      <div class="cm-tabs">
        <button class="cm-tab cm-tab--active" data-tab="register">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
          Register Id
        </button>
        <button class="cm-tab" data-tab="list">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
          Registered Id
          <span class="cm-count-badge" id="cmCountBadge">0</span>
        </button>
      </div>

      <!-- Tab: Register -->
      <div class="cm-pane" id="tabRegister">
        <div class="cm-form">
          <div class="cm-form-row cm-form-row--three">
            <div class="cm-field">
              <label>Last Name <span class="cm-required">*</span></label>
              <input type="text" id="reg_last_name" maxlength="100">
            </div>
            <div class="cm-field">
              <label>First Name <span class="cm-required">*</span></label>
              <input type="text" id="reg_first_name" maxlength="100">
            </div>
            <div class="cm-field cm-field--mi">
              <label>M.I.</label>
              <input type="text" id="reg_mi" maxlength="5">
            </div>
          </div>
          <div class="cm-form-row">
            <div class="cm-field">
              <label>School ID Number <span class="cm-required">*</span></label>
              <input type="text" id="reg_id_number" maxlength="50" autocomplete="off">
              <span class="cm-field-hint"></span>
            </div>
          </div>
          <div class="cm-form-error" id="regError"></div>
          <button class="cm-btn-register" id="regSubmitBtn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Register Student
          </button>
        </div>
      </div>

      <!-- Tab: Card List -->
      <div class="cm-pane cm-pane--hidden" id="tabList">
        <div class="cm-list-toolbar">
          <input type="text" id="cmSearch" class="cm-search" placeholder="Search name or ID…">
        </div>
        <div class="cm-table-wrap" id="cmTableWrap">
          <table class="cm-table">
            <thead>
              <tr>
                <th>School ID</th>
                <th>Name</th>
                <th>Status</th>
                <th>Registered</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="cmTableBody"></tbody>
          </table>
          <div class="cm-empty" id="cmEmpty" style="display:none;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
            <p>No cards registered yet.</p>
          </div>
        </div>
      </div>

      <div class="cm-toast" id="cmToast"></div>
    </div>
  </div>

  <script src="{{ asset('js/attendance.js') }}"></script>
</body>
</html>
