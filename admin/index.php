<?php
/**
 * PhotoKY - Admin Dashboard Overview
 */

$adminPageTitle = 'Dashboard';
require_once __DIR__ . '/header.php';

$db = getDB();
$csrfToken = getCsrfToken();

// Core metrics
$metrics = ['total_photos' => 0, 'total_bytes' => 0, 'total_views' => 0, 'total_downloads' => 0];
$totalFolders = 0;
$totalComments = 0;
$uploadsToday = 0;
$pendingPhotosCount = 0;
$formatStats = [];
$recentPhotos = [];
$recentComments = [];

if ($db) {
    try {
        $metrics = $db->query("
            SELECT 
                COUNT(*) as total_photos,
                COALESCE(SUM(file_size), 0) as total_bytes,
                COALESCE(SUM(views), 0) as total_views,
                COALESCE(SUM(downloads), 0) as total_downloads
            FROM photos
        ")->fetch() ?: $metrics;

        $totalFolders = 0;
        $totalComments = (int)$db->query("SELECT COUNT(*) FROM comments")->fetchColumn();
        $todayPrefix = date('Y-m-d') . ' 00:00:00';
        $upStmt = $db->prepare("SELECT COUNT(*) FROM photos WHERE created_at >= :td");
        $upStmt->execute([':td' => $todayPrefix]);
        $uploadsToday = (int)$upStmt->fetchColumn();
        $pendingPhotosCount = (int)$db->query("SELECT COUNT(*) FROM photos WHERE is_approved = 0")->fetchColumn();

        // Format breakdown
        $formatStats = $db->query("
            SELECT UPPER(file_ext) as ext, COUNT(*) as cnt, COALESCE(SUM(file_size), 0) as bytes
            FROM photos
            GROUP BY file_ext
            ORDER BY bytes DESC
        ")->fetchAll();

        // Recent photos
        $recentPhotos = $db->query("
            SELECT p.*
            FROM photos p
            ORDER BY p.id DESC
            LIMIT 8
        ")->fetchAll();

        // Recent comments
        $recentComments = $db->query("
            SELECT c.*, p.uuid as photo_uuid, p.title as photo_title, p.filename as photo_filename
            FROM comments c
            INNER JOIN photos p ON c.photo_id = p.id
            ORDER BY c.id DESC
            LIMIT 6
        ")->fetchAll();
    } catch (\Throwable $e) {}
}

$totalBytes = (int)($metrics['total_bytes'] ?? 0);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">ภาพรวมระบบ</h1>
        <p class="text-muted small mb-0">สถิติการจัดเก็บไฟล์ การตรวจสอบและอนุมัติรูปภาพ และจัดการความคิดเห็น</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/photos.php?action=new" class="btn-pill-primary btn-sm">+ อัปโหลดรูปภาพ</a>
        <a href="<?= BASE_URL ?>/admin/comments.php" class="btn-pill-secondary btn-sm">จัดการความคิดเห็น</a>
    </div>
</div>

<!-- Pending Approval Alert Banner -->
<?php if ($pendingPhotosCount > 0): ?>
<div class="alert rounded-4 p-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3 shadow-sm" style="background: #FEF3C7; border: 1px solid #FDE68A !important; color: #92400E;">
    <div class="d-flex align-items-center gap-3">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <div>
            <div class="fw-bold fs-6">มีภาพถ่ายรอการตรวจสอบ <?= number_format($pendingPhotosCount) ?> รายการ</div>
            <div class="small">ผู้ใช้อัปโหลดภาพเข้ามาใหม่และรอให้ผู้ดูแลระบบตรวจสอบและอนุมัติก่อนนำมาแสดงผลสู่สาธารณะ</div>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/admin/photos.php?status=pending" class="btn btn-sm btn-warning text-dark fw-bold rounded-pill px-3 shadow-sm">
        ตรวจสอบและอนุมัติภาพ &rarr;
    </a>
</div>
<?php endif; ?>

<!-- Metrics Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="admin-card h-100">
            <div class="text-muted small fw-semibold text-uppercase mb-1">รูปภาพทั้งหมด</div>
            <div class="h2 fw-bold mb-1"><?= number_format((int)$metrics['total_photos']) ?></div>
            <div class="small text-success fw-medium">+<?= number_format($uploadsToday) ?> รูปในวันนี้</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="admin-card h-100 <?= $pendingPhotosCount > 0 ? 'border-warning' : '' ?>">
            <div class="text-muted small fw-semibold text-uppercase mb-1">รอการอนุมัติ</div>
            <div class="h2 fw-bold mb-1 <?= $pendingPhotosCount > 0 ? 'text-warning' : '' ?>"><?= number_format($pendingPhotosCount) ?></div>
            <div class="small"><?= $pendingPhotosCount > 0 ? '<a href="' . BASE_URL . '/admin/photos.php?status=pending" class="text-warning text-decoration-none fw-semibold">ตรวจสอบทันที &rarr;</a>' : '<span class="text-muted">ตรวจสอบครบแล้ว</span>' ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="admin-card h-100">
            <div class="text-muted small fw-semibold text-uppercase mb-1">พื้นที่จัดเก็บที่ใช้</div>
            <div class="h2 fw-bold mb-1"><?= e(formatBytes($totalBytes)) ?></div>
            <div class="small text-muted">ยอดเข้าชม <?= number_format((int)$metrics['total_views']) ?> ครั้ง</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="admin-card h-100">
            <div class="text-muted small fw-semibold text-uppercase mb-1">ความคิดเห็น</div>
            <div class="h2 fw-bold mb-1"><?= number_format($totalComments) ?></div>
            <div class="small text-muted">ข้อความคิดเห็นและการตอบกลับ</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Uploads -->
    <div class="col-lg-8">
        <div class="admin-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 fw-bold mb-0">รูปภาพที่อัปโหลดล่าสุด</h2>
                <a href="<?= BASE_URL ?>/admin/photos.php" class="small text-decoration-none fw-semibold">จัดการทั้งหมด &rarr;</a>
            </div>

            <?php if (empty($recentPhotos)): ?>
                <div class="text-muted small text-center py-4">ยังไม่มีรูปภาพที่อัปโหลดเข้ามา</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>รูปภาพ</th>
                                <th>สถานะ</th>
                                <th>ข้อมูลเจ้าของภาพ</th>
                                <th>ขนาดไฟล์</th>
                                <th>เวลาที่อัปโหลด</th>
                                <th class="text-end">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPhotos as $p): ?>
                            <tr id="photo_<?= e($p['uuid']) ?>">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= BASE_URL ?>/uploads/thumbnails/<?= e($p['filename']) ?>" class="rounded" style="width: 44px; height: 44px; object-fit: cover;" alt="">
                                        <div class="min-w-0">
                                            <a href="<?= BASE_URL ?>/photo.php?uuid=<?= e($p['uuid']) ?>" target="_blank" class="fw-semibold text-dark text-decoration-none d-block text-truncate" style="max-width: 170px;">
                                                <?= e($p['title'] ?: $p['original_name']) ?>
                                            </a>
                                            <span class="text-muted" style="font-size: 0.75rem;">ID #<?= (int)$p['id'] ?> • <?= e(strtoupper($p['file_ext'])) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (($p['is_approved'] ?? 1) == 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0" style="font-size: 0.72rem;">อนุมัติแล้ว</span>
                                    <?php elseif (($p['is_approved'] ?? 1) == 2): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-0" style="font-size: 0.72rem;">ปฏิเสธ</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-0" style="font-size: 0.72rem;">รออนุมัติ</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['owner_name']) || !empty($p['owner_phone'])): ?>
                                        <div class="fw-medium"><?= e($p['owner_name'] ?: '-') ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= e($p['owner_phone'] ?: '') ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatBytes((int)$p['file_size']) ?></td>
                                <td class="text-muted"><?= timeAgo($p['created_at']) ?></td>
                                <td class="text-end text-nowrap">
                                    <?php if (($p['is_approved'] ?? 1) == 0): ?>
                                        <button type="button" class="btn btn-sm btn-success rounded-pill px-2 py-0 me-1" onclick="quickApprovePhoto(<?= (int)$p['id'] ?>, 'approve')">อนุมัติ</button>
                                        <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-2 py-0 me-1" onclick="quickApprovePhoto(<?= (int)$p['id'] ?>, 'reject')">ปฏิเสธ</button>
                                    <?php endif; ?>
                                    <a href="<?= BASE_URL ?>/admin/photos.php?edit=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0">แก้ไข</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0 ms-1" onclick="window.confirmDeletePhoto('<?= e($p['uuid']) ?>')">ลบ</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Format Breakdown & Recent Comments -->
    <div class="col-lg-4">
        <!-- Storage Breakdown -->
        <div class="admin-card mb-4">
            <h2 class="h5 fw-bold mb-3">สัดส่วนพื้นที่ตามประเภทไฟล์</h2>
            <?php if (empty($formatStats)): ?>
                <div class="text-muted small">ยังไม่มีข้อมูลการจัดเก็บ</div>
            <?php else: ?>
                <?php foreach ($formatStats as $fs): ?>
                    <?php $pct = $totalBytes > 0 ? round(((int)$fs['bytes'] / $totalBytes) * 100, 1) : 0; ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold"><?= e($fs['ext']) ?> <span class="text-muted fw-normal">(<?= (int)$fs['cnt'] ?> รูป)</span></span>
                            <span class="text-muted"><?= formatBytes((int)$fs['bytes']) ?> (<?= $pct ?>%)</span>
                        </div>
                        <div class="progress progress-thin">
                            <div class="progress-bar" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recent Comments -->
        <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 fw-bold mb-0">ความคิดเห็นล่าสุด</h2>
                <a href="<?= BASE_URL ?>/admin/comments.php" class="small text-decoration-none fw-semibold">ดูทั้งหมด &rarr;</a>
            </div>
            <?php if (empty($recentComments)): ?>
                <div class="text-muted small text-center py-3">ยังไม่มีความคิดเห็น</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($recentComments as $c): ?>
                        <div class="border-bottom pb-2">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="fw-semibold small"><?= e($c['author_name']) ?></span>
                                <span class="text-muted" style="font-size: 0.72rem;"><?= timeAgo($c['created_at']) ?></span>
                            </div>
                            <p class="small text-secondary mb-1 text-truncate"><?= e($c['content']) ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="<?= BASE_URL ?>/photo.php?uuid=<?= e($c['photo_uuid']) ?>#commentsSection" target="_blank" class="text-muted text-decoration-none" style="font-size: 0.74rem;">
                                    ในภาพ: <?= e(mb_strimwidth($c['photo_title'] ?: 'รูปภาพ #' . $c['photo_id'], 0, 26, '...')) ?>
                                </a>
                                <button type="button" class="btn btn-link btn-sm text-danger p-0 text-decoration-none" style="font-size: 0.75rem;" onclick="adminDeleteComment(<?= (int)$c['id'] ?>)">ลบ</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const ADMIN_CSRF = "<?= e($csrfToken) ?>";

async function quickApprovePhoto(photoId, action) {
    const isApprove = action === 'approve';
    try {
        const fd = new FormData();
        fd.append('photo_id', photoId);
        fd.append('action', action);
        fd.append('csrf_token', ADMIN_CSRF);

        const res = await fetch((window.BASE_URL || '') + '/api/photo-approve.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: isApprove ? 'อนุมัติภาพเรียบร้อย' : 'ปฏิเสธภาพเรียบร้อย',
                timer: 1000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', data.error || 'Failed to update photo status', 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Network or server error', 'error');
    }
}

async function adminDeleteComment(commentId) {
    const confirm = await Swal.fire({
        title: 'Delete Comment?',
        text: 'This permanently deletes the comment and any replies.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        customClass: { confirmButton: 'btn-pill-danger me-2', cancelButton: 'btn-pill-secondary' },
        buttonsStyling: false
    });
    if (!confirm.isConfirmed) return;

    const res = await fetch(window.BASE_URL + '/api/comments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', comment_id: commentId, csrf_token: ADMIN_CSRF })
    });
    const data = await res.json();
    if (data.success) {
        location.reload();
    } else {
        Swal.fire('Error', data.error || 'Could not delete comment', 'error');
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
