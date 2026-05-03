<?php 
    require_once __DIR__ . '/guard.php';

    $orgId = $_SESSION['user_id'];
    $editId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $isEdit = $editId !== null;
    $errors = [];
    $success = '';
    $tournament = null;
    $rules =[];

    //Mevcut oyunlari Cek
    try {
        $games = $pdo->query("SELECT  id, name FROM Game ORDER BY name")->fetchAll();
    } catch (Exception $e) {
        $games= [];
    }

    //Duzenleme modunda mevcut veriyi cek
    if ($isEdit) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM Tournament WHERE id = ? AND organizer_id = ?");
            $stmt->execute([$editId, $orgId]);
            $tournament = $stmt->fetch();

            if (!$tournament) {
                header('Location: tournaments.php');
                exit;
            }

            //Kurallari cek
            $stmtR = $pdo->prepare("SELECT * FROM Tournament_Rule WHERE tournament_id = ? ORDER BY sort_order");
            $stmtR->execute([$editId]);
            $rules = $stmtR->fetchAll();
        } catch (Exception $e) {
            $rules = [];
        }
    }
    
    //FORM GONDERIMI
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name       = trim($_POST['name'] ?? '');
        $gameId     = (int)($_POST['game_id'] ?? 0);
        $format     = $_POST['format'] ?? 'single_elimination';
        $maxTeams   = (int)($_POST['max_teams'] ?? 8);
        $prizePool  = (float)($_POST['prize_pool'] ?? 0);
        $startDate  = $_POST['start_date'] ?? '';
        $endDate    = $_POST['end_date'] ?? '';
        $checkIn    = (int)($_POST['checkin_minutes'] ?? 15);
        $noshow     = (int)($_POST['noshow_minutes'] ?? 10);
        $description =trim($_POST['description'] ?? '');
        $status     = $_POST['status_override'] ?? 'draft';
        $ruleTexts  = $_POST['rules'] ?? [];
        $prize1st   = (float)($_POST['prize_1st'] ?? 0);
        $prize2nd   = (float)($_POST['prize_2nd'] ?? 0);
        $prize3rd   = (float)($_POST['prize_3rd'] ?? 0);

        //Validasyon
        if(empty($name))                            $errors[] = 'Tournament name is mandatory.';
        if($gameId === 0)                           $errors[] = 'Game selection is mandatory.';
        if(empty($startDate))                       $errors[] = 'Start date is mandatory.';
        if(empty($endDate))                         $errors[] = 'End date is mandatory.';
        if($startDate && $endDate < $startDate)     $errors[] = 'End date cant be before start date.';
        if(!in_array($maxTeams, [4,8,16,32,64]))    $errors[] = 'Invalid team count.';

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $data = [
                    'name' => $name,
                    'game_id' => $gameId,
                    'max_teams' => $maxTeams,
                    'prize_pool' => $prizePool,
                    'prize_1st' => $prize1st,
                    'prize_2nd' => $prize2nd,
                    'prize_3rd' => $prize3rd,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'checkin_minutes' => $checkIn,
                    'noshow_minutes' => $noshow,
                    'description' => $description,
                    'status' => $status,
                    'organizer_id' => $orgId,
                ];

                if ($isEdit) {
                    $pdo->prepare("
                        UPDATE Tournament SET
                            name = :name, game_id = :game_id, max_teams = :max_teams,
                            prize_pool = :prize_pool, prize_1st = :prize_1st, prize_2nd = :prize_2nd, prize_3rd = :prize_3rd,
                            start_date = :start_date, end_date = :end_date, 
                            checkin_minutes = :checkin_minutes, noshow_minutes = :noshow_minutes,
                            description = :description, status = :status
                        WHERE id = {$editId} AND organizer_id = :organizer_id
                    ")->execute($data);
                    $tournamentId = $editId;

                    //Kurallaru sil ve yeniden ekle
                    $pdo->prepare("DELETE FROM Tournament_Rule WHERE tournament_id = ?")->execute([$editId]);
                }else {
                    $pdo->prepare("
                        INSERT INTO Tournament(name, game_id, max_teams, prize_pool, prize_1st, prize_2nd, prize_3rd, start_date, end_date, checkin_minutes, noshow_minutes, description, status, organizer_id)
                        VALUES(:name, :game_id, :max_teams, :prize_pool, :prize_1st, :prize_2nd, :prize_3rd, :start_date, :end_date, :checkin_minutes, :noshow_minutes, :description, :status, :organizer_id)    
                    ")->execute($data);
                    $tournamentId = (int) $pdo->lastInsertId();
                }

                //Kurallari kaydet
                $stmtRule = $pdo->prepare("
                    INSERT INTO Tournament_Rule (tournament_id, rule_text, sort_order)
                    VALUES (?, ?, ?)
                ");
                foreach ($ruleTexts as $i => $ruleText) {
                    $ruleText = trim($ruleText);
                    if (!empty($ruleText)) {
                        $stmtRule->execute([$tournamentId, $ruleText, $i + 1]);
                    }
                }

                $pdo->commit();

                $success = $isEdit
                    ? 'Tournament successfully updated.'
                    : 'Tournament successfully created.';

                if (!$isEdit) {
                    header("Location: tournament-create.php?id={$tournamentId}&created=1");
                    exit;
                }

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }


    //Sayfa Basligi
    $pageTitle = $isEdit ? 'Edit Tournament' : 'New Tournament';
    $pageSubtitle = $isEdit
        ? htmlspecialchars($tournament['name'] ?? '')
        : 'Create and publish a tournament.';

    require_once __DIR__ . '/layout-top.php';
?>

<?php if(!empty($errors)): ?>
    <div class="op-alert op-alert--error">
        <?php foreach($errors as $err): ?>
            <div>• <?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if($success): ?>
    <div class="op-alert op-alert--success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if(isset($_GET['created'])): ?>
    <div class="op-alert op-alert--success">
        Tournament created! You can edit rules or 
        <a href="tournaments.php" class="op-link">back to tournaments list.</a>
    </div>
<?php endif; ?>

<div id="js-errors" class="op-alert op-alert--error" style="display: none; margin-bottom: 16px;"></div>

<form method="POST" id="tournamentForm" novalidate>

    <!-- TEMEL BİLGİLER -->
     <div class="op-card" style="margin-bottom: 16px;">
        <div class="op-card-head">
            <span class="op-card-title">Basic Information</span>
        </div>
        <div class="op-form-grid">
            <div class="op-field op-field--full">
                <label class="op-label">Tournament Name *</label>
                <input class="op-input" type="text" name="name" maxlength="150" placeholder="Name"
                        value="<?= htmlspecialchars($tournament['name'] ?? '') ?>" required>
            </div>

            <div class="op-field">
                <label class="op-label">Game *</label>
                <select class="op-select" name="game_id" required>
                    <option value="">Choose...</option>
                    <?php foreach($games as $g): ?>
                        <option value="<?= $g['id'] ?>"
                            <?= ($tournament['game_id'] ?? '') == $g['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class ="op-field">
                <label class="op-label">Max Team Count *</label>
                <select class="op-select" name="max_teams" required>
                    <?php foreach([4,8,16,32,64] as $n): ?>
                        <option value="<?= $n ?>"
                            <?= ($tournament['max_teams'] ?? 8) == $n ? 'selected' : '' ?>>
                            <?= $n ?> Team
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="op-field">
                <label class="op-label">Start Date *</label>
                <input class="op-input" type="datetime-local" name="start_date"
                        value="<?= $tournament ? date('Y-m-d\TH:i' , strtotime($tournament['start_date'])) : '' ?>" required>
            </div>

            <div class="op-field">
                <label class="op-label">End Date</label>
                <input class="op-input" type="datetime-local" name="end_date"
                        value="<?= $tournament ? date('Y-m-d\TH:i' , strtotime($tournament['end_date'])) : '' ?>" required>
            </div>

            <div class="op-field">
                <label class="op-label">Check-in Time</label>
                <input class="op-input" type="number" name="checkin_minutes" min="5" max="60"
                        value="<?= $tournament['checkin_minutes'] ?? 15 ?>">
            </div>

            <div class="op-field">
                <label class="op-label">No-show Time</label>
                <input class="op-input" type="number" name="noshow_minutes" min="5" max="30"
                        value="<?= $tournament['noshow_minutes'] ?? 10 ?>">
            </div>

            <div class="op-field">
                <label class="op-label">Description</label>
                <textarea class="op-textarea" name="description" rows="3"
                        placeholder="Short description about tournament"><?= htmlspecialchars($tournament['description'] ?? '') ?></textarea>
            </div>
        </div>
     </div>

    <!-- ÖDÜL DAĞILIMI -->
     <div class="op-card" style="margin-bottom: 16px;">
        <div class="op-card-head">
            <span class="op-card-title">Prize Distribution</span>
        </div>
        <div class="op-form-grid">
            <div class="op-field">
                <label class="op-label">Total Prize Pool</label>
                <input class="op-input" type="number" name="prize_pool" min="0" step="100"
                        placeholder="0"
                        value="<?= $tournament['prize_pool'] ?? ''?>">
            </div>
        </div>
        <div class="op-prize-grid">
            <div class="op-prize-item">
                <div class="op-prize-rank">🥇 Champion</div>
                <input class="op-input" type="number" name="prize_1st" min="0"
                        placeholder="₺3.000"
                        value="<?= $tournament['prize_1st'] ?? '' ?>">
            </div>
            <div class="op-prize-item">
                <div class="op-prize-rank">🥈 Second</div>
                <input class="op-input" type="number" name="prize_2nd" min="0"
                        placeholder="₺1.500"
                        value="<?= $tournament['prize_2nd'] ?? '' ?>">
            </div>
            <div class="op-prize-item">
                <div class="op-prize-rank">🥉 Third</div>
                <input class="op-input" type="number" name="prize_3rd" min="0"
                        placeholder="₺500"
                        value="<?= $tournament['prize_3rd'] ?? '' ?>">
            </div>
        </div>
     </div>

    <!-- KURALLAR -->
     <div class="op-card" style="margin-bottom: 16px;">
        <div class="op-card-head">
            <span class="op-card-title">Tournament Rules</span>
            <button type="button" class="op-btn-sm op-btn-sm--accent" onclick="addRule()">
                 + Add Rule
            </button>
        </div>
        <div id="rules-list" class="op-rules-list">
            <?php if(!empty($rules)): ?>
                <?php foreach($rules as $rule): ?>
                    <div class="op-rule-row">
                        <input class="op-input" type="text" name="rules[]"
                                value="<?= htmlspecialchars($rule['rule_text']) ?>"
                                placeholder="Enter rule text...">
                        <button type="button" class="op-rule-del" onclick="this.parentElement.remove()">x</button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="op-rule-row">
                    <input class="op-input" type="text" name="rules[]" value="Match format Bo3, Final Bo5" placeholder="Enter rule text...">
                    <button type="button" class="op-rule-del" onclick="this.parentElement.remove()">x</button>
                </div>
                <div class="op-rule-row">
                    <input class="op-input" type="text" name="rules[]" value="5 active players are mandatory per team" placeholder="Enter rule text...">
                    <button type="button" class="op-rule-del" onclick="this.parentElement.remove()">x</button>
                </div>
                <div class="op-rule-row">
                    <input class="op-input" type="text" name="rules[]" value="Check-in: mandatory 15 min before the match " placeholder="Enter rule text...">
                    <button type="button" class="op-rule-del" onclick="this.parentElement.remove()">x</button>
                </div>
            <?php endif; ?>
        </div>
     </div>

    <!-- DURUM + KAYDET -->     
     <div class="op-card">
        <div class="op-card-head">
            <span class="op-card-title">Publication Status</span>
        </div>
        <div class="op-form-grid">
            <div class="op-field">
                <label class="op-label">Status</label>
                <select class="op-select" name="status">
                    <option value="draft"           <?= ($tournament['status'] ?? 'draft') === 'draft'      ? 'selected' : '' ?>>Draft — Not visible on the site</option>
                    <option value="upcoming"        <?= ($tournament['status'] ?? '') === 'upcoming'        ? 'selected' : '' ?>>Upcoming - Announced</option>
                    <option value="registration"    <?= ($tournament['status'] ?? '') === 'registration'    ? 'selected' : '' ?>>Registration is open — Teams can participate.</option>
                    <option value="live"            <?= ($tournament['status'] ?? '') === 'live'            ? 'selected' : '' ?>>Live - Tournament started</option>
                    <option value="finished"        <?= ($tournament['status'] ?? '') === 'finished'        ? 'selected' : '' ?>>Finished</option>
                </select>
            </div>
        </div>

        <div class="op-form-actions">
            <a href="tournaments.php" class="op-btn op-btn--ghost">Cancel</a>
            <button type="submit" name="status_override" value="draft" class="op-btn op-btn--ghost">
                Save Draft
            </button>
            <button type="submit" class="op-btn op-btn--primary">
                <?= $isEdit ? 'Save Changes' : 'Publish' ?>
            </button>
        </div>
     </div>

</form>

<script>
// 1. Add Rule Butonu İşlevi
function addRule() {
    const list = document.getElementById('rules-list');
    const row = document.createElement('div');
    row.className = 'op-rule-row';
    row.innerHTML = `
        <input class="op-input" type="text" name="rules[]" placeholder="Enter rule text...">
        <button type="button" class="op-rule-del" onclick="this.parentElement.remove()">x</button>
    `;
    list.appendChild(row);
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('tournamentForm');
    const startDateEl = document.querySelector('input[name="start_date"]');
    const endDateEl = document.querySelector('input[name="end_date"]');
    const errorContainer = document.getElementById('js-errors');

    function showErrorList(errors) {
        errorContainer.innerHTML = errors.map(err => `<div>• ${err}</div>`).join('');
        errorContainer.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function hideErrors() {
        errorContainer.style.display = 'none';
        errorContainer.innerHTML = '';
    }

    // 2. Anlık Tarih Kontrolü
    function checkDates() {
        if(startDateEl.value && endDateEl.value) {
            if(new Date(endDateEl.value) < new Date(startDateEl.value)) {
                showErrorList(["End date can't be before start date."]);
                endDateEl.value = ""; // Formu hatalı tarihten temizle
            } else {
                hideErrors();
            }
        }
    }
    if(startDateEl) startDateEl.addEventListener('change', checkDates);
    if(endDateEl) endDateEl.addEventListener('change', checkDates);

    // 3. Form Validasyon (submit olmadan önce)
    if(form) {
        form.addEventListener('submit', (e) => {
            let errors = [];
            
            if(!form.name.value.trim()) errors.push("Tournament name is mandatory.");
            if(!form.game_id.value) errors.push("Game selection is mandatory.");
            if(!startDateEl.value) errors.push("Start date is mandatory.");
            if(!endDateEl.value) errors.push("End date is mandatory.");
            
            if(startDateEl.value && endDateEl.value && new Date(endDateEl.value) < new Date(startDateEl.value)) {
                errors.push("End date can't be before start date.");
            }
            
            if(errors.length > 0) {
                e.preventDefault();
                showErrorList(errors);
            } else {
                hideErrors();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/layout-bottom.php'; ?>