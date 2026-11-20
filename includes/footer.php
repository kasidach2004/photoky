</main>

<!-- Footer -->
<footer class="footer-photoky">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <a href="<?= BASE_URL ?>/" class="brand-logo mb-2 d-inline-block">
                    photo<span>ky.</span>
                </a>
                <p class="text-muted small mb-0">
                    ระบบคลังภาพออนไลน์และแบ่งปันรูปภาพคุณภาพสูง ฟรี ไม่ต้องสมัครสมาชิก ปลอดภัยด้วยการตรวจสอบก่อนเผยแพร่
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <div class="d-flex justify-content-center justify-content-md-end gap-3 mb-2">
                    <a href="<?= BASE_URL ?>/" class="footer-link">หน้าหลัก</a>
                    <a href="<?= BASE_URL ?>/explore.php" class="footer-link">สำรวจรูปภาพ</a>
                    <a href="<?= BASE_URL ?>/upload.php" class="footer-link">อัปโหลดรูปภาพ</a>
                    <a href="<?= BASE_URL ?>/admin/login.php" class="footer-link">ผู้ดูแลระบบ</a>
                </div>
                <div class="text-muted small">
                    &copy; <?= date('Y') ?> PhotoKY. ระบบจัดเก็บและแบ่งปันรูปภาพออนไลน์ปลอดภัยสูง (สูงสุดไม่เกิน 15 MB ต่อรูป)<bR>
                    <p>พัฒนาโดย:กษิด์เดช ปราบพาล</p>
                              <p>เจ้าของงาน ครูกรรณิกา มีลา</p>
          
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.all.min.js"></script>

<!-- App Scripts -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/uploader.js"></script>

</body>
</html>
