<?php
$pageTitle  = 'Dettes';
$activePage = 'dettes';
$root       = '../';
require_once '../includes/header.php';

$db = getDB();

// FILTRES
$filterClient  = (int)($_GET['client_id'] ?? 0);
$filterStatut  = $_GET['statut'] ?? '';
$filterDate    = $_GET['date'] ?? '';

$where  = "cp.statut != 'paye'";
$params = [];
if ($filterClient) { $where .= ' AND cl.id=?'; $params[] = $filterClient; }
if ($filterStatut) { $where .= ' AND cp.statut=?'; $params[] = $filterStatut; }
if ($filterDate) { $where .= ' AND a.date_arrivee=?'; $params[] = $filterDate; }

$stmt = $db->prepare("
    SELECT cp.*, cl.nom as client_nom, cl.telephone, c.code_complet, c.type as type_colis,
           a.date_arrivee, t.nom as transitaire_nom
    FROM colis_proprietaires cp
    JOIN clients cl ON cp.client_id = cl.id
    JOIN colis c ON cp.colis_id = c.id
    JOIN arrivages a ON c.arrivage_id = a.id
    JOIN transitaires t ON a.transitaire_id = t.id
    WHERE $where
    ORDER BY a.date_arrivee DESC, cl.nom
");
$stmt->execute($params);
$dettes = $stmt->fetchAll();

$totalSolde = array_sum(array_column($dettes, 'solde'));
$clients    = $db->query("SELECT id, nom FROM clients ORDER BY nom")->fetchAll();
?>
<div class="page-content">

    <div class="card">
        <div class="card-header">
            <div class="card-title" style="color:var(--danger)">
                <i class="fas fa-exclamation-circle"></i> Suivi des dettes
            </div>
            <div style="display:flex;gap:8px">
                <span class="badge badge-danger" style="font-size:14px;padding:8px 14px">
                    Total : <?= number_format($totalSolde, 0, ',', ' ') ?> FCFA
                </span>
                <button class="btn btn-outline btn-sm" onclick="exportTableToCSV('tableDettes','dettes')">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="filters-bar">
                <select name="client_id" class="form-control" style="width:200px">
                    <option value="">Tous les clients</option>
                    <?php foreach ($clients as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= $filterClient == $cl['id'] ? 'selected' : '' ?>>
                            <?= sanitize($cl['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="statut" class="form-control">
                    <option value="">Tous statuts</option>
                    <option value="partiel" <?= $filterStatut === 'partiel' ? 'selected' : '' ?>>Partiel</option>
                    <option value="non_paye" <?= $filterStatut === 'non_paye' ? 'selected' : '' ?>>Non payé</option>
                </select>
                <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>" class="form-control" style="width:160px">
                <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Filtrer</button>
                <a href="dettes.php" class="btn btn-outline"><i class="fas fa-times"></i> Réinitialiser</a>
            </form>

            <div class="table-responsive">
                <table id="tableDettes">
                    <thead>
                        <tr>
                            <th>Date arrivage</th>
                            <th>Client</th>
                            <th>Téléphone</th>
                            <th>Colis</th>
                            <th>Type</th>
                            <th>Transitaire</th>
                            <th>Montant dû</th>
                            <th>Payé</th>
                            <th>Solde</th>
                            <th>Statut</th>
                            <th class="no-export">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($dettes): ?>
                            <?php foreach ($dettes as $d): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($d['date_arrivee'])) ?></td>
                                <td><strong><?= sanitize($d['client_nom']) ?></strong></td>
                                <td><?= sanitize($d['telephone'] ?: '-') ?></td>
                                <td><span class="colis-code"><?= sanitize($d['code_complet']) ?></span></td>
                                <td>
                                    <?php if ($d['type_colis'] === 'mixte'): ?>
                                        <span class="badge badge-info">Mixte</span>
                                    <?php else: ?>
                                        <span class="badge badge-primary">Individuel</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= sanitize($d['transitaire_nom']) ?></td>
                                <td><?= number_format((float)$d['montant_du'], 0, ',', ' ') ?> FCFA</td>
                                <td style="color:var(--success)">
                                    <?= number_format((float)$d['montant_paye'], 0, ',', ' ') ?> FCFA
                                    <?php $pct = $d['montant_du'] > 0 ? round(($d['montant_paye'] / $d['montant_du']) * 100) : 0; ?>
                                    <div class="progress-wrap">
                                        <div class="progress-bar-outer">
                                            <div class="progress-bar-inner" style="width:<?= $pct ?>%;background:var(--success)"></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-weight:700;color:var(--danger)">
                                    <?= number_format((float)$d['solde'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td>
                                    <?php if ($d['statut'] === 'partiel'): ?>
                                        <span class="badge badge-warning">Partiel</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Non payé</span>
                                    <?php endif; ?>
                                </td>
                                <td class="no-export">
                                    <a href="paiements.php?cp_id=<?= $d['id'] ?>&client=<?= urlencode($d['client_nom']) ?>"
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i> Payer
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="bilan-total-row">
                                <td colspan="8"><strong>TOTAL DETTES</strong></td>
                                <td colspan="3" style="color:var(--danger)">
                                    <strong><?= number_format($totalSolde, 0, ',', ' ') ?> FCFA</strong>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="11">
                                <div class="empty-state">
                                    <i class="fas fa-check-circle" style="color:var(--success)"></i>
                                    <h3>Aucune dette en cours</h3>
                                    <p>Tous les colis sont soldés.</p>
                                </div>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
