<?php
/**
 * PhotoKY - Single Photo View, Owner Info Manager & Public Comments Hub
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$uuid = trim($_GET['uuid'] ?? $_GET['id'] ?? '');

if (empty($uuid)) {
    header("Location: " . BASE_URL . "/explore.php");
    exit;
}

$db = getDB();
$photo = null;

if ($db) {
    $stmt = $db->prepare("
        SELECT p.*
        FROM photos p
        WHERE p.uuid = :u1 OR p.id = :u2
        LIMIT 1
    ");
    $stmt->execute([':u1' => $uuid, ':u2' => $uuid]);
    $photo = $stmt->fetch();
}

if (!$photo) {
    http_response_code(404);
    $pageTitle = 'Photo Not Found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container py-5 text-center">
            <h2 class="fw-bold mb-3">Photo Not Found</h2>
            <p class="text-muted mb-4">The photo you are looking for may have been deleted or the link is incorrect.</p>
            <a href="' . BASE_URL . '/" class="btn-pill-primary">Go to Home</a>
          </div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Handle Download Request
if (isset($_GET['download'])) {
    $isAdmin = isAdminLoggedIn();

    // Security Guard: Unapproved or rejected photos cannot be downloaded by non-admins
    if ((int)($photo['is_approved'] ?? 1) !== 1 && !$isAdmin) {
        http_response_code(403);
        $pageTitle = 'ไม่อนุญาตให้ดาวน์โหลด - รอการอนุมัติ';
        require_once __DIR__ . '/includes/header.php';
        echo '<div class="container py-5 text-center">
                <div class="security-shield-card mx-auto" style="max-width: 600px;">
                    <div class="mb-3 text-warning">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    </div>
                    <h3 class="fw-bold mb-2">ไม่อนุญาตให้ดาวน์โหลดไฟล์นี้</h3>
                    <p class="text-muted mb-4">ภาพถ่ายนี้อยู่ระหว่างการตรวจสอบความปลอดภัยโดยผู้ดูแลระบบ จึงยังไม่เปิดให้ดาวน์โหลดสู่สาธารณะ</p>
                    <a href="' . BASE_URL . '/" class="btn-pill-primary">กลับสู่หน้าแรก</a>
                </div>
              </div>';
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }

    $filePath = ORIGINALS_PATH . '/' . $photo['filename'];
    if (file_exists($filePath)) {
        $db->prepare("UPDATE photos SET downloads = downloads + 1 WHERE id = :id")->execute([':id' => $photo['id']]);
        
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $photo['mime_type']);
        header('Content-Disposition: attachment; filename="' . rawurlencode($photo['original_name']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

// Increment view counter
$db->prepare("UPDATE photos SET views = views + 1 WHERE id = :id")->execute([':id' => $photo['id']]);

// Count existing comments
$commentCount = 0;
try {
    $cStmt = $db->prepare("SELECT COUNT(*) FROM comments WHERE photo_id = :pid AND is_approved = 1");
    $cStmt->execute([':pid' => $photo['id']]);
    $commentCount = (int)$cStmt->fetchColumn();
} catch (\Throwable $e) {}

$isAdmin = isAdminLoggedIn();
// Strict Rule: Non-admins have zero edit or delete permissions (even if ?token= is in the URL)
$isOwner = false;
$providedToken = '';
$hasOwnerDetails = !empty($photo['owner_name']) || !empty($photo['owner_phone']) || !empty($photo['extra_info']);

// If URL contains ?token=..., strip it cleanly so users never see or use management tokens
if (isset($_GET['token'])) {
    header("Location: " . BASE_URL . "/photo.php?uuid=" . urlencode($photo['uuid']));
    exit;
}

$pageTitle = $photo['title'] ?: $photo['original_name'];
require_once __DIR__ . '/includes/header.php';

$origUrl = BASE_URL . '/' . (!empty($photo['file_path']) ? $photo['file_path'] : 'uploads/originals/' . $photo['filename']);
$viewUrl = BASE_URL . '/photo.php?uuid=' . $photo['uuid'];
$downloadUrl = BASE_URL . '/photo.php?uuid=' . $photo['uuid'] . '&download=1';
$exif = !empty($photo['exif_data']) ? json_decode($photo['exif_data'], true) : null;
$csrfToken = getCsrfToken();
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/" class="text-decoration-none text-muted">หน้าหลัก</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/explore.php" class="text-decoration-none text-muted">สำรวจรูปภาพ</a></li>
            <li class="breadcrumb-item active text-truncate" style="max-width: 280px;" aria-current="page"><?= e($photo['title'] ?: $photo['original_name']) ?></li>
        </ol>
    </nav>

    <!-- Approval Notice Banner -->
    <?php if (($photo['is_approved'] ?? 1) == 0): ?>
    <div id="pendingAlert" class="alert rounded-4 p-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3" style="background: #FEF3C7; border: 1px solid #FDE68A !important; color: #92400E;">
        <div class="d-flex align-items-center gap-3">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <div>
                <div class="fw-bold fs-6">รอการตรวจสอบและอนุมัติจาก Admin (Pending Approval)</div>
                <div class="small">ภาพถ่ายนี้ถูกอัปโหลดเรียบร้อยแล้ว แต่อยู่ในคิวรอผู้ดูแลระบบตรวจสอบความปลอดภัยก่อนนำไปแสดงบนหน้าเว็บสาธารณะ</div>
            </div>
        </div>
        <?php if ($isAdmin): ?>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-semibold" onclick="handleAdminApproval(<?= (int)$photo['id'] ?>, 'approve')">
                อนุมัติภาพนี้ (Approve)
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" onclick="handleAdminApproval(<?= (int)$photo['id'] ?>, 'reject')">
                ปฏิเสธ (Reject)
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php elseif (($photo['is_approved'] ?? 1) == 2): ?>
    <div class="alert rounded-4 p-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3" style="background: #FEE2E2; border: 1px solid #FECACA !important; color: #991B1B;">
        <div class="d-flex align-items-center gap-3">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            <div>
                <div class="fw-bold fs-6">ภาพนี้ถูกปฏิเสธโดยผู้ดูแลระบบ (Rejected)</div>
                <div class="small">ภาพนี้ไม่ผ่านเกณฑ์การตรวจสอบความปลอดภัยของระบบ และไม่ถูกแสดงผลสู่สาธารณะ</div>
            </div>
        </div>
        <?php if ($isAdmin): ?>
        <div>
            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-semibold" onclick="handleAdminApproval(<?= (int)$photo['id'] ?>, 'approve')">
                เปลี่ยนเป็นอนุมัติ (Approve)
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Main Image Stage & Comments (Left) -->
        <div class="col-lg-8">
            <div class="photo-viewer-wrap mb-3">
                <a href="<?= e($origUrl) ?>" target="_blank" title="Click to view full image in new tab">
                    <img src="<?= e($origUrl) ?>" alt="<?= e($photo['title'] ?: $photo['original_name']) ?>" class="photo-viewer-img">
                </a>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h1 class="h4 fw-bold mb-1" id="displayPhotoTitle"><?= e($photo['title'] ?: $photo['original_name']) ?></h1>
                    <?php if (!empty($photo['description'])): ?>
                        <p class="text-muted mb-0" id="displayPhotoDesc"><?= nl2br(e($photo['description'])) ?></p>
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <a href="<?= e($downloadUrl) ?>" class="btn-pill-accent">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        ดาวน์โหลด
                    </a>
                    
                    <button type="button" class="btn-pill-secondary" onclick="window.showEmbedModal('<?= e($photo['uuid']) ?>', '<?= e($origUrl) ?>', '<?= e($viewUrl) ?>')">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="18" cy="5" r="3"></circle>
                            <circle cx="6" cy="12" r="3"></circle>
                            <circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                        แชร์รูปภาพ
                    </button>

                    <?php if ($isAdmin): ?>
                    <!-- Edit Details Button (Admin Only) -->
                    <button type="button" id="editDetailsBtn" class="btn-pill-secondary" data-bs-toggle="modal" data-bs-target="#editPhotoModal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        แก้ไขข้อมูล (Admin)
                    </button>

                    <!-- Delete Button (Admin Only) -->
                    <button type="button" id="deleteBtn" class="btn-pill-danger" onclick="window.confirmDeletePhoto('<?= e($photo['uuid']) ?>', null, '<?= BASE_URL ?>/')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        ลบรูปภาพ (Admin)
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Public Comments & Replies Section -->
            <section class="comments-card mt-4" id="commentsSection">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h5 fw-bold mb-0">
                        ความคิดเห็น <span class="text-muted fw-normal" id="commentsCountLabel">(<?= $commentCount ?>)</span>
                    </h2>
                    <span class="text-muted small">แสดงความคิดเห็นได้โดยไม่ต้องเข้าสู่ระบบ</span>
                </div>

                <!-- New Comment Form -->
                <form id="newCommentForm" class="mb-4 pb-4 border-bottom">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <!-- Anti-Spam Honeypot -->
                    <input type="text" name="website" id="hpWebsite" class="comment-hp" tabindex="-1" autocomplete="off">

                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="commentAuthor" class="form-label small fw-semibold text-muted mb-1">ชื่อผู้แสดงความคิดเห็น *</label>
                            <input type="text" id="commentAuthor" class="form-control form-control-sm rounded-3" maxlength="80" placeholder="ระบุชื่อของคุณ" required>
                        </div>
                        <div class="col-12">
                            <label for="commentContent" class="form-label small fw-semibold text-muted mb-1">ข้อความคิดเห็น *</label>
                            <textarea id="commentContent" class="form-control rounded-3" rows="3" maxlength="2000" placeholder="เขียนความคิดเห็นเกี่ยวกับรูปภาพนี้..." required></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" id="submitCommentBtn" class="btn-pill-primary btn-sm">
                                ส่งความคิดเห็น
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Comments List Container -->
                <div id="commentsList">
                    <div class="text-center text-muted small py-3">กำลังโหลดความคิดเห็น...</div>
                </div>
            </section>
        </div>

        <!-- Sidebar: Owner Details, File Info, EXIF & Direct Links (Right) -->
        <div class="col-lg-4">
            <!-- Owner & Additional Details Box (Read-Only for Public & Owners, Editable by Admin Only) -->
            <div class="metadata-box" id="ownerInfoBox">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">ข้อมูลเจ้าของภาพ & รายละเอียด</h5>
                    <?php if ($isAdmin): ?>
                    <button type="button" id="sidebarEditOwnerBtn" class="btn btn-sm btn-link text-dark fw-semibold text-decoration-none p-0" data-bs-toggle="modal" data-bs-target="#editPhotoModal">
                        แก้ไข (Admin)
                    </button>
                    <?php endif; ?>
                </div>

                <?php if ($hasOwnerDetails): ?>
                    <?php if (!empty($photo['owner_name'])): ?>
                    <div class="metadata-item">
                        <span class="metadata-label">เจ้าของภาพ :</span>
                        <span class="metadata-value"><?= e($photo['owner_name']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($photo['owner_phone'])): ?>
                    <div class="metadata-item">
                        <span class="metadata-label">หมายเลขโทรศัพท์ :</span>
                        <a href="tel:<?= e(preg_replace('/[^0-9+\-]/', '', $photo['owner_phone'])) ?>" class="metadata-value text-decoration-none">
                            <?= e($photo['owner_phone']) ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($photo['extra_info'])): ?>
                    <div class="pt-2 mt-2 border-top">
                        <div class="metadata-label small mb-1">ข้อมูลเพิ่มเติม :</div>
                        <div class="small text-secondary" style="white-space: pre-wrap; word-break: break-word;"><?= e($photo['extra_info']) ?></div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted small mb-0" id="emptyOwnerText">ไม่ได้ระบุข้อมูลเจ้าของภาพ</p>
                <?php endif; ?>
            </div>

            <!-- File Info Box -->
            <div class="metadata-box">
                <h5 class="fw-bold mb-3">ข้อมูลไฟล์รูปภาพ</h5>
                
                <div class="metadata-item">
                    <span class="metadata-label">รหัสรูปภาพ</span>
                    <span class="metadata-value">#<?= (int)$photo['id'] ?></span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">ความละเอียด</span>
                    <span class="metadata-value"><?= (int)$photo['width'] ?> × <?= (int)$photo['height'] ?> px</span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">ขนาดไฟล์</span>
                    <span class="metadata-value"><?= formatBytes($photo['file_size']) ?></span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">นามสกุลไฟล์</span>
                    <span class="metadata-value text-uppercase"><?= e($photo['file_ext']) ?></span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">ประเภทไฟล์</span>
                    <span class="metadata-value"><?= e($photo['mime_type']) ?></span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">ยอดเข้าชม</span>
                    <span class="metadata-value"><?= number_format($photo['views']) ?> ครั้ง</span>
                </div>
                <div class="metadata-item">
                    <span class="metadata-label">อัปโหลดเมื่อ</span>
                    <span class="metadata-value"><?= timeAgo($photo['created_at']) ?></span>
                </div>
            </div>

            <!-- EXIF Data Box -->
            <?php if (!empty($exif)): ?>
            <div class="metadata-box">
                <h5 class="fw-bold mb-3">ข้อมูลกล้องและเลนส์</h5>
                <?php if (!empty($exif['camera_make']) || !empty($exif['camera_model'])): ?>
                <div class="metadata-item">
                    <span class="metadata-label">กล้อง</span>
                    <span class="metadata-value"><?= e(trim(($exif['camera_make'] ?? '') . ' ' . ($exif['camera_model'] ?? ''))) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($exif['lens'])): ?>
                <div class="metadata-item">
                    <span class="metadata-label">เลนส์</span>
                    <span class="metadata-value"><?= e($exif['lens']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($exif['focal_length'])): ?>
                <div class="metadata-item">
                    <span class="metadata-label">ระยะโฟกัส</span>
                    <span class="metadata-value"><?= e($exif['focal_length']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($exif['f_number'])): ?>
                <div class="metadata-item">
                    <span class="metadata-label">รูรับแสง</span>
                    <span class="metadata-value"><?= e($exif['f_number']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($exif['exposure_time'])): ?>
                <div class="metadata-item">
                    <span class="metadata-label">ความเร็วชัตเตอร์</span>
                    <span class="metadata-value"><?= e($exif['exposure_time']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($exif['iso'])): ?>
                <div class="metadata-item">
                    <span class="metadata-label">ค่าความไวแสง</span>
                    <span class="metadata-value"><?= e($exif['iso']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($exif['date_taken'])): ?>
                <div class="metadata-item">
                    <span class="metadata-label">วันที่ถ่ายภาพ</span>
                    <span class="metadata-value"><?= e($exif['date_taken']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Direct Links Quick Box -->
            <div class="metadata-box">
                <h5 class="fw-bold mb-3">ลิงก์สำหรับแชร์รูปภาพ</h5>
                <label class="form-label small text-muted">ลิงก์รูปภาพโดยตรง</label>
                <div class="input-group mb-3">
                    <input type="text" class="form-control code-copy-input" value="<?= e($origUrl) ?>" readonly>
                    <button class="btn btn-outline-secondary" onclick="window.copyToClipboard('<?= e($origUrl) ?>', 'คัดลอกลิงก์แล้ว!')">คัดลอก</button>
                </div>

                <label class="form-label small text-muted">โค้ดสำหรับฝังภาพ (Markdown)</label>
                <div class="input-group">
                    <input type="text" class="form-control code-copy-input" value="![PhotoKY Image](<?= e($origUrl) ?>)" readonly>
                    <button class="btn btn-outline-secondary" onclick="window.copyToClipboard('![PhotoKY Image](<?= e($origUrl) ?>)', 'คัดลอกโค้ดแล้ว!')">คัดลอก</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Edit Photo & Owner Details Modal (Admin Only) -->
<div class="modal fade" id="editPhotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-bottom px-4 py-3">
                <h5 class="modal-title fw-bold">แก้ไขข้อมูลภาพ & เจ้าของภาพ (เฉพาะ Admin)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPhotoForm">
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ชื่อรูปภาพ</label>
                        <input type="text" id="editTitle" class="form-control rounded-3" maxlength="255" value="<?= e($photo['title'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">คำอธิบายรูปภาพ</label>
                        <textarea id="editDescription" class="form-control rounded-3" rows="2"><?= e($photo['description'] ?? '') ?></textarea>
                    </div>

                    <hr class="my-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-uppercase text-secondary">ข้อมูลเจ้าของภาพ</span>
                        <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0" onclick="clearOwnerDetails()">ล้างข้อมูลเจ้าของภาพ</button>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">เจ้าของภาพ :</label>
                            <input type="text" id="editOwnerName" class="form-control rounded-3" maxlength="150" value="<?= e($photo['owner_name'] ?? '') ?>" placeholder="ชื่อช่างภาพ / เจ้าของภาพ">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">หมายเลขโทรศัพท์ :</label>
                            <input type="tel" id="editOwnerPhone" class="form-control rounded-3" maxlength="50" value="<?= e($photo['owner_phone'] ?? '') ?>" placeholder="08x-xxx-xxxx">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ข้อมูลเพิ่มเติม :</label>
                        <textarea id="editExtraInfo" class="form-control rounded-3" rows="3" placeholder="สถานที่ถ่ายภาพ, เครดิต, สิทธิ์การใช้งาน, หรือบันทึกเพิ่มเติม..."><?= e($photo['extra_info'] ?? '') ?></textarea>
                    </div>

                    <hr class="my-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="editIsPublic" <?= $photo['is_public'] ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="editIsPublic">แสดงในคลังภาพสาธารณะ</label>
                    </div>
                </div>
                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn-pill-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn-pill-primary btn-sm">บันทึกการเปลี่ยนแปลง</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const PHOTO_ID = <?= (int)$photo['id'] ?>;
const PHOTO_UUID = "<?= e($photo['uuid']) ?>";
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
const CSRF_TOKEN = "<?= e($csrfToken) ?>";

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
    // Restore guest display name convenience for commenting
    const savedName = localStorage.getItem('photoky_comment_author');
    if (savedName && document.getElementById('commentAuthor')) {
        document.getElementById('commentAuthor').value = savedName;
    }

    // Load comments
    loadComments();

    // Handle Edit Photo & Owner Details Submit (Admin Only)
    document.getElementById('editPhotoForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!IS_ADMIN) return;
        const payload = {
            uuid: PHOTO_UUID,
            title: document.getElementById('editTitle').value.trim(),
            description: document.getElementById('editDescription').value.trim(),
            owner_name: document.getElementById('editOwnerName').value.trim(),
            owner_phone: document.getElementById('editOwnerPhone').value.trim(),
            extra_info: document.getElementById('editExtraInfo').value.trim(),
            is_public: document.getElementById('editIsPublic').checked ? 1 : 0
        };

        try {
            const res = await fetch(window.BASE_URL + '/api/photo-update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                window.showToast('บันทึกข้อมูลเรียบร้อยแล้ว');
                setTimeout(() => location.reload(), 600);
            } else {
                Swal.fire('ข้อผิดพลาด', data.error || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
            }
        } catch (err) {
            Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย', 'error');
        }
    });

    // Handle New Top-Level Comment Submit
    document.getElementById('newCommentForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const authorEl = document.getElementById('commentAuthor');
        const contentEl = document.getElementById('commentContent');
        const hpEl = document.getElementById('hpWebsite');
        const btn = document.getElementById('submitCommentBtn');

        const authorName = authorEl.value.trim();
        const content = contentEl.value.trim();
        if (!authorName || !content) return;

        btn.disabled = true;
        btn.innerText = 'Posting...';

        try {
            const res = await fetch(window.BASE_URL + '/api/comments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    photo_id: PHOTO_ID,
                    author_name: authorName,
                    content: content,
                    website: hpEl.value,
                    csrf_token: CSRF_TOKEN
                })
            });
            const data = await res.json();
            if (data.success) {
                localStorage.setItem('photoky_comment_author', authorName);
                contentEl.value = '';
                window.showToast('Comment posted');
                loadComments();
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Unable to Post',
                    text: data.error || 'Please check your input.',
                    customClass: { confirmButton: 'btn-pill-primary' },
                    buttonsStyling: false
                });
            }
        } catch (err) {
            Swal.fire('Error', 'Network error while posting comment.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Post Comment';
        }
    });
});

async function clearOwnerDetails() {
    const confirm = await Swal.fire({
        title: 'Clear Owner Details?',
        text: 'This removes owner name, phone number, and additional info while keeping the photo intact.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Clear',
        cancelButtonText: 'Cancel',
        customClass: { confirmButton: 'btn-pill-danger me-2', cancelButton: 'btn-pill-secondary' },
        buttonsStyling: false
    });
    if (!confirm.isConfirmed) return;

    const res = await fetch(window.BASE_URL + '/api/photo-update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            uuid: PHOTO_UUID,
            action: 'clear_owner_info'
        })
    });
    const data = await res.json();
    if (data.success) {
        window.showToast('ล้างข้อมูลเจ้าของภาพเรียบร้อยแล้ว');
        setTimeout(() => location.reload(), 600);
    } else {
        Swal.fire('ข้อผิดพลาด', data.error || 'ไม่สามารถล้างข้อมูลได้', 'error');
    }
}

async function loadComments() {
    const container = document.getElementById('commentsList');
    const countLabel = document.getElementById('commentsCountLabel');
    try {
        const res = await fetch(`${window.BASE_URL}/api/comments.php?photo_id=${PHOTO_ID}`);
        const data = await res.json();
        if (!data.success) {
            container.innerHTML = '<div class="text-muted small text-center py-3">ไม่สามารถโหลดความคิดเห็นได้</div>';
            return;
        }

        countLabel.innerText = `(${data.total})`;
        if (!data.comments || data.comments.length === 0) {
            container.innerHTML = '<div class="text-muted small text-center py-4">ยังไม่มีความคิดเห็น ร่วมแสดงความคิดเห็นเป็นคนแรกได้เลย!</div>';
            return;
        }

        container.innerHTML = data.comments.map(c => renderCommentHtml(c, data.is_admin)).join('');
    } catch (e) {
        container.innerHTML = '<div class="text-muted small text-center py-3">เกิดข้อผิดพลาดในการโหลดความคิดเห็น</div>';
    }
}

function renderCommentHtml(c, isAdmin) {
    const initial = escapeHtml((c.author_name || '?').trim().charAt(0));
    const repliesHtml = (c.replies && c.replies.length > 0)
        ? `<div class="comment-replies">${c.replies.map(r => renderReplyHtml(r, isAdmin)).join('')}</div>`
        : '';

    const adminDeleteBtn = isAdmin
        ? `<button type="button" class="btn btn-link btn-sm text-danger p-0 text-decoration-none ms-3" onclick="deleteComment(${c.id})">ลบ</button>`
        : '';

    return `
        <div class="comment-item" id="comment_${c.id}">
            <div class="d-flex gap-3">
                <div class="comment-avatar">${initial}</div>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
                        <span class="comment-author">${escapeHtml(c.author_name)}</span>
                        <span class="comment-date" title="${escapeHtml(c.created_at)}">${escapeHtml(c.formatted_dt)} • ${escapeHtml(c.time_ago)}</span>
                    </div>
                    <div class="comment-body mb-2">${escapeHtml(c.content)}</div>
                    <div class="d-flex align-items-center">
                        <button type="button" class="btn btn-link btn-sm text-muted p-0 text-decoration-none fw-semibold" onclick="toggleReplyForm(${c.id})">ตอบกลับ</button>
                        ${adminDeleteBtn}
                    </div>
                    <div id="replyFormWrap_${c.id}" class="mt-3 d-none"></div>
                </div>
            </div>
            ${repliesHtml}
        </div>
    `;
}

function renderReplyHtml(r, isAdmin) {
    const initial = escapeHtml((r.author_name || '?').trim().charAt(0));
    const adminDeleteBtn = isAdmin
        ? `<button type="button" class="btn btn-link btn-sm text-danger p-0 text-decoration-none ms-2" onclick="deleteComment(${r.id})">ลบ</button>`
        : '';

    return `
        <div class="comment-reply-item" id="comment_${r.id}">
            <div class="d-flex gap-2">
                <div class="comment-avatar" style="width:28px;height:28px;font-size:0.75rem;">${initial}</div>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
                        <span class="comment-author small">${escapeHtml(r.author_name)}</span>
                        <span class="comment-date" title="${escapeHtml(r.created_at)}">${escapeHtml(r.formatted_dt)}</span>
                        ${adminDeleteBtn}
                    </div>
                    <div class="comment-body small">${escapeHtml(r.content)}</div>
                </div>
            </div>
        </div>
    `;
}

function toggleReplyForm(parentId) {
    const wrap = document.getElementById(`replyFormWrap_${parentId}`);
    if (!wrap) return;
    if (!wrap.classList.contains('d-none')) {
        wrap.classList.add('d-none');
        wrap.innerHTML = '';
        return;
    }

    const savedName = escapeHtml(localStorage.getItem('photoky_comment_author') || '');
    wrap.classList.remove('d-none');
    wrap.innerHTML = `
        <div class="p-3 bg-light border rounded-3">
            <div class="row g-2">
                <div class="col-md-5">
                    <input type="text" id="replyAuthor_${parentId}" class="form-control form-control-sm rounded-3" maxlength="80" placeholder="ชื่อของคุณ" value="${savedName}" required>
                </div>
                <div class="col-12">
                    <textarea id="replyContent_${parentId}" class="form-control form-control-sm rounded-3" rows="2" maxlength="2000" placeholder="เขียนข้อความตอบกลับ..." required></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn-pill-secondary btn-sm py-1 px-3" onclick="toggleReplyForm(${parentId})">ยกเลิก</button>
                    <button type="button" class="btn-pill-primary btn-sm py-1 px-3" onclick="submitReply(${parentId})">ส่งข้อความตอบกลับ</button>
                </div>
            </div>
        </div>
    `;
    document.getElementById(`replyContent_${parentId}`)?.focus();
}

async function submitReply(parentId) {
    const authorName = document.getElementById(`replyAuthor_${parentId}`)?.value.trim() || '';
    const content = document.getElementById(`replyContent_${parentId}`)?.value.trim() || '';

    if (!authorName || !content) {
        Swal.fire({ icon: 'warning', title: 'Missing Fields', text: 'Please enter your name and reply message.' });
        return;
    }

    try {
        const res = await fetch(window.BASE_URL + '/api/comments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                photo_id: PHOTO_ID,
                parent_id: parentId,
                author_name: authorName,
                content: content,
                website: '',
                csrf_token: CSRF_TOKEN
            })
        });
        const data = await res.json();
        if (data.success) {
            localStorage.setItem('photoky_comment_author', authorName);
            window.showToast('Reply posted');
            loadComments();
        } else {
            Swal.fire('Notice', data.error || 'Could not post reply.', 'warning');
        }
    } catch (e) {
        Swal.fire('Error', 'Network error while posting reply.', 'error');
    }
}

async function deleteComment(commentId) {
    const confirm = await Swal.fire({
        title: 'Delete Comment?',
        text: 'This will permanently delete this comment and any replies under it.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        customClass: { confirmButton: 'btn-pill-danger me-2', cancelButton: 'btn-pill-secondary' },
        buttonsStyling: false
    });
    if (!confirm.isConfirmed) return;

    const res = await fetch(window.BASE_URL + '/api/comments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'delete',
            comment_id: commentId,
            csrf_token: CSRF_TOKEN
        })
    });
    const data = await res.json();
    if (data.success) {
        window.showToast('Comment deleted');
        loadComments();
    } else {
        Swal.fire('Error', data.error || 'Could not delete comment', 'error');
    }
}

async function handleAdminApproval(photoId, action) {
    const isApprove = action === 'approve';
    const confirm = await Swal.fire({
        title: isApprove ? 'อนุมัติภาพนี้?' : 'ปฏิเสธภาพนี้?',
        text: isApprove ? 'เมื่ออนุมัติแล้ว ภาพจะปรากฏในหน้าแรกและคลังภาพสาธารณะทันที' : 'เมื่อปฏิเสธ ภาพจะไม่แสดงในคลังภาพสาธารณะ',
        icon: isApprove ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonText: isApprove ? 'อนุมัติทันที' : 'ปฏิเสธภาพ',
        cancelButtonText: 'ยกเลิก',
        customClass: {
            confirmButton: isApprove ? 'btn-pill-primary me-2' : 'btn btn-danger rounded-pill me-2',
            cancelButton: 'btn-pill-secondary'
        },
        buttonsStyling: false
    });
    if (!confirm.isConfirmed) return;

    try {
        const fd = new FormData();
        fd.append('photo_id', photoId);
        fd.append('action', action);
        fd.append('csrf_token', CSRF_TOKEN);

        const res = await fetch((window.BASE_URL || '') + '/api/photo-approve.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: data.message,
                timer: 1300,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('เกิดข้อผิดพลาด', data.error || 'ไม่สามารถบันทึกสถานะได้', 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Network error during approval update.', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
