<?php
define("ACCESS_SECURITY","true");
include '../../security/config.php';
include '../../security/constants.php';
include '../access_validate.php';

session_start();
$accessObj = new AccessValidate();
if($accessObj->validate()!="true"){
    header('location:../logout-account');
    exit;
}

$ticket_id = $_GET['id'] ?? '';
if(empty($ticket_id)) {
    header('location:index.php');
    exit;
}

// Fetch Main Ticket
$query = "SELECT * FROM tbl_support_tickets WHERE ticket_id = '$ticket_id'";
$ticket_res = mysqli_query($conn, $query);
$ticket = mysqli_fetch_assoc($ticket_res);

if(!$ticket) {
    header('location:index.php?err=Ticket not found');
    exit;
}

// Fetch Attachments
$attach_res = mysqli_query($conn, "SELECT * FROM tbl_ticket_attachments WHERE ticket_id = '$ticket_id'");

// Fetch Replies
$replies_res = mysqli_query($conn, "SELECT * FROM tbl_ticket_replies WHERE ticket_id = '$ticket_id' ORDER BY created_at ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>View Ticket #<?php echo $ticket_id; ?> | Admin Panel</title>
    <?php include "../header_contents.php"; ?>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bubble-user: rgba(255, 255, 255, 0.03);
            --bubble-admin: linear-gradient(135deg, rgba(6, 182, 212, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%);
            --accent-glow: 0 5px 20px rgba(6, 182, 212, 0.1);
            --brand-gradient: linear-gradient(135deg, #FFD700 0%, #FFA000 100%);
            --glass-bg: rgba(255, 255, 255, 0.015);
            --glass-border: rgba(255, 255, 255, 0.04);
        }

        * {
            font-family: 'DM Sans', sans-serif !important;
        }

        .admin-main-content {
            background-color: var(--page-bg) !important;
            background-image: radial-gradient(var(--border-dim) 1px, transparent 1px) !important;
            background-size: 26px 26px !important;
        }

        .ticket-detail-grid { 
            display: grid; 
            grid-template-columns: 1fr 280px; 
            gap: 15px; 
            align-items: start;
        }
        
        @media (max-width: 1100px) {
            .ticket-detail-grid { grid-template-columns: 1fr; }
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .chat-container { 
            display: flex; 
            flex-direction: column; 
            gap: 15px; 
            padding: 15px; 
        }

        .message-bubble { 
            max-width: 80%; 
            padding: 12px 16px; 
            border-radius: 14px; 
            position: relative;
            backdrop-filter: blur(8px);
            transition: all 0.3s ease;
            animation: slideInUp 0.4s cubic-bezier(0.23, 1, 0.32, 1) backwards;
        }
        
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-bubble:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.3); }

        .message-bubble.user { 
            align-self: flex-start; 
            background: var(--bubble-user); 
            border: 1px solid rgba(255,255,255,0.04);
            border-bottom-left-radius: 3px;
        }

        .message-bubble.admin { 
            align-self: flex-end; 
            background: var(--bubble-admin); 
            border: 1px solid rgba(6, 182, 212, 0.15);
            border-bottom-right-radius: 3px;
            box-shadow: var(--accent-glow);
        }
        
        .bubble-meta { 
            font-size: 9px; 
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--brand); 
            margin-bottom: 8px; 
            display: flex; 
            justify-content: space-between; 
            opacity: 0.5;
        }
        
        .bubble-content { 
            font-size: 12px; 
            line-height: 1.5; 
            color: #cbd5e1;
            font-weight: 500;
        }

        .subject-highlight {
            background: linear-gradient(90deg, rgba(6, 182, 212, 0.05) 0%, transparent 100%);
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            font-weight: 900;
            color: #fff;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .reply-box { 
            background: rgba(255,255,255,0.01);
            backdrop-filter: blur(20px);
            padding: 20px; 
            border-radius: 16px; 
            border: 1px solid rgba(255,255,255,0.03);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            margin-top: 10px;
        }

        .btn-brand-save {
            background: var(--brand-gradient) !important;
            color: #000 !important;
            font-weight: 900 !important;
            padding: 10px 24px !important;
            border-radius: 10px !important;
            border: none !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            font-size: 11px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 5px 15px rgba(255, 160, 0, 0.2) !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
        }
        .btn-brand-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 160, 0, 0.35) !important;
        }
        
        .info-card {
            background: rgba(255,255,255,0.01);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.03);
            padding: 20px;
        }

        .info-item { margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.02); }
        .info-item:last-child { margin-bottom: 0; border-bottom: none; padding-bottom: 0; }
        
        .info-label { font-size: 8px; font-weight: 900; text-transform: uppercase; color: var(--brand); letter-spacing: 2px; display: block; margin-bottom: 8px; opacity: 0.4; }
        .info-value { font-size: 13px; color: #fff; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        
        .attachment-card { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 12px; 
            background: rgba(255,255,255,0.01); 
            border-radius: 12px; 
            margin-bottom: 8px;
            text-decoration: none; 
            border: 1px solid rgba(255,255,255,0.02); 
            transition: all 0.3s ease;
        }
        .attachment-card:hover { 
            border-color: rgba(6, 182, 212, 0.3); 
            transform: translateX(5px);
            background: rgba(6, 182, 212, 0.03); 
        }
        .attachment-card i { font-size: 20px; color: var(--brand); }
        .attachment-info { flex: 1; min-width: 0; }
        .attachment-name { display: block; font-size: 10px; font-weight: 800; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .status-select {
            background: rgba(15, 23, 42, 0.6) !important;
            border: 1px solid rgba(255,255,255,0.05) !important;
            color: #fff !important;
            border-radius: 8px !important;
            padding: 6px 12px !important;
            font-weight: 900 !important;
            font-size: 10px !important;
            text-transform: uppercase;
            outline: none !important;
            cursor: pointer !important;
        }

        .btn-back-modern {
            padding: 8px 16px;
            font-size: 9px;
            font-weight: 900;
            border-radius: 40px;
            background: rgba(255,255,255,0.01);
            border: 1px solid rgba(255,255,255,0.04);
            color: #fff;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-back-modern:hover {
            background: var(--brand);
            color: #000;
            border-color: transparent;
        }

        .brand-input-reply {
            width: 100%;
            background: rgba(0,0,0,0.2) !important;
            border: 1px solid rgba(255,255,255,0.04) !important;
            border-radius: 12px !important;
            color: #fff !important;
            padding: 15px !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
            resize: none !important;
        }
        .brand-input-reply:focus {
            border-color: var(--brand) !important;
            background: rgba(0,0,0,0.4) !important;
            box-shadow: 0 0 30px rgba(255, 160, 0, 0.1) !important;
        }

        /* Image Modal */
        .image-modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(10px);
            z-index: 9999;
            display: none;
            align-items: center; justify-content: center;
            padding: 20px;
            cursor: zoom-out;
            animation: fadeIn 0.3s ease;
        }
        .modal-content-img {
            max-width: 90%;
            max-height: 85vh;
            border-radius: 12px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        .image-modal-overlay.active { display: flex; }
        .image-modal-overlay.active .modal-content-img { transform: scale(1); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>

<div class="admin-layout-wrapper">
    <?php include "../components/side-menu.php"; ?>
    
    <div class="admin-main-content">
        <div class="dash-header">
            <div class="header-title-area">
                <a href="index.php" class="btn-back-modern">
                    <i class='bx bx-left-arrow-alt' style="font-size: 18px;"></i> Back to Tickets
                </a>
                <div class="ms-3">
                    <h2 style="font-weight: 900; letter-spacing: -1px; font-size: 24px; color: #fff;">Ticket <span style="color: var(--brand);">Detail</span></h2>
                    <p style="opacity: 0.6; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Conversation with <strong style="color: #fff;"><?php echo $ticket['name']; ?></strong></p>
                </div>
            </div>
            <div class="header-actions">
                <div class="d-flex align-items-center gap-3">
                    <span style="font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.6;">Status:</span>
                    <form action="manager-tickets.php" method="POST" class="d-flex gap-2">
                        <input type="hidden" name="action_type" value="update_status">
                        <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                        <select name="status" class="status-select" onchange="this.form.submit()">
                            <option value="open" <?php if($ticket['status'] == 'open') echo 'selected'; ?>>Pending</option>
                            <option value="in_progress" <?php if($ticket['status'] == 'in_progress') echo 'selected'; ?>>In Progress</option>
                            <option value="closed" <?php if($ticket['status'] == 'closed') echo 'selected'; ?>>Closed</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <div class="ticket-detail-grid mt-4">
            <!-- Left Side: Conversation -->
            <div class="d-flex flex-column gap-4">
                <div class="glass-card">
                    <div class="subject-highlight">
                        <div style="width: 40px; h-40px; border-radius: 12px; background: rgba(6, 182, 212, 0.2); display: flex; align-items: center; justify-content: center;">
                            <i class='bx bx-message-square-detail' style="color: #06b6d4; font-size: 20px;"></i>
                        </div>
                        <div>
                            <span style="display: block; font-size: 9px; opacity: 0.5; text-transform: uppercase; letter-spacing: 2px;">Subject / Category</span>
                            <?php echo htmlspecialchars($ticket['subject']); ?>
                        </div>
                    </div>
                    
                    <div class="chat-container">
                        <!-- Initial Message (User) -->
                        <div class="message-bubble user">
                            <div class="bubble-meta">
                                <span><i class='bx bx-user-circle'></i> <?php echo $ticket['name']; ?></span>
                                <span><?php echo date('d M, h:i A', strtotime($ticket['created_at'])); ?></span>
                            </div>
                            <div class="bubble-content">
                                <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
                            </div>
                        </div>

                        <!-- Replies -->
                        <?php while($reply = mysqli_fetch_assoc($replies_res)): ?>
                            <div class="message-bubble <?php echo $reply['sender_type']; ?>">
                                <div class="bubble-meta">
                                    <span>
                                        <?php if($reply['sender_type'] == 'admin'): ?>
                                            <i class='bx bxs-check-shield' style="color: #06b6d4;"></i> Support Team
                                        <?php else: ?>
                                            <i class='bx bx-user-circle'></i> <?php echo $ticket['name']; ?>
                                        <?php endif; ?>
                                    </span>
                                    <span><?php echo date('d M, h:i A', strtotime($reply['created_at'])); ?></span>
                                </div>
                                <div class="bubble-content">
                                    <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <?php if($ticket['status'] != 'closed'): ?>
                <div class="reply-box">
                    <h4 style="font-weight: 900; margin-bottom: 25px; color: #fff; text-transform: uppercase; letter-spacing: 2px; font-size: 14px; display: flex; align-items: center; gap: 12px;">
                        <i class='bx bx-reply' style="font-size: 24px; color: var(--brand);"></i> Post a Response
                    </h4>
                    <form action="manager-tickets.php" method="POST">
                        <input type="hidden" name="action_type" value="send_reply">
                        <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
                        <input type="hidden" name="user_email" value="<?php echo $ticket['email']; ?>">
                        
                        <div class="form-group mb-4">
                            <textarea name="reply_message" class="brand-input-reply" rows="6" placeholder="Type your response here..." required></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2" style="opacity: 0.5;">
                                <i class='bx bx-info-circle'></i>
                                <p style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin: 0;">User will receive an email notification</p>
                            </div>
                            <button type="submit" class="btn-brand-save">Send Response <i class='bx bx-paper-plane'></i></button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                    <div class="alert text-center" style="padding: 40px; border-radius: 24px; background: rgba(6, 182, 212, 0.03); border: 2px dashed rgba(6, 182, 212, 0.15);">
                        <i class='bx bx-lock-alt' style="font-size: 32px; color: #06b6d4; margin-bottom: 15px; display: block;"></i>
                        <h4 style="color: #fff; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; font-size: 14px;">Ticket is Closed</h4>
                        <p style="font-size: 11px; opacity: 0.5; font-weight: 700;">Re-open the ticket to send additional replies.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Side: Sidebar Info -->
            <div class="d-flex flex-column gap-4">
                <div class="info-card">
                    <h5 style="font-weight: 900; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 25px; font-size: 10px; color: #fff; opacity: 0.6; display: flex; align-items: center; gap: 10px;">
                        <i class='bx bx-user' style="color: var(--brand);"></i> User Context
                    </h5>
                    
                    <div class="info-item">
                        <span class="info-label">Account ID</span>
                        <span class="info-value">
                            <?php if($ticket['user_id'] != 'guest'): ?>
                                <a href="../users-data/manager.php?id=<?php echo $ticket['user_id']; ?>" style="color: #06b6d4; text-decoration: none; font-weight: 900; display: flex; align-items: center; gap: 8px;">
                                    <?php echo $ticket['user_id']; ?> <i class='bx bx-right-top-arrow-circle'></i>
                                </a>
                            <?php else: ?>
                                <span style="opacity: 0.5;">Guest User</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Email Contact</span>
                        <span class="info-value" style="word-break: break-all; font-size: 12px; opacity: 0.8;"><?php echo $ticket['email']; ?></span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Priority Level</span>
                        <span class="info-value d-flex align-items-center gap-3">
                             <div style="width: 12px; height: 12px; border-radius: 4px; background: <?php echo $ticket['priority'] == 'High' ? '#ef4444' : ($ticket['priority'] == 'Medium' ? '#f59e0b' : '#10b981'); ?>; box-shadow: 0 0 20px <?php echo $ticket['priority'] == 'High' ? 'rgba(239, 68, 68, 0.4)' : ($ticket['priority'] == 'Medium' ? 'rgba(245, 158, 11, 0.4)' : 'rgba(16, 185, 129, 0.4)'); ?>;"></div>
                             <span style="font-size: 12px; font-weight: 900; text-transform: uppercase;"><?php echo $ticket['priority']; ?></span>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Verification ID</span>
                        <span class="info-value" style="font-family: monospace; letter-spacing: 1px; color: var(--brand);">
                            <?php echo $ticket['profile_id'] ?: 'Not Provided'; ?>
                        </span>
                    </div>
                </div>

                <div class="info-card">
                    <h5 style="font-weight: 900; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 25px; font-size: 10px; color: #fff; opacity: 0.6; display: flex; align-items: center; gap: 10px;">
                        <i class='bx bx-paperclip' style="color: var(--brand);"></i> Attachments
                    </h5>
                    <div class="attachment-list">
                        <?php 
                        mysqli_data_seek($attach_res, 0);
                        if(mysqli_num_rows($attach_res) > 0): ?>
                            <?php while($attach = mysqli_fetch_assoc($attach_res)): 
                                $ext = strtolower(pathinfo($attach['file_path'], PATHINFO_EXTENSION));
                                $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            ?>
                                <div class="attachment-group">
                                    <a href="<?php echo $isImg ? 'javascript:void(0)' : '../../'.$attach['file_path']; ?>" 
                                       class="attachment-card" 
                                       <?php if($isImg) echo 'onclick="openImageModal(\'../../'.$attach['file_path'].'\')"'; else echo 'target="_blank"'; ?>>
                                        <div style="width: 40px; h-40px; border-radius: 10px; background: rgba(255,255,255,0.03); display: flex; align-items: center; justify-content: center;">
                                            <i class='bx <?php echo $isImg ? "bx-image-alt" : "bx-file"; ?>' style="font-size: 20px;"></i>
                                        </div>
                                        <div class="attachment-info">
                                            <span class="attachment-name"><?php echo $attach['file_name']; ?></span>
                                            <span style="font-size: 9px; opacity: 0.4; text-transform: uppercase;"><?php echo $isImg ? 'View Image' : 'Download File'; ?></span>
                                        </div>
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="padding: 30px 15px; text-align: center; border-radius: 20px; background: rgba(255,255,255,0.01); border: 2px dashed rgba(255,255,255,0.05);">
                                <i class='bx bx-cloud-off' style="font-size: 24px; opacity: 0.2; margin-bottom: 10px; display: block;"></i>
                                <p style="font-size: 10px; color: #fff; opacity: 0.3; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">No files attached</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="image-modal-overlay" id="imgModal" onclick="closeImageModal()">
    <img src="" class="modal-content-img" id="modalImg" onclick="event.stopPropagation()">
</div>

<script>
    function openImageModal(src) {
        document.getElementById('modalImg').src = src;
        document.getElementById('imgModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeImageModal() {
        document.getElementById('imgModal').classList.remove('active');
        document.body.style.overflow = 'auto';
    }
</script>
<script src="../script.js"></script>
</body>
</html>
