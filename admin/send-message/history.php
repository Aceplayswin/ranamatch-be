<?php
header("Cache-Control: no cache");
session_cache_limiter("private_no_expire");

define("ACCESS_SECURITY", "true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if ($accessObj->validate() != "true" || $accessObj->isAllowed("access_message") == "false") {
    header('location:../logout-account');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../header_contents.php"; ?>
    <title><?php echo $APP_NAME; ?>: Broadcast History</title>
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
        padding: 30px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.4); 
        margin-bottom: 30px;
    }

    .cus-inp {
        width: 100%; height: 40px; background: var(--input-bg) !important;
        border: 1px solid var(--input-border) !important; border-radius: 12px !important;
        padding: 0 15px !important; color: var(--text-main) !important; font-size: 13px !important;
        transition: all 0.3s ease;
    }

    .action-btn {
        height: 36px; background: var(--accent-blue);
        color: #fff; border: none; border-radius: 10px; font-weight: 700;
        font-size: 12px; cursor: pointer; transition: all 0.3s;
        padding: 0 20px; display: flex; align-items: center; gap: 8px;
    }

    .badge { font-weight: 800; text-transform: uppercase; font-size: 9px; letter-spacing: 0.5px; padding: 6px 10px; border-radius: 6px; }
    .bg-success-subtle { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .bg-primary-subtle { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .bg-danger-subtle { background: rgba(244, 63, 94, 0.1); color: #f43f5e; }

    /* Modal Styling */
    .modal-content { background: var(--panel-bg); border: 1px solid var(--border-dim); border-radius: 24px; color: var(--text-main); }
    .modal-header { border-bottom: 1px solid var(--border-dim); padding: 20px 30px; }
    .modal-footer { border-top: 1px solid var(--border-dim); padding: 20px 30px; }
    .modal-body { padding: 30px; }
    .form-label { font-size: 11px; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 8px; }
    textarea.cus-inp { height: 100px !important; padding: 12px !important; resize: none; }
</style>
</head>
<body>
<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    <div class="admin-main-content hide-native-scrollbar">
        <div class="dash-header">
            <div>
                <span class="dash-breadcrumb">Notifications</span>
                <h1 class="dash-title">Broadcast History</h1>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="action-btn" style="background: var(--input-bg); border: 1px solid var(--border-dim); color: var(--text-main);">
                    <i class='bx bx-plus'></i> Schedule New
                </a>
                <button class="action-btn" onclick="FetchHistory()"><i class='bx bx-refresh'></i> Refresh</button>
            </div>
        </div>

        <div class="glass-card">
            <div class="row g-3 align-items-center mb-4">
                <div class="col-md-4">
                    <input type="text" id="hist_search" class="cus-inp" placeholder="Search title, message, or User ID..." onkeyup="FetchHistory()">
                </div>
                <div class="col-md-3">
                    <select id="hist_target" class="cus-inp" onchange="FetchHistory()">
                        <option value="any">All Targets</option>
                        <option value="all">Global Broadcasts</option>
                        <option value="specific">Specific Users</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="hist_status" class="cus-inp" onchange="FetchHistory()">
                        <option value="any">All Status</option>
                        <option value="active">Active Now</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0" style="font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-dim);">
                            <th class="py-3">Message Details</th>
                            <th class="py-3">Target</th>
                            <th class="py-3">Schedule Window</th>
                            <th class="py-3">Status</th>
                            <th class="py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="history_body">
                        <!-- Loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-black uppercase tracking-widest">Edit Broadcast</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_id">
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" id="edit_title" class="cus-inp">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Start Time</label>
                        <input type="datetime-local" id="edit_start" class="cus-inp">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">End Time</label>
                        <input type="datetime-local" id="edit_end" class="cus-inp">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Target Type</label>
                        <select id="edit_to" class="cus-inp">
                            <option value="all">All Users</option>
                            <option value="specific">Specific User</option>
                        </select>
                    </div>
                    <div class="col-12 mb-3" id="edit_uid_area">
                        <label class="form-label">Target User ID</label>
                        <input type="text" id="edit_uid" class="cus-inp">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Message Content</label>
                        <textarea id="edit_content" class="cus-inp"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn text-white" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="action-btn" onclick="SaveEdit()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(() => {
        FetchHistory();
        $('#edit_to').change(function() {
            $('#edit_uid_area').toggle(this.value === 'specific');
        });
    });

    function FetchHistory() {
        const q = $('#hist_search').val();
        const target = $('#hist_target').val();
        const status = $('#hist_status').val();

        $.ajax({
            url: 'get-history.php',
            type: 'GET',
            data: { q, target, status },
            success: function(resp) {
                const history = JSON.parse(resp);
                let html = '';
                const now = new Date();

                history.forEach(h => {
                    const start = new Date(h.start_time);
                    const end = new Date(h.end_time);
                    let badge = '';
                    
                    if(now >= start && now <= end) badge = '<span class="badge bg-success-subtle">Active</span>';
                    else if(now < start) badge = '<span class="badge bg-primary-subtle">Upcoming</span>';
                    else badge = '<span class="badge bg-danger-subtle">Expired</span>';

                    html += `
                        <tr class="align-middle">
                            <td class="py-4">
                                <div class="fw-bold mb-1">${h.title}</div>
                                <div class="text-dim small line-clamp-1">${h.message}</div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary">${h.target_type}</span>
                                ${h.target_uid ? `<div class="mt-1 small text-dim">${h.target_uid}</div>` : ''}
                            </td>
                            <td>
                                <div class="small">From: ${h.start_time}</div>
                                <div class="small">To: ${h.end_time}</div>
                            </td>
                            <td>${badge}</td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick='OpenEditModal(${JSON.stringify(h)})'><i class='bx bx-edit-alt'></i></button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="DeleteNotice(${h.id})"><i class='bx bx-trash'></i></button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                $('#history_body').html(html || '<tr><td colspan="5" class="text-center py-5">No messages found.</td></tr>');
            }
        });
    }

    function OpenEditModal(h) {
        $('#edit_id').val(h.id);
        $('#edit_title').val(h.title);
        $('#edit_content').val(h.message);
        $('#edit_to').val(h.target_type).trigger('change');
        $('#edit_uid').val(h.target_uid || '');
        $('#edit_start').val(h.start_time.replace(' ', 'T'));
        $('#edit_end').val(h.end_time.replace(' ', 'T'));
        new bootstrap.Modal('#editModal').show();
    }

    function SaveEdit() {
        const data = {
            action: 'edit',
            id: $('#edit_id').val(),
            m_heading: $('#edit_title').val(),
            m_content: $('#edit_content').val(),
            m_to: $('#edit_to').val(),
            target_id: $('#edit_uid').val(),
            start_time: $('#edit_start').val(),
            end_time: $('#edit_end').val()
        };

        $.post('manage-broadcast.php', data, function(resp) {
            if(resp === 'success') {
                Swal.fire('Updated!', 'Changes saved successfully.', 'success');
                bootstrap.Modal.getInstance('#editModal').hide();
                FetchHistory();
            } else {
                Swal.fire('Error', resp, 'error');
            }
        });
    }

    function DeleteNotice(id) {
        Swal.fire({
            title: 'Delete Message?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f43f5e',
            confirmButtonText: 'Yes, delete'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('manage-broadcast.php', { action: 'delete', id: id }, function(resp) {
                    if(resp === 'success') {
                        Swal.fire('Deleted!', '', 'success');
                        FetchHistory();
                    }
                });
            }
        });
    }
</script>
</body>
</html>
