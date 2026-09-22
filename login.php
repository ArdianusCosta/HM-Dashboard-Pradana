
<?php 
session_start();
include('./db_connect.php');
  ob_start();
  // if(!isset($_SESSION['system'])){

    $system = $conn->query("SELECT * FROM system_settings")->fetch_array();
    foreach($system as $k => $v){
      $_SESSION['system'][$k] = $v;
    }
  // }
  ob_end_flush();
?>
<?php 
if(isset($_SESSION['login_id']))
header("location:index.php?page=home");

?>

<?php include 'header.php' ?>


<!DOCTYPE html>
<html lang="en">
<style>
	

  #img{
    background-color: #FFFFFF;
    border-radius: 100% ;
    background-position: center;
    width: 40%;
    align: center;
    margin-left:100px;
  }

  #text{
    font-weight : bold;
  }

	main#main{
		width:100%;
		height: calc(100%);
		background:white;
	}
	
	#login-right .card{
		margin: auto
	}
	.logo {
    margin: auto;
    font-size: 8rem;
    background: white;
    padding: .5em 0.8em;
    border-radius: 50% 50%;
    color: #000000b3;
}

html, body {
  height: 100%;
  margin: 0;
  padding: 0;
}

.full-height.d-flex {
  display: flex !important;
  flex-direction: column;
  min-height: 100vh; /* Menggunakan min-height agar lebih fleksibel */
  display: flex;
  justify-content: center;
  align-items: center;
}

.grad {
  background: linear-gradient(135deg, #9BB1C8, #E7ECF0, #DBA980); /* Ungu → Biru → Toska */
  position: relative;
  overflow: hidden;
  z-index: 1;
}

/* Update Card menjadi Glassmorphism */
.card.animasi{
  background: rgba(255, 255, 255, 0.05) !important; /* Transparan */
  backdrop-filter: blur(10px);           /* Efek Blur Kaca */
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 30px !important;        /* Membulat di semua sisi */
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
  z-index: 10;
  width: 100%;
  max-width: 420px; /* Ukuran pas agar terlihat 'press' */
  margin: auto;
}

/* Memastikan elemen form di dalam menyesuaikan frame */
#login-form {
  width: 100%;
}

.form-control {
  background: rgba(255, 255, 255, 0.9) !important; /* Input tetap solid agar mudah dibaca */
  border: none;
  border-radius: 10px !important;
  color: #000 !important;
}

/* Mengatur teks agar hitam kontras sesuai permintaan */
.form-items h3, .form-items p, label {
  color: #000000 !important;
}

/* Tombol Login agar menyesuaikan frame */
.btn-block {
  width: 100%;
  border-radius: 30px !important;
  padding: 10px;
  font-weight: bold;
  transition: 0.3s;
}

.btn-block:hover {
  filter: brightness(1.1);
  transform: translateY(-2px);
}

/* Reset class lama yang mungkin mengganggu centering */
#login-right .card {
  margin: auto;
}

.animasi{
  animation: slideInRight 1s ease-out;
}
.fade-in{
  animation: fadeIn 1.2s ease-out;
}

.logo-bg {
  position: absolute;
  opacity: 0.08;
  animation: spin 20s linear infinite;
  z-index: 0;
  width: 120px;
}

.logo1 { top: 5%; left: 5%; }
.logo2 { top: 10%; right: 10%; }
.logo3 { top: 30%; left: 30%; }
.logo4 { top: 35%; right: 20%; }
.logo5 { top: 50%; left: 5%; }
.logo6 { top: 55%; right: 8%; }
.logo7 { bottom: 5%; left: 30%; }
.logo8 { bottom: 30%; right: 45%; }
.logo9 { bottom: 80%; right: 45%; }
.logo10 { bottom: 10%; right: 30%; }

/* Animasi rotate */
@keyframes spin {
  from { transform: rotate(0deg);}
  to { transform: rotate(360deg);}
}

/* Pastikan container utama punya posisi relatif */
.container-fluid.grad {
  position: relative;
  overflow: hidden;
  z-index: 1;
}

/* Menimpa class .card agar menjadi transparan tanpa ubah HTML */
.card.animasi {
    background: rgba(255, 255, 255, 0.1) !important; /* Transparansi 10% */
    backdrop-filter: blur(15px) saturate(150%); /* Efek kaca buram */
    -webkit-backdrop-filter: blur(15px) saturate(150%);
    border: 1px solid rgba(255, 255, 255, 0.2) !important; /* Outline halus */
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.1);
    color: #B75301; /* Memastikan teks utama tetap berwarna cokelat khas */
}

/* Membuat field input juga sedikit transparan agar menyatu */
.card.animasi .form-control {
    background: rgba(255, 255, 255, 0.2) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    color: #444 !important;
}

/* Menyesuaikan icon di dalam input group */
.card.animasi .input-group-text {
    background: transparent !important;
    border: none !important;
    color: #B75301 !important;
    padding-right: 15px;
    /* Hilangkan bayangan apapun agar icon terlihat rata di dalam cekungan */
    box-shadow: none !important;
}

/* Memastikan placeholder input tetap terlihat */
.card.animasi .form-control::placeholder {
    color: rgba(0, 0, 0, 0.4);
}

/* Memberikan kontras pada teks deskripsi kecil */
.card.animasi .form-items p, 
.card.animasi .icheck-primary label {
    color: #444 !important;
    text-shadow: 0px 0px 1px rgba(255,255,255,0.5);
}

/* 1. Menghilangkan sekat antara input dan icon */
.card.animasi .input-group {
    background: rgba(255, 255, 255, 0.2) !important; /* Dibuat lebih tipis transparansinya */
    border-radius: 30px !important; /* Lebih bulat agar efek cekung terlihat halus */
    border: 1px solid rgba(255, 255, 255, 0.3) !important; 
    overflow: hidden;
    display: flex;
    align-items: center;
    /* Kunci Efek Cekung (Inset Shadow) */
    box-shadow: inset 2px 2px 5px rgba(255, 255, 255, 0.2) !important;
}

/* 2. Membuat input transparan dan tanpa border sendiri */
.card.animasi .form-control {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    height: 45px !important;
    color: #000 !important;
}

/* 3. Menghilangkan kotak pada logo email dan mata */
.card.animasi .input-group-text {
    background: transparent !important;
    border: none !important;
    color: #B75301 !important; /* Warna cokelat khas HaiMotion */
    padding-right: 15px;
}

.card.animasi .btn-block {
    background-color: #B75301 !important;
    border-radius: 30px !important;
    border: none !important;
    height: 50px !important;
    font-weight: bold;
    /* Efek cekung untuk tombol */
    box-shadow: inset 3px 3px 5px rgba(0, 0, 0, 0.3), 
                inset -2px -2px 5px rgba(255, 255, 255, 0.2) !important;
    transition: all 0.2s ease;
}

/* Efek Hover untuk Button Login */
.card.animasi .btn-block:hover {
    background-color: #a34a01 !important; /* Warna sedikit lebih gelap saat disorot */
    /* Menambah kedalaman efek cekung saat kursor mengarah ke tombol */
    box-shadow: inset 5px 5px 8px rgba(0, 0, 0, 0.4), 
                inset -3px -3px 8px rgba(255, 255, 255, 0.2) !important;
    transform: translateY(1px); /* Efek sedikit menekan ke bawah */
    transition: all 0.2s ease;
}

/* Efek Klik untuk Button Login */
.card.animasi .btn-block:active {
    /* Bayangan ke dalam lebih gelap saat tombol diklik */
    box-shadow: inset 8px 8px 12px rgba(0, 0, 0, 0.5) !important;
    transform: translateY(2px);

}

</style>

<body >
<div class="container-fluid grad d-flex justify-content-center align-items-center full-height">
  
  <img src="images/bnw.png" class="logo-bg logo1" alt="logo background">
  <img src="images/bnw.png" class="logo-bg logo2" alt="logo background">
  <img src="images/bnw.png" class="logo-bg logo3" alt="logo background">
  <img src="images/bnw.png" class="logo-bg logo4" alt="logo background">
  <img src="images/bnw.png" class="logo-bg logo5" alt="logo background">
  <img src="images/bnw.png" class="logo-bg logo6" alt="logo background">
  <img src="images/bnw.png" class="logo-bg logo7" alt="logo background">
  <img src="images/bnw.png" class="logo-bg logo8" alt="logo background">
  <img src="images/bnw.png" class="logo-bg logo9" alt="logo background">
  <img src="images/bnw.png" class="logo-bg logo10" alt="logo background">
  
  <div class="row w-100 justify-content-center align-items-center" style="z-index: 2; padding: 0 5%;">
    
    <div class="card animasi" style="border-radius: 40px; width: 90%; max-width: 420px; border: none;">
      <div class="card-body p-4">
        <div class="form-body without-side">
          <div class="iofrm-layout">
            <div class="img-holder text-center mb-3">
              <img src="assets/Logo1.png" alt="Logo" width="100px">
            </div>
            <div class="form-content text-center mb-4">
              <h4 class="font-weight-bold" style="color: #B75301;">Login to account</h4>
              <p class="small text-muted">Enter the official HaiMotion workstation. Secure access for authorized personnel only.</p>
            </div>
          </div>

          <form action="" id="login-form">
            <div class="form-group mb-3">
              <label class="small font-weight-bold" style="color: #000000">Email</label>
              <div class="input-group">
                <input type="email" class="form-control form-control-sm" name="email" required placeholder="Email">
                <div class="input-group-append">
                  <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                </div>
              </div>
            </div>

            <div class="form-group mb-3">
              <label class="small font-weight-bold" style="color: #000000">Password</label>
              <div class="input-group">
                <input type="password" class="form-control form-control-sm" name="password" id="password" required placeholder="Enter password">
                <div class="input-group-append">
                  <span class="input-group-text" id="togglePassword"><i class="fas fa-eye"></i></span>
                </div>
              </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
              <div class="icheck-primary">
                <input type="checkbox" id="remember">
                <label for="remember" style="color: #000;"><small>Remember Me</small></label>
              </div>
            </div>

            <button type="submit" class="btn btn-block text-white font-weight-bold">Login</button>
          </form>
        </div>
      </div>
    </div>
  </div> 
</div>
    

</body>
<!-- /.login-logo -->
      
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<!-- /.login-box -->
<script>
  $(document).ready(function(){
    $('#login-form').submit(function(e){
    e.preventDefault()
    start_load()
    if($(this).find('.alert-danger').length > 0 )
      $(this).find('.alert-danger').remove();
    $.ajax({
      url:'ajax.php?action=login',
      method:'POST',
      data:$(this).serialize(),
      error:err=>{
        console.log(err)
        end_load();

      },
      success:function(resp){
        if(resp == 1){
          location.href ='index.php?page=home';
        }else if(resp == 3){
          $('#login-form').prepend('<div class="alert alert-danger">Akun Anda telah di-nonaktifkan atau berstatus Resign. Silakan hubungi Administrator.</div>')
          end_load();
        }else{
          $('#login-form').prepend('<div class="alert alert-danger">Username or password is incorrect.</div>')
          end_load();
        }
      }
    })
  })
  })

  // Toggle Show/Hide Password
    $(document).on('click', '#togglePassword', function(){
      let input = $('#password');
      let icon = $(this).find('i');
      if(input.attr('type') === 'password'){
        input.attr('type', 'text');
        icon.removeClass('fa-eye').addClass('fa-eye-slash');
      } else {
        input.attr('type', 'password');
        icon.removeClass('fa-eye-slash').addClass('fa-eye');
      }
    });
</script>
<?php include 'footer.php' ?>

</body>
</html>
