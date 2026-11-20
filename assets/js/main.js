/**
 * PhotoKY - Global JavaScript Utilities & Modal Integrations
 */

window.showToast = function (msg, icon = 'success') {
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2400,
    timerProgressBar: true
  });
  Toast.fire({
    icon: icon,
    title: msg
  });
};

window.copyToClipboard = function (text, successMsg = 'คัดลอกลงคลิปบอร์ดแล้ว!') {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => {
      window.showToast(successMsg);
    }).catch(() => {
      window.fallbackCopy(text, successMsg);
    });
  } else {
    window.fallbackCopy(text, successMsg);
  }
};

window.fallbackCopy = function (text, successMsg) {
  const textArea = document.createElement("textarea");
  textArea.value = text;
  textArea.style.position = "fixed";
  textArea.style.left = "-999999px";
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  try {
    document.execCommand('copy');
    window.showToast(successMsg);
  } catch (err) {
    prompt("คัดลอกลิงก์: Ctrl+C, Enter", text);
  }
  document.body.removeChild(textArea);
};

window.showEmbedModal = function (uuid, directUrl, viewUrl) {
  const markdownCode = `![PhotoKY Image](${directUrl})`;
  const htmlCode = `<a href="${viewUrl}" target="_blank"><img src="${directUrl}" alt="PhotoKY Image" /></a>`;
  const bbCode = `[url=${viewUrl}][img]${directUrl}[/img][/url]`;

  Swal.fire({
    title: 'แชร์และโค้ดสำหรับฝังรูปภาพ',
    html: `
      <div class="text-start mb-3">
        <label class="form-label small fw-semibold text-muted">ลิงก์รูปภาพโดยตรง</label>
        <div class="input-group mb-2">
          <input type="text" readonly class="form-control code-copy-input" value="${directUrl}" id="embed_direct">
          <button class="btn btn-outline-secondary" onclick="window.copyToClipboard('${directUrl}', 'คัดลอกลิงก์แล้ว!')">คัดลอก</button>
        </div>

        <label class="form-label small fw-semibold text-muted">โค้ด Markdown</label>
        <div class="input-group mb-2">
          <input type="text" readonly class="form-control code-copy-input" value="${markdownCode}" id="embed_md">
          <button class="btn btn-outline-secondary" onclick="window.copyToClipboard('${markdownCode.replace(/'/g, "\\'")}', 'คัดลอกโค้ดแล้ว!')">คัดลอก</button>
        </div>

        <label class="form-label small fw-semibold text-muted">โค้ด HTML</label>
        <div class="input-group mb-2">
          <input type="text" readonly class="form-control code-copy-input" value='${htmlCode}' id="embed_html">
          <button class="btn btn-outline-secondary" onclick="window.copyToClipboard('${htmlCode.replace(/'/g, "\\'")}', 'คัดลอกโค้ดแล้ว!')">คัดลอก</button>
        </div>

        <label class="form-label small fw-semibold text-muted">โค้ด BBCode (เว็บบอร์ด)</label>
        <div class="input-group mb-2">
          <input type="text" readonly class="form-control code-copy-input" value="${bbCode}" id="embed_bb">
          <button class="btn btn-outline-secondary" onclick="window.copyToClipboard('${bbCode.replace(/'/g, "\\'")}', 'คัดลอกโค้ดแล้ว!')">คัดลอก</button>
        </div>
      </div>
    `,
    showConfirmButton: true,
    confirmButtonText: 'ปิดหน้าต่าง',
    customClass: {
      confirmButton: 'btn-pill-primary'
    },
    buttonsStyling: false,
    width: 600
  });
};

window.confirmDeletePhoto = function (uuid, token = null, redirectUrl = null) {
  if (!token && window.Keychain) {
    token = Keychain.getPhotoToken(uuid);
  }

  Swal.fire({
    title: 'ยืนยันการลบรูปภาพนี้?',
    text: 'เมื่อลบแล้วไฟล์รูปภาพและข้อมูลทั้งหมดจะถูกลบออกจากระบบอย่างถาวร',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'ยืนยัน ลบรูปภาพ',
    cancelButtonText: 'ยกเลิก',
    customClass: {
      confirmButton: 'btn-pill-danger me-2',
      cancelButton: 'btn-pill-secondary'
    },
    buttonsStyling: false
  }).then((result) => {
    if (result.isConfirmed) {
      const formData = new FormData();
      formData.append('uuid', uuid);
      if (token) formData.append('token', token);

      fetch((window.BASE_URL || '') + '/api/delete.php', {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            if (window.Keychain) Keychain.removePhoto(uuid);
            Swal.fire({
              icon: 'success',
              title: 'ลบสำเร็จ',
              text: 'ลบรูปภาพออกจากระบบเรียบร้อยแล้ว',
              timer: 1400,
              showConfirmButton: false
            }).then(() => {
              if (redirectUrl) {
                window.location.href = redirectUrl;
              } else {
                const card = document.getElementById('photo_' + uuid);
                if (card) card.remove();
                else location.reload();
              }
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'ลบไม่สำเร็จ',
              text: data.error || 'ไม่สามารถลบรูปภาพได้ รหัสจัดการไม่ถูกต้อง',
              customClass: { confirmButton: 'btn-pill-primary' },
              buttonsStyling: false
            });
          }
        })
        .catch(err => {
          Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย', 'error');
        });
    }
  });
};
