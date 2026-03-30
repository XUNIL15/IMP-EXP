<?php
$pageTitle  = 'Bilan journalier';
$activePage = 'bilan';
$root       = '../';
require_once '../includes/header.php';

$db = getDB();

$date = $_GET['date'] ?? date('Y-m-d');

// KPI DU JOUR
$stmtKpi = $db->prepare("
    SELECT
        COUNT(c.id) as nb_colis,
        SUM(CASE WHEN c.type='individuel' THEN 1 ELSE 0 END) as nb_individuel,
        SUM(CASE WHEN c.type='mixte' THEN 1 ELSE 0 END) as nb_mixte,
        COALESCE(SUM(c.poids), 0) as total_kilos,
        SUM(CASE WHEN c.type='individuel' THEN c.poids ELSE 0 END) as kilos_individuel,
        SUM(CASE WHEN c.type='mixte' THEN c.poids ELSE 0 END) as kilos_mixte,
        COALESCE(SUM(c.montant), 0) as total_montant,
        SUM(CASE WHEN c.type='individuel' THEN c.montant ELSE 0 END) as montant_individuel,
        SUM(CASE WHEN c.type='mixte' THEN c.montant ELSE 0 END) as montant_mixte
    FROM colis c
    JOIN arrivages a ON c.arrivage_id = a.id
    WHERE a.date_arrivee = ?
");
$stmtKpi->execute([$date]);
$kpi = $stmtKpi->fetch();

// ENCAISSEMENTS DU JOUR
$stmtEnc = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE date_paiement = ?");
$stmtEnc->execute([$date]);
$totalEnc = (float)$stmtEnc->fetchColumn();

// MONTANT DU DU JOUR
$stmtDu = $db->prepare("
    SELECT COALESCE(SUM(cp.montant_du), 0)
    FROM colis_proprietaires cp
    JOIN colis c ON cp.colis_id = c.id
    JOIN arrivages a ON c.arrivage_id = a.id
    WHERE a.date_arrivee = ?
");
$stmtDu->execute([$date]);
$totalDu = (float)$stmtDu->fetchColumn();

// DETTES DU JOUR
$stmtDettes = $db->prepare("
    SELECT cp.*, cl.nom as client_nom, cl.telephone, c.code_complet, c.type as type_colis,
           a.date_arrivee, t.nom as transitaire_nom
    FROM colis_proprietaires cp
    JOIN clients cl ON cp.client_id = cl.id
    JOIN colis c ON cp.colis_id = c.id
    JOIN arrivages a ON c.arrivage_id = a.id
    JOIN transitaires t ON a.transitaire_id = t.id
    WHERE a.date_arrivee = ? AND cp.statut != 'paye'
    ORDER BY cl.nom
");
$stmtDettes->execute([$date]);
$dettes = $stmtDettes->fetchAll();

$totalDette = array_sum(array_column($dettes, 'solde'));

// LISTE COLIS DU JOUR
$stmtColis = $db->prepare("
    SELECT c.*, t.nom as transitaire_nom,
           COUNT(cp.id) as nb_prop,
           COALESCE(SUM(cp.montant_du), 0) as montant_du_total,
           COALESCE(SUM(cp.montant_paye), 0) as montant_paye_total,
           COALESCE(SUM(cp.solde), 0) as solde_total
    FROM colis c
    JOIN arrivages a ON c.arrivage_id = a.id
    JOIN transitaires t ON a.transitaire_id = t.id
    LEFT JOIN colis_proprietaires cp ON cp.colis_id = c.id
    WHERE a.date_arrivee = ?
    GROUP BY c.id
    ORDER BY c.type, c.id
");
$stmtColis->execute([$date]);
$colisDuJour = $stmtColis->fetchAll();

$dateDisplay = date('d/m/Y', strtotime($date));
?>
<div class="page-content">

    <!-- SÉLECTEUR DE DATE -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-body" style="padding:16px">
            <form method="get" class="filters-bar">
                <label style="font-weight:600;color:var(--gray-700)">
                    <i class="fas fa-calendar-day"></i> Date du bilan :
                </label>
                <input type="date" name="date" value="<?= $date ?>" class="form-control" style="width:180px">
                <button type="submit" class="btn btn-primary"><i class="fas fa-eye"></i> Afficher</button>
                <button type="button" class="btn btn-danger" onclick="exporterBilanPDF()">
                    <i class="fas fa-file-pdf"></i> Exporter PDF
                </button>
                <a href="bilan.php?date=<?= date('Y-m-d') ?>" class="btn btn-outline">
                    <i class="fas fa-calendar-check"></i> Aujourd'hui
                </a>
            </form>
        </div>
    </div>

    <div id="bilan-content">

        <!-- KPI CARDS -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon"><i class="fas fa-boxes"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= (int)$kpi['nb_colis'] ?></div>
                    <div class="stat-label">Total colis</div>
                </div>
            </div>
            <div class="stat-card info">
                <div class="stat-icon"><i class="fas fa-box"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= (int)$kpi['nb_individuel'] ?> / <?= (int)$kpi['nb_mixte'] ?></div>
                    <div class="stat-label">Individuels / Mixtes</div>
                </div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon"><i class="fas fa-weight-hanging"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format((float)$kpi['total_kilos'], 1) ?> kg</div>
                    <div class="stat-label">Total kilos</div>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format((float)$kpi['total_montant'], 0, ',', ' ') ?></div>
                    <div class="stat-label">Montant total (FCFA)</div>
                </div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($totalEnc, 0, ',', ' ') ?></div>
                    <div class="stat-label">Total encaissé (FCFA)</div>
                </div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($totalDette, 0, ',', ' ') ?></div>
                    <div class="stat-label">Total dettes du jour (FCFA)</div>
                </div>
            </div>
        </div>

        <!-- RECAP INDIVIDUEL / MIXTE -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-table"></i> Récapitulatif par type - <?= $dateDisplay ?></div>
            </div>
            <div class="card-body" style="padding:0">
                <div class="table-responsive">
                    <table id="tableRecap">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Nombre de colis</th>
                                <th>Total kilos (kg)</th>
                                <th>Total montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-primary">Individuel</span></td>
                                <td><?= (int)$kpi['nb_individuel'] ?></td>
                                <td><?= number_format((float)$kpi['kilos_individuel'], 2) ?></td>
                                <td><?= number_format((float)$kpi['montant_individuel'], 0, ',', ' ') ?> FCFA</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-info">Mixte</span></td>
                                <td><?= (int)$kpi['nb_mixte'] ?></td>
                                <td><?= number_format((float)$kpi['kilos_mixte'], 2) ?></td>
                                <td><?= number_format((float)$kpi['montant_mixte'], 0, ',', ' ') ?> FCFA</td>
                            </tr>
                            <tr class="bilan-total-row">
                                <td><strong>TOTAL</strong></td>
                                <td><strong><?= (int)$kpi['nb_colis'] ?></strong></td>
                                <td><strong><?= number_format((float)$kpi['total_kilos'], 2) ?></strong></td>
                                <td><strong><?= number_format((float)$kpi['total_montant'], 0, ',', ' ') ?> FCFA</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- DETAIL COLIS -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-list"></i> Détail des colis du <?= $dateDisplay ?></div>
            </div>
            <div class="card-body" style="padding:0">
                <div class="table-responsive">
                    <table id="tableColisBilan">
                        <thead>
                            <tr>
                                <th>Code colis</th>
                                <th>Type</th>
                                <th>Transitaire</th>
                                <th>Poids (kg)</th>
                                <th>Montant</th>
                                <th>Encaissé</th>
                                <th>Solde</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($colisDuJour): ?>
                                <?php foreach ($colisDuJour as $c): ?>
                                <tr>
                                    <td><span class="colis-code"><?= sanitize($c['code_complet']) ?></span></td>
                                    <td>
                                        <?php if ($c['type'] === 'mixte'): ?>
                                            <span class="badge badge-info">Mixte</span>
                                        <?php else: ?>
                                            <span class="badge badge-primary">Individuel</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= sanitize($c['transitaire_nom']) ?></td>
                                    <td><?= number_format((float)$c['poids'], 2) ?></td>
                                    <td><?= number_format((float)$c['montant'], 0, ',', ' ') ?> FCFA</td>
                                    <td style="color:var(--success);font-weight:600">
                                        <?= number_format((float)$c['montant_paye_total'], 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td style="color:<?= (float)$c['solde_total'] > 0 ? 'var(--danger)' : 'var(--success)' ?>;font-weight:700">
                                        <?= number_format((float)$c['solde_total'], 0, ',', ' ') ?> FCFA
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-times"></i>
                                        <h3>Aucun colis pour cette date</h3>
                                    </div>
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- DETTES DU JOUR -->
        <?php if ($dettes): ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title" style="color:var(--danger)">
                    <i class="fas fa-exclamation-circle"></i> Dettes du jour - <?= $dateDisplay ?>
                </div>
            </div>
            <div class="card-body" style="padding:0">
                <div class="table-responsive">
                    <table id="tableDettesBilan">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Téléphone</th>
                                <th>Colis</th>
                                <th>Type</th>
                                <th>Montant dû</th>
                                <th>Payé</th>
                                <th>Solde</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dettes as $d): ?>
                            <tr>
                                <td><strong><?= sanitize($d['client_nom']) ?></strong></td>
                                <td><?= sanitize($d['telephone'] ?: '-') ?></td>
                                <td><span class="colis-code"><?= sanitize($d['code_complet']) ?></span></td>
                                <td><?= ucfirst($d['type_colis']) ?></td>
                                <td><?= number_format((float)$d['montant_du'], 0, ',', ' ') ?> FCFA</td>
                                <td style="color:var(--success)"><?= number_format((float)$d['montant_paye'], 0, ',', ' ') ?> FCFA</td>
                                <td style="font-weight:700;color:var(--danger)"><?= number_format((float)$d['solde'], 0, ',', ' ') ?> FCFA</td>
                                <td>
                                    <?php if ($d['statut'] === 'partiel'): ?>
                                        <span class="badge badge-warning">Partiel</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Non payé</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="bilan-total-row">
                                <td colspan="6"><strong>TOTAL DETTES</strong></td>
                                <td colspan="2" style="color:var(--danger)"><strong><?= number_format($totalDette, 0, ',', ' ') ?> FCFA</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- end bilan-content -->
</div>

<script>
function exporterBilanPDF() {
    const date = '<?= $dateDisplay ?>';

    const kpis = [
        { label: 'Date', value: date },
        { label: 'Total colis', value: '<?= (int)$kpi['nb_colis'] ?>' },
        { label: 'Colis individuels', value: '<?= (int)$kpi['nb_individuel'] ?>' },
        { label: 'Colis mixtes', value: '<?= (int)$kpi['nb_mixte'] ?>' },
        { label: 'Total kilos', value: '<?= number_format((float)$kpi['total_kilos'], 2) ?> kg' },
        { label: 'Total montant', value: '<?= number_format((float)$kpi['total_montant'], 0, ',', ' ') ?> FCFA' },
        { label: 'Total encaissé', value: '<?= number_format($totalEnc, 0, ',', ' ') ?> FCFA' },
        { label: 'Total dû', value: '<?= number_format($totalDu, 0, ',', ' ') ?> FCFA' },
        { label: 'Total dettes', value: '<?= number_format($totalDette, 0, ',', ' ') ?> FCFA' }
    ];

    const colisRows = [];
    <?php foreach ($colisDuJour as $c): ?>
    colisRows.push([
        '<?= addslashes($c['code_complet']) ?>',
        '<?= $c['type'] === 'mixte' ? 'Mixte' : 'Individuel' ?>',
        '<?= addslashes($c['transitaire_nom']) ?>',
        '<?= number_format((float)$c['poids'], 2) ?>',
        '<?= number_format((float)$c['montant'], 0, ',', ' ') ?> FCFA',
        '<?= number_format((float)$c['montant_paye_total'], 0, ',', ' ') ?> FCFA',
        '<?= number_format((float)$c['solde_total'], 0, ',', ' ') ?> FCFA'
    ]);
    <?php endforeach; ?>

    const dettesRows = [];
    <?php foreach ($dettes as $d): ?>
    dettesRows.push([
        '<?= addslashes($d['client_nom']) ?>',
        '<?= addslashes($d['telephone'] ?? '') ?>',
        '<?= addslashes($d['code_complet']) ?>',
        '<?= number_format((float)$d['montant_du'], 0, ',', ' ') ?> FCFA',
        '<?= number_format((float)$d['montant_paye'], 0, ',', ' ') ?> FCFA',
        '<?= number_format((float)$d['solde'], 0, ',', ' ') ?> FCFA',
        '<?= $d['statut'] === 'partiel' ? 'Partiel' : 'Non payé' ?>'
    ]);
    <?php endforeach; ?>

    const tables = [
        {
            title: 'Detail des colis',
            headers: ['Code colis', 'Type', 'Transitaire', 'Poids (kg)', 'Montant', 'Encaissé', 'Solde'],
            rows: colisRows
        }
    ];

    if (dettesRows.length) {
        tables.push({
            title: 'Dettes du jour',
            headers: ['Client', 'Telephone', 'Colis', 'Montant du', 'Paye', 'Solde', 'Statut'],
            rows: dettesRows
        });
    }

    exportBilanPDF({
        date: date,
        title: 'Bilan journalier du ' + date,
        entete: 'Gestion Import/Export',
        kpis: kpis,
        tables: tables
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
