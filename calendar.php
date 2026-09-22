<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Calendar</title>

  <!-- FullCalendar + Bootstrap -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css" />
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" />

  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">

  <!-- jQuery + Moment + FullCalendar -->
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js"></script>

  <!-- Google API -->
  <script src="https://apis.google.com/js/api.js"></script>

  <style>
    html, body {
      height: 100%;
      margin: 0;
      background-color: #f8f9fa;
      font-family: 'Segoe UI', sans-serif;
    }

    .container-fluid {
      padding: 5px;
    }

    #calendar {
      background: #fff;
      border-radius: 8px;
      padding: 10px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .fc-day-header {
      background-color: #B75301;
      padding: 10px 0;
      font-weight: bold;
      color: white;
    }

    .fc-event.bg-primary,
    .fc-event.bg-success,
    .fc-event.bg-warning,
    .fc-event.bg-danger {
      color: #fff !important;
      height: 30px;
    }

    .fc-event.bg-success {
      cursor: default;
    }

    .swal2-actions {
      justify-content: space-between !important;
    }
  </style>
</head>

<body>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Calendar</h3>
    <button id="addEventBtn" class="btn text-white" style="background-color:#B75301;">
      + Add Event
    </button>
  </div>
  <div id="calendar"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
/* ================= GOOGLE CALENDAR CONFIG ================= */

const CURRENT_LOGIN_TYPE = <?php echo json_encode((int)($_SESSION['login_type'] ?? 0)); ?>;
const GOOGLE_API_KEY = 'AIzaSyC8wEmVkA72xJgHZ-MZiZNKnHyVJSwiqFQ';
const INDONESIA_HOLIDAY_CALENDAR =
  'id.indonesian#holiday@group.v.calendar.google.com';

function initGoogleApi(callback) {
  gapi.load('client', () => {
    gapi.client.init({
      apiKey: GOOGLE_API_KEY,
      discoveryDocs: [
        'https://www.googleapis.com/discovery/v1/apis/calendar/v3/rest'
      ]
    }).then(callback);
  });
}

function fetchIndonesiaHolidays(start, end, callback) {
  gapi.client.calendar.events.list({
    calendarId: INDONESIA_HOLIDAY_CALENDAR,
    timeMin: start.toISOString(),
    timeMax: end.toISOString(),
    singleEvents: true,
    orderBy: 'startTime'
  }).then(res => {
    const events = (res.result.items || []).map(e => ({
      id: 'holiday-' + e.id,
      title: e.summary,
      start: e.start.date || e.start.dateTime,
      end: e.end.date || e.end.dateTime,
      allDay: true,
      editable: false,
      className: 'bg-success',
      description: 'Indonesian Public Holiday'
    }));
    callback(events);
  });
}

/* ================= FULLCALENDAR ================= */

$(document).ready(function () {

  let currentEventId = null;

  $('#calendar').fullCalendar({

    editable: true,
    selectable: true,
    eventResizableFromStart: true,
    eventDurationEditable: true,

    header: {
      left: 'prev,next today',
      center: 'title',
      right: 'month,agendaWeek,agendaDay'
    },

    events: function (start, end, timezone, callback) {

      $.getJSON('load.php', {
        start: start.format(),
        end: end.format()
      }).done(function (dbEvents) {

        if (CURRENT_LOGIN_TYPE === 4) {
          callback(dbEvents || []);
          return;
        }

        initGoogleApi(() => {
          fetchIndonesiaHolidays(start.toDate(), end.toDate(), function (holidayEvents) {
            callback([
              ...dbEvents,
              ...holidayEvents
            ]);
          });
        });

      }).fail(function () {
        callback([]);
      });
    },

    select: function () {
      showEventModal();
    },

    eventClick: function (event) {
      if (event.id && event.id.startsWith('holiday-')) {
        Swal.fire('Public Holiday', event.title, 'info');
        return;
      }
      currentEventId = event.id;
      showEventModal(event);
    },

    eventAllow: function (dropInfo, draggedEvent) {
      return !(draggedEvent.id && draggedEvent.id.startsWith('holiday-'));
    },

    eventDrop: function (event) {
      updateEvent(event);
    },

    eventResize: function (event) {
      updateEvent(event);
    },

    eventRender: function (event, element) {
      if (event.className) {
        element.addClass(event.className);
      }
    }

  });

  $('#addEventBtn').click(() => {
    currentEventId = null;
    showEventModal();
  });

  /* ================= MODAL ================= */

  function showEventModal(event = null) {

    const isEdit = !!event;

    Swal.fire({
      title: isEdit ? 'Edit Event' : 'Add Event',
      html: `
        <small>Title</small>
        <input id="eventTitle" class="swal2-input" value="${isEdit ? event.title : ''}">

        <small>Start</small>
        <input id="eventStart" type="datetime-local" class="swal2-input"
          value="${isEdit ? moment(event.start).format('YYYY-MM-DDTHH:mm') : ''}">

        <small>End</small>
        <input id="eventEnd" type="datetime-local" class="swal2-input"
          value="${isEdit && event.end ? moment(event.end).format('YYYY-MM-DDTHH:mm') : ''}">

        <small>Description</small>
        <textarea id="eventDescription" class="swal2-textarea">${isEdit ? (event.description || '') : ''}</textarea>

        <small>Color</small>
        <input id="eventColor" class="swal2-input" list="colors"
          value="${isEdit ? event.className[0] : 'bg-primary'}">

        <datalist id="colors">
          <option value="bg-primary">
          <option value="bg-success">
          <option value="bg-warning">
          <option value="bg-danger">
        </datalist>
      `,
      showCancelButton: true,
      showDenyButton: isEdit,
      confirmButtonText: isEdit ? 'Save' : 'Add',
      denyButtonText: 'Delete',
      reverseButtons: true,

      preConfirm: () => {
        const title = $('#eventTitle').val();
        const start = $('#eventStart').val();
        const end = $('#eventEnd').val();
        const color = $('#eventColor').val();
        const description = $('#eventDescription').val();

        if (!title || !start || !end) {
          Swal.showValidationMessage('All fields required');
          return false;
        }
        return { title, start, end, color, description };
      }
    }).then(result => {

      if (result.isConfirmed) {

        const data = {
          ...result.value
        };

        let url = 'insert.php';
        if (isEdit) {
          url = 'update.php';
          data.id = event.id;
        }

        $.post(url, data, () => location.reload());

      } else if (result.isDenied && isEdit) {
        confirmDelete(event.id);
      }

    });
  }

  function confirmDelete(id) {
    Swal.fire({
      title: 'Delete event?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545'
    }).then(result => {
      if (result.isConfirmed) {
        $.post('delete.php', { id }, () => location.reload());
      }
    });
  }

  function updateEvent(event) {
    $.post('update.php', {
      id: event.id,
      title: event.title,
      color: event.className[0],
      start: moment(event.start).format('YYYY-MM-DD HH:mm:ss'),
      end: moment(event.end).format('YYYY-MM-DD HH:mm:ss')
    });
  }

});
</script>

</body>
</html>
