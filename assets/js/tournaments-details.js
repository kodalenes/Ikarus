/* =========================================
   IKARUS GG - TOURNAMENT DETAILS JS
   ========================================= */

/* ─── Tab System ──────────────────────────────────────────── */
function switchDetailTab(name, el) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    el.classList.add('active');

    if (name === 'bracket') {
        renderBracket();
    }
}

/* ─── Bracket Render ──────────────────────────────────────── */
function renderBracket() {
    if (typeof window.bracketData === 'undefined' || !window.bracketData) {
        return;
    }

    var container = $('#bracket-container');

    // Daha önce render edildiyse tekrar çizme
    if (container.children().length > 0) return;

    container.bracket({
        init:         window.bracketData,
        dir:          'lr',
        teamWidth:    155,
        scoreWidth:   34,
        matchMargin:  48,
        roundMargin:  60,
        decorator: {
            edit: function() {},
            render: function(container, data) {
                var name = (data === null || data === undefined)
                    ? '<span style="color:var(--text-faint);font-style:italic;font-size:11px;">TBD</span>'
                    : $('<span>').text(data).html();
                container.html(name);
            }
        }
    });

    // Tur başlıklarını ekle
    if (typeof window.stageLabels !== 'undefined') {
        container.find('.round').each(function(i) {
            var label = window.stageLabels[i] || ('Tur ' + (i + 1));
            $(this).prepend(
                '<div class="bracket-round-title">' + label + '</div>'
            );
        });
    }
}

/* ─── DOMContentLoaded ────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
    var bracketTab = document.getElementById('tab-bracket');
    if (bracketTab && bracketTab.classList.contains('active')) {
        renderBracket();
    }
});