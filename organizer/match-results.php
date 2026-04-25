<?php
    require_once __DIR__ . '/guard.php';

    $orgId = $_SESSION['user_id'];
    $errors = [];
    $success = '';

    //Sonuc kaydetme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['match_id'])) {
        $matchId = (int)$_POST['match_id'];
        $score1 = isset($_POST['score1']) && $_POST['score1'] !== '' ? (int)$_POST['score1'] : null;
        $score2 = isset($_POST['score2']) && $_POST['score2'] !== '' ? (int)$_POST['score2'] : null;

        if ($score1 === null || $score2 === null) {
            $errors[] = 'Both score must be entered.';
        }else if ($score1 < 0 || $score2 < 0) {
            $errors[] = 'Score cant be negative.';
        }else if ($score1 === $score2) {
            $errors[] = 'Cant be tie - the mmust be winner.';
        }else {
            try {
                //Macin bu organizatore ait oldugunu dogrulama
                $check = $pdo->prepare("
                    SELECT m.id FROM Matches m 
                    JOIN Tournament t ON t.id = m.tournament_id
                    WHERE m.id = ? AND t.organizer_id = ?
                ");
                $check->execute([$matchId, $orgId]);
                
                if (!$check->fetch()) {
                    $errors[] = 'You dont have permision on this match.';
                }else {
                    $pdo->prepare("
                        UPDATE Matches SET score_team1 = ?, score_team2 = ?
                        WHERE id = ?
                    ")->execute([$score1, $score2, $matchId]);

                    $success = 'Match result saved.';
                }
            } catch (Exception $e) {
                $errors[] = 'Database error.';
            }
        }
    }

    //Bekleyen maclari cek

    try {
        $stmtPending = $pdo->prepare("
            SELECT m.id, m.stage, m.date, m.score_team1, m.score_team2,
                    t1.name AS home_team, t2.name AS away_team,
                    tour.name AS tournament_name, tour.id AS tournament_id
            FROM Matches m
            JOIN Team t1 ON t1.id = m.home_team_id
            JOIN Team t2 ON t2.id = m.away_team_id
            JOIN Tournament tour ON tour.id = m.tournament_id
            WHERE tour.organizer_id = ? 
                AND tour.status IN ('live', 'registration')
            ORDER BY m.score_team1 IS NOT NULL, tour.name, m.stage, m.date
        ");
        $stmtPending->execute([$orgId]);
        $matches = $stmtPending->fetchAll();

        $grouped = [];
        foreach ($matches as $m) {
            $grouped[$m['tournament_name']][] = $m;
        }
    } catch (Exception $e) {
        $grouped = [];
    }

    $pageTitle = 'Match Results';
    $pageSubtitle = 'Enter pending amtch results';

    require_once __DIR__ . '/layout-top.php';
?>

<?php if(!empty($errors)): ?>
    <div class="op-alert op-alert--error">
        <?php foreach($errors as $e): ?>
            <div>• <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if($success): ?>
    <div class="op-alert op-alert--success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if(empty($grouped)): ?>
    <div class="op-empty op-card">
        There is no pending match result.All matches are up-to date!
    </div>
<?php else: ?>
    <?php foreach($grouped as $tournamentName => $tMatches): ?>
        <div class="op-card" style="margin-bottom: 16px;">
            <div class="op-card-head">
                <span class="op-card-title"><?= htmlspecialchars($tournamentName) ?></span>
                <span class="op-td-muted" style="font-size: 12px;">
                    <?= count(array_filter($tMatches, fn($m) => $m['score_team1'] === null)) ?> waiting
                </span>
            </div>

            <div class="op-match-list">
                <?php foreach($tMatches as $match):
                    $isDone = $match['score_team1'] !== null;
                ?>
                    <div class="op-match-row" <?= $isDone ? 'op-match-row--done' : '' ?>>
                        <div class="op-match-stage"><?= htmlspecialchars($match['stage'] ?? 'Match') ?></div>

                        <div class="op-match-teams">
                            <span class="op-match-team"><?= htmlspecialchars($match['home_team']) ?></span>
                            <span class="op-match-vs">vs</span>
                            <span class="op-match-team"><?= htmlspecialchars($match['away_team']) ?></span>
                        </div>

                        <?php if($isDone): ?>
                            <div class="op-match-result">
                                <span class="op-score <?= $match['score_team1'] > $match['score_team2'] ? 'op-score--win' : '' ?>">
                                    <?= $match['score_team2'] ?>
                                </span>
                            </div>
                            <form method="post" style="display: contents;">
                                <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                                <input class="op-score-input" type="number" name="score1"
                                        value="<?= $match['score_team1'] ?>" min="0" max="99">
                                <span class="op-score-sep">-</span>
                                <input class="op-score-input" type="number" name="score2"
                                        value="<?= $match['score_team2'] ?>" min="0" max="99">
                                <button class="op-btn-sm op-btn-sm--accent" type="submit">Update</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: contents;">
                                <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                                <input class="op-score-input" type="number" name="score1"
                                        placeholder="0" min="0" max="99">
                                <span class="op-score-sep">-</span>
                                <input class="op-score-input" type="number" name="score2"
                                        placeholder="0" min="0" max="99">
                                <button class="op-btn-sm op-btn-sm--primary" type="submit">Save</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/layout-bottom.php' ?>