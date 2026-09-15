<?php include 'db_connect.php' ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap4.min.css">

<script>
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});
</script>

<?php
  /* ── Stat counts ── */
  $total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
  $total_emp   = $conn->query("SELECT COUNT(*) as c FROM users WHERE type = 3")->fetch_assoc()['c'];
  $total_admin = $conn->query("SELECT COUNT(*) as c FROM users WHERE type = 1")->fetch_assoc()['c'];
?>

<div class="container-fluid um-page-header-wrap">
  <div class="row align-items-end">
    <div class="col-md-6">
      <div class="um-title-group">
        <h4 class="fw-bold um-title">User Management</h4>
        <p class="um-subtitle">Manage and monitor all system users</p>
      </div>
    </div>
    <div class="col-md-6">
      <div class="d-flex justify-content-end">
        <?php if(isset($_SESSION['login_type']) && $_SESSION['login_type'] < 3): ?>
          <button class="btn text-white" id="add_user_btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15" style="margin-right:6px;vertical-align:-2px;"><path d="M12 5v14M5 12h14"/></svg>
            New User
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <!-- STATS STRIP -->
  <div class="um-stats-strip">
    <div class="um-stat-card">
      <div class="um-stat-icon um-icon-amber">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
        </svg>
      </div>
      <div>
        <div class="um-stat-label">Total Users</div>
        <div class="um-stat-value"><?= $total_users ?></div>
      </div>
    </div>
  
    <div class="um-stat-card">
      <div class="um-stat-icon um-icon-green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="9" cy="6" r="2.5"/>
          <path d="M2 20c0-3.5 3-6 7-6"/>
          <rect x="12" y="13" width="9" height="7" rx="1.5"/>
          <path d="M15 13v-1.5a1.5 1.5 0 013 0V13"/>
          <line x1="15.5" y1="16.5" x2="17.5" y2="16.5"/>
        </svg>
      </div>
      <div>
        <div class="um-stat-label">Employees</div>
        <div class="um-stat-value"><?= $total_emp ?></div>
      </div>
    </div>
  
    <div class="um-stat-card">
      <div class="um-stat-icon um-icon-blue">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="8" r="3.5"/>
          <path d="M4.5 20.5c0-4 3.358-7 7.5-7s7.5 3 7.5 7"/>
          <path d="M15 6.2A3.5 3.5 0 0116.5 9a3.5 3.5 0 01-1 2.45"/>
          <path d="M18 20.5c0-2.5 1.5-4.5 3-5.5"/>
        </svg>
      </div>
      <div>
        <div class="um-stat-label">Admins</div>
        <div class="um-stat-value"><?= $total_admin ?></div>
      </div>
    </div>
  </div>

    <!-- Desktop Table -->
  <div class="table-responsive card p-4 d-none d-md-block">
    <table class="table" id="list">
      <thead>
        <tr>
          <th class="text-center">No</th>
          <th>Avatar</th>
          <th>Name</th>
          <th class="text-center">Email</th>
          <th class="text-center">Role</th>
          <th class="text-center">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $i = 1;
          $type = array('','Admin','Project Manager','Employee','Client');
          $qry = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users order by concat(firstname,' ',lastname) asc");
          while($row= $qry->fetch_assoc()):
            $avatar = !empty($row['avatar']) ? 'assets/uploads/'.$row['avatar'] : 'assets/uploads/empty-placeholder.png';
            $encoded_id = encode_id($row['id']);
        ?>
        <tr data-firstname="<?php echo htmlspecialchars(ucwords($row['firstname'])) ?>" data-lastname="<?php echo htmlspecialchars(ucwords($row['lastname'])) ?>">
          <th class="text-center"><?php echo $i++ ?></th>
          <td class="text-center">
            <img src="<?php echo $avatar ?>" alt="Avatar" width="40" height="40" class="rounded-circle" onerror="this.onerror=null;this.src='assets/uploads/empty-placeholder.png';">
          </td>
          <td><b><?php echo ucwords($row['firstname']) ?> <span class="user-lastname"><?php echo ucwords($row['lastname']) ?></span></b></td>
          <td class="text-center"><b><?php echo $row['email'] ?></b></td>
          <td class="text-center"><b><em><?php echo $type[$row['type']] ?></em></b></td>
          <td class="text-center">
            <div class="dropdown">
              <button type="button" class="btn p-0" data-toggle="dropdown">
                <i class="fa fa-ellipsis-v"></i>
              </button>
              <div class="dropdown-menu dropdown-menu-right">
                <a class="dropdown-item view_user" href="javascript:void(0)" data-id="<?php echo $encoded_id ?>">
                  <i class="fa fa-eye mr-2"></i> View
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="./index.php?page=edit_user&id=<?php echo $encoded_id ?>">
                  <i class="fa fa-cog mr-2"></i> Edit
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger delete_user_trigger" href="javascript:void(0)" data-id="<?php echo $encoded_id ?>" data-name="<?php echo ucwords($row['name']) ?>">
                  <i class="fa fa-trash mr-2"></i> Delete
                </a>
              </div>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile Card Container -->
  <div class="mobile-user-list d-block d-md-none"></div>
  <div class="mobile-user-pagination d-block d-md-none mt-3"></div>
  
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger"><i class="fa fa-trash"></i> Konfirmasi Hapus</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        Apakah Anda yakin ingin menghapus user: <b id="deleteUserName"></b>?
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button class="btn btn-danger" id="confirmDeleteBtn">Hapus</button>
      </div>
    </div>
  </div>
</div>

<style>
/* Existing styles from user_list.php (kept intact) */
body {
  background-color: #F7F6F3;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  color: #1A1714;
}
.um-page-header-wrap { padding-top: 10px; padding-bottom: 4px; }
.um-title-group { display: flex; flex-direction: column; gap: 2px; }
.um-eyebrow {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: #B75301;
}
.um-title {
  font-size: clamp(20px, 3vw, 26px) !important;
  font-weight: 500 !important;
  color: #1A1714 !important;
  letter-spacing: -.3px;
  margin-bottom: 0 !important;
  line-height: 1.2;
}
.um-subtitle {
  font-size: 13px;
  color: #7A746C;
  margin-bottom: 0;
}
#add_user_btn, #add_user_btn_mobile {
  background-color: #B75301 !important;
  color: #ffffff !important;
  border: none !important;
  padding: 10px 20px !important;
  border-radius: 8px !important;
  font-size: 14px !important;
  font-weight: 500 !important;
  box-shadow: 0 2px 8px rgba(183,83,1,.28) !important;
  transition: background-color .2s, box-shadow .2s, transform .15s !important;
  display: inline-flex;
  align-items: center;
}
#add_user_btn:hover, #add_user_btn_mobile:hover {
  background-color: #9A4500 !important;
  box-shadow: 0 4px 14px rgba(183,83,1,.35) !important;
  transform: translateY(-1px);
}
.um-stats-strip {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin: 16px 0 18px;
}
.um-stat-card {
  background: #FFFFFF;
  border: 1px solid #E8E5DF;
  border-radius: 12px;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 1px 3px rgba(26,23,20,.06), 0 1px 2px rgba(26,23,20,.04);
  transition: box-shadow .2s;
}
.um-stat-card:hover { box-shadow: 0 4px 16px rgba(26,23,20,.08), 0 2px 6px rgba(26,23,20,.05); }
.um-stat-icon {
  width: 42px; height: 42px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.um-stat-icon svg { width: 19px; height: 19px; }
.um-icon-amber { background: #FAEEE7;  color: #B75301; }
.um-icon-green { background: #EAF4EF; color: #2E7D5E; }
.um-icon-blue  { background: #EEF0FA;    color: #3D4EAC; }
.um-stat-label { font-size: 12px; color: #7A746C; margin-bottom: 3px; }
.um-stat-value { font-size: 24px; font-weight: 700; color: #1A1714; line-height: 1; }
.table-responsive.card.p-4 {
  background: #FFFFFF;
  border: 1px solid #E8E5DF;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(26,23,20,.06), 0 1px 2px rgba(26,23,20,.04);
  padding: 0 !important;
  overflow: visible !important;
}
.dataTables_wrapper { padding: 18px 20px 16px; }
.dataTables_wrapper .dataTables_filter { float: right; text-align: right; }
.dataTables_wrapper .dataTables_filter label {
  font-size: 0;
  display: flex;
  align-items: center;
  margin-bottom: 0;
}
.dataTables_wrapper .dataTables_filter input {
  font-size: 13px;
  border: 1px solid #E8E5DF;
  border-radius: 30px;
  padding: 8px 16px 8px 38px;
  color: #1A1714;
  background-color: #F7F6F3;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23B0A99F' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: 13px center;
  transition: border-color .2s, box-shadow .2s, width .2s;
  width: 220px;
  outline: none;
  box-shadow: none;
  display: inline-block;
  margin-left: 0 !important;
}
.dataTables_wrapper .dataTables_filter input:focus {
  border-color: #B75301;
  background-color: #fff;
  box-shadow: 0 0 0 3px rgba(183,83,1,.08);
  width: 260px;
}
.dataTables_wrapper .dataTables_length { float: left; }
.dataTables_wrapper .dataTables_length select {
  appearance: none; -webkit-appearance: none;
  border: 1px solid #E8E5DF;
  border-radius: 6px;
  background-color: #F7F6F3;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%237A746C' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 8px center;
  padding: 6px 28px 6px 10px;
  font-size: 13px;
  color: #1A1714;
  margin: 0 6px;
  outline: none;
}
#list {
  border-collapse: separate !important;
  border-spacing: 0;
  width: 100% !important;
  margin-top: 8px !important;
}
#list thead tr { border-bottom: 2px solid #E8E5DF; }
#list thead th {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: #B0A99F;
  padding: 12px 16px;
  border-bottom: 2px solid #E8E5DF !important;
  border-top: none !important;
  background: #FFFFFF;
}
#list tbody tr {
  border-bottom: 1px solid #F0EDE8;
  cursor: pointer;
  transition: background-color .15s ease;
  position: relative;
  z-index: 1;
}
#list tbody tr:focus-within {
  background-color: #FAFAF8 !important;
  z-index: 9999 !important;
}
#list tbody tr:hover,
#list tbody tr.hover-row { background-color: #FAFAF8 !important; }
#list tbody td {
  padding: 13px 16px;
  font-size: 14px;
  color: #1A1714;
  border-top: none !important;
  vertical-align: middle;
}
#list tbody th {
  padding: 13px 16px;
  font-size: 13px;
  color: #B0A99F;
  border-top: none !important;
  vertical-align: middle;
  font-weight: 400;
  text-align: center;
}
#list tbody .dropdown {
  display: flex;
  justify-content: center;
}
#list tbody img.rounded-circle {
  width: 36px !important;
  height: 36px !important;
  border: 1.5px solid #E8E5DF;
  object-fit: cover;
}
#list tbody td b { font-weight: 500; }
#list tbody .dropdown > .btn {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 6px;
  background: transparent !important;
  color: #7A746C !important;
  padding: 0;
  transition: all .15s;
  line-height: 1;
  box-shadow: none !important;
}
#list tbody .dropdown > .btn:hover,
#list tbody .dropdown > .btn:focus,
#list tbody .dropdown > .btn.show {
  background-color: #F7F6F3 !important;
  border-color: #B0A99F !important;
  color: #1A1714 !important;
}
#list .dropdown-menu {
  border: 1px solid #E8E5DF;
  border-radius: 8px;
  box-shadow: 0 12px 40px rgba(26,23,20,.12), 0 4px 12px rgba(26,23,20,.06);
  padding: 4px 0;
  min-width: 155px;
  margin-top: 4px;
  background: #FFFFFF;
  z-index: 9999 !important;
  animation: umDropIn 0.3s ease-out;
}
@keyframes umDropIn {
  from { opacity: 0; margin-top: -5px; }
  to   { opacity: 1; margin-top: 4px; }
}
#list .dropdown-item {
  font-size: 13px;
  font-weight: 500;
  padding: 9px 16px;
  color: #1A1714;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: background-color .12s;
}
#list .dropdown-item i { width: 14px; flex-shrink: 0; }
#list .dropdown-item .fa-eye   { color: #3D4EAC; }
#list .dropdown-item .fa-cog   { color: #B07D2E; }
#list .dropdown-item .fa-trash { color: #C4362D; }
#list .dropdown-item:hover              { background-color: #F7F6F3; color: #1A1714; }
#list .dropdown-item.text-danger        { color: #C4362D !important; }
#list .dropdown-item.text-danger:hover  { background-color: #FAEAEA; }
#list .dropdown-divider { margin: 2px 0; border-color: #F0EDE8; }
.dataTables_wrapper .dataTables_info {
  font-size: 13px;
  color: #7A746C;
  padding-top: 14px;
}
.dataTables_wrapper .dataTables_paginate { padding-top: 10px; }
.dataTables_wrapper .dataTables_paginate .pagination { gap: 4px; flex-wrap: wrap; }
.dataTables_wrapper .dataTables_paginate .page-link,
.mobile-user-pagination .page-link {
  font-size: 13px;
  font-weight: 500;
  padding: 6px 12px;
  border-radius: 6px !important;
  border: 1px solid #B75301 !important;
  color: #B75301 !important;
  background: #FFF8F3 !important;
  margin: 0;
  transition: all .15s;
  line-height: 1.5;
  box-shadow: none !important;
  text-decoration: none !important;
}

.dataTables_wrapper .dataTables_paginate .page-link:hover,
.mobile-user-pagination .page-link:hover {
  background: #B75301 !important;
  border-color: #B75301 !important;
  color: #fff !important;
}

.dataTables_wrapper .dataTables_paginate .active .page-link,
.mobile-user-pagination .active .page-link,
.mobile-user-pagination .current .page-link {
  background: #B75301 !important;
  border-color: #B75301 !important;
  color: #fff !important;
  box-shadow: 0 2px 6px rgba(183,83,1,.25) !important;
}
table.dataTable thead th.sorting::before,
table.dataTable thead th.sorting::after,
table.dataTable thead th.sorting_asc::before,
table.dataTable thead th.sorting_asc::after,
table.dataTable thead th.sorting_desc::before,
table.dataTable thead th.sorting_desc::after {
  display: none !important;
}
.dataTables_scroll,
.dataTables_scrollBody { overflow: visible !important; }
#list tbody tr {
  opacity: 0;
  transform: translateY(20px);
  animation: rowFadeUp 0.4s ease forwards;
}
@keyframes rowFadeUp {
  to { opacity: 1; transform: none; }
}
.um-stat-card {
  animation: statFadeUp 0.5s ease forwards;
  opacity: 0;
}
.um-stat-card:nth-child(1) { animation-delay: 0.1s; }
.um-stat-card:nth-child(2) { animation-delay: 0.2s; }
.um-stat-card:nth-child(3) { animation-delay: 0.3s; }
@keyframes statFadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}
@media (max-width: 1024px) {
  .um-stats-strip { grid-template-columns: 1fr 1fr; }
  .um-stats-strip .um-stat-card:last-child { grid-column: span 2; }
  #list thead th, #list tbody td, #list tbody th { padding: 10px 10px; }
  .dataTables_wrapper .dataTables_filter input { min-width: 150px; }
}
@media (max-width: 480px) {
  .um-stats-strip { grid-template-columns: 1fr; }
  .um-stats-strip .um-stat-card:last-child { grid-column: span 1; }
}
@media (max-width: 991px) {
  .table-responsive.card.p-4 {
    overflow-x: auto !important;
    overflow-y: visible !important;
    display: block;
    width: 100%;
    -webkit-overflow-scrolling: touch;
  }
}

/* NEW MOBILE CARD STYLES */
.mobile-user-list {
  padding: 0 15px;
}
.mobile-user-card {
  background: #fff;
  border-radius: 12px;
  margin-bottom: 12px;
  border: 1px solid #e8e5df;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  transition: all 0.2s;
}
.mobile-user-card .card-content {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  position: relative;
}
.mobile-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 12px;
  border: 1px solid #e8e5df;
}
.user-info {
  flex: 1;
}
.user-name {
  font-weight: 600;
  font-size: 16px;
  color: #1a1714;
}
.user-email {
  font-size: 12px;
  color: #7a746c;
  margin-top: 2px;
}
.user-role {
  font-size: 11px;
  font-weight: 500;
  color: #b75301;
  margin-top: 4px;
  font-style: italic;
}
.user-actions {
  position: relative;
}
.user-actions .fa-ellipsis-v {
  font-size: 18px;
  color: #7a746c;
  padding: 8px;
  cursor: pointer;
}
.action-menu {
  position: absolute;
  right: 0;
  top: 30px;
  background: white;
  border: 1px solid #e8e5df;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  z-index: 100;
  min-width: 100px;
}
.action-menu a {
  display: block;
  padding: 8px 12px;
  font-size: 13px;
  color: #1a1714;
  text-decoration: none;
}
.action-menu a:hover {
  background: #f7f6f3;
}
.action-menu a.delete-action {
  color: #c4362d;
}

/* BOTTOM SHEET STYLES (copied from mytask.php) */
.bottom-sheet {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: white;
  border-radius: 20px 20px 0 0;
  transform: translateY(100%);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 1050;
  max-height: 80vh;
  overflow-y: auto;
  box-shadow: 0 -2px 20px rgba(0,0,0,0.1);
}
.bottom-sheet.active {
  transform: translateY(0);
}
.sheet-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e8e5df;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  background: white;
  z-index: 1;
}
.sheet-title {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #1a1714;
}
.sheet-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #7a746c;
}
.sheet-content {
  padding: 12px 0;
}
.sheet-content .dropdown-item {
  padding: 12px 20px;
  font-size: 15px;
  cursor: pointer;
}
.sheet-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  z-index: 1040;
  opacity: 0;
  transition: opacity 0.3s;
  pointer-events: none;
}
.sheet-backdrop.active {
  opacity: 1;
  pointer-events: auto;
}
body.sheet-open {
  overflow: hidden;
}
</style>

<script>
// ============================================
// BOTTOM SHEET FUNCTIONS
// ============================================
function showBottomSheet(title, content) {
  $('.bottom-sheet, .sheet-backdrop').remove();
  var sheetHTML = `
    <div class="bottom-sheet" id="bottomSheet">
      <div class="sheet-header">
        <h5 class="sheet-title">${title}</h5>
        <button type="button" class="sheet-close">&times;</button>
      </div>
      <div class="sheet-content">
        ${content}
      </div>
    </div>
    <div class="sheet-backdrop"></div>
  `;
  $('body').append(sheetHTML);
  $('body').addClass('sheet-open');
  setTimeout(function() {
    $('#bottomSheet').addClass('active');
    $('.sheet-backdrop').addClass('active');
  }, 10);
  $('.sheet-close').off('click').on('click', closeBottomSheet);
  $('.sheet-backdrop').off('click').on('click', closeBottomSheet);
  $(document).off('keyup.sheet').on('keyup.sheet', function(e) {
    if (e.key === 'Escape') closeBottomSheet();
  });
}
function closeBottomSheet() {
  $('#bottomSheet').removeClass('active');
  $('.sheet-backdrop').removeClass('active');
  setTimeout(function() {
    $('.bottom-sheet, .sheet-backdrop').remove();
    $('body').removeClass('sheet-open');
  }, 300);
}

// ============================================
// MOBILE CARD LAYOUT BUILDER
// ============================================
function buildMobileUserLayout() {
  if ($(window).width() >= 768) return;
  var $container = $('.mobile-user-list');
  $container.empty();
  var rows = [];
  $('#list tbody tr').each(function() {
    var $row = $(this);
    var avatar = $row.find('img.rounded-circle').attr('src');
    var firstname = $row.data('firstname');
    var lastname = $row.data('lastname');
    var email = $row.find('td:nth-child(4) b').text().trim();
    var role = $row.find('td:nth-child(5) b').text().trim();
    var viewUserLink = $row.find('.view_user').data('id');
    var editLink = $row.find('a[href*="edit_user"]').attr('href');
    var deleteData = $row.find('.delete_user_trigger').data();
    rows.push({ avatar, firstname, lastname, email, role, viewUserLink, editLink, deleteData });
  });
  rows.forEach(function(user, idx) {
    var plainName = user.firstname + (user.lastname ? ' ' + user.lastname : '');
    var card = $(`
      <div class="mobile-user-card" data-name="${plainName}" data-email="${user.email}" data-role="${user.role}">
        <div class="card-content">
          <img src="${user.avatar}" class="mobile-avatar">
          <div class="user-info">
            <div class="user-name">${user.firstname}${user.lastname ? ' <span class="user-lastname">'+user.lastname+'</span>' : ''}</div>
            <div class="user-email">${user.email}</div>
            <div class="user-role">${user.role}</div>
          </div>
          <div class="user-actions">
            <i class="fa fa-ellipsis-v"></i>
            <div class="action-menu" style="display:none;">
              <a href="#" class="view-action" data-id="${user.viewUserLink}">View</a>
              <a href="${user.editLink}">Edit</a>
              <a href="#" class="delete-action" data-id="${user.deleteData.id}" data-name="${user.deleteData.name}">Delete</a>
            </div>
          </div>
        </div>
      </div>
    `);
    $container.append(card);
  });
  // Attach click handlers
  $('.mobile-user-card').on('click', function(e) {
    if ($(e.target).closest('.user-actions').length) return;
    var id = $(this).find('.view-action').data('id');
    if (id) uni_modal("<i class='fa fa-id-card'></i> User Details", "view_user.php?id=" + id);
  });
  $('.user-actions .fa-ellipsis-v').off('click.mobileCard').on('click.mobileCard', function(e) {
    e.stopPropagation();
    $(this).siblings('.action-menu').toggle();
  });
  $(document).off('click.mobileCardOutside').on('click.mobileCardOutside', function(e) {
    if (!$(e.target).closest('.user-actions').length) {
      $('.action-menu').hide();
    }
  });
  // Delete action
  $(document).off('click', '.delete-action').on('click', '.delete-action', function(e) {
    e.preventDefault();
    var id = $(this).data('id');
    var name = $(this).data('name');
    $('#deleteUserName').text(name);
    $('#confirmDeleteBtn').data('id', id);
    $('#confirmDeleteModal').modal('show');
  });

  var pagination = $('.dataTables_paginate').html();
  $('.mobile-user-pagination').html(pagination);
}



// ============================================
// INITIALIZATION
// ============================================
$(document).ready(function(){
  // DataTable initialization
  var dataTable = $('#list').DataTable({
    "paging": true,
    "lengthChange": true,
    "searching": true,
    "ordering": true,
    "info": true,
    "autoWidth": false,
    "responsive": false
  });
  // Add user button handlers
  $('#add_user_btn, #add_user_btn_mobile').click(function(){
    window.location.href = "index.php?page=new_user";
  });
  // View user (desktop)
  $(document).on('click', '.view_user', function(){
    uni_modal("<i class='fa fa-id-card'></i> User Details","view_user.php?id="+$(this).data('id'));
  });
  // Delete modal trigger (desktop)
  $(document).on('click', '.delete_user_trigger', function(){
    var id = $(this).data('id');
    var name = $(this).data('name');
    $('#deleteUserName').text(name);
    $('#confirmDeleteBtn').data('id', id);
    $('#confirmDeleteModal').modal('show');
  });
  // Confirm delete
  $('#confirmDeleteBtn').click(function(){
    var id = $(this).data('id');
    $('#confirmDeleteModal').modal('hide');
    $.ajax({
      url:'ajax.php?action=delete_user',
      method:'POST',
      data:{id:id},
      success:function(resp){
        resp = (typeof resp === 'string') ? resp.trim() : resp;
        if (resp == 1) {
          alert_toast("User delete success.", "success");
          setTimeout(function() { location.replace('index.php?page=user_list'); }, 1000);
        } else {
          Swal.fire({ icon:'error', title:'Gagal menghapus', html:'<pre>'+resp+'</pre>' });
        }
      },
      error:function(xhr){
        Swal.fire({ icon:'error', title:'AJAX Error', text:xhr.statusText });
      }
    });
  });

  $(document).on('click', '.mobile-user-pagination .paginate_button a', function(e) {
  e.preventDefault();
  var pageText = $(this).text().trim();

  if (pageText === 'Next') {
    dataTable.page('next').draw('page');
  } else if (pageText === 'Previous') {
    dataTable.page('previous').draw('page');
  } else if (!isNaN(pageText)) {
    dataTable.page(parseInt(pageText) - 1).draw('page');
  }

  buildMobileUserLayout();
});
  // Row click for desktop: view user details
  $(document).on('click', '#list tbody tr', function(e) {
    if ($(e.target).closest('.dropdown, .dropdown-menu, .btn, .dropdown-item').length) return;
    var encodedId = $(this).find('.view_user').data('id');
    if (encodedId) uni_modal("<i class='fa fa-id-card'></i> User Details", "view_user.php?id=" + encodedId);
  });
  // Hover effect for desktop
  $(document).on('mouseenter', '#list tbody tr', function() { $(this).addClass('hover-row'); })
             .on('mouseleave', '#list tbody tr', function() { $(this).removeClass('hover-row'); });
  // Mobile filter button
  $('#filterUserMobile').on('click', function(e) {
    e.preventDefault();
    var content = `
      <a href="#" class="dropdown-item sheet-filter-item" data-filter="all">All Roles</a>
      <div class="dropdown-divider"></div>
      <a href="#" class="dropdown-item sheet-filter-item" data-filter="Admin">Admin</a>
      <a href="#" class="dropdown-item sheet-filter-item" data-filter="Project Manager">Project Manager</a>
      <a href="#" class="dropdown-item sheet-filter-item" data-filter="Employee">Employee</a>
      <a href="#" class="dropdown-item sheet-filter-item" data-filter="Client">Client</a>
    `;
    showBottomSheet('Filter by Role', content);
  });
  $('#sortUserMobile').on('click', function(e) {
    e.preventDefault();
    var content = `
      <a href="#" class="dropdown-item sheet-filter-item" data-sort="name-asc">Name (A-Z)</a>
      <a href="#" class="dropdown-item sheet-filter-item" data-sort="name-desc">Name (Z-A)</a>
      <a href="#" class="dropdown-item sheet-filter-item" data-sort="email-asc">Email (A-Z)</a>
      <a href="#" class="dropdown-item sheet-filter-item" data-sort="email-desc">Email (Z-A)</a>
      <a href="#" class="dropdown-item sheet-filter-item" data-sort="role-asc">Role (A-Z)</a>
      <a href="#" class="dropdown-item sheet-filter-item" data-sort="role-desc">Role (Z-A)</a>
    `;
    showBottomSheet('Sort Options', content);
  });
  $(document).on('click', '.sheet-filter-item', function(e) {
    e.preventDefault();
    var filter = $(this).data('filter');
    var sort = $(this).data('sort');
    if (filter) {
      currentFilterRole = filter === 'all' ? 'all' : filter;
      $('#filterUserLabelMobile').text(filter === 'all' ? 'All Roles' : filter);
      
    } else if (sort) {
      currentSortBy = sort;
      var sortText = $(this).text();
      $('#sortUserLabelMobile').text('Sort: ' + sortText);
      
    }
    closeBottomSheet();
  });
  // Counter animation for stat cards
  function animateCounter($el) {
    var target = parseInt($el.text());
    var duration = 1000;
    var step = Math.ceil(duration / (target || 1));
    var current = 0;
    var timer = setInterval(function() {
      current += Math.ceil(target / (duration / step));
      if (current >= target) { current = target; clearInterval(timer); }
      $el.text(current);
    }, step);
  }
  $('.um-stat-value').each(function() { animateCounter($(this)); });
  // Initial mobile layout build
  if ($(window).width() < 768) {
    buildMobileUserLayout();
    
  }
  // Resize handler
  $(window).on('resize', function() {
    if ($(window).width() < 768) {
      $('.table-responsive.card').addClass('d-none').removeClass('d-block');
      $('.mobile-user-list').removeClass('d-none').addClass('d-block');
      buildMobileUserLayout();
      
    } else {
      $('.table-responsive.card').removeClass('d-none').addClass('d-block');
      $('.mobile-user-list').removeClass('d-block').addClass('d-none');
    }
  });
  // Row animation on page change (desktop)
  function animateRows() {
    $('#list tbody tr').each(function(i) {
      $(this).css('animation', 'none').css('opacity', '0').css('transform', 'translateY(20px)');
      var $row = $(this);
      setTimeout(function() { $row.css('animation', 'rowFadeUp 0.4s ease forwards'); }, i * 60);
    });
  }
  animateRows();
    $('#list').on('draw.dt', function() {
    animateRows();
    if ($(window).width() < 768) {
      buildMobileUserLayout();
    }
  });
  // Other legacy scripts
  $('.summernote').summernote({ height: 200 });
  $('#user_ids').select2({ placeholder: "Select users", dropdownParent: $('#addTaskModal') });
  $('#avatar').change(function(){
    const file = this.files[0];
    if (file){
      let reader = new FileReader();
      reader.onload = function(e){ $('#avatarPreview').attr('src', e.target.result).show(); }
      reader.readAsDataURL(file);
    }
  });
  $('#password, #cpass').on('keyup', function(){
    if ($('#password').val() != $('#cpass').val()) $('#passHelp').text('Passwords do not match.');
    else $('#passHelp').text('');
  });
  $('#email').on('blur', function(){
    var email = $(this).val();
    if(email != ''){
      $.ajax({
        url: 'ajax.php?action=check_email', method: 'POST', data: {email: email},
        success: function(resp){ if(resp == 1) $('#emailHelp').text('Email sudah digunakan.'); else $('#emailHelp').text(''); }
      });
    }
  });
  $('#userForm').submit(function(e){
    e.preventDefault();
    var formData = new FormData(this);
    $.ajax({
      url: 'ajax.php?action=save_user', type: 'POST', data: formData, contentType: false, processData: false,
      success: function(resp){
        if(resp == 1){
          Swal.fire({ icon: 'success', title: 'Sukses', text: 'User berhasil ditambahkan.', timer: 2000, showConfirmButton: false });
          $('#userModal').modal('hide');
          setTimeout(() => location.reload(), 2000);
        } else if (resp == 2) {
          Swal.fire({ icon: 'error', title: 'Gagal', text: 'Email sudah digunakan.' });
        }
      }
    });
  });
});
</script>