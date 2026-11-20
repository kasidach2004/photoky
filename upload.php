<?php
/**
 * PhotoKY - Dedicated Upload Studio
 * - No Folders (Direct upload to gallery)
 * - Max 15 MB per file
 * - Explicit Submit Button ("กดส่งรูปภาพ")
 * - Admin safety review before public display
 */

$pageTitle = 'อัปโหลดรูปภาพ';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center mb-4">
                <h1 class="h2 fw-bold mb-2">อัปโหลดและส่งรูปภาพ</h1>
                <p class="text-muted mb-0">
                    รองรับไฟล์ภาพ JPG, JPEG, PNG, WEBP, GIF ขนาดไม่เกิน <strong>15 MB</strong> ต่อรูป
                </p>
            </div>

            <!-- Security & Approval Notice Banner -->
            <div class="alert border rounded-4 p-3 mb-4 d-flex align-items-center gap-3" style="background: #F8FAFC; border-color: #E2E8F0 !important;">
                <div class="text-success flex-shrink-0">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                </div>
                <div class="small text-secondary" style="line-height: 1.6;">
                    <strong class="text-dark">ขั้นตอนการตรวจสอบความปลอดภัย (Admin Review):</strong>
                    เมื่อท่านเลือกรูปภาพและกดปุ่ม <strong>"กดส่งรูปภาพ"</strong> ระบบจะจัดเก็บภาพเข้าสู่คิวรอให้ Admin ตรวจสอบและอนุมัติก่อนเผยแพร่ออกสู่หน้าเว็บสาธารณะ เพื่อป้องกันการโจมตีและไฟล์อันตราย
                </div>
            </div>

            <!-- Step 1: Owner & Additional Information Card -->
            <div class="card border rounded-4 p-4 mb-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-bold text-dark">1. ข้อมูลเจ้าของภาพและรายละเอียด <span class="text-danger fw-normal small">(กรุณาตรวจสอบให้ถูกต้อง เมื่อส่งแล้วไม่สามารถแก้ไขหรือลบได้)</span></span>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="isPublicToggle" checked>
                        <label class="form-check-label small text-muted" for="isPublicToggle">
                            เผยแพร่หน้าเว็บเมื่ออนุมัติ
                        </label>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="photoTitleInput" class="form-label small fw-semibold mb-1">ชื่อภาพ : <span class="text-danger">*</span></label>
                        <input type="text" id="photoTitleInput" class="form-control rounded-3" maxlength="255" placeholder="ตั้งชื่อภาพสวยๆ สำหรับแสดงบนหน้าเว็บ เช่น ความทรงจำวัยเรียน, รอยยิ้มวันวาน..." required>
                    </div>
                    <div class="col-md-6">
                        <label for="ownerNameInput" class="form-label small fw-semibold mb-1">เจ้าของภาพ :</label>
                        <input type="text" id="ownerNameInput" class="form-control rounded-3" maxlength="150" placeholder="ระบุชื่อเจ้าของภาพ / ช่างภาพ">
                    </div>
                    <div class="col-md-6">
                        <label for="ownerPhoneInput" class="form-label small fw-semibold mb-1">หมายเลขโทรศัพท์ :</label>
                        <input type="tel" id="ownerPhoneInput" class="form-control rounded-3" maxlength="50" placeholder="เบอร์โทรศัพท์ติดต่อ เช่น 08x-xxx-xxxx">
                    </div>
                    <div class="col-12">
                        <label for="extraInfoInput" class="form-label small fw-semibold mb-1">ข้อมูลเพิ่มเติม :</label>
                        <textarea id="extraInfoInput" class="form-control rounded-3" rows="2" placeholder="ระบุรายละเอียดเพิ่มเติม เช่น สถานที่ถ่ายภาพ, เครดิต, สิทธิ์การใช้งาน หรือข้อความอื่นๆ..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Step 2: Select / Drag & Drop Images -->
            <div class="card border rounded-4 p-4 shadow-sm">
                <div class="fw-bold text-dark mb-3">2. เลือกรูปภาพและกดส่ง</div>

                <input type="file" id="fileInput" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="d-none">
                
                <div class="upload-dropzone" id="uploadDropzone">
                    <div class="upload-dropzone-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="16 16 12 12 8 16"></polyline>
                            <line x1="12" y1="12" x2="12" y2="21"></line>
                            <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path>
                        </svg>
                    </div>
                    <h3 class="h5 fw-bold mb-1">ลากและวางรูปภาพที่นี่ หรือคลิกเพื่อเลือกไฟล์</h3>
                    <p class="text-muted small mb-0">เลือกรูปภาพได้หลายไฟล์พร้อมกัน แล้วกดปุ่ม <strong>"กดส่งรูปภาพ"</strong> ด้านล่าง</p>
                    <div class="upload-meta-pills mt-3">
                        <span class="meta-pill">สูงสุดไม่เกิน 15 MB / รูป</span>
                        <span class="meta-pill">JPG, JPEG, PNG, WEBP, GIF</span>
                        <span class="meta-pill">วางภาพด่วน (Ctrl+V)</span>
                    </div>
                </div>

                <!-- Staged Upload Queue -->
                <div id="uploadQueue" class="mt-4 text-start d-none"></div>

                <!-- Explicit Submit Button Bar -->
                <div id="uploadSubmitBar" class="mt-4 pt-3 border-top d-none">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="small text-secondary">
                            รูปภาพที่รอการกดส่ง: <strong class="text-dark" id="stagedFilesCount">0</strong> รูป
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" id="clearQueueBtn" class="btn-pill-secondary">
                                ล้างรายการที่เลือก
                            </button>
                            <button type="button" id="submitUploadBtn" class="btn-pill-accent px-4">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path d="M22 2L11 13"></path>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                                <span>กดส่งรูปภาพเพื่อรอ Admin อนุมัติ</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload Summary -->
            <div id="uploadSummary" class="mt-4 text-start d-none">
                <div class="mb-3">
                    <h5 class="fw-bold mb-0">รูปภาพที่ส่งเข้าสู่ระบบแล้ว</h5>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
