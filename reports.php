<?php
include 'db_connect.php';
require_once __DIR__ . '/helpers/status.helper.php';
?>

<div class="container-fluid mb-3">
    
    <div class="row align-items-center mb-3">
        <div class="col-6">
            <h3 class="m-0 text-dark font-weight-bold">Project Progress</h3>
        </div>
        <div class="col-6 text-right">
            <button class="btn text-white" id="print">
                <i class="fa fa-print mr-1"></i> Print
            </button>
        </div>
    </div>

    <div class="col-lg-12 mb-3 px-0">
        <div class="bg-white shadow-sm" style="border-radius: 20px; border: 1px solid #eee; overflow: hidden;">
            <div class="table-responsive" id="printable">
                <table class="table table-hover m-0" style="width: 100%;">
                    <colgroup>
                        <col width="5%">
                        <col width="30%">
                        <col width="15%">
                        <col width="15%">
                        <col width="10%">
                        <col width="25%">
                    </colgroup>
                    
                    <thead>
                        <tr style="background-color: #E3E3E3;">
                            <th class="text-left py-3 border-0 pl-4" style="font-weight: 800; color: #333; text-transform: uppercase; font-size: 13px;">No</th>
                            <th class="text-left py-3 border-0" style="font-weight: 800; color: #333; text-transform: uppercase; font-size: 13px;">Project</th>
                            <th class="text-center py-3 border-0" style="font-weight: 800; color: #333; text-transform: uppercase; font-size: 13px;">Due Date</th>
                            <th class="text-center py-3 border-0" style="font-weight: 800; color: #333; text-transform: uppercase; font-size: 13px;">Status</th>
                            <th class="text-center py-3 border-0" style="font-weight: 800; color: #333; text-transform: uppercase; font-size: 13px;">Task</th>
                            <th class="text-left py-3 border-0 pr-4" style="font-weight: 800; color: #333; text-transform: uppercase; font-size: 13px;">Progress</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        $i = 1;
                        $stat = array("Pending","Started","On-Progress","On-Hold","Over Due","Done");
                        
                        $where = "";
                        if($_SESSION['login_type'] == 2){
                            $where = " where manager_id = '{$_SESSION['login_id']}' ";
                        }elseif($_SESSION['login_type'] == 3 || $_SESSION['login_type'] == 4){
                            // employees and clients see only projects they belong to
                            $where = " where concat('[',REPLACE(user_ids,',','],['),']') LIKE '%[{$_SESSION['login_id']}]%' ";
                        }
                        
                        $qry = $conn->query("SELECT * FROM project_list $where order by name asc");
                        while($row= $qry->fetch_assoc()):
                            $tprog = $conn->query("SELECT * FROM task_list where project_id = {$row['id']}")->num_rows;
                            $cprog = $conn->query("SELECT * FROM task_list where project_id = {$row['id']} and status = 5")->num_rows; 
                            $prog = $tprog > 0 ? ($cprog/$tprog) * 100 : 0;
                            $prog = $prog > 0 ?  number_format($prog,2) : $prog;
                            
                            // $prod = $conn->query("SELECT * FROM user_productivity where project_id = {$row['id']}")->num_rows;
                            // if($row['status'] == 0 && strtotime(date('Y-m-d')) >= strtotime($row['start_date'])):
                            //     if($prod > 0 || $cprog > 0)
                            //         $row['status'] = 2;
                            //     else
                            //         $row['status'] = 1;
                            // elseif($row['status'] > 0 && strtotime(date('Y-m-d')) > strtotime($row['end_date'])):
                            //     $row['status'] = 4;
                            // endif;
                        ?>
                        
                        <tr style="border-bottom: 1px solid #f0f0f0;">
                            <td class="text-left pl-4 align-middle"><b><?php echo $i++ ?></b></td>
                            
                            <td class="text-left align-middle">
                                <div style="font-weight: 700; color: #333; font-size: 16px;">
                                    <?php echo ucwords($row['name']) ?>
                                </div>
                            </td>
                            
                            <td class="text-center align-middle">
                                <span class="sequential-date" 
                                    data-date="<?php echo date("Y-m-d", strtotime($row['end_date'])) ?>"
                                    style="font-weight: 650; font-size: 16px; color: #333; font-variant-numeric: tabular-nums; display: inline-block; width: 140px;">
                                    0000-00-00
                                </span>
                            </td>
                            
                            <td class="text-center align-middle">
                                <?php
                                  $status_label = $stat[$row['status']];
                                  $badge_class = 'secondary';
                                  
                                  if($status_label =='Pending') $custom_color = '#6c757d';
                                  elseif($status_label =='Started') $custom_color = '#1a237e';
                                  elseif($status_label =='On-Progress') $custom_color = '#4fc3f7';
                                  elseif($status_label =='On-Hold') $custom_color = '#ffc107';
                                  elseif($status_label =='Over Due') $custom_color = '#dc3545';
                                  elseif($status_label =='Done') $custom_color = '#28a745';
                                  
                                  echo "<span class='badge' style='background-color: {$custom_color}; color: white; width: 100px; display: inline-block; padding: 8px 0; border-radius: 10px; font-size: 12px; text-align: center;'>{$status_label}</span>";
                                ?>
                            </td>
                            
                            <td class="text-center align-middle">
                                <span style="font-size: 12px; border-radius: 8px; font-weight: 600;">
                                    <span class="rolling-num" data-val="<?php echo $cprog ?>">0</span>
                                    
                                    <span class="mx-1">/</span>
                                    
                                    <span class="rolling-num" data-val="<?php echo $tprog ?>">0</span>
                                </span>
                            </td>
                            
                            <td class="project_progress pr-4 align-middle progress-container">
                                <div class="progress progress-custom" style="height: 10px; margin-bottom: 5px; background-color: #e9ecef; border-radius: 10px;">
                                    <div class="progress-bar animate-bar" role="progressbar" 
                                        style="width: 0%; background: linear-gradient(90deg, #CD874D 10%, #B75301 80%); border-radius: 10px; transition: width 1.5s cubic-bezier(0.25, 0.1, 0.25, 1);" 
                                        aria-valuenow="<?php echo $prog ?>" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                
                                <small style="display: block; color: #555; font-size: 12px;">
                                    <b class="animate-percent" data-target="<?php echo $prog ?>" style="color: #B75301;">0.00%</b> Complete
                                </small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    #print {
        background-color: #B75301 !important;
        color: white !important;
        border-radius: 10px !important; 
        padding: 10px 20px !important;
        font-weight: 700 !important; 
        font-size: 13px !important;
        letter-spacing: 0.5px;
        text-transform: uppercase; 
        border: none !important;
        box-shadow: 0 4px 14px rgba(183, 83, 1, 0.25) !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    /* Efek Hover (Saat mouse di atas tombol) */
    #print:hover {
        background-color: #964401 !important; 
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(183, 83, 1, 0.35) !important; 
        color: white !important;
    }

    /* Efek Klik (Saat tombol ditekan) */
    #print:active {
        transform: translateY(0); /* Kembali ke posisi semula */
        box-shadow: 0 4px 14px rgba(183, 83, 1, 0.25) !important;
    }

    #print i {
        font-size: 12px !important;
        margin-right: 5px !important;
    }
    @media (max-width: 490px) {
        #printable table,
        #printable thead,
        #printable tbody,
        #printable th,
        #printable td,
        #printable tr {
            display: block !important;
            width: 100% !important;
        }

        /* Hide desktop header */
        #printable thead {
            display: none !important;
        }

        /* Each row becomes mobile card like project_list */
        #printable tbody tr {
            display: grid !important;
            grid-template-columns: 40px 1fr 95px !important;
            grid-template-rows: auto auto auto !important;
            gap: 8px 12px !important;
            padding: 15px 12px !important;
            border-bottom: 1px solid #f0f0f0 !important;
            align-items: center !important;
            background: #fff;
        }

        /* Remove td default spacing issues */
        #printable tbody td {
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
            width: auto !important;
        }

        /* No */
        #printable tbody td:nth-child(1) {
            grid-column: 1;
            grid-row: 1;
            text-align: center !important;
        }

        /* Project */
        #printable tbody td:nth-child(2) {
            grid-column: 2;
            grid-row: 1;
        }

        /* Due date */
        #printable tbody td:nth-child(3) {
            grid-column: 2;
            grid-row: 2;
            font-size: 12px !important;
            color: #666 !important;
        }

        /* Status */
        #printable tbody td:nth-child(4) {
            grid-column: 3;
            grid-row: 1;
            text-align: center !important;
        }

        #printable tbody td:nth-child(4) .badge {
            width: 90px !important;
            min-width: 90px !important;
            font-size: 0.7rem !important;
        }

        /* Task */
        #printable tbody td:nth-child(5) {
            grid-column: 3;
            grid-row: 2;
            font-size: 12px !important;
            text-align: center !important;
        }

        /* Progress */
        #printable tbody td:nth-child(6) {
            grid-column: 1 / span 3;
            grid-row: 3;
            width: 100% !important;
        }

        #printable tbody td:nth-child(6) .progress {
            width: 100% !important;
        }

        /* Prevent date width overflow */
        .sequential-date {
            width: auto !important;
            font-size: 12px !important;
        }
    }
</style>

<script>
    document.addEventListener("DOMContentLoaded", function() {

    // === 1. FUNGSI ANIMASI ANGKA PERSEN (Desimal) ===
    const runPercentAnimation = (el) => {
        const target = parseFloat(el.getAttribute('data-target')); 
        const duration = 1500; // Durasi 1.5 detik (sinkron dengan bar)
        
        if (target === 0 || isNaN(target)) {
            el.innerText = "0.00%";
            return;
        }

        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            
            // Efek easing (melambat di akhir)
            const ease = 1 - Math.pow(1 - progress, 3);
            
            const current = ease * target;
            el.innerText = current.toFixed(2) + "%";

            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                el.innerText = target.toFixed(2) + "%"; 
            }
        };
        window.requestAnimationFrame(step);
    };

    // === 2. OBSERVER UNTUK DETEKSI SCROLL ===
    const progressObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const container = entry.target;
                
                // A. Jalankan Animasi Bar (CSS Transition)
                const bar = container.querySelector('.animate-bar');
                // Ambil nilai asli dari aria-valuenow dan set ke width style
                bar.style.width = bar.getAttribute('aria-valuenow') + '%';
                
                // B. Jalankan Animasi Angka (JS)
                const percentText = container.querySelector('.animate-percent');
                runPercentAnimation(percentText);
                
                // Stop agar animasi hanya jalan sekali per load
                progressObserver.unobserve(container);
            }
        });
    }, { threshold: 0.5 });

    // Pasang sensor ke semua baris
    document.querySelectorAll('.progress-container').forEach(el => {
        progressObserver.observe(el);
    });
});

    // ini untuk motion effect task sequential rolling //
    document.addEventListener("DOMContentLoaded", function() {
    
    // Fungsi animasi angka (Counting Up)
    const runRollingNumber = (el) => {
        const target = parseInt(el.getAttribute('data-val'));
        const duration = 1500; // Durasi animasi 1.5 detik
        
        // Jika target 0, langsung set dan keluar
        if (target === 0) {
            el.innerText = "0";
            return;
        }

        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            
            // Efek easing (keluar pelan)
            const ease = 1 - Math.pow(1 - progress, 3);
            
            const current = Math.floor(ease * target);
            el.innerText = current;

            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                el.innerText = target; // Pastikan angka akhir akurat
            }
        };
        window.requestAnimationFrame(step);
    };

    // Observer untuk mendeteksi scroll
    const taskObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            // Jika elemen terlihat di layar
            if (entry.isIntersecting) {
                const el = entry.target;
                
                // Jalankan animasi
                runRollingNumber(el);
                
                // Stop memantau elemen ini (agar animasi tidak berulang-ulang saat scroll naik-turun)
                taskObserver.unobserve(el);
            }
        });
    }, { threshold: 0.5 }); // Animasi jalan saat 50% elemen terlihat

    // Pasang observer ke semua angka task
    document.querySelectorAll('.rolling-num').forEach(el => {
        taskObserver.observe(el);
    });
});

    // ini untuk motion effect due date sequential rolling //
    document.addEventListener("DOMContentLoaded", function() {
    
    // Fungsi pembantu untuk animasi angka (Promise-based)
    function animateValue(obj, start, end, duration, updateCallback) {
        return new Promise(resolve => {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                
                // Efek easing (melambat di akhir)
                const easeProgress = 1 - Math.pow(1 - progress, 3); 
                
                const currentVal = Math.floor(progress * (end - start) + start);
                
                // Update tampilan melalui callback
                updateCallback(currentVal);

                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    updateCallback(end); // Pastikan angka akhir tepat
                    resolve(); // Selesai, lanjut ke animasi berikutnya
                }
            };
            window.requestAnimationFrame(step);
        });
    }

    const dateElements = document.querySelectorAll('.sequential-date');

    // Gunakan IntersectionObserver agar animasi baru jalan saat discroll ke layar (Opsional tapi keren)
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const fullDate = el.getAttribute('data-date'); // format "2026-11-06"
                const parts = fullDate.split('-');
                
                const targetYear = parseInt(parts[0]);
                const targetMonth = parseInt(parts[1]);
                const targetDay = parseInt(parts[2]);

                // Mulai Sequence Animasi
                runSequence(el, targetYear, targetMonth, targetDay);
                
                // Hentikan observasi setelah animasi jalan sekali
                observer.unobserve(el); 
            }
        });
    }, { threshold: 0.5 });

    dateElements.forEach(el => observer.observe(el));

    // Fungsi Utama Sequence
    async function runSequence(el, year, month, day) {
        // Format Helper: Menambah '0' di depan jika angka < 10
        const pad = (num) => String(num).padStart(2, '0');

        // TAHAP 1: Gulirkan TAHUN (0000 -> 2026)
        // Bulan & Tanggal diam di 00
        await animateValue(el, 1900, year, 800, (val) => {
            el.innerText = `${val}-00-00`; 
        });

        // TAHAP 2: Gulirkan BULAN (00 -> 11)
        // Tahun sudah diam (locked), Tanggal masih 00
        await animateValue(el, 1, month, 500, (val) => {
            el.innerText = `${year}-${pad(val)}-00`;
        });

        // TAHAP 3: Gulirkan TANGGAL (00 -> 06)
        // Tahun & Bulan sudah diam (locked)
        await animateValue(el, 1, day, 500, (val) => {
            el.innerText = `${year}-${pad(month)}-${pad(val)}`;
        });
    }
});

    $('#print').click(function(){
        var content = $('#printable').clone();
        
        // Style khusus Print
        content.find('.badge').css({'border': '1px solid #000', 'color': '#000', 'background': 'none'});
        content.find('.progress').css({'border': '1px solid #000', 'background': 'none'});
        content.find('.progress-bar').css({'background-color': '#000'}); 
        
        var printWindow = window.open('', '', 'width=900,height=600');
        var headContent = `
            <html>
                <head>
                    <title>Project Progress Report</title>
                    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #333; padding: 8px; text-align: left; font-size: 12px; }
                        .text-center { text-align: center; }
                        h4 { margin-bottom: 20px; text-align: center; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <h4>Project Progress Report (<?php echo date("F d, Y") ?>)</h4>
        `;
        
        printWindow.document.write(headContent + content.html() + '</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function(){
            printWindow.print();
            printWindow.close();
        }, 1000);
    })
</script>