(function () {
    'use strict';

    const form = document.getElementById('offlineFieldForm');
    const projectSelect = document.getElementById('fieldProject');
    const contractorSelect = document.getElementById('fieldContractor');
    const floorPlanSelect = document.getElementById('fieldFloorPlan');
    const floorPlan = document.getElementById('floorPlan');
    const floorImage = document.getElementById('floorImage');
    const floorPin = document.getElementById('floorPin');
    const floorPrompt = document.getElementById('floorPrompt');
    const pinX = document.getElementById('fieldPinX');
    const pinY = document.getElementById('fieldPinY');
    const alertBox = document.getElementById('fieldAlert');
    const saveButton = document.getElementById('saveFieldReport');
    let referenceData;

    async function renderPendingReports() {
        if (!window.offlineDefectQueue) return;
        const items = await window.offlineDefectQueue.pendingItems();
        const panel = document.getElementById('pendingPanel');
        const list = document.getElementById('pendingReports');
        list.replaceChildren();
        panel.hidden = items.length === 0;
        items.forEach(item => {
            const row = document.createElement('li');
            row.className = 'queue-item';
            const text = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = item.summary || 'Field report';
            const status = document.createElement('small');
            status.textContent = item.status === 'failed'
                ? `Needs attention: ${item.lastError || 'upload rejected'}`
                : `${item.status === 'syncing' ? 'Uploading' : 'Waiting to upload'} · ${new Date(item.createdAt).toLocaleString()}`;
            text.append(title, status);
            const actions = document.createElement('div');
            actions.className = 'queue-actions';
            if (item.status === 'failed') {
                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'queue-action';
                retry.textContent = 'Retry';
                retry.addEventListener('click', async () => {
                    retry.disabled = true;
                    await window.offlineDefectQueue.retry(item.id);
                    renderPendingReports();
                });
                actions.appendChild(retry);
            }
            const discard = document.createElement('button');
            discard.type = 'button';
            discard.className = 'queue-action danger';
            discard.textContent = 'Remove';
            discard.addEventListener('click', async () => {
                if (!window.confirm('Remove this saved report from the device? This cannot be undone.')) return;
                await window.offlineDefectQueue.discard(item.id);
                renderPendingReports();
            });
            actions.appendChild(discard);
            row.append(text, actions);
            list.appendChild(row);
        });
    }

    function showAlert(message) {
        alertBox.textContent = message;
        alertBox.hidden = !message;
    }

    function updateConnection() {
        const pill = document.getElementById('connectionPill');
        pill.textContent = navigator.onLine ? 'Online' : 'Offline';
        pill.classList.toggle('online', navigator.onLine);
    }

    function addOptions(select, items) {
        const first = select.options[0];
        select.replaceChildren(first);
        items.forEach(item => {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.name;
            select.appendChild(option);
        });
    }

    function refreshFloorPlans() {
        const projectId = Number(projectSelect.value);
        const plans = (referenceData.floorPlans || []).filter(plan => !projectId || Number(plan.projectId) === projectId);
        addOptions(floorPlanSelect, plans);
        floorImage.hidden = true;
        floorPin.hidden = true;
        floorPrompt.hidden = false;
        pinX.value = '';
        pinY.value = '';
    }

    function selectedPlan() {
        const id = Number(floorPlanSelect.value);
        return (referenceData.floorPlans || []).find(plan => Number(plan.id) === id);
    }

    function showSelectedPlan() {
        const plan = selectedPlan();
        pinX.value = '';
        pinY.value = '';
        floorPin.hidden = true;
        if (!plan || !plan.imagePath) {
            floorImage.hidden = true;
            floorPrompt.hidden = false;
            return;
        }
        floorImage.src = plan.imagePath;
        floorImage.hidden = false;
        floorPrompt.hidden = true;
    }

    function placePin(event) {
        if (floorImage.hidden || !floorImage.complete || !floorImage.naturalWidth) return;
        const rect = floorImage.getBoundingClientRect();
        const x = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
        const y = Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height));
        pinX.value = x.toFixed(6);
        pinY.value = y.toFixed(6);
        const containerRect = floorPlan.getBoundingClientRect();
        floorPin.style.left = `${rect.left - containerRect.left + (x * rect.width)}px`;
        floorPin.style.top = `${rect.top - containerRect.top + (y * rect.height)}px`;
        floorPin.hidden = false;
    }

    async function loadReferenceData() {
        if (!window.offlineDefectQueue) throw new Error('Offline storage failed to start.');
        if (navigator.onLine) {
            try { await window.offlineDefectQueue.refreshFieldData(); } catch (error) { console.warn(error); }
        }
        referenceData = await window.offlineDefectQueue.getReferenceData();
        if (!referenceData || !referenceData.projects || !referenceData.floorPlans) {
            throw new Error('Open the main app online and sign in once to prepare this device for field mode.');
        }
        addOptions(projectSelect, referenceData.projects);
        addOptions(contractorSelect, referenceData.contractors || []);
        refreshFloorPlans();
        renderPendingReports();
    }

    async function submitReport(event) {
        event.preventDefault();
        showAlert('');
        if (!form.reportValidity()) return;
        if (!pinX.value || !pinY.value) {
            showAlert('Tap the floor-plan preview to mark the defect location.');
            return;
        }
        saveButton.disabled = true;
        saveButton.textContent = 'Saving safely…';
        try {
            const data = new FormData(form);
            const item = await window.offlineDefectQueue.enqueue(data, data.get('title'));
            const result = navigator.onLine ? await window.offlineDefectQueue.syncPending() : { status: 'offline' };
            if (result.status === 'failed') {
                await window.offlineDefectQueue.discard(item.id);
                throw new Error(result.message || 'The server rejected this report.');
            }
            if (result.status === 'synced' || result.status === 'empty') {
                window.location.href = '/defects.php';
                return;
            }
            window.location.href = '/offline-field.html?saved=1';
        } catch (error) {
            showAlert(error.message || 'This report could not be stored on the device.');
            saveButton.disabled = false;
            saveButton.textContent = 'Save field report';
        }
    }

    projectSelect.addEventListener('change', refreshFloorPlans);
    floorPlanSelect.addEventListener('change', showSelectedPlan);
    floorPlan.addEventListener('pointerup', placePin);
    floorImage.addEventListener('error', () => {
        floorImage.hidden = true;
        floorPrompt.hidden = false;
        floorPrompt.textContent = 'This preview was not cached. Reopen it once while online to prepare it for offline use.';
    });
    form.addEventListener('submit', submitReport);
    window.addEventListener('online', updateConnection);
    window.addEventListener('offline', updateConnection);
    window.addEventListener('offline-defect-queue-status', renderPendingReports);

    updateConnection();
    document.getElementById('savedBanner').hidden = !new URLSearchParams(location.search).has('saved');
    loadReferenceData().catch(error => {
        showAlert(error.message);
        form.querySelectorAll('input, select, textarea, button').forEach(control => { control.disabled = true; });
    });
})();
