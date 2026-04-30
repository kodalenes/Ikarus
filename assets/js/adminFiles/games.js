// ============================================================================
// 4. OYUN (GAMES.PHP) İŞLEMLERİ
// ============================================================================

function toggleAddForm() {
    const card = document.getElementById('addGameCard');
    const isHidden = card.style.display === 'none';
    card.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
        document.getElementById('gf-name').focus();
    }
}

function submitAddGame() {
    const name  = document.getElementById('gf-name').value.trim();
    const genre = document.getElementById('gf-genre').value.trim();
    const size  = document.getElementById('gf-size').value;
    const errEl = document.getElementById('gf-name-error');
 
    if (!name) { errEl.textContent = 'Game name is required.'; return; }
    errEl.textContent = '';
 
    const params = new URLSearchParams({
        action: 'add_game',
        name: name,
        genre: genre,
        max_team_size: size,
        csrf_token: getCsrfToken()
    });

    fetch('games.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message, 'success');
            document.getElementById('gf-name').value  = '';
            document.getElementById('gf-genre').value = '';
            document.getElementById('gf-size').value  = '5';
            document.getElementById('addGameCard').style.display = 'none';
            appendGameRow(data);
        } else {
            errEl.textContent = data.message;
        }
    })
    .catch(() => showToast('Connection error.', 'error'));
}

function appendGameRow(data) {
    const tbody = document.querySelector('#gamesTable tbody');
    if (!tbody) { window.location.reload(); return; }
 
    const tr = document.createElement('tr');
    tr.dataset.id = data.id;
    tr.innerHTML = `
        <td>
            <div class="adm-game-cell">
                <div class="adm-game-icon">${data.name.substring(0,2).toUpperCase()}</div>
                <span class="op-td-name adm-game-name">${escHtml(data.name)}</span>
            </div>
        </td>
        <td class="op-td-muted adm-game-genre">${escHtml(data.genre)}</td>
        <td><span class="adm-team-size-badge adm-game-size">${data.max_team_size}v${data.max_team_size}</span></td>
        <td class="op-td-muted">—</td>
        <td><span class="op-td-muted">—</span></td>
        <td class="op-td-muted">Today</td>
        <td>
            <div class="op-row-actions" style="opacity:1; gap:6px;">
                <button class="op-btn-sm op-btn-sm--accent" onclick="openEditRow(${data.id})">Edit</button>
                <button class="adm-btn-danger" onclick="deleteGame(${data.id}, '${escHtml(data.name)}')">Delete</button>
            </div>
        </td>
    `;
    tbody.prepend(tr);
 
    const editTr = document.createElement('tr');
    editTr.className = 'adm-edit-row';
    editTr.id = `edit-row-${data.id}`;
    editTr.style.display = 'none';
    editTr.innerHTML = `
        <td colspan="7">
            <div class="adm-inline-edit">
                <div class="adm-gf-field">
                    <label class="op-label">Name</label>
                    <input class="op-input" type="text" id="ef-name-${data.id}" value="${escHtml(data.name)}" maxlength="50">
                </div>
                <div class="adm-gf-field">
                    <label class="op-label">Genre</label>
                    <input class="op-input" type="text" id="ef-genre-${data.id}" value="${escHtml(data.genre === '—' ? '' : data.genre)}" maxlength="50">
                </div>
                <div class="adm-gf-field adm-gf-field--sm">
                    <label class="op-label">Max Team</label>
                    <input class="op-input" type="number" id="ef-size-${data.id}" value="${data.max_team_size}" min="1" max="20">
                </div>
                <div class="adm-gf-actions">
                    <button class="op-btn-sm" onclick="closeEditRow(${data.id})">Cancel</button>
                    <button class="op-btn adm-btn-primary" onclick="submitEditGame(${data.id})">Save</button>
                </div>
            </div>
        </td>
    `;
    tr.insertAdjacentElement('afterend', editTr);
}

function openEditRow(id) {
    document.querySelectorAll('.adm-edit-row').forEach(r => r.style.display = 'none');
    document.getElementById(`edit-row-${id}`).style.display = 'table-row';
    document.getElementById(`ef-name-${id}`).focus();
}
 
function closeEditRow(id) {
    document.getElementById(`edit-row-${id}`).style.display = 'none';
}

function submitEditGame(id) {
    const name  = document.getElementById(`ef-name-${id}`).value.trim();
    const genre = document.getElementById(`ef-genre-${id}`).value.trim();
    const size  = document.getElementById(`ef-size-${id}`).value;
 
    if (!name) { showToast('Game name cannot be empty.', 'error'); return; }
 
    const params = new URLSearchParams({
        action: 'edit_game',
        id: id,
        name: name,
        genre: genre,
        max_team_size: size,
        csrf_token: getCsrfToken()
    });

    fetch('games.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message, 'success');
            closeEditRow(id);
 
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) {
                row.querySelector('.adm-game-name').textContent  = data.name;
                row.querySelector('.adm-game-genre').textContent = data.genre;
                row.querySelector('.adm-game-icon').textContent  = data.name.substring(0,2).toUpperCase();
                row.querySelector('.adm-game-size').textContent  = `${data.max_team_size}v${data.max_team_size}`;
            }
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(() => showToast('Connection error.', 'error'));
}

function deleteGame(id, name) {
    adminConfirm(
        `Delete game <strong>"${name}"</strong>? Tournaments linked to this game may break.`,
        () => {
            const params = new URLSearchParams({
                action: 'delete_game',
                id: id,
                csrf_token: getCsrfToken() 
            });

            fetch('games.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    [document.querySelector(`tr[data-id="${id}"]`), document.getElementById(`edit-row-${id}`)].forEach(el => {
                        if (!el) return;
                        el.style.transition = 'opacity 0.3s';
                        el.style.opacity = '0';
                        setTimeout(() => el.remove(), 320);
                    });
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message || 'Error occurred.', 'error');
                }
            })
            .catch(() => showToast('Connection error.', 'error'));
        }
    );
}