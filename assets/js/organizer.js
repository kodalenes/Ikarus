//Kural ekleme
function addRule() {
    const list = document.getElementById('rules-list');
    if(!list) return;

    const row = document.createElement('div');

    row.className = 'op-rule-row';
    row.innerHTML = `
        <input class="op-input" type="text" name="rules[]" placeholder="Enter rules text...">
        <button type="button" class="op-rule-del" onclick="this.parentElement.remove()">x</button>
    `;
    list.appendChild(row);
    row.querySelector('input').focus();
}

//Turnuva silme
function deleteTournament(id, name) {
    if (!confirm(`Are you sure you want to delete the "${name}" tournament?\nThis action is irreversible.`)) {
        return;
    }

    fetch('tournament-create.php' , {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${id}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            const row = document.querySelector(`[data-tournament-id="$[id]"]`);
            if (row) {
                row.style.opacity = '0';
                row.style.transition = 'opacity 0.3s';
                setTimeout(() => row.remove(),300);
            }else{
                window.location.reload();
            }
        }else{
            alert(data.message || 'Error occurred');
        }
    })
    .catch(() => alert('Connection error.'));
}

//Alert otomatik kapat
document.addEventListener('DOMContentLoaded' , () => {
    const successAlerts = document.querySelectorAll('.op-alert--success');
    successAlerts.forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove,500);
        }, 3000);
    });
});