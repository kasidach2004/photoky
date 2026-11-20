/**
 * PhotoKY - Staged Drag & Drop Image Uploader with Explicit Submit Button
 * - Max 15 MB per file
 * - Previews selected files in queue first
 * - Uploads only when the user clicks "กดส่งรูปภาพ (Submit & Upload)"
 */

class PhotoUploader {
  constructor(options = {}) {
    this.dropzone = document.querySelector(options.dropzone || '#uploadDropzone');
    this.fileInput = document.querySelector(options.fileInput || '#fileInput');
    this.queueContainer = document.querySelector(options.queueContainer || '#uploadQueue');
    this.submitBar = document.querySelector(options.submitBar || '#uploadSubmitBar');
    this.submitBtn = document.querySelector(options.submitBtn || '#submitUploadBtn');
    this.clearBtn = document.querySelector(options.clearBtn || '#clearQueueBtn');
    this.summaryContainer = document.querySelector(options.summaryContainer || '#uploadSummary');
    this.privacyToggle = document.querySelector(options.privacyToggle || '#isPublicToggle');

    this.maxSize = 15 * 1024 * 1024; // Strict 15 MB max per image
    this.allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    this.queue = [];
    this.activeXhrs = new Map();
    this.isUploading = false;

    if (this.dropzone && this.fileInput) {
      this.initEvents();
    }
  }

  initEvents() {
    ['dragenter', 'dragover'].forEach(eventName => {
      this.dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.dropzone.classList.add('dragover');
      });
    });

    ['dragleave', 'drop'].forEach(eventName => {
      this.dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.dropzone.classList.remove('dragover');
      });
    });

    this.dropzone.addEventListener('drop', (e) => {
      const files = e.dataTransfer.files;
      if (files && files.length > 0) {
        this.handleFiles(files);
      }
    });

    this.dropzone.addEventListener('click', (e) => {
      if (e.target.closest('button, a, input, label, textarea')) return;
      this.fileInput.click();
    });

    this.fileInput.addEventListener('change', () => {
      if (this.fileInput.files && this.fileInput.files.length > 0) {
        this.handleFiles(this.fileInput.files);
        this.fileInput.value = '';
      }
    });

    if (this.submitBtn) {
      this.submitBtn.addEventListener('click', () => {
        this.startUpload();
      });
    }

    if (this.clearBtn) {
      this.clearBtn.addEventListener('click', () => {
        this.clearPendingQueue();
      });
    }

    // Paste from clipboard support (Ctrl+V)
    window.addEventListener('paste', (e) => {
      if (e.target && ['INPUT', 'TEXTAREA'].includes(e.target.tagName)) return;
      const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items;
      if (!items) return;
      const files = [];
      for (let item of items) {
        if (item.kind === 'file' && item.type.startsWith('image/')) {
          files.push(item.getAsFile());
        }
      }
      if (files.length > 0) {
        this.handleFiles(files);
      }
    });
  }

  handleFiles(files) {
    const validFiles = [];

    for (let file of files) {
      const ext = file.name.split('.').pop().toLowerCase();

      // Strict 15 MB check
      if (file.size > this.maxSize) {
        Swal.fire({
          icon: 'error',
          title: 'ไฟล์มีขนาดเกิน 15 MB',
          text: `ไฟล์ "${file.name}" มีขนาด ${(file.size / 1024 / 1024).toFixed(2)} MB (จำกัดไม่เกิน 15 MB ต่อรูปเพื่อประสิทธิภาพของเซิร์ฟเวอร์)`,
          confirmButtonText: 'ตกลง',
          customClass: { confirmButton: 'btn-pill-primary' },
          buttonsStyling: false
        });
        continue;
      }

      if (!this.allowedExtensions.includes(ext)) {
        Swal.fire({
          icon: 'error',
          title: 'ไม่รองรับนามสกุลไฟล์นี้',
          text: `ไฟล์ "${file.name}" ไม่ได้รับอนุญาต รองรับเฉพาะ JPG, JPEG, PNG, WEBP และ GIF เท่านั้น`,
          confirmButtonText: 'ตกลง',
          customClass: { confirmButton: 'btn-pill-primary' },
          buttonsStyling: false
        });
        continue;
      }

      validFiles.push(file);
    }

    if (validFiles.length > 0) {
      validFiles.forEach(file => this.enqueueFile(file));
      this.updateSubmitBar();
    }
  }

  enqueueFile(file) {
    const fileId = 'file_' + Math.random().toString(36).substring(2, 11);
    const item = {
      id: fileId,
      file: file,
      status: 'staged', // staged, uploading, success, error, cancelled
      progress: 0,
      previewUrl: URL.createObjectURL(file)
    };

    this.queue.push(item);
    this.renderQueueItem(item);
  }

  renderQueueItem(item) {
    if (!this.queueContainer) return;
    this.queueContainer.classList.remove('d-none');

    const formattedSize = (item.file.size / 1024 / 1024).toFixed(2) + ' MB';
    const defaultTitleEl = document.querySelector('#photoTitleInput');
    const prefillTitle = defaultTitleEl ? defaultTitleEl.value.trim() : '';
    const html = `
      <div class="upload-item" id="${item.id}">
        <img src="${item.previewUrl}" class="upload-item-thumb" alt="Preview">
        <div class="upload-item-info">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="upload-item-name text-muted small">ไฟล์: ${this.escapeHtml(item.file.name)} (${formattedSize})</span>
            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1" id="${item.id}_status" style="font-size: 0.74rem;">รอการกดส่ง (Ready)</span>
          </div>
          <div class="mb-2">
            <input type="text" class="form-control form-control-sm rounded-2" id="${item.id}_custom_title" value="${this.escapeHtml(prefillTitle)}" maxlength="255" placeholder="ตั้งชื่อภาพนี้ (หากเว้นว่างจะใช้ช่อง 'ชื่อภาพ' ด้านบน)...">
          </div>
          <div class="progress progress-thin">
            <div class="progress-bar" id="${item.id}_bar" style="width: 0%"></div>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-circle p-1" style="width:28px;height:28px;line-height:1;" onclick="window.uploader.cancelUpload('${item.id}')" title="นำออก">
          ✕
        </button>
      </div>
    `;

    this.queueContainer.insertAdjacentHTML('beforeend', html);
  }

  updateSubmitBar() {
    const stagedCount = this.queue.filter(i => i.status === 'staged').length;
    const countSpan = document.getElementById('stagedFilesCount');
    if (countSpan) countSpan.innerText = stagedCount;

    if (this.submitBar) {
      if (stagedCount > 0 || this.isUploading) {
        this.submitBar.classList.remove('d-none');
      } else {
        this.submitBar.classList.add('d-none');
      }
    }

    if (this.submitBtn) {
      this.submitBtn.disabled = stagedCount === 0 || this.isUploading;
      if (this.isUploading) {
        this.submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>กำลังอัปโหลดและส่งตรวจสอบ...`;
      } else {
        this.submitBtn.innerHTML = `
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M22 2L11 13"></path>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
          <span>กดส่งรูปภาพ (${stagedCount} รูป) เพื่อรอ Admin อนุมัติ</span>
        `;
      }
    }
  }

  startUpload() {
    const stagedItems = this.queue.filter(item => item.status === 'staged');
    if (stagedItems.length === 0) {
      Swal.fire('แจ้งเตือน', 'กรุณาเลือกรูปภาพก่อนกดส่ง', 'info');
      return;
    }

    // Require a Photo Title (either in main #photoTitleInput or per-file title input) so raw filenames aren't used
    const mainTitleEl = document.querySelector('#photoTitleInput');
    const mainTitleVal = mainTitleEl ? mainTitleEl.value.trim() : '';
    for (let item of stagedItems) {
      const perFileTitleEl = document.getElementById(`${item.id}_custom_title`);
      const perFileVal = perFileTitleEl ? perFileTitleEl.value.trim() : '';
      if (!mainTitleVal && !perFileVal) {
        Swal.fire({
          icon: 'warning',
          title: 'กรุณาตั้งชื่อภาพ',
          text: 'โปรดระบุ "ชื่อภาพ" ก่อนกดส่ง เพื่อให้เวลาแสดงผลบนหน้าเว็บเห็นชื่อภาพที่สวยงามแทนชื่อไฟล์เดิม',
          confirmButtonText: 'ตกลง',
          customClass: { confirmButton: 'btn-pill-primary' },
          buttonsStyling: false
        }).then(() => {
          if (mainTitleEl) mainTitleEl.focus();
          else if (perFileTitleEl) perFileTitleEl.focus();
        });
        return;
      }
    }

    this.isUploading = true;
    this.updateSubmitBar();
    this.processQueue();
  }

  processQueue() {
    const nextItem = this.queue.find(item => item.status === 'staged');
    if (!nextItem) {
      this.isUploading = false;
      this.updateSubmitBar();
      return;
    }

    this.uploadSingleFile(nextItem);
  }

  uploadSingleFile(item) {
    item.status = 'uploading';
    const statusBar = document.getElementById(`${item.id}_bar`);
    const statusText = document.getElementById(`${item.id}_status`);

    if (statusText) {
      statusText.innerText = 'กำลังส่ง 0%';
      statusText.className = 'badge bg-primary-subtle text-primary rounded-pill px-2 py-1';
    }

    const formData = new FormData();
    formData.append('photos[]', item.file);

    if (this.privacyToggle) {
      formData.append('is_public', this.privacyToggle.checked ? '1' : '0');
    }

    const ownerNameEl = document.querySelector('#ownerNameInput');
    const ownerPhoneEl = document.querySelector('#ownerPhoneInput');
    const extraInfoEl = document.querySelector('#extraInfoInput');
    const mainTitleEl = document.querySelector('#photoTitleInput');
    const perFileTitleEl = document.getElementById(`${item.id}_custom_title`);

    const chosenTitle = (perFileTitleEl && perFileTitleEl.value.trim())
      ? perFileTitleEl.value.trim()
      : (mainTitleEl ? mainTitleEl.value.trim() : '');

    if (chosenTitle) {
      formData.append('title', chosenTitle);
    }
    if (ownerNameEl && ownerNameEl.value.trim()) {
      formData.append('owner_name', ownerNameEl.value.trim());
    }
    if (ownerPhoneEl && ownerPhoneEl.value.trim()) {
      formData.append('owner_phone', ownerPhoneEl.value.trim());
    }
    if (extraInfoEl && extraInfoEl.value.trim()) {
      formData.append('extra_info', extraInfoEl.value.trim());
    }

    const xhr = new XMLHttpRequest();
    this.activeXhrs.set(item.id, xhr);

    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable) {
        const percent = Math.round((e.loaded / e.total) * 100);
        item.progress = percent;
        if (statusBar) statusBar.style.width = percent + '%';
        if (statusText) statusText.innerText = `กำลังส่ง ${percent}%`;
      }
    };

    xhr.onload = () => {
      this.activeXhrs.delete(item.id);
      if (xhr.status === 200) {
        try {
          const res = JSON.parse(xhr.responseText);
          if (res.success && res.photos && res.photos.length > 0) {
            item.status = 'success';
            if (statusBar) statusBar.style.width = '100%';
            if (statusText) {
              statusText.innerText = 'ส่งสำเร็จ (รอ Admin อนุมัติ)';
              statusText.className = 'badge badge-pending rounded-pill px-2 py-1';
            }

            res.photos.forEach(photo => {
              this.showUploadedResult(photo);
            });
          } else {
            const errMsg = (res.errors && res.errors[0] && res.errors[0].error) || res.error || 'อัปโหลดไม่สำเร็จ';
            this.handleUploadError(item, errMsg);
          }
        } catch (e) {
          this.handleUploadError(item, 'เซิร์ฟเวอร์ตอบกลับผิดพลาด');
        }
      } else {
        let serverErr = `ข้อผิดพลาดจากเซิร์ฟเวอร์ (${xhr.status})`;
        try {
          const errJson = JSON.parse(xhr.responseText);
          if (errJson.error) serverErr = errJson.error;
          else if (errJson.errors && errJson.errors[0]) serverErr = errJson.errors[0].error;
        } catch (e) {}
        this.handleUploadError(item, serverErr);
      }

      this.processQueue();
    };

    xhr.onerror = () => {
      this.activeXhrs.delete(item.id);
      this.handleUploadError(item, 'การเชื่อมต่อเครือข่ายขัดข้อง');
      this.processQueue();
    };

    xhr.open('POST', (window.BASE_URL || '') + '/api/upload.php', true);
    xhr.send(formData);
  }

  handleUploadError(item, errorMsg) {
    item.status = 'error';
    const statusText = document.getElementById(`${item.id}_status`);
    const statusBar = document.getElementById(`${item.id}_bar`);
    if (statusText) {
      statusText.innerText = 'ไม่สำเร็จ';
      statusText.className = 'badge badge-rejected rounded-pill px-2 py-1';
      statusText.title = errorMsg;
    }
    if (statusBar) {
      statusBar.style.backgroundColor = '#DC2626';
    }

    Swal.fire({
      icon: 'error',
      title: 'อัปโหลดไม่สำเร็จ',
      text: `${item.file.name}: ${errorMsg}`,
      confirmButtonText: 'ตกลง',
      customClass: { confirmButton: 'btn-pill-primary' },
      buttonsStyling: false
    });
  }

  cancelUpload(fileId) {
    const item = this.queue.find(i => i.id === fileId);
    if (!item) return;

    if (this.activeXhrs.has(fileId)) {
      this.activeXhrs.get(fileId).abort();
      this.activeXhrs.delete(fileId);
    }

    item.status = 'cancelled';
    this.queue = this.queue.filter(i => i.id !== fileId);
    const el = document.getElementById(fileId);
    if (el) el.remove();

    if (this.queue.length === 0 && this.queueContainer) {
      this.queueContainer.classList.add('d-none');
    }

    this.updateSubmitBar();
    if (this.isUploading) {
      this.processQueue();
    }
  }

  clearPendingQueue() {
    const stagedIds = this.queue.filter(i => i.status === 'staged').map(i => i.id);
    stagedIds.forEach(id => this.cancelUpload(id));
  }

  showUploadedResult(photo) {
    if (!this.summaryContainer) return;
    this.summaryContainer.classList.remove('d-none');

    const isPending = photo.is_approved === 0 || photo.is_approved === false;
    const statusNotice = isPending
      ? `<div class="mb-2"><span class="badge badge-pending px-2 py-1 rounded-pill" style="font-size:0.75rem;">⏳ รอ Admin ตรวจสอบและอนุมัติ (Pending Approval)</span><div class="text-muted small mt-1" style="font-size: 0.8rem;">บันทึกภาพและข้อมูลเรียบร้อยแล้ว ภาพจะแสดงบนหน้าเว็บไซต์สาธารณะหลังจาก Admin ตรวจสอบความปลอดภัยเสร็จสิ้น</div></div>`
      : `<div class="mb-2"><span class="badge badge-approved px-2 py-1 rounded-pill" style="font-size:0.75rem;">✓ อนุมัติแล้ว (Approved)</span></div>`;

    const displayTitle = photo.title || photo.original_name;
    const card = document.createElement('div');
    card.className = 'card mb-3 p-3 rounded-4';
    card.innerHTML = `
      <div class="row g-3 align-items-center">
        <div class="col-auto">
          <img src="${photo.thumb_url}" class="rounded-3 border" style="width: 76px; height: 76px; object-fit: cover;" alt="">
        </div>
        <div class="col">
          <div class="fw-bold text-truncate mb-1">${this.escapeHtml(displayTitle)}</div>
          <div class="text-muted small mb-1">${photo.formatted_size} • ${photo.width}×${photo.height} px</div>
          ${statusNotice}
          <div class="d-flex flex-wrap gap-2 mt-2">
            <a href="${photo.view_url}" class="btn btn-sm btn-pill-primary">เปิดดูรูปภาพ</a>
            <button class="btn btn-sm btn-pill-secondary" onclick="window.copyToClipboard('${photo.view_url}', 'คัดลอกลิงก์รูปภาพแล้ว!')">คัดลอกลิงก์</button>
          </div>
        </div>
      </div>
    `;
    this.summaryContainer.prepend(card);
  }

  escapeHtml(str) {
    const div = document.createElement('div');
    div.innerText = str;
    return div.innerHTML;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  if (document.querySelector('#uploadDropzone')) {
    window.uploader = new PhotoUploader();
  }
});
