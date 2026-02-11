(function(){
  if (!window.DICOM_VIEWER_BOOTSTRAP || !window.DICOM_VIEWER_BOOTSTRAP.patientId) return;

  const cfg = window.DICOM_VIEWER_BOOTSTRAP;
  const app = document.getElementById('dicom-app');
  const viewportEl = document.getElementById('dicom-viewport');
  const studyListEl = document.getElementById('dicom-study-list');
  const sliderEl = document.getElementById('dicom-slice-slider');
  const labelEl = document.getElementById('dicom-slice-label');
  const overlayEl = document.getElementById('dicom-overlay');
  const uploadForm = document.getElementById('dicom-upload-form');
  const expertiseForm = document.getElementById('dicom-expertise-form');

  let currentImageIds = [];
  let currentIndex = 0;
  let currentMeta = [];

  cornerstoneWADOImageLoader.external.cornerstone = cornerstone;
  cornerstoneWADOImageLoader.external.dicomParser = dicomParser;
  cornerstoneTools.external.cornerstone = cornerstone;
  cornerstoneTools.external.Hammer = Hammer;
  cornerstoneTools.external.cornerstoneMath = cornerstoneMath;

  cornerstoneTools.init({
    mouseEnabled: true,
    touchEnabled: true,
    globalToolSyncEnabled: false,
    showSVGCursors: true
  });

  cornerstone.enable(viewportEl);
  cornerstoneTools.addTool(cornerstoneTools.WwwcTool);
  cornerstoneTools.addTool(cornerstoneTools.PanTool);
  cornerstoneTools.addTool(cornerstoneTools.ZoomTool);
  cornerstoneTools.addStackStateManager(viewportEl, ['stack']);

  function qs(params){
    return new URLSearchParams(params).toString();
  }

  function fetchJson(params){
    return fetch(cfg.apiUrl + '?' + qs(params), {credentials: 'same-origin'}).then(r => r.json());
  }


  async function saveExpertise(){
    if (!expertiseForm) return;
    const fd = new FormData(expertiseForm);
    fd.append('action', 'save_expertise');
    fd.append('patient_id', String(cfg.patientId));
    const res = await fetch(cfg.apiUrl, {method: 'POST', body: fd, credentials: 'same-origin'});
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Gagal menyimpan ekspertise');
    return json;
  }

  function setActiveTool(name){
    ['Wwwc','Pan','Zoom'].forEach(t => cornerstoneTools.setToolPassive(t));
    cornerstoneTools.setToolActive(name, { mouseButtonMask: 1 });
    app.querySelectorAll('[data-tool]').forEach(btn => btn.classList.toggle('active', btn.dataset.tool === name));
  }

  function renderOverlay(meta, image){
    if (!meta) return;
    let ww = '-';
    let wl = '-';
    if (image && image.windowWidth && image.windowCenter) {
      ww = Math.round(image.windowWidth);
      wl = Math.round(image.windowCenter);
    }
    overlayEl.textContent = [
      `Patient: ${meta.patient_name || '-'}`,
      `StudyDate: ${meta.study_date || '-'}`,
      `Modality: ${meta.modality || '-'}`,
      `Series: ${meta.series_description || '-'}`,
      `Instance#: ${meta.instance_number || '-'}`,
      `WW/WL: ${ww}/${wl}`
    ].join('\n');
  }

  async function showImage(index){
    if (!currentImageIds.length) return;
    currentIndex = Math.max(0, Math.min(index, currentImageIds.length - 1));
    const image = await cornerstone.loadAndCacheImage(currentImageIds[currentIndex]);
    cornerstone.displayImage(viewportEl, image);
    renderOverlay(currentMeta[currentIndex], image);
    sliderEl.value = String(currentIndex + 1);
    labelEl.textContent = `Slice ${currentIndex + 1}/${currentImageIds.length}`;
  }

  async function loadInstances(seriesId){
    const res = await fetchJson({action: 'list_instances', series_id: seriesId});
    if (!res.ok) throw new Error(res.error || 'Gagal memuat instance');
    currentMeta = res.data || [];
    currentImageIds = currentMeta.map(i => i.image_id);
    if (!currentImageIds.length) throw new Error('Series tidak memiliki image yang bisa ditampilkan');

    cornerstoneTools.addToolState(viewportEl, 'stack', {
      currentImageIdIndex: 0,
      imageIds: currentImageIds
    });

    sliderEl.min = '1';
    sliderEl.max = String(currentImageIds.length);
    sliderEl.value = '1';
    await showImage(0);
  }

  async function loadStudies(){
    const res = await fetchJson({action: 'list_studies', patient_id: cfg.patientId});
    if (!res.ok) {
      studyListEl.textContent = res.error || 'Gagal memuat study';
      return;
    }

    const studies = res.data || [];
    studyListEl.innerHTML = '';
    if (!studies.length) {
      studyListEl.innerHTML = '<div class="muted">Belum ada study untuk pasien ini.</div>';
      return;
    }

    for (const study of studies) {
      const wrapper = document.createElement('div');
      wrapper.className = 'study-item';
      wrapper.innerHTML = `<div><strong>${study.description || study.study_uid}</strong></div>
        <div class="muted">${study.modality || '-'} • ${study.study_date || '-'}</div>`;

      const seriesRes = await fetchJson({action: 'list_series', study_id: study.id});
      if (seriesRes.ok && Array.isArray(seriesRes.data)) {
        seriesRes.data.forEach(series => {
          const item = document.createElement('div');
          item.className = 'series-item';
          item.textContent = `${series.modality || ''} ${series.description || series.series_uid}`.trim();
          item.addEventListener('click', async () => {
            document.querySelectorAll('.series-item.active').forEach(el => el.classList.remove('active'));
            item.classList.add('active');
            try {
              await loadInstances(series.id);
            } catch (err) {
              alert(err.message || 'Gagal menampilkan series');
            }
          });
          wrapper.appendChild(item);
        });
      }

      studyListEl.appendChild(wrapper);
    }
  }

  sliderEl.addEventListener('input', () => {
    showImage(Number(sliderEl.value) - 1).catch(() => {});
  });

  viewportEl.addEventListener('wheel', (evt) => {
    if (!currentImageIds.length) return;
    evt.preventDefault();
    const next = evt.deltaY > 0 ? currentIndex + 1 : currentIndex - 1;
    showImage(next).catch(() => {});
  }, {passive: false});

  document.getElementById('dicom-reset-btn').addEventListener('click', () => {
    cornerstone.reset(viewportEl);
    showImage(currentIndex).catch(() => {});
  });

  app.querySelectorAll('[data-tool]').forEach(btn => {
    btn.addEventListener('click', () => setActiveTool(btn.dataset.tool));
  });

  if (expertiseForm) {
    expertiseForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await saveExpertise();
        alert('Ekspertise tersimpan dan disinkronkan ke pemeriksaan pasien.');
      } catch (err) {
        alert(err.message || 'Gagal menyimpan ekspertise');
      }
    });
  }

  if (uploadForm) {
    uploadForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(uploadForm);
      const res = await fetch(cfg.apiUrl, {method: 'POST', body: fd, credentials: 'same-origin'});
      const json = await res.json();
      if (!json.ok) {
        alert(json.error || 'Upload gagal');
        return;
      }
      alert(`Upload berhasil (${json.inserted} instance)`);
      loadStudies().catch(() => {});
    });
  }

  setActiveTool('Wwwc');
  loadStudies().catch(err => {
    console.error(err);
    studyListEl.textContent = 'Gagal memuat data.';
  });
})();
