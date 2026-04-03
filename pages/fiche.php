<?php
$pageTitle  = 'Fiche par transitaire';
$activePage = 'fiche';
$root       = '../';
require_once '../includes/header.php';

$db = getDB();

$filterArrivage = (int)($_GET['arrivage_id'] ?? 0);

$arrivages = $db->query("
    SELECT a.id, a.date_arrivee,
           COUNT(DISTINCT c.transitaire_id) AS nb_trans,
           COUNT(c.id) AS nb_colis
    FROM arrivages a
    LEFT JOIN colis c ON c.arrivage_id = a.id
    GROUP BY a.id
    ORDER BY a.date_arrivee DESC
")->fetchAll();

$ficheData = [];

if ($filterArrivage) {
    $stmtColis = $db->prepare("
        SELECT c.id, c.code_complet, c.code_reel, c.type, c.poids, c.montant,
               t.id AS trans_id, t.nom AS trans_nom,
               a.date_arrivee
        FROM colis c
        JOIN arrivages a ON c.arrivage_id = a.id
        LEFT JOIN transitaires t ON c.transitaire_id = t.id
        WHERE c.arrivage_id = ?
        ORDER BY t.nom, c.code_complet
    ");
    $stmtColis->execute([$filterArrivage]);
    $colis = $stmtColis->fetchAll();

    $stmtRep = $db->prepare("
        SELECT r.colis_id, r.poids, r.montant, r.statut, cl.nom AS client_nom
        FROM repartitions r
        JOIN clients cl ON r.client_id = cl.id
        WHERE r.colis_id IN (
            SELECT id FROM colis WHERE arrivage_id = ?
        )
        ORDER BY r.id
    ");
    $stmtRep->execute([$filterArrivage]);
    $reps = $stmtRep->fetchAll();

    $repsByCol = [];
    foreach ($reps as $r) {
        $repsByCol[$r['colis_id']][] = $r;
    }

    foreach ($colis as $c) {
        $key = $c['trans_id'] ?? 0;
        if (!isset($ficheData[$key])) {
            $ficheData[$key] = [
                'nom'   => $c['trans_nom'] ?? 'Sans transitaire',
                'colis' => [],
            ];
        }
        $ficheData[$key]['colis'][] = [
            'id'          => $c['id'],
            'code'        => $c['code_complet'],
            'type'        => $c['type'],
            'poids'       => (float)$c['poids'],
            'montant'     => (float)$c['montant'],
            'repartitions' => $repsByCol[$c['id']] ?? [],
        ];
    }
}

$jours_fr = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi',
             'Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'];
$mois_fr  = [1=>'Jan',2=>'Fév',3=>'Mar',4=>'Avr',5=>'Mai',6=>'Juin',
             7=>'Juil',8=>'Août',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Déc'];

$dateFiche = '';
if ($filterArrivage) {
    $stmtD = $db->prepare("SELECT date_arrivee FROM arrivages WHERE id=?");
    $stmtD->execute([$filterArrivage]);
    $rowD = $stmtD->fetch();
    if ($rowD) {
        $ts = strtotime($rowD['date_arrivee']);
        $jour = $jours_fr[date('l', $ts)] ?? date('l', $ts);
        $dateFiche = strtoupper($jour . ' ' . date('d', $ts) . '_' . date('m', $ts) . '_' . date('y', $ts));
    }
}
?>
<div class="page-content">

<div class="card" style="margin-bottom:16px">
    <div class="card-body" style="padding:14px 20px">
        <form method="get" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <label class="form-label" style="margin:0;white-space:nowrap">
                <i class="fas fa-calendar-day" style="color:var(--primary)"></i> Sélectionner un arrivage :
            </label>
            <select name="arrivage_id" class="form-control" style="width:220px" onchange="this.form.submit()">
                <option value="">-- Choisir une date --</option>
                <?php foreach ($arrivages as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $filterArrivage == $a['id'] ? 'selected' : '' ?>>
                        <?= date('d/m/Y', strtotime($a['date_arrivee'])) ?>
                        (<?= $a['nb_colis'] ?> colis / <?= $a['nb_trans'] ?> transitaire<?= $a['nb_trans'] > 1 ? 's' : '' ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($filterArrivage): ?>
                <a href="fiche.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Effacer</a>
                <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimer
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!$filterArrivage): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>Sélectionnez un arrivage</h3>
                <p>Choisissez une date ci-dessus pour afficher la fiche récapitulative par transitaire.</p>
            </div>
        </div>
    </div>
<?php elseif (empty($ficheData)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-boxes"></i>
                <h3>Aucun colis trouvé</h3>
                <p>Cet arrivage ne contient aucun colis enregistré.</p>
            </div>
        </div>
    </div>
<?php else: ?>

    <!-- EN-TÊTE DE DATE (style papier) -->
    <div class="fiche-date-header"><?= htmlspecialchars($dateFiche) ?></div>

    <?php foreach ($ficheData as $transId => $bloc): ?>
    <?php
        $totalColis  = count($bloc['colis']);
        $totalPoids  = array_sum(array_column($bloc['colis'], 'poids'));
    ?>

    <!-- BLOC TRANSITAIRE -->
    <div class="fiche-transitaire-bloc">

        <!-- ENTÊTE TRANSITAIRE -->
        <div class="fiche-trans-header">
            <div class="fiche-trans-title">
                <span class="fiche-nb-colis"><?= $totalColis ?> COLI<?= $totalColis > 1 ? 'S' : '' ?></span>
                <span class="fiche-trans-name"><?= strtoupper(sanitize($bloc['nom'])) ?></span>
            </div>
            <div class="fiche-trans-total">
                <span>Poids total : <strong><?= number_format($totalPoids, 2) ?> kg</strong></span>
            </div>
        </div>

        <!-- COLIS DU TRANSITAIRE -->
        <div class="fiche-colis-grid">
            <?php foreach ($bloc['colis'] as $c): ?>
            <?php
                $reps       = $c['repartitions'];
                $sumPoids   = array_sum(array_column($reps, 'poids'));
                $nbPaye     = count(array_filter($reps, fn($r) => $r['statut'] == 1));
                $nbTotal    = count($reps);
                $prixKg     = $c['poids'] > 0 ? $c['montant'] / $c['poids'] : 0;
            ?>
            <div class="fiche-colis-card">
                <!-- Entête colis -->
                <div class="fiche-colis-header">
                    <div style="display:flex;align-items:center;gap:8px">
                        <span class="fiche-colis-type">
                            <?= $c['type'] === 'mixte' ? '1 COLI MIX' : '1 COLI IND' ?>
                        </span>
                        <span class="fiche-colis-code"><?= sanitize($c['code']) ?></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="font-size:12px;color:var(--gray-500)"><?= number_format($c['poids'], 2) ?> kg total</span>
                        <?php if ($nbTotal > 0): ?>
                            <span class="badge <?= $nbPaye === $nbTotal ? 'badge-success' : ($nbPaye > 0 ? 'badge-warning' : 'badge-danger') ?>" style="font-size:10px">
                                <?= $nbPaye ?>/<?= $nbTotal ?> payé<?= $nbPaye > 1 ? 's' : '' ?>
                            </span>
                        <?php else: ?>
                            <span class="badge badge-warning" style="font-size:10px">Non réparti</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($reps)): ?>
                <!-- Tableau des clients -->
                <table class="fiche-table">
                    <thead>
                        <tr>
                            <th style="width:28px">N°</th>
                            <th>Poids (kg)</th>
                            <th>Client</th>
                            <?php if ($c['montant'] > 0): ?>
                            <th>Montant</th>
                            <?php endif; ?>
                            <th style="width:40px;text-align:center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reps as $i => $r): ?>
                        <tr class="<?= $r['statut'] == 1 ? 'fiche-row-paye' : '' ?>">
                            <td class="fiche-num"><?= $i + 1 ?>.</td>
                            <td class="fiche-poids"><?= number_format((float)$r['poids'], 2) ?> kg</td>
                            <td class="fiche-client"><?= strtoupper(sanitize($r['client_nom'])) ?></td>
                            <?php if ($c['montant'] > 0): ?>
                            <td class="fiche-montant"><?= number_format((float)$r['montant'], 0, ',', ' ') ?></td>
                            <?php endif; ?>
                            <td class="fiche-statut">
                                <?php if ($r['statut'] == 1): ?>
                                    <span class="fiche-check">✓</span>
                                <?php else: ?>
                                    <span class="fiche-cross">✗</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if ($c['type'] === 'mixte' && count($reps) > 1): ?>
                    <tfoot>
                        <tr class="fiche-total-row">
                            <td></td>
                            <td><strong><?= number_format($sumPoids, 2) ?> kg</strong></td>
                            <td colspan="<?= $c['montant'] > 0 ? 2 : 1 ?>">
                                <span style="color:<?= abs($sumPoids - $c['poids']) < 0.01 ? 'var(--success)' : 'var(--danger)' ?>;font-size:11px">
                                    <?= abs($sumPoids - $c['poids']) < 0.01 ? '✓ Poids complet' : '⚠ Poids incomplet (' . number_format($c['poids'] - $sumPoids, 2) . ' kg restant)' ?>
                                </span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
                <?php else: ?>
                <div style="padding:12px 14px;text-align:center;color:var(--gray-500);font-size:12px;font-style:italic">
                    <i class="fas fa-exclamation-circle"></i> Aucune répartition saisie
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
    <?php endforeach; ?>

<?php endif; ?>
</div>

<style>
/* ======================================================
   FICHE RÉCAPITULATIVE - Style papier
   ====================================================== */

.fiche-date-header {
    font-family: 'Courier New', monospace;
    font-size: 15px;
    font-weight: 700;
    color: var(--gray-700);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 16px;
    padding: 8px 0;
    border-bottom: 2px solid var(--gray-900);
    display: inline-block;
}

/* Bloc par transitaire */
.fiche-transitaire-bloc {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin-bottom: 28px;
    overflow: hidden;
    border: 1px solid var(--gray-200);
}

.fiche-trans-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 20px;
    background: var(--gray-900);
    color: var(--white);
    flex-wrap: wrap;
    gap: 8px;
}

.fiche-trans-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.fiche-nb-colis {
    background: var(--primary);
    color: white;
    font-size: 12px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 99px;
    letter-spacing: .5px;
}

.fiche-trans-name {
    font-size: 18px;
    font-weight: 700;
    letter-spacing: 1px;
    color: var(--white);
    font-family: 'Courier New', monospace;
}

.fiche-trans-total {
    font-size: 13px;
    color: rgba(255,255,255,.75);
}

/* Grille de colis */
.fiche-colis-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    padding: 16px;
}

/* Carte colis */
.fiche-colis-card {
    flex: 1 1 320px;
    min-width: 280px;
    max-width: 500px;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius);
    overflow: hidden;
}

.fiche-colis-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 12px;
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    flex-wrap: wrap;
}

.fiche-colis-type {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    background: var(--primary);
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
}

.fiche-colis-code {
    font-family: 'Courier New', monospace;
    font-size: 14px;
    font-weight: 700;
    color: var(--primary-dark);
    background: var(--gray-100);
    border: 1px solid var(--gray-200);
    padding: 2px 8px;
    border-radius: 4px;
    letter-spacing: .5px;
}

/* Tableau interne */
.fiche-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.fiche-table thead th {
    background: var(--gray-100);
    padding: 6px 10px;
    font-size: 11px;
    font-weight: 600;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: .4px;
    border-bottom: 1px solid var(--gray-200);
}

.fiche-table tbody td {
    padding: 7px 10px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}

.fiche-table tbody tr:last-child td { border-bottom: none; }

.fiche-row-paye {
    background: #f0fdf4;
}

.fiche-num {
    color: var(--gray-500);
    font-size: 12px;
    text-align: right;
    padding-right: 4px !important;
}

.fiche-poids {
    font-weight: 600;
    color: var(--primary);
    white-space: nowrap;
    font-family: 'Courier New', monospace;
}

.fiche-client {
    font-weight: 600;
    color: var(--gray-900);
    letter-spacing: .3px;
}

.fiche-montant {
    color: var(--gray-700);
    font-size: 12px;
    white-space: nowrap;
    font-family: 'Courier New', monospace;
}

.fiche-statut {
    text-align: center;
}

.fiche-check {
    font-size: 16px;
    font-weight: 900;
    color: var(--success);
}

.fiche-cross {
    font-size: 14px;
    font-weight: 700;
    color: var(--danger);
    opacity: .5;
}

.fiche-total-row td {
    background: var(--gray-50);
    border-top: 2px solid var(--gray-200);
    font-size: 12px;
    padding: 5px 10px !important;
}

/* ======================================================
   IMPRESSION
   ====================================================== */
@media print {
    .sidebar, .topbar, .card:first-child, .btn, .btn-icon { display: none !important; }
    .main-content { margin-left: 0 !important; padding-top: 0 !important; }
    .page-content { padding: 0 !important; }
    .fiche-transitaire-bloc { page-break-inside: avoid; margin-bottom: 20px; box-shadow: none; border: 1px solid #ccc; }
    .fiche-colis-card { page-break-inside: avoid; border: 1px solid #ccc; }
    .fiche-trans-header { background: #1e293b !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .fiche-row-paye { background: #f0fdf4 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<?php require_once '../includes/footer.php'; ?>
