<?php 
include 'db_connect.php'; 
// Pastikan sesi dimulai dan variabel sesi ada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Untuk menghindari Notice Undefined Index jika sesi belum diset
$login_id = isset($_SESSION['login_id']) ? $_SESSION['login_id'] : '';
$login_avatar = isset($_SESSION['login_avatar']) && !empty($_SESSION['login_avatar']) ? $_SESSION['login_avatar'] : 'empty-placeholder.png';
$login_firstname = isset($_SESSION['login_firstname']) ? $_SESSION['login_firstname'] : 'Guest';

$type_arr = array('', "Admin", "Project Manager", "Employee"); 

// ðŸ’¡ KRITIS: ENKRIPSI ID LOGIN DI SINI
$encoded_login_id = $login_id ? encode_id($login_id) : '';
?>

<nav class="main-header navbar navbar-expand navbar-light">
  <ul class="navbar-nav">
    <?php if (isset($_SESSION['login_id'])): ?>
      <li class="nav-item">
        <!-- FIXED: Added mobile-specific ID and classes -->
        <a class="nav-link sidebar-toggle" data-widget="pushmenu" href="#" role="button" id="mobileSidebarToggle">
          <i class="fas fa-bars"></i>
        </a>
      </li>
    <?php endif; ?>
  </ul>
  

  <ul class="navbar-nav ml-auto align-items-center">

    <!-- ===================== CHAT UNREAD (Hidden by default, only show for non-clients with unread messages) ===================== -->
    <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] < 4): ?>
    <li class="nav-item" id="chat-nav-item" style="display:none;">
      <a class="nav-link position-relative" href="index.php?page=chat" title="Chat">
        <i class="fa fa-comments"></i>
        <span class="badge badge-danger navbar-badge" id="chat-unread-count" style="display:none; font-size: 0.7rem; padding: 2px 5px;"></span>
      </a>
    </li>
    <?php endif; ?>

    <!-- ===================== NOTIFICATION ===================== -->
    <li class="nav-item dropdown">
      <a class="nav-link position-relative" data-toggle="dropdown" href="#" title="Notifikasi">
        <i class="fa fa-bell"></i>
        <span class="badge badge-danger navbar-badge" id="notification-count" style="display:none;"></span>
      </a>
      <div class="dropdown-menu dropdown-menu-right shadow-lg border-0 p-0" id="notification-dropdown" style="width: 340px; border-radius: 0.75rem; overflow:hidden;">
        <div class="text-center py-2">
          <p id="notification-header"></p>
          <hr>
        </div>
        <div id="notification-list" style="max-height: 300px; overflow-y: auto;" class="list-group list-group-flush">
          <div class="list-group-item text-center text-muted small">Loading...</div>
        </div>
        <div class="text-center py-2 border-top bg-light">
          <a href="javascript:void(0)" id="mark-all-read" class="small text-primary font-weight-bold">
            Mark as read
          </a>
        </div>
      </div>
    </li>

    <!-- ===================== FULLSCREEN ===================== -->
    <li class="nav-item">
      <a class="nav-link" data-widget="fullscreen" href="#" role="button">
        <i class="fas fa-expand-arrows-alt"></i>
      </a>
    </li>

    <li class="nav-item dropdown ml-2 mr-4">
        
        <a class="nav-link p-0" href="#" id="navbarDropdownProfile" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="display: flex; align-items: center;">
            <img src="assets/uploads/<?php echo $login_avatar; ?>" 
                 class="img-circle elevation-2" 
                 alt="User Avatar" 
                 onerror="this.onerror=null;this.src='assets/uploads/empty-placeholder.png';"
                 style="width: 38px; height: 38px; object-fit: cover; border-radius: 50%;">
            <span class="ml-2"><b><?php echo ucwords($login_firstname); ?></b></span>
        </a>

        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdownProfile">
            
            <a class="dropdown-item view_profile_trigger" 
               href="javascript:void(0)" 
               data-id="<?php echo $encoded_login_id; ?>">
                <i class="fa fa-user mr-2"></i> View Profile
            </a>
            
            <a class="dropdown-item" href="index.php?page=edit_user&id=<?php echo $encoded_login_id; ?>">
                <i class="fa fa-cog mr-2"></i> Settings
            </a>
            
            <div class="dropdown-divider"></div>
            
            <a class="dropdown-item" href="ajax.php?action=logout">
                <i class="fa fa-sign-out-alt mr-2"></i> Log Out
            </a>
        </div>
    </li>
    </ul>
</nav>

<!-- Add mobile sidebar overlay -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<style>
/* ====== Notifikasi Dropdown Style Global ====== */
#notification-dropdown {
  width: 340px !important;
  border-radius: 0.75rem !important;
  overflow: hidden !important;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
  font-size: 14px !important;
}

#notification-dropdown .dropdown-header {
  font-size: 14px !important;
  padding: 0.5rem !important;
}

#notification-list .list-group-item {
  padding: 0.6rem 0.75rem !important;
  border: none !important;
}

#notification-list .list-group-item:hover {
  background-color: #f8f9fa !important;
}

.navbar .fa-bell {
  font-size: 18px !important;
}

#notification-count {
  font-size: 11px !important;
  position: absolute !important;
  top: 6px !important;
  right: 8px !important;
  min-width: 16px !important;
  height: 16px !important;
  padding: 0 !important;
  line-height: 16px !important;
  text-align: center !important;
  border-radius: 50% !important;
}

#chat-unread-count {
  font-size: 11px !important;
  position: absolute !important;
  top: 6px !important;
  right: 8px !important;
  min-width: 16px !important;
  height: 16px !important;
  padding: 0 !important;
  line-height: 16px !important;
  text-align: center !important;
  border-radius: 50% !important;
}

/* Pastikan dropdown tidak ikut overflow parent */
.navbar-nav > .dropdown .dropdown-menu {
  position: absolute !important;
}

/* ====== Mobile Sidebar Fix ====== */
.sidebar-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  z-index: 1037;
}

@media (max-width: 991px) {
  /* Mobile-specific sidebar fixes */
  body.sidebar-open .sidebar-overlay {
    display: block;
  }
  
  body.sidebar-open {
    overflow: hidden;
  }
  
  .main-sidebar {
    position: fixed !important;
    height: 100vh !important;
    z-index: 1038;
    transform: translateX(-100%);
    transition: transform 0.3s ease-in-out;
  }
  
  body.sidebar-open .main-sidebar,
  body:not(.sidebar-collapse) .main-sidebar {
    transform: translateX(0);
    box-shadow: 0 0 15px rgba(0,0,0,0.3);
  }
  
  /* Force content to full width on mobile */
  .content-wrapper {
    margin-left: 0 !important;
    transition: transform 0.3s ease-in-out;
  }
  
  body.sidebar-open .content-wrapper {
    transform: translateX(250px);
  }
  
  /* Make sidebar toggle more prominent on mobile */
  #mobileSidebarToggle {
    font-size: 1.4rem;
    padding: 0.5rem;
  }
  
  /* Prevent horizontal scroll */
  html, body {
    overflow-x: hidden;
    position: relative;
  }
}

/* Make sure sidebar is visible on larger screens */
@media (min-width: 992px) {
  .main-sidebar {
    transform: none !important;
  }
}

/* Hide username on mobile */
@media (max-width: 768px) {
  .navbar-nav .nav-link span.ml-2 {
    display: none !important;
  }
  
  /* Optional: Adjust avatar size on mobile */
  .navbar-nav .img-circle {
    width: 32px !important;
    height: 32px !important;
  }
}

/* Hide username on smaller mobile screens */
@media (max-width: 576px) {
  /* You can also hide other elements if needed */
  .navbar-nav .nav-item.dropdown {
    margin-left: 0.5rem;
    margin-right: 0.5rem;
  }
}
</style>

<script>
$(document).ready(function() {
  // Fungsi untuk mengekstrak ID terenkripsi dan page dari URL
  function extractPageAndId(url) {
    const match = url.match(/page=([^&]+)&id=([^&]+)/);
    if (match) {
      return { page: match[1], id: match[2] };
    }
    const chatMatch = url.match(/page=chat&thread_id=([^&]+)/);
    if (chatMatch) {
      return { page: 'chat', id: chatMatch[1], param: 'thread_id' };
    }
    return { page: null, id: null };
  }

  // ====== MOBILE SIDEBAR TOGGLE FIX ======
  // Mobile sidebar toggle handler
  $('#mobileSidebarToggle').on('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    if ($(window).width() <= 991) {
      // Mobile behavior
      $('body').toggleClass('sidebar-open');
      
      // Also toggle Bootstrap's collapse class
      const isOpen = $('body').hasClass('sidebar-open');
      if (isOpen) {
        $('body').removeClass('sidebar-collapse');
      } else {
        $('body').addClass('sidebar-collapse');
      }
    } else {
      // Desktop - use Bootstrap's default behavior
      $('body').toggleClass('sidebar-collapse');
    }
  });
  
  // CLICK OUTSIDE TO CLOSE - FIXED VERSION
  $(document).on('click touchstart', function(e) {
    // Only on mobile
    if ($(window).width() > 991) return;
    
    const $target = $(e.target);
    
    // If sidebar is open AND user clicks outside sidebar (not on toggle button)
    if ($('body').hasClass('sidebar-open') && 
        !$target.closest('.main-sidebar').length && 
        !$target.closest('#mobileSidebarToggle').length) {
      closeMobileSidebar();
    }
  });
  
  // Overlay click handler
  $('#sidebarOverlay').on('click', function() {
    if ($(window).width() <= 991) {
      closeMobileSidebar();
    }
  });
  
  // Prevent clicks inside sidebar from triggering document click
  $('.main-sidebar').on('click touchstart', function(e) {
    e.stopPropagation();
  });
  
  // Function to close mobile sidebar
  function closeMobileSidebar() {
    $('body').removeClass('sidebar-open');
    $('body').addClass('sidebar-collapse');
  }
  
  // Close sidebar when clicking ESC key
  $(document).on('keydown', function(e) {
    if (e.key === 'Escape' && $(window).width() <= 991) {
      closeMobileSidebar();
    }
  });
  
  // Close sidebar when clicking a link inside sidebar (mobile only)
  $(document).on('click', '.main-sidebar a', function() {
    if ($(window).width() <= 991) {
      // Add a small delay to allow navigation
      setTimeout(closeMobileSidebar, 100);
    }
  });
  
  // Initialize sidebar state on page load
  function initSidebarState() {
    if ($(window).width() <= 991) {
      // Ensure sidebar is closed on mobile by default
      $('body').addClass('sidebar-collapse');
      $('body').removeClass('sidebar-open');
    }
  }
  
  // Run on load
  initSidebarState();
  
  // Run on resize
  $(window).on('resize', function() {
    initSidebarState();
  });

  // ====== Profile Dropdown JS (view_profile_trigger) ======
  $(document).on('click', '.view_profile_trigger', function(){
    let encodedUserId = $(this).attr('data-id');
    if(encodedUserId) {
      uni_modal(
        "Profile Details", 
        "view_user.php?id=" + encodedUserId,
        "md"
      );
    } else {
      alert_toast("User ID tidak ditemukan.", "danger");
    }
    $(this).closest('.dropdown-menu').removeClass('show');
  });

  // ====== Load Notifications ======
  function timeAgo(dateStr) {
    const now = new Date();
    const then = new Date(dateStr);
    const diff = Math.floor((now - then) / 1000);

    if (diff < 60) return "Just now";
    if (diff < 3600) return Math.floor(diff / 60) + " minutes ago";
    if (diff < 86400) return Math.floor(diff / 3600) + " hours ago";
    return Math.floor(diff / 86400) + " day ago";
  }

  function load_notifications() {
    if (typeof $ === 'undefined' || !$.ajax) {
      console.error("jQuery (AJAX) is not available.");
      return;
    }
    
    $.ajax({
      url: 'ajax.php?action=fetch_notifications',
      method: 'GET',
      dataType: 'json',
      success: function(resp) {
        if (resp && resp.status == 1) {
          const list = $('#notification-list');
          const countBadge = $('#notification-count');
          const notifications = resp.notifications;
          const unreadCount = resp.unread_count;

          $('#notification-header').text(`${unreadCount} Notification`);
          countBadge.text(unreadCount).toggle(unreadCount > 0);
          list.empty();

          if (notifications.length > 0) {
            notifications.forEach(n => {
              const isUnread = n.is_read == 0;
              const itemClass = isUnread ? 'list-group-item bg-light font-weight-bold text-primary' : 'list-group-item text-dark';
              const dot = isUnread ? '<span class="badge badge-danger ml-2" style="width:8px; height:8px; border-radius:50%;"></span>' : '';

              let icon = '<i class="fa fa-bell text-secondary mr-2"></i>';
              if (n.type == 1) icon = '<i class="fa fa-tasks text-primary mr-2"></i>';
              else if (n.type == 2) icon = '<i class="fa fa-sync-alt text-warning mr-2"></i>';
              else if (n.type == 4) icon = '<i class="fa fa-comment-dots text-info mr-2"></i>';

              let short_message = n.message.replace(/\*\*/g, '');
              short_message = short_message.length > 70 ? short_message.substring(0, 70) + '...' : short_message;
              const time = `<small class="text-muted d-block">${timeAgo(n.date_created)}</small>`;
              
              const originalLink = n.link && n.link.trim() !== '' ? n.link : '#';

              const html = `
                <a href="${originalLink}" class="${itemClass} notification-item border-0"
                  data-id="${n.id}" data-original-link="${originalLink}" style="cursor:pointer;">
                  <div class="d-flex align-items-start">
                    ${icon}
                    <div class="flex-fill">
                      <div>${short_message} ${dot}</div>
                      ${time}
                    </div>
                  </div>
                </a>`;
              list.append(html);
            });
          } else {
            list.append('<div class="list-group-item text-center text-muted small">Tidak ada notifikasi baru.</div>');
          }
        } else {
          $('#notification-list').html('<div class="list-group-item text-center text-danger small">Gagal memuat notifikasi.</div>');
          $('#notification-count').hide();
        }
      },
      error: function() {
        console.error("Gagal mengambil notifikasi");
      }
    });
  }

  // ====== Mark as Read ======
  function mark_as_read(id) {
    $.ajax({
      url: 'ajax.php?action=mark_as_read',
      method: 'POST',
      data: { id: id },
      success: function(resp) {
        if (resp == 1) load_notifications();
      }
    });
  }

  $('#mark-all-read').click(function(e) {
    e.preventDefault();
    mark_as_read('all');
  });

  // Notification item click handler
  $(document).on('click', '.notification-item', function(e) {
    const id = $(this).data('id');
    
    // Mark as Read (Jika belum dibaca)
    if ($(this).hasClass('font-weight-bold')) { 
      mark_as_read(id);
    }
    
    // Close dropdown
    $(this).closest('.dropdown-menu').removeClass('show');
  });

  // Load notifications and set interval
  load_notifications();
  setInterval(load_notifications, 30000);
  
  // ====== LOAD UNREAD CHAT COUNT ======
  function load_chat_unread_count() {
    $.ajax({
      url: 'ajax.php?action=get_total_unread_chat_count',
      method: 'GET',
      dataType: 'json',
      success: function(resp) {
        if (resp && resp.unread_count !== undefined) {
          const count = resp.unread_count;
          const badge = $('#chat-unread-count');
          const chatNavItem = $('#chat-nav-item');
          
          if (badge.length > 0 && chatNavItem.length > 0) {
            if (count > 0) {
              // Show the chat icon and badge when there are unread messages
              chatNavItem.show();
              badge.text(count).show();
            } else {
              // Hide the entire chat icon when no unread messages
              chatNavItem.hide();
            }
          }
        }
      },
      error: function(xhr, status, error) {
        console.error("Failed to load chat unread count:", status, error);
      }
    });
  }
  
  // Load chat unread count and set interval
  load_chat_unread_count();
  setInterval(load_chat_unread_count, 15000);
});
</script>