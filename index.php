<?php
/**
 * PhotoKY - Home Page (Pexels-Inspired Clean White & Gray Layout)
 * - No Folders (Streamlined direct gallery)
 * - Staged upload with explicit "กดส่งรูปภาพ (Submit)" button
 * - Admin approval before public display
 */

$pageTitle = 'คลังรูปภาพออนไลน์และการแบ่งปันคุณภาพสูง';
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$sort = trim($_GET['sort'] ?? 'latest');

// Fetch live statistics (only approved public photos)
$stats = [
    'photos'  => 0,
    'storage' => '0 B',
    'views'   => 0
];

if ($db) {
    try {
        $statRow = $db->query("
            SELECT 
                COUNT(*) as total_photos, 
                COALESCE(SUM(file_size), 0) as total_bytes,
                COALESCE(SUM(views), 0) as total_views
            FROM photos
            WHERE is_public = 1 AND is_approved = 1
        ")->fetch();
        
        $stats['photos'] = (int)($statRow['total_photos'] ?? 0);
        $stats['storage'] = formatBytes((int)($statRow['total_bytes'] ?? 0));
        $stats['views'] = (int)($statRow['total_views'] ?? 0);
    } catch (\Throwable $e) {}
}

// Fetch approved public photos (latest 24 in Pexels Masonry style)
$recentPhotos = [];
if ($db) {
    try {
        $orderBy = ($sort === 'popular') ? 'p.views DESC, p.id DESC' : 'p.id DESC';
        $stmt = $db->query("
            SELECT p.*
            FROM photos p
            WHERE p.is_public = 1 AND p.is_approved = 1
            ORDER BY {$orderBy}
            LIMIT 24
        ");
        $recentPhotos = $stmt->fetchAll();
    } catch (\Throwable $e) {}
}
?>

<!-- Pexels-Style Hero Section -->
<section class="pexels-hero text-center overflow-hidden">
    <div class="container">
        <h1 class="display-title">
            ปีกแห่งกาลเวลา
        </h1>
        <p class="hero-subtitle">
            ค้นหาและดาวน์โหลดภาพถ่ายความละเอียดต้นฉบับ หรือร่วมแบ่งปันผลงานของคุณโดยไม่ต้องสมัครสมาชิก ปลอดภัยด้วยการกลั่นกรองจากผู้ดูแลระบบ
        </p>

        <?php if (!empty($recentPhotos)): ?>
        <?php
            $sliderPhotos = array_slice($recentPhotos, 0, 12);
            // Ensure enough items in track so seamless -50% loop fills wide screens smoothly
            while (count($sliderPhotos) < 8) {
                $sliderPhotos = array_merge($sliderPhotos, $sliderPhotos);
            }
            $marqueeTrackPhotos = array_merge($sliderPhotos, $sliderPhotos);
        ?>
        <style>
            .hero-slider-wrap {
                position: relative;
                width: 100%;
                overflow: hidden;
                padding: 0.5rem 0 1.75rem;
                -webkit-mask-image: linear-gradient(to right, transparent 0%, black 7%, black 93%, transparent 100%);
                mask-image: linear-gradient(to right, transparent 0%, black 7%, black 93%, transparent 100%);
            }
            .hero-slider-track {
                display: flex;
                align-items: center;
                gap: 1rem;
                width: max-content;
                animation: slideLeftMarquee 38s linear infinite;
            }
            .hero-slider-wrap:hover .hero-slider-track {
                animation-play-state: paused;
            }
            .hero-slide-card {
                width: 230px;
                height: 155px;
                border-radius: 14px;
                overflow: hidden;
                flex-shrink: 0;
                position: relative;
                background: #E2E8F0;
                box-shadow: 0 8px 20px -6px rgba(15, 23, 42, 0.14);
                border: 2px solid #FFFFFF;
                transition: transform 0.25s ease, box-shadow 0.25s ease;
                display: block;
            }
            .hero-slide-card:hover {
                transform: translateY(-4px) scale(1.03);
                box-shadow: 0 14px 28px -6px rgba(15, 23, 42, 0.22);
            }
            .hero-slide-card img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }
            .hero-slide-caption {
                position: absolute;
                inset: auto 0 0 0;
                padding: 0.45rem 0.65rem;
                background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,0.68) 100%);
                color: #FFFFFF;
                font-size: 0.76rem;
                font-weight: 600;
                text-align: left;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            @keyframes slideLeftMarquee {
                0% { transform: translateX(0); }
                100% { transform: translateX(-50%); }
            }
        </style>
        <div class="hero-slider-wrap mb-2">
            <div class="hero-slider-track">
                <?php foreach ($marqueeTrackPhotos as $sp): ?>
                    <?php
                        $spThumb = BASE_URL . '/uploads/thumbnails/' . $sp['filename'];
                        $spView = BASE_URL . '/photo.php?uuid=' . $sp['uuid'];
                        $spTitle = !empty($sp['title']) ? $sp['title'] : $sp['original_name'];
                        $spOwner = !empty($sp['owner_name']) ? $sp['owner_name'] : '';
                    ?>
                    <a href="<?= $spView ?>" class="hero-slide-card" title="<?= e($spTitle) ?>">
                        <img src="<?= $spThumb ?>" alt="<?= e($spTitle) ?>" loading="lazy">
                        <div class="hero-slide-caption">
                            <div><?= e($spTitle) ?></div>
                            <?php if ($spOwner !== ''): ?>
                                <div style="font-size: 0.68rem; font-weight: 400; opacity: 0.85;">โดย <?= e($spOwner) ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pexels-Style Large Search Bar -->
        <form action="<?= BASE_URL ?>/explore.php" method="GET" class="pexels-search-box mb-4">
            <svg class="pexels-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="q" class="pexels-search-input" placeholder="ค้นหารูปภาพ ชื่อผลงาน หรือชื่อเจ้าของภาพ...">
            <button type="submit" class="pexels-search-btn">ค้นหา</button>
        </form>

        <!-- Quick Upload Trigger & Stats -->
        <div class="d-flex flex-wrap justify-content-center align-items-center gap-2 text-muted small">
            <span>คลังภาพที่ผ่านการอนุมัติแล้ว <strong><?= number_format($stats['photos']) ?></strong> รูป</span>
            <span>•</span>
            <span>ยอดเข้าชม <strong><?= number_format($stats['views']) ?></strong> ครั้ง</span>
            <span>•</span>
            <button type="button" class="btn btn-sm btn-link text-dark fw-bold text-decoration-none p-0" data-bs-toggle="collapse" data-bs-target="#quickUploadSection">
                + อัปโหลดรูปภาพของคุณที่นี่
            </button>
        </div>

        <!-- Collapsible Upload Box on Home Page (With Required Submit Button) -->
        <div class="collapse mt-4" id="quickUploadSection">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-start">
                    <div class="card border rounded-4 p-4 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">อัปโหลดรูปภาพใหม่</h5>
                            <span class="badge badge-pending rounded-pill px-3 py-1">🛡️ รอ Admin ตรวจสอบก่อนเผยแพร่</span>
                        </div>

                        <!-- Owner & Extra Details Form -->
                        <div class="row g-2 mb-3">
                            <div class="col-12 mb-1">
                                <label class="form-label small fw-semibold mb-1">ชื่อภาพ : <span class="text-danger">*</span></label>
                                <input type="text" id="photoTitleInput" class="form-control form-control-sm rounded-3" maxlength="255" placeholder="ตั้งชื่อภาพสวยๆ สำหรับแสดงบนหน้าเว็บ..." required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1">เจ้าของภาพ :</label>
                                <input type="text" id="ownerNameInput" class="form-control form-control-sm rounded-3" maxlength="150" placeholder="ชื่อเจ้าของภาพ / ช่างภาพ">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1">หมายเลขโทรศัพท์ :</label>
                                <input type="tel" id="ownerPhoneInput" class="form-control form-control-sm rounded-3" maxlength="50" placeholder="08x-xxx-xxxx">
                            </div>
                            <div class="col-12 mt-2">
                                <label class="form-label small fw-semibold mb-1">ข้อมูลเพิ่มเติม : <span class="text-danger fw-normal">(กรุณาตรวจสอบให้ถูกต้อง เมื่อส่งแล้วไม่สามารถแก้ไขหรือลบได้)</span></label>
                                <textarea id="extraInfoInput" class="form-control form-control-sm rounded-3" rows="2" placeholder="ระบุรายละเอียดภาพ สถานที่ เครดิต หรือบันทึกเพิ่มเติม..."></textarea>
                            </div>
                        </div>

                        <!-- Dropzone -->
                        <input type="file" id="fileInput" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="d-none">
                        <div class="upload-dropzone" id="uploadDropzone">
                            <div class="upload-dropzone-icon">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="16 16 12 12 8 16"></polyline>
                                    <line x1="12" y1="12" x2="12" y2="21"></line>
                                    <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path>
                                </svg>
                            </div>
                            <h3 class="h6 fw-bold mb-1">ลากและวางรูปภาพที่นี่ หรือคลิกเพื่อเลือกรูปภาพ</h3>
                            <p class="text-muted small mb-0">เมื่อเลือกรูปแล้ว กรุณากดปุ่ม <strong>"กดส่งรูปภาพ"</strong> ด้านล่างเพื่อยืนยันการอัปโหลด</p>
                            <div class="upload-meta-pills">
                                <span class="meta-pill">ขนาดไม่เกิน 15 MB / รูป</span>
                                <span class="meta-pill">JPG, PNG, WEBP, GIF</span>
                                <span class="meta-pill">วางภาพจากคลิปบอร์ด (Ctrl+V)</span>
                            </div>
                        </div>

                        <!-- Staged Upload Queue -->
                        <div id="uploadQueue" class="mt-3 d-none"></div>

                        <!-- Explicit Submit Action Bar -->
                        <div id="uploadSubmitBar" class="mt-3 pt-3 border-top d-none">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div class="small text-muted">
                                    เลือกรูปภาพแล้ว <strong id="stagedFilesCount">0</strong> รูป (จำกัดไม่เกิน 15 MB/รูป)
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" id="clearQueueBtn" class="btn-pill-secondary btn-sm">
                                        ล้างรายการ
                                    </button>
                                    <button type="button" id="submitUploadBtn" class="btn-pill-accent">
                                        กดส่งรูปภาพเพื่อรอ Admin อนุมัติ
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Results Summary Box -->
                        <div id="uploadSummary" class="mt-3 d-none"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pexels-Style Gallery Section -->
<section class="py-5">
    <div class="container">
        <!-- Category / Sort Navigation Pills -->
        <div class="pexels-nav-tabs">
            <a href="<?= BASE_URL ?>/?sort=latest" class="pexels-tab <?= $sort !== 'popular' ? 'active' : '' ?>">ภาพมาใหม่ล่าสุด</a>
            <a href="<?= BASE_URL ?>/?sort=popular" class="pexels-tab <?= $sort === 'popular' ? 'active' : '' ?>">ภาพยอดนิยม</a>
            <a href="<?= BASE_URL ?>/explore.php" class="pexels-tab">ค้นหาและกรองรูปภาพทั้งหมด</a>
            <a href="<?= BASE_URL ?>/upload.php" class="pexels-tab">+ อัปโหลดรูปภาพ</a>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 fw-bold mb-0">
                <?= $sort === 'popular' ? 'ภาพถ่ายยอดนิยม' : 'ภาพในอดีต' ?>
            </h2>
            <a href="<?= BASE_URL ?>/explore.php" class="btn-pill-secondary btn-sm">
                ดูทั้งหมด &rarr;
            </a>
        </div>

        <?php if (empty($recentPhotos)): ?>
            <div class="text-center py-5 border rounded-4 bg-light">
                <div class="text-muted mb-3">ยังไม่มีรูปภาพที่ผ่านการอนุมัติในขณะนี้ ร่วมแบ่งปันรูปภาพแรกของคุณได้เลย!</div>
                <a href="<?= BASE_URL ?>/upload.php" class="btn-pill-primary">อัปโหลดรูปภาพแรก</a>
            </div>
        <?php else: ?>
            <!-- Pexels Masonry Grid -->
            <div class="pexels-masonry">
                <?php foreach ($recentPhotos as $photo): ?>
                    <?php 
                        $thumbPath = BASE_URL . '/uploads/thumbnails/' . $photo['filename'];
                        $viewUrl = BASE_URL . '/photo.php?uuid=' . $photo['uuid'];
                        $downloadUrl = BASE_URL . '/photo.php?uuid=' . $photo['uuid'] . '&download=1';
                        $photoTitleDisplay = !empty($photo['title']) ? $photo['title'] : $photo['original_name'];
                        $ownerDisplay = !empty($photo['owner_name']) ? $photo['owner_name'] : '';
                        $avatarChar = mb_substr($ownerDisplay !== '' ? $ownerDisplay : $photoTitleDisplay, 0, 1);
                    ?>
                    <div class="pexels-item" id="photo_<?= e($photo['uuid']) ?>">
                        <a href="<?= $viewUrl ?>" class="d-block">
                            <img src="<?= $thumbPath ?>" alt="<?= e($photoTitleDisplay) ?>" class="pexels-item-img" loading="lazy" decoding="async">
                        </a>
                        <div class="pexels-item-overlay" onclick="if(event.target === this) window.location.href='<?= $viewUrl ?>';" style="cursor: pointer;">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span class="badge bg-dark bg-opacity-75 text-white rounded-pill px-3 py-1 text-truncate" style="max-width: 70%; font-size: 0.78rem; backdrop-filter: blur(4px);">
                                    <?= e($photoTitleDisplay) ?>
                                </span>
                                <span class="badge bg-dark bg-opacity-50 text-white rounded-pill px-2 py-1" style="font-size: 0.72rem; backdrop-filter: blur(4px);">
                                    <?= (int)$photo['width'] ?>×<?= (int)$photo['height'] ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <a href="<?= $viewUrl ?>" class="pexels-author-badge text-truncate">
                                    <span class="pexels-author-avatar"><?= e($avatarChar) ?></span>
                                    <div class="text-truncate">
                                        <div class="fw-bold text-truncate" style="line-height: 1.2;"><?= e($photoTitleDisplay) ?></div>
                                        <?php if ($ownerDisplay !== ''): ?>
                                            <div class="small opacity-75 text-truncate" style="font-size: 0.74rem;">โดย <?= e($ownerDisplay) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <a href="<?= $downloadUrl ?>" class="pexels-download-btn" title="ดาวน์โหลดภาพต้นฉบับ">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="7 10 12 15 17 10"></polyline>
                                        <line x1="12" y1="15" x2="12" y2="3"></line>
                                    </svg>
                                    <span>ดาวน์โหลด</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
