<?php include 'db_connect.php'; ?>

<?php
// Cek apakah parameter ID ada di URL
if (isset($_GET['id'])) {
    
    // 1. Ambil ID yang dienkripsi dari URL
    $encoded_id = $_GET['id'];
    
    // 2. Gunakan fungsi decode_id untuk mengembalikan ke ID numerik
    // Fungsi ini ada di db_connect.php
    $id = decode_id($encoded_id); // Mengembalikan ID numerik (misalnya 17) atau null jika gagal.

    // 3. Verifikasi apakah decoding berhasil (ID numerik valid)
    if (!is_null($id)) {
        
        $type_arr = array('', "Admin", "Project Manager", "Employee");

        // 4. Lakukan query menggunakan ID numerik yang sudah didapat
        $qry = $conn->query("SELECT *, CONCAT(firstname,' ',lastname) AS name FROM users WHERE id = $id");

        if ($qry && $qry->num_rows > 0) {
            $row = $qry->fetch_assoc();
            foreach ($row as $k => $v) {
                $$k = $v;
            }
        } else {
            // Jika ID numerik valid, tapi tidak ditemukan di DB
            echo "<div class='p-3 text-center text-danger'>User tidak ditemukan.</div>";
            exit;
        }
    } else {
        // Jika parameter ID ada, tapi proses decoding gagal
        echo "<div class='p-3 text-center text-danger'>Parameter ID tidak valid.</div>";
        exit;
    }
} else {
    // Jika parameter ID tidak ada di URL
    echo "<div class='p-3 text-center text-danger'>Parameter ID tidak valid.</div>";
    exit;
}
?>

<div class="container-fluid">
  <div class="card card-widget widget-user shadow">
    <div class="widget-user-header bg-dark">
      <h3 class="widget-user-username mb-0"><?php echo ucwords($firstname) ?> <span class="user-lastname"><?php echo ucwords($lastname) ?></span></h3>
      <h5 class="widget-user-desc"><?php echo $email ?></h5>
    </div>

    <div class="widget-user-image mt-2">
      <?php if(empty($avatar) || !is_file('assets/uploads/'.$avatar)): ?>
        <span class="brand-image img-circle elevation-2 d-flex justify-content-center align-items-center bg-primary text-white font-weight-500" 
              style="width: 90px; height: 90px;">
          <h4><?php echo strtoupper(substr($firstname, 0,1).substr($lastname, 0,1)) ?></h4>
        </span>
      <?php else: ?>
        <img class="img-circle elevation-2" 
             src="assets/uploads/<?php echo $avatar ?>" 
             alt="User Avatar" 
             style="width: 90px; height: 90px; object-fit: cover;"
             onerror="this.onerror=null;this.src='assets/uploads/empty-placeholder.png';">
      <?php endif; ?>
    </div>

    <div class="card-footer">
      <div class="container-fluid">
        <dl class="row mb-0">
          <dt class="col-sm-4">Role</dt>
          <dd class="col-sm-8"><em><?php echo $type_arr[$type] ?></em></dd>
          <dt class="col-sm-4">Staff ID</dt>
          <dd class="col-sm-8"><?php echo $nik ?></dd>
          <dt class="col-sm-4">Bio</dt>
          <dd class="col-sm-8"><?php echo $address ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <a class="dropdown-item btn" href="./index.php?page=edit_user&id=<?php echo $encoded_id ?>"> <i class="fa fa-cog mr-2"></i> Edit</a>
</div>

<style>
  #uni_modal .modal-footer { 
    display: none; 
  }
  #uni_modal .modal-footer.display { 
    display: flex; 
  }

  /* Container styling */
  .container-fluid {
    padding: 15px;
    background: #f8f5f0;
  }

  /* Card styling */
  .card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(183, 83, 1, 0.1);
    overflow: hidden;
    margin-bottom: 15px;
  }

  /* Widget user header */
  .widget-user-header {
    padding: 20px 15px;
    text-align: center;
    background: linear-gradient(135deg, #b75301 0%, #d49867 100%);
  }

  .widget-user-username {
    font-size: 1.4rem;
    font-weight: 600;
    color: white;
    margin-bottom: 5px !important;
  }

  .widget-user-desc {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.95);
  }

  /* User image styling */
  .widget-user-image {
    text-align: center;
    margin-top: -25px;
  }

  .brand-image {
    font-size: 1.1rem;
    font-weight: 600;
    border: 3px solid white;
    box-shadow: 0 2px 6px rgba(183, 83, 1, 0.1);
  }

  .img-circle {
    border: 3px solid white;
    box-shadow: 0 2px 6px rgba(183, 83, 1, 0.1);
  }

  /* Card footer */
  .card-footer {
    background: white;
    padding: 15px;
  }

  /* Description list styling */
  dl.row {
    margin: 0;
  }

  dt {
    font-weight: 600;
    color: #b75301;
    padding: 6px 10px;
    background: rgba(183, 83, 1, 0.05);
    border-radius: 4px;
    margin-bottom: 3px;
    font-size: 0.85rem;
  }

  dd {
    padding: 6px 10px;
    color: #666;
    margin-bottom: 3px;
    font-size: 0.85rem;
    border-left: 2px solid #d49867;
    background: #fcfaf6;
    border-radius: 0 4px 4px 0;
  }

  /* Remove gap after bio */
  dd:last-child {
    margin-bottom: 0;
  }

  /* Edit button styling - FITS TEXT EXACTLY */
  .dropdown-item.btn {
    background: #b75301;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    margin-top: 10px;
    transition: background 0.2s ease;
    line-height: 1;
    min-width: auto;
    width: auto;
  }

  .dropdown-item.btn:hover {
    background: #9a4601;
    color: white;
    text-decoration: none;
  }

  .dropdown-item.btn i {
    font-size: 0.8rem;
  }

  /* Responsive design */
  @media (max-width: 768px) {
    .container-fluid {
      padding: 10px;
    }
    
    .widget-user-header {
      padding: 15px 10px;
    }
    
    .widget-user-username {
      font-size: 1.2rem;
    }
    
    .card-footer {
      padding: 12px;
    }
    
    dt, dd {
      padding: 5px 8px;
      font-size: 0.8rem;
    }
    
    .dropdown-item.btn {
      padding: 3px 6px;
      font-size: 0.8rem;
      gap: 4px;
    }
  }
</style>