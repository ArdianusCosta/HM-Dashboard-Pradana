<?php include 'header.php' ?>
  
  <aside class="main-sidebar sidebar-light-dark elevation-3 main-sidebar sidebar-light-dark elevation-3 d-flex flex-column">
    <div class="ml-3 mt-5">
   	<a href="./" class="">
        <h3 class="text-center p-0 m-0">
          <img src="assets/Logo.png" id="logoFull" class="img-fluid d-block" style="width: 80%;" alt="Full Logo">
        </h3>  
        <h3 class="text-center p-0 m-0">
          <img src="assets/Logo1.png" id="logoMini" class="img-fluid d-none" style="width: 40px;" alt="Mini Logo">
        </h3>
    </a>
    <small ><p class="text-dark text-center pr-3" style="text-decoration: none">PT Pradana Nusa Energi</p> </small>
    </div>

    <div class="sidebar pb-2 mb-4">
      <nav class=" mb-auto ml-auto">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item dropdown mb-2">
            <a href="./" class="nav-link nav-home ">
              <i class="nav-icon fas fa-th-large"></i>
              <p>
                Overview
              </p>
            </a>  
          </li> 
          <li class="nav-item mb-2">
            <a href="./index.php?page=project_list" class="nav-link nav-project_list">
              <i class="nav-icon fas fa-layer-group"></i>
              <p>
                Projects
              </p>
            </a>
          </li> 
          <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] < 4): ?>
          <li class="nav-item mb-2">
            <a href="./index.php?page=mytask&expand=true" class="nav-link nav-mytask">
                  <i class="fas fa-clone nav-icon"></i>
                  <p>My Task</p>
                </a>
          </li>
          <?php endif; ?>
          <li class="nav-item mb-2">
            <?php
              // Halaman yang termasuk ke menu Users
              $userPages = ['view_task'];
              $currentPage = $_GET['page'] ?? '';
              $isAktif = in_array($currentPage, $userPages) ? 'active' : '';
            ?>
                <a href="./index.php?page=task_list" class="nav-link <?= $isAktif ?> nav-task_list">
                  <i class="fas fa-tasks nav-icon"></i>
                  <p>Task</p>
                </a>
          </li> 

          <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] < 4): ?>
          <li class="nav-item">
            <a href="index.php?page=chat" class="nav-link nav-chat">
              <i class="nav-icon fas fa-comments"></i>
              <p>Messenger</p>
            </a>
          </li>
          <?php endif; ?>

          <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] < 4): ?>
          <li class="nav-item dropdown mb-2">
            <a href="./index.php?page=kanban" class="nav-link nav-kanban">
              <i class="nav-icon fas fa-clipboard"></i>
              <p>Kanban</p>
            </a>
          </li>
          <?php endif; ?>  

          <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] < 3): ?>
           <li class="nav-item mb-2">
                <a href="./index.php?page=reports" class="nav-link nav-reports">
                  <i class="fas fa-th-list nav-icon"></i>
                  <p>Report</p>
                </a>
          </li> 
          <?php endif; ?> 

        <?php
          $currentPage = $_GET['page'] ?? '';
          $isActiveTaskCalendar = ($currentPage == 'task_calendar') ? 'active' : '';
          
        ?>
          <?php if(isset($_SESSION['login_type']) ): // allow all logged-in users, including clients ?>
          <li class="nav-item mb-2">
            <a href="./index.php?page=task_calendar" class="nav-link nav-Task_Calendar <?= $isActiveTaskCalendar ?>">
              <i class="nav-icon fas fa-calendar-check"></i>
              <p>
                Calendar
              </p>
            </a>
          </li>
          <?php endif; ?>
        
         <?php
          if ($_SESSION['login_type'] == 1):
            // Halaman yang termasuk ke menu Users
            $userPages = ['user_list', 'manage_user', 'new_user'];
            $currentPage = $_GET['page'] ?? '';
            $isActive = in_array($currentPage, $userPages) ? 'active' : '';
          ?>
            <li class="nav-item mb-2">
              <a href="./index.php?page=user_list" class="nav-link <?= $isActive ?> nav-user_list">
                <i class="nav-icon fas fa-users"></i>
                <p>Users</p>
              </a>
            </li>
          <?php endif; ?>

        </ul>
      </nav>
    </div>
  
    
     <div class="mt-auto p-2">
        <hr>
          <div class="nav-item dropdown mb-2 nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
            <div class="nav-item ">
                <a href="#" id="openGuideModal" class="nav-link tree-item">
                  <i class="fa fa-question-circle nav-icon"></i>
                  <p>Guide / FAQ</p>
                </a>
            </div>
          </div>
          <div class="nav-item dropdown mb-2 nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
            <div class="nav-item ">
                <a href="ajax.php?action=logout" class="nav-link tree-item ">
                  <i class="fa fa-power-off nav-icon"></i>
                  <p>Logout</p>
                </a>
            </div>
          </div>
      </div>
  </aside>

  <style>
    #guideModal .modal-content {
      background: #fff6ee;
      border: 1px solid #d28a5a;
      box-shadow: 0 0 30px rgba(183,83,1,0.15);
    }
    #guideModal .modal-header {
      background: linear-gradient(135deg, #B75301 0%, #d98b5f 100%);
      color: #fff;
      border-bottom: 1px solid rgba(255,255,255,0.35);
    }
    #guideModal .modal-header .btn-group .btn {
      color: #fff;
      border-color: rgba(255,255,255,0.55);
    }
    #guideModal .modal-header .btn-group .btn.active {
      color: #B75301;
      background: #fff;
    }
    #guideModal .modal-header .close {
      color: #fff;
      opacity: 1;
    }
    #guideModal .modal-header .modal-title,
    #guideModal .modal-header small {
      color: #fff !important;
    }
    #guideModal .modal-body {
      color: #000;
    }
    #guideModal .modal-body h5,
    #guideModal .modal-body h6,
    #guideModal .modal-body p,
    #guideModal .modal-body small {
      color: #000;
    }
    #guideModal .guide-section-card .font-weight-bold,
    #guideModal .guide-section-card small {
      color: #000;
    }
    #guideModal .modal-footer {
      background: #fff2e6;
      border-top: 1px solid #d28a5a;
    }
    #guideModal .btn-outline-primary {
      color: #fff;
      background-color: #B75301;
      border-color: #B75301;
    }
    #guideModal .btn-outline-primary:hover,
    #guideModal .btn-outline-primary:focus {
      background-color: #d36b1f;
      border-color: #d36b1f;
      color: #fff;
    }
    #guideModal .guide-section-card {
      border-color: #d28a5a;
      background: #fff8f0;
    }
    #guideModal .guide-section-card:hover {
      background: #fff2e2;
    }
    #guideModal .badge-primary {
      background: #B75301;
    }
    #guideModalBreadcrumb {
      background: rgba(255,255,255,0.6);
      color: #42280f;
    }
  </style>
  <div class="modal fade" id="guideModal" tabindex="-1" role="dialog" aria-labelledby="guideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <div class="modal-header d-flex align-items-center">
          <div>
            <h5 class="modal-title" id="guideModalLabel">Guide / FAQ</h5>
            <small class="text-muted" id="guideModalSubtitle">Quick overview for using the dashboard.</small>
          </div>
          <div class="btn-group btn-group-sm ml-3" role="group" aria-label="Language switch">
            <button type="button" class="btn btn-outline-secondary active" data-lang="id">ID</button>
            <button type="button" class="btn btn-outline-secondary" data-lang="en">EN</button>
          </div>
          <button type="button" class="close ml-auto" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="px-4 pt-2 pb-3 border-bottom" id="guideModalBreadcrumb" style="display:none"></div>
        <div class="modal-body" id="guideModalBody"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="guideBackButton" style="display:none">Sections</button>
          <button type="button" class="btn btn-outline-secondary" id="guidePrevButton" style="display:none">Previous</button>
          <button type="button" class="btn btn-outline-primary" id="guideNextButton" style="display:none">Next</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
  	$(document).ready(function(){
      var page = '<?php echo isset($_GET['page']) ? $_GET['page'] : 'home' ?>';
  		var s = '<?php echo isset($_GET['s']) ? $_GET['s'] : '' ?>';
      if(s!='')
        page = page+'_'+s;
      
      // === LOGIKA BARU UNTUK PROJECT AKTIF ===
      // Halaman yang dianggap sebagai bagian dari menu Projects
      var projectPages = ['project_list', 'view_project', 'new_project', 'edit_project'];
      
      if(projectPages.includes(page)){
          // Set Project link (nav-project_list) menjadi aktif
          $('.nav-link.nav-project_list').addClass('active');
          
          // Logika untuk membuka menu tree (jika Projects suatu hari punya submenu)
          if($('.nav-link.nav-project_list').hasClass('tree-item') == true){
            $('.nav-link.nav-project_list').closest('.nav-treeview').siblings('a').addClass('active')
            $('.nav-link.nav-project_list').closest('.nav-treeview').parent().addClass('menu-open')
          }
      } else if($('.nav-link.nav-'+page).length > 0){
          // Logika default untuk halaman lain
          $('.nav-link.nav-'+page).addClass('active')
          if($('.nav-link.nav-'+page).hasClass('tree-item') == true){
            $('.nav-link.nav-'+page).closest('.nav-treeview').siblings('a').addClass('active')
            $('.nav-link.nav-'+page).closest('.nav-treeview').parent().addClass('menu-open')
          }
        if($('.nav-link.nav-'+page).hasClass('nav-is-tree') == true){
          $('.nav-link.nav-'+page).parent().addClass('menu-open')
        }
      }
      // === AKHIR LOGIKA BARU ===
  	})

    const toggleSidebar = document.getElementById('toggleSidebar');
    if (toggleSidebar) {
        toggleSidebar.addEventListener('click', function () {
            const sidebar = document.getElementById('sidebar');
            const logoFull = document.getElementById('logoFull');
            const logoMini = document.getElementById('logoMini');

            sidebar.classList.toggle('collapsed');

            if (sidebar.classList.contains('collapsed')) {
                logoFull.classList.add('d-none');
                logoMini.classList.remove('d-none');
            } else {
                logoFull.classList.remove('d-none');
                logoMini.classList.add('d-none');
            }
        });
    }

    const guideRole = <?php echo json_encode($_SESSION['login_type'] ?? 0); ?>;
    const guideState = {
      view: 'sections',
      currentSectionKey: null,
      currentPageIndex: 0,
      lang: 'id'
    };
    // For each page inside a section, set an image path like: image: 'assets/images/guide/overview-dashboard.png'
    const guideConfig = {
      id: {
        title: 'Panduan / FAQ',
        subtitle: 'Ringkasan cara menggunakan halaman pada dashboard.',
        sections: {
          overview: {
            title: 'Overview',
            summary: 'Halaman utama untuk melihat ringkasan proyek, tugas, dan notifikasi.',
            pages: [
              {title: 'Dashboard', body: 'Menampilkan ringkasan proyek, status tugas, dan notifikasi terbaru. Cocok untuk memantau aktivitas tim secara cepat.', image: ''}
            ]
          },
          projects: {
            title: 'Projects',
            summary: 'Daftar proyek yang Anda kelola atau ikuti.',
            pages: [
              {title: 'Project List', body: 'Tampilan daftar proyek dengan status dan anggota. Klik proyek untuk melihat detail, timeline, dan tim terkait.', image: ''},
              {title: 'Project Detail', body: 'Halaman detail proyek menunjukkan deskripsi, progress, dan anggota tim. Hanya admin/manager dapat melihat pengaturan lengkap proyek.', image: ''}
            ]
          },
          mytask: {
            title: 'My Task',
            summary: 'Tugas yang ditugaskan langsung ke Anda.',
            pages: [
              {title: 'Task Overview', body: 'Menampilkan semua tugas pribadi dengan status, tanggal jatuh tempo, dan filter tugas.', image: ''}
            ]
          },
          task: {
            title: 'Task',
            summary: 'Daftar tugas lengkap berdasarkan hak akses.',
            pages: [
              {title: 'Task List', body: 'Lihat semua tugas yang relevan dengan peran Anda, termasuk status dan tanggal penting.', image: ''},
              {title: 'Task Detail', body: 'Halaman detail tugas menampilkan informasi lengkap, komentar, dan lampiran tugas.', image: ''}
            ]
          },
          messenger: {
            title: 'Messenger',
            summary: 'Chat internal untuk berkomunikasi dengan tim.',
            pages: [
              {title: 'Chat', body: 'Gunakan Messenger untuk mengirim pesan dan berdiskusi dengan tim Anda secara langsung.', image: ''}
            ]
          },
          kanban: {
            title: 'Kanban',
            summary: 'Tampilan papan tugas untuk memindahkan status tugas.',
            pages: [
              {title: 'Kanban Board', body: 'Kelola tugas dengan drag-and-drop dari satu kolom status ke kolom berikutnya.', image: ''}
            ]
          },
          report: {
            title: 'Report',
            summary: 'Laporan proyek dan tugas untuk admin dan manager.',
            pages: [
              {title: 'Project Reports', body: 'Analisis progress proyek, beban tugas, dan ringkasan performa tim.', image: ''}
            ]
          },
          calendar: {
            title: 'Calendar',
            summary: 'Kalender event dan batas waktu tugas.',
            pages: [
              {title: 'Event Calendar', body: 'Lihat tanggal tugas penting dan event proyek dalam tampilan kalender.', image: ''}
            ]
          },
          users: {
            title: 'Users',
            summary: 'Halaman manajemen pengguna untuk admin.',
            pages: [
              {title: 'User Management', body: 'Tambah, ubah, atau lihat daftar pengguna sistem, termasuk admin, manager, dan staf.', image: ''}
            ]
          },
          clientFAQ: {
            title: 'FAQ',
            summary: 'Bantuan akses khusus untuk klien.',
            pages: [
              {title: 'Client Access', body: 'Klien hanya dapat melihat proyek dan tugas mereka sendiri. Klien tidak dapat mengakses pengelolaan pengguna atau laporan internal.', image: ''}
            ]
          }
        }
      },
      en: {
        title: 'Guide / FAQ',
        subtitle: 'Quick overview for using the dashboard pages.',
        sections: {
          overview: {
            title: 'Overview',
            summary: 'The main page for seeing project summaries, task status, and notifications.',
            pages: [
              {title: 'Dashboard', body: 'Displays the project summary, task status, and latest notifications. Good for quickly monitoring team activity.', image: ''}
            ]
          },
          projects: {
            title: 'Projects',
            summary: 'Your managed or assigned projects list.',
            pages: [
              {title: 'Project List', body: 'Shows projects with status and members. Click a project to view details, timeline, and team.', image: ''},
              {title: 'Project Detail', body: 'Project detail shows description, progress, and team members. Admins/managers see full project settings.', image: ''}
            ]
          },
          mytask: {
            title: 'My Task',
            summary: 'Tasks assigned directly to you.',
            pages: [
              {title: 'Task Overview', body: 'Displays all personal tasks with status, due dates, and filters.', image: ''}
            ]
          },
          task: {
            title: 'Task',
            summary: 'A complete task list based on your permissions.',
            pages: [
              {title: 'Task List', body: 'View all tasks relevant to your role, including status and deadlines.', image: ''},
              {title: 'Task Detail', body: 'Task detail page shows full information, comments, and attachments.', image: ''}
            ]
          },
          messenger: {
            title: 'Messenger',
            summary: 'Internal chat to communicate with your team.',
            pages: [
              {title: 'Chat', body: 'Use Messenger to send messages and discuss directly with your team.', image: ''}
            ]
          },
          kanban: {
            title: 'Kanban',
            summary: 'Board view for moving tasks through stages.',
            pages: [
              {title: 'Kanban Board', body: 'Manage tasks by dragging them between status columns.', image: ''}
            ]
          },
          report: {
            title: 'Report',
            summary: 'Project and task reports for admins and managers.',
            pages: [
              {title: 'Project Reports', body: 'Analyze project progress, workload, and team performance summaries.', image: ''}
            ]
          },
          calendar: {
            title: 'Calendar',
            summary: 'Event calendar and task deadlines.',
            pages: [
              {title: 'Event Calendar', body: 'See important due dates and project events in a calendar layout.', image: ''}
            ]
          },
          users: {
            title: 'Users',
            summary: 'User management page for admins.',
            pages: [
              {title: 'User Management', body: 'Add, edit, or review users including admins, managers, and staff.', image: ''}
            ]
          },
          clientFAQ: {
            title: 'FAQ',
            summary: 'Client-specific access guidance.',
            pages: [
              {title: 'Client Access', body: 'Clients only see their projects and tasks. Clients do not access user management or internal reports.', image: ''}
            ]
          }
        }
      }
    };

    function getSectionType() {
      if (guideRole === 4) return 'client';
      if (guideRole === 3) return 'staff';
      return 'adminManager';
    }

    function getSectionKeys() {
      const sectionType = getSectionType();
      if (sectionType === 'adminManager') {
        return ['overview', 'projects', 'mytask', 'task', 'messenger', 'kanban', 'report', 'calendar', 'users'];
      }
      if (sectionType === 'staff') {
        return ['overview', 'mytask', 'task', 'messenger', 'kanban', 'calendar'];
      }
      return ['overview', 'projects', 'task', 'calendar', 'clientFAQ'];
    }

    function getSectionData(sectionKey) {
      return guideConfig[guideState.lang].sections[sectionKey] || guideConfig.en.sections[sectionKey] || null;
    }

    function renderSectionList() {
      const sectionKeys = getSectionKeys();
      const html = sectionKeys.map(key => {
        const section = getSectionData(key);
        if (!section) return '';
        return '<button type="button" class="btn btn-outline-secondary btn-block text-left mb-2 guide-section-card" data-section="' + key + '">' +
          '<div class="d-flex justify-content-between align-items-center">' +
            '<div>' +
              '<div class="font-weight-bold">' + section.title + '</div>' +
              '<small class="text-muted">' + section.summary + '</small>' +
            '</div>' +
            '<span class="badge badge-primary badge-pill">' + section.pages.length + '</span>' +
          '</div>' +
        '</button>';
      }).join('');
      $('#guideModalBreadcrumb').hide();
      $('#guideModalBody').html(html);
      $('#guideBackButton, #guidePrevButton, #guideNextButton').hide();
    }

    function renderPageView() {
      const section = getSectionData(guideState.currentSectionKey);
      if (!section) {
        guideState.view = 'sections';
        return renderGuide();
      }
      const page = section.pages[guideState.currentPageIndex] || section.pages[0];
      let html = '';
      html += '<div class="mb-3">';
      html += '<h5 class="font-weight-bold">' + section.title + '</h5>';
      html += '<p class="text-muted mb-3">' + section.summary + '</p>';
      html += '</div>';
      html += '<div class="mb-4">';
      html += '<h6 class="font-weight-bold">' + page.title + '</h6>';
      html += '<p class="text-muted">' + page.body + '</p>';
      if (page.image) {
        html += '<div class="mt-3 text-center"><img src="' + page.image + '" class="img-fluid rounded" alt="' + page.title + '"></div>';
      }
      html += '</div>';
      if (section.pages.length > 1) {
        html += '<div class="mb-3">';
        html += '<small class="text-muted">Page ' + (guideState.currentPageIndex + 1) + ' of ' + section.pages.length + '</small>';
        html += '</div>';
      }
      $('#guideModalBreadcrumb').show().html('<small class="text-muted">' + section.title + ' &gt; ' + page.title + '</small>');
      $('#guideModalBody').html(html);
      $('#guideBackButton').show();
      if (section.pages.length > 1) {
        $('#guidePrevButton').toggle(guideState.currentPageIndex > 0);
        $('#guideNextButton').toggle(guideState.currentPageIndex < section.pages.length - 1);
      } else {
        $('#guidePrevButton, #guideNextButton').hide();
      }
    }

    function renderGuide() {
      const modalTitle = guideConfig[guideState.lang].title || guideConfig.en.title;
      const modalSubtitle = guideConfig[guideState.lang].subtitle || guideConfig.en.subtitle;
      $('#guideModalLabel').text(modalTitle);
      $('#guideModalSubtitle').text(modalSubtitle);
      if (guideState.view === 'page') {
        renderPageView();
      } else {
        renderSectionList();
      }
    }

    $('#openGuideModal').on('click', function(e) {
      e.preventDefault();
      guideState.view = 'sections';
      guideState.currentSectionKey = null;
      guideState.currentPageIndex = 0;
      guideState.lang = $('#guideModal .btn-group button.active').data('lang') || 'id';
      renderGuide();
      $('#guideModal').modal('show');
    });

    $(document).on('click', '#guideModal .btn-group button', function() {
      const lang = $(this).data('lang');
      $('#guideModal .btn-group button').removeClass('active');
      $(this).addClass('active');
      guideState.lang = lang;
      renderGuide();
    });

    $(document).on('click', '.guide-section-card', function() {
      const sectionKey = $(this).data('section');
      guideState.currentSectionKey = sectionKey;
      guideState.currentPageIndex = 0;
      guideState.view = 'page';
      renderGuide();
    });

    $('#guideBackButton').on('click', function() {
      guideState.view = 'sections';
      guideState.currentSectionKey = null;
      renderGuide();
    });

    $('#guidePrevButton').on('click', function() {
      if (guideState.currentPageIndex > 0) {
        guideState.currentPageIndex -= 1;
        renderGuide();
      }
    });

    $('#guideNextButton').on('click', function() {
      const section = getSectionData(guideState.currentSectionKey);
      if (section && guideState.currentPageIndex < section.pages.length - 1) {
        guideState.currentPageIndex += 1;
        renderGuide();
      }
    });

    $('#guideModal').on('shown.bs.modal', function() {
      guideState.view = 'sections';
      guideState.currentSectionKey = null;
      guideState.currentPageIndex = 0;
      guideState.lang = $('#guideModal .btn-group button.active').data('lang') || 'id';
      renderGuide();
    });
  </script>