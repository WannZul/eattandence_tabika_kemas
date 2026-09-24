(() => {
  const app = document.getElementById('registrationApp');
  if (!app) return;

  const video = document.getElementById('cameraVideo');
  const cameraSelect = document.getElementById('cameraSelect');
  const status = document.getElementById('cameraStatus');
  const message = document.getElementById('registrationMessage');
  const captureButton = document.getElementById('captureImage');
  const saveButton = document.getElementById('saveStudent');
  const preview = document.getElementById('previewGrid');
  const countText = document.getElementById('captureCount');
  const progress = document.getElementById('captureProgress');
  const replacementMode = document.getElementById('replacementMode');
  const replacementSummary = document.getElementById('replacementSummary');
  const confirmationArea = document.getElementById('replaceConfirmationArea');
  const confirmation = document.getElementById('replaceConfirmation');
  const confirmationText = document.getElementById('replaceConfirmationText');
  const cancelReplacement = document.getElementById('cancelReplacement');
  const captureHeading = document.getElementById('captureHeading');
  const identityFields = ['studentId', 'studentName', 'studentClass'].map((id) => document.getElementById(id));
  let stream = null;
  let images = [];
  let operation = 'create';
  let selectedFaceStatus = '';

  const setMessage = (text, type = 'info') => {
    message.className = text ? `alert alert-${type}` : '';
    message.textContent = text;
  };

  const stopCamera = () => {
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
    video.srcObject = null;
    captureButton.disabled = true;
    status.textContent = 'Kamera telah ditutup.';
  };

  const loadCameras = async () => {
    const devices = await navigator.mediaDevices.enumerateDevices();
    const current = cameraSelect.value;
    cameraSelect.replaceChildren(new Option('Kamera automatik', ''));
    devices.filter((device) => device.kind === 'videoinput').forEach((camera, index) => cameraSelect.add(new Option(camera.label || `Kamera ${index + 1}`, camera.deviceId)));
    if ([...cameraSelect.options].some((option) => option.value === current)) cameraSelect.value = current;
  };

  const startCamera = async () => {
    stopCamera();
    setMessage('');
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
      setMessage('Kamera pelayar memerlukan HTTPS atau localhost dan pelayar moden.', 'error');
      return;
    }
    try {
      status.textContent = 'Meminta kebenaran kamera…';
      const selected = cameraSelect.value;
      stream = await navigator.mediaDevices.getUserMedia({video: selected ? {deviceId: {exact: selected}, width:{ideal:1280}, height:{ideal:720}} : {width:{ideal:1280}, height:{ideal:720}}, audio:false});
      video.srcObject = stream;
      await video.play();
      await loadCameras();
      const settings = stream.getVideoTracks()[0]?.getSettings() || {};
      status.textContent = `Kamera aktif${settings.width ? ` · ${settings.width}×${settings.height}` : ''}. Pandang kamera dan ubah sudut sedikit bagi setiap gambar.`;
      captureButton.disabled = images.length >= 6;
    } catch (error) {
      const descriptions = {NotAllowedError:'Kebenaran kamera ditolak. Benarkan kamera pada tetapan pelayar.',NotFoundError:'Tiada kamera dijumpai.',NotReadableError:'Kamera sedang digunakan oleh aplikasi lain.',OverconstrainedError:'Kamera pilihan tidak tersedia. Pilih kamera lain.'};
      setMessage(descriptions[error.name] || 'Kamera tidak dapat dibuka.', 'error');
      status.textContent = 'Kamera gagal dibuka.';
    }
  };

  const analyzeImage = async (canvas) => {
    if (canvas.width < 480 || canvas.height < 360) return 'Resolusi kamera terlalu rendah (minimum 480×360).';
    const sample = document.createElement('canvas');
    sample.width = 160;
    sample.height = 120;
    const sampleContext = sample.getContext('2d', {willReadFrequently:true});
    sampleContext.drawImage(canvas, 0, 0, 160, 120);
    const data = sampleContext.getImageData(0, 0, 160, 120).data;
    const gray = [];
    let sum = 0;
    for (let i = 0; i < data.length; i += 4) {
      const value = .299 * data[i] + .587 * data[i + 1] + .114 * data[i + 2];
      gray.push(value);
      sum += value;
    }
    const mean = sum / gray.length;
    let variance = 0;
    gray.forEach((value) => { variance += (value - mean) ** 2; });
    variance /= gray.length;
    let edges = 0;
    for (let y = 1; y < 119; y++) {
      for (let x = 1; x < 159; x++) {
        const i = y * 160 + x;
        edges += Math.abs(gray[i] - gray[i - 1]) + Math.abs(gray[i] - gray[i - 160]);
      }
    }
    const edgeScore = edges / (158 * 118);
    if (mean < 45) return 'Gambar terlalu gelap. Tambah pencahayaan.';
    if (mean > 215) return 'Gambar terlalu terang. Kurangkan cahaya.';
    if (Math.sqrt(variance) < 20 || edgeScore < 11) return 'Gambar kurang jelas. Stabilkan kamera dan fokuskan muka.';
    if ('FaceDetector' in window) {
      try {
        const faces = await new FaceDetector({fastMode:true,maxDetectedFaces:2}).detect(canvas);
        if (faces.length !== 1) return faces.length === 0 ? 'Muka tidak dikesan. Letakkan muka di tengah.' : 'Lebih daripada satu muka dikesan.';
        const box = faces[0].boundingBox;
        if ((box.width * box.height) / (canvas.width * canvas.height) < .08) return 'Muka terlalu jauh daripada kamera.';
      } catch (_) { /* quality checks above remain the fallback */ }
    }
    return '';
  };

  const updateProgress = () => {
    countText.textContent = `${images.length} daripada 6 gambar`;
    progress.style.width = `${images.length / 6 * 100}%`;
    captureButton.disabled = !stream || images.length >= 6;
    saveButton.disabled = images.length !== 6 || (operation === 'replace' && !confirmation.checked);
    identityFields.forEach((field) => { field.readOnly = operation === 'replace' || images.length > 0; });
  };

  const clearCaptures = () => {
    images = [];
    preview.replaceChildren();
    updateProgress();
  };

  const capture = async () => {
    if (!stream || video.readyState < 2) return;
    if (identityFields.some((field) => !field.value.trim())) {
      setMessage('Lengkapkan ID, nama dan kelas sebelum mengambil gambar.', 'error');
      return;
    }
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const context = canvas.getContext('2d', {willReadFrequently:true});
    context.translate(canvas.width, 0);
    context.scale(-1, 1);
    context.drawImage(video, 0, 0);
    const issue = await analyzeImage(canvas);
    if (issue) {
      setMessage(issue, 'error');
      return;
    }
    const image = canvas.toDataURL('image/jpeg', .86);
    images.push(image);
    const img = new Image();
    img.src = image;
    img.alt = `Gambar wajah ${images.length}`;
    preview.appendChild(img);
    updateProgress();
    setMessage(`Gambar ${images.length} berjaya diambil.`, 'success');
  };

  const reset = () => {
    clearCaptures();
    setMessage(operation === 'replace' ? 'Tangkapan dikosongkan. Identiti murid kekal dikunci dalam mod penggantian.' : 'Tangkapan dikosongkan. Anda boleh mengambil semula.', 'info');
  };

  const beginReplacement = (button) => {
    if (images.length > 0 && !window.confirm('Tangkapan semasa akan dikosongkan. Teruskan memilih murid ini?')) return;
    operation = 'replace';
    selectedFaceStatus = button.dataset.faceStatus;
    identityFields[0].value = button.dataset.studentId;
    identityFields[1].value = button.dataset.studentName;
    identityFields[2].value = button.dataset.studentClass;
    confirmation.checked = false;
    replacementSummary.textContent = `${button.dataset.studentName} (${button.dataset.studentId}), ${button.dataset.studentClass}.`;
    confirmationText.textContent = selectedFaceStatus === 'ready'
      ? 'Saya mengesahkan profil yang sedang Sedia akan dinyahaktifkan sementara dan enam imej baharu akan menggantikan imej semasa.'
      : 'Saya mengesahkan enam imej baharu ini akan menggantikan imej wajah murid yang dipilih.';
    replacementMode.hidden = false;
    confirmationArea.hidden = false;
    cancelReplacement.hidden = false;
    captureHeading.textContent = '6 imej gantian baharu';
    saveButton.textContent = 'Ganti enam imej';
    clearCaptures();
    setMessage('Mod penggantian aktif. Ambil enam imej baharu, kemudian tandakan pengesahan sebelum menyimpan.', 'warning');
    replacementMode.scrollIntoView({behavior:'smooth', block:'start'});
  };

  const cancelReplace = () => {
    operation = 'create';
    selectedFaceStatus = '';
    confirmation.checked = false;
    replacementMode.hidden = true;
    confirmationArea.hidden = true;
    cancelReplacement.hidden = true;
    captureHeading.textContent = '6 sudut wajah';
    saveButton.textContent = 'Simpan murid';
    clearCaptures();
    identityFields.forEach((field) => {
      field.value = '';
      field.readOnly = false;
    });
    setMessage('Penggantian dibatalkan. Borang kembali ke mod pendaftaran murid baharu.', 'info');
  };

  const save = async () => {
    if (operation === 'replace' && !confirmation.checked) {
      setMessage('Sahkan penggantian imej sebelum menyimpan.', 'error');
      return;
    }
    saveButton.disabled = true;
    setMessage(operation === 'replace' ? 'Menggantikan enam imej secara selamat…' : 'Menyimpan murid dan imej secara selamat…', 'info');
    const payload = {
      operation,
      student_id: identityFields[0].value,
      student_name: identityFields[1].value,
      student_class: identityFields[2].value,
      images
    };
    if (operation === 'replace') {
      payload.replace_confirmed = confirmation.checked;
      payload.ready_replace_confirmed = selectedFaceStatus === 'ready' && confirmation.checked;
    }
    try {
      const response = await fetch('save_student.php', {
        method:'POST',
        headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':app.dataset.csrf},
        body:JSON.stringify(payload)
      });
      if (response.status === 401 || response.status === 403) {
        let authMessage = 'Sesi anda telah tamat atau akses tidak lagi sah. Log masuk semula, kemudian ambil semula gambar jika perlu.';
        const authType = response.headers.get('content-type') || '';
        if (authType.includes('application/json')) {
          try {
            const authResult = await response.json();
            if (authResult.message) authMessage = `${authResult.message} Log masuk semula dan ulang penghantaran.`;
          } catch (_) { /* keep fixed reauthentication instruction */ }
        }
        throw new Error(authMessage);
      }
      const contentType = response.headers.get('content-type') || '';
      if (!contentType.includes('application/json')) {
        throw new Error('Pelayan mengembalikan respons yang tidak sah. Muat semula halaman dan log masuk semula jika sesi tamat.');
      }
      let result;
      try {
        result = await response.json();
      } catch (_) {
        throw new Error('Respons pelayan tidak dapat dibaca. Cuba lagi tanpa menutup halaman ini.');
      }
      if (!response.ok || !result.success) throw new Error(result.message || 'Simpanan gagal.');
      setMessage(result.message, 'success');
      stopCamera();
      images = [];
      preview.replaceChildren();
      identityFields.forEach((field) => { field.value = ''; field.readOnly = false; });
      operation = 'create';
      selectedFaceStatus = '';
      confirmation.checked = false;
      replacementMode.hidden = true;
      confirmationArea.hidden = true;
      cancelReplacement.hidden = true;
      captureHeading.textContent = '6 sudut wajah';
      saveButton.textContent = 'Simpan murid';
      updateProgress();
      window.setTimeout(() => window.location.reload(), 1200);
    } catch (error) {
      setMessage(error.message || 'Ralat rangkaian semasa menyimpan.', 'error');
      updateProgress();
    }
  };

  document.getElementById('startCamera').addEventListener('click', startCamera);
  document.getElementById('stopCamera').addEventListener('click', stopCamera);
  captureButton.addEventListener('click', capture);
  document.getElementById('resetImages').addEventListener('click', reset);
  saveButton.addEventListener('click', save);
  cancelReplacement.addEventListener('click', cancelReplace);
  confirmation.addEventListener('change', updateProgress);
  document.querySelectorAll('.replace-face-images').forEach((button) => button.addEventListener('click', () => beginReplacement(button)));
  cameraSelect.addEventListener('change', () => { if (stream) startCamera(); });
  window.addEventListener('beforeunload', stopCamera);
  updateProgress();
})();
