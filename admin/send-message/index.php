<?php
header("Cache-Control: no cache");
session_cache_limiter("private_no_expire");

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() == "true") {
    if ($accessObj->isAllowed("access_message") == "false") {
        echo "You're not allowed to view this page. Please grant access!";
        return;
    }
}
else {
    header('location:../logout-account');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Send Notification</title>
    <link href='../style.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
<style><?php include "../components/theme-variables.php"; ?></style>
<style>
    body {
        font-family: var(--font-body) !important;
        background-color: var(--page-bg) !important;
        min-height: 100vh; color: var(--text-main); margin: 0; padding: 0; overflow: hidden;
    }

    .admin-main-content {
        padding: 24px;
        margin-left: 260px;
        min-height: 100vh;
        background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.05), transparent);
        transition: margin-left 0.3s ease;
    }
    @media (max-width: 900px) { .admin-main-content { margin-left: 0; padding: 15px; } }

    .dash-header {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 30px; border-bottom: 1px solid var(--border-dim);
        padding-bottom: 15px;
    }
    .dash-title h1 { font-size: 24px; font-weight: 800; color: var(--text-main); margin: 0; }
    .dash-breadcrumb { font-size: 11px; font-weight: 700; color: var(--accent-blue); text-transform: uppercase; letter-spacing: 1px; }

    .glass-card {
        background: var(--panel-bg);
        border: 1px solid var(--border-dim); border-radius: 24px;
        padding: 40px; width: 100%; max-width: 580px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.4); margin: 40px auto;
        position: relative; overflow: visible;
    }
    .glass-card::before {
        content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        background: linear-gradient(90deg, var(--accent-blue), var(--accent-emerald));
    }

    .form-group { margin-bottom: 20px; position: relative; }
    .form-label {
        display: block; font-size: 11px; font-weight: 800; color: var(--text-dim);
        text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;
    }
    .cus-inp {
        width: 100%; height: 48px; background: var(--input-bg) !important;
        border: 1px solid var(--input-border) !important; border-radius: 12px !important;
        padding: 0 15px !important; color: var(--text-main) !important; font-size: 14px !important;
        transition: all 0.3s ease;
    }
    .cus-inp:focus {
        border-color: var(--accent-blue) !important; background: var(--table-row-hover) !important;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
    }
    
    select.cus-inp option {
        background-color: var(--panel-bg) !important;
        color: var(--text-main) !important;
    }

    textarea.cus-inp { height: 120px !important; padding: 12px !important; resize: none; }

    .action-btn {
        width: 100%; height: 50px; background: var(--accent-blue);
        color: #fff; border: none; border-radius: 14px; font-weight: 800;
        font-size: 15px; cursor: pointer; transition: all 0.3s;
        display: flex; align-items: center; justify-content: center; gap: 10px;
    }
    .action-btn:hover {
        transform: translateY(-2px); background: #2563eb;
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
    }
    
    .status-area {
        margin-top: 15px; font-size: 11px; font-weight: 700; text-align: center;
        padding: 10px; border-radius: 10px; display: none;
    }

    /* Suggestions List Styles */
    .hint-list {
        position: absolute; top: 100%; left: 0; right: 0; z-index: 1000;
        background: var(--panel-bg); border: 1px solid var(--border-dim);
        border-radius: 12px; margin-top: 5px; max-height: 200px;
        overflow-y: auto; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    .hint-item {
        padding: 10px 15px; cursor: pointer; transition: all 0.2s;
        border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .hint-item:last-child { border-bottom: none; }
    .hint-item:hover { background: rgba(59, 130, 246, 0.1); }
    .hint-name { font-size: 13px; font-weight: 700; color: var(--text-main); }
    .hint-sub { font-size: 10px; color: var(--text-dim); }
</style>
</head>
<body>
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div>
                <span class="dash-breadcrumb">Admin Panel</span>
                <h1 class="dash-title">Send Message</h1>
            </div>
            <button class="btn-action" style="height: 36px; border-radius: 8px; background: var(--input-bg); border: 1px solid var(--border-dim); color: var(--text-main); font-size: 12px; font-weight: 700; padding: 0 15px;" onclick="window.location.reload()"><i class='bx bx-refresh'></i> Refresh</button>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="glass-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="m-0 font-black uppercase tracking-widest text-accent-blue">Schedule New Broadcast</h5>
                        <a href="history.php" class="btn btn-sm btn-outline-primary" style="font-size: 11px; font-weight: 800; border-radius: 8px;">
                            <i class='bx bx-history'></i> VIEW HISTORY
                        </a>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Title</label>
                        <input type="text" id="m_heading" class="cus-inp" placeholder="Enter title...">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Start Time</label>
                                <input type="datetime-local" id="start_time" class="cus-inp">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">End Time</label>
                                <input type="datetime-local" id="end_time" class="cus-inp">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Send To</label>
                        <select id="m_to" class="cus-inp">
                            <option value="all">All Users</option>
                            <option value="specific">Specific User</option>
                        </select>
                    </div>

                    <div id="specific_user_area" class="form-group" style="display: none;">
                        <label class="form-label">Search User (Hint enabled)</label>
                        <input type="text" id="target_id" class="cus-inp" placeholder="Start typing User ID / Name..." onkeyup="fetchHints(this.value)" autocomplete="off">
                        <div id="hint_box" class="hint-list"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Message</label>
                        <textarea id="m_content" class="cus-inp" placeholder="Type message body here..."></textarea>
                    </div>

                    <button class="action-btn" onclick="BroadcastMessage()">
                        <i class='bx bxs-paper-plane'></i> Send Notice
                    </button>
                    
                    <div id="status_portal" class="status-area"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    window.onload = () => {
        const now = new Date();
        const tomorrow = new Date(now.getTime() + (24 * 60 * 60 * 1000));
        document.getElementById('start_time').value = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        document.getElementById('end_time').value = new Date(tomorrow.getTime() - tomorrow.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    };

    document.getElementById('m_to').addEventListener('change', function() {
        document.getElementById('specific_user_area').style.display = (this.value === 'specific') ? 'block' : 'none';
        if(this.value !== 'specific') $('#hint_box').hide();
    });

    function fetchHints(val) {
        if(val.length < 2) {
            $('#hint_box').hide();
            return;
        }
        $.ajax({
            url: 'search-users.php',
            type: 'GET',
            data: { q: val },
            success: function(resp) {
                const users = JSON.parse(resp);
                let html = '';
                if(users.length > 0) {
                    users.forEach(u => {
                        html += `<div class="hint-item" onclick="selectUser('${u.id}', '${u.username}')">
                                    <div class="hint-name">${u.username}</div>
                                    <div class="hint-sub">ID: ${u.id} | MOB: ${u.mobile}</div>
                                 </div>`;
                    });
                    $('#hint_box').html(html).show();
                } else {
                    $('#hint_box').hide();
                }
            }
        });
    }

    function selectUser(id, name) {
        $('#target_id').val(id);
        $('#hint_box').hide();
        Swal.fire({ title: 'User Verified', text: 'Selected: ' + name, icon: 'success', timer: 1000, showConfirmButton: false });
    }

    function BroadcastMessage() {
        const head = $('#m_heading').val();
        const start = $('#start_time').val();
        const end = $('#end_time').val();
        const to = $('#m_to').val();
        const uid = $('#target_id').val();
        const msg = $('#m_content').val();

        if(!head || !msg || !start || !end) return Swal.fire('Error', 'Please fill all fields', 'error');

        $.post('manage-notice.php', {
            m_heading: head, start_time: start, end_time: end, m_to: to, target_id: uid, m_content: msg
        }, function(resp) {
            if(resp.includes('success')) {
                Swal.fire('Success', 'Broadcast Scheduled!', 'success');
                $('#m_heading, #m_content, #target_id').val('');
            } else {
                Swal.fire('Error', resp, 'error');
            }
        });
    }
</script>
</body>
</html>