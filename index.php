<?php
require_once __DIR__ . '/db.php';

$errors = [];

$citiesStmt = $pdo->query('SELECT idCitta, nome, provincia FROM citta ORDER BY nome');
$cities = $citiesStmt->fetchAll();

$professionsStmt = $pdo->query('SELECT idProfessione, nome FROM professione ORDER BY nome');
$professions = $professionsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $descrizione = trim($_POST['descrizione'] ?? '');
    $tariffaRaw = $_POST['tariffa_oraria'] ?? '';
    $tariffa = is_numeric($tariffaRaw) ? (float) $tariffaRaw : null;
    $disponibilita = isset($_POST['disponibilita']) ? 1 : 0;
    $idCitta = isset($_POST['idCitta']) ? (int) $_POST['idCitta'] : 0;
    $selectedProfessions = array_map('intval', $_POST['professioni'] ?? []);

    if ($nome === '') {
        $errors[] = 'Il nome è obbligatorio.';
    }

    if ($tariffa === null || $tariffa < 0) {
        $errors[] = 'La tariffa oraria deve essere un numero maggiore o uguale a 0.';
    }

    $cityIds = array_column($cities, 'idCitta');
    if ($idCitta <= 0 || !in_array($idCitta, $cityIds, true)) {
        $errors[] = 'Seleziona una città valida.';
    }

    $allowedProfessionIds = array_column($professions, 'idProfessione');
    $selectedProfessions = array_values(array_intersect($selectedProfessions, $allowedProfessionIds));

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $insert = $pdo->prepare('INSERT INTO professionista (nome, telefono, email, descrizione, tariffa_oraria, disponibilita, idCitta) VALUES (:nome, :telefono, :email, :descrizione, :tariffa, :disponibilita, :idCitta)');
            $insert->execute([
                ':nome' => $nome,
                ':telefono' => $telefono !== '' ? $telefono : null,
                ':email' => $email !== '' ? $email : null,
                ':descrizione' => $descrizione !== '' ? $descrizione : null,
                ':tariffa' => $tariffa,
                ':disponibilita' => $disponibilita,
                ':idCitta' => $idCitta,
            ]);

            $newId = (int) $pdo->lastInsertId();

            if (!empty($selectedProfessions)) {
                $ppStmt = $pdo->prepare('INSERT INTO professionista_professione (idProfessionista, idProfessione) VALUES (:idProfessionista, :idProfessione)');
                foreach ($selectedProfessions as $idProfessione) {
                    $ppStmt->execute([
                        ':idProfessionista' => $newId,
                        ':idProfessione' => $idProfessione,
                    ]);
                }
            }

            $pdo->commit();
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Errore durante l\'inserimento: ' . $e->getMessage();
        }
    }
}

// Filtri
$filterProfessione = isset($_GET['professione']) ? (int) $_GET['professione'] : 0;
$filterCitta = isset($_GET['citta']) ? (int) $_GET['citta'] : 0;
$filterTariffaMaxRaw = $_GET['tariffa_max'] ?? '';
$filterTariffaMax = is_numeric($filterTariffaMaxRaw) ? (float) $filterTariffaMaxRaw : null;
$filterDisponibili = isset($_GET['solo_disponibili']) ? 1 : 0;

$conditions = [];
$params = [];

if ($filterProfessione > 0) {
    $conditions[] = 'EXISTS (
        SELECT 1 FROM professionista_professione pp2
        WHERE pp2.idProfessionista = p.idProfessionista
          AND pp2.idProfessione = :fProfessione
    )';
    $params[':fProfessione'] = $filterProfessione;
}

if ($filterCitta > 0) {
    $conditions[] = 'p.idCitta = :fCitta';
    $params[':fCitta'] = $filterCitta;
}

if ($filterTariffaMax !== null) {
    $conditions[] = 'p.tariffa_oraria <= :fTariffaMax';
    $params[':fTariffaMax'] = $filterTariffaMax;
}

if ($filterDisponibili) {
    $conditions[] = 'p.disponibilita = 1';
}

$whereClause = '';
if (!empty($conditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

$listSql = "
    SELECT
        p.idProfessionista,
        p.nome,
        p.tariffa_oraria,
        p.disponibilita,
        c.nome AS citta,
        c.provincia,
        GROUP_CONCAT(DISTINCT pr.nome ORDER BY pr.nome SEPARATOR ', ') AS professioni
    FROM professionista p
    JOIN citta c ON c.idCitta = p.idCitta
    LEFT JOIN professionista_professione pp ON pp.idProfessionista = p.idProfessionista
    LEFT JOIN professione pr ON pr.idProfessione = pp.idProfessione
    $whereClause
    GROUP BY p.idProfessionista
    ORDER BY p.nome
";

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$professionisti = $listStmt->fetchAll();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Artigiani Finder - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f8f9fb; }
        h1 { color: #333; }
        form { background: #fff; padding: 16px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input[type="text"], input[type="email"], input[type="number"], textarea, select { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        textarea { resize: vertical; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px; }
        .checkbox-item { background: #f2f2f2; padding: 6px 10px; border-radius: 6px; }
        .actions { margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        .error { color: #b30000; margin-bottom: 12px; }
        .filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
        .btn { padding: 8px 14px; border: none; border-radius: 6px; cursor: pointer; }
        .btn-primary { background-color: #007bff; color: #fff; }
        .btn-secondary { background-color: #6c757d; color: #fff; }
        .btn-link { color: #007bff; text-decoration: none; }
        .availability { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Artigiani Finder - Pannello Admin</h1>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <h2>Inserisci professionista</h2>
        <label for="nome">Nome *</label>
        <input type="text" name="nome" id="nome" required value="<?= e($_POST['nome'] ?? '') ?>">

        <label for="telefono">Telefono</label>
        <input type="text" name="telefono" id="telefono" value="<?= e($_POST['telefono'] ?? '') ?>">

        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="<?= e($_POST['email'] ?? '') ?>">

        <label for="descrizione">Descrizione</label>
        <textarea name="descrizione" id="descrizione" rows="3"><?= e($_POST['descrizione'] ?? '') ?></textarea>

        <label for="tariffa_oraria">Tariffa oraria (€) *</label>
        <input type="number" name="tariffa_oraria" id="tariffa_oraria" step="0.01" min="0" required value="<?= e($_POST['tariffa_oraria'] ?? '') ?>">

        <?php
            $defaultDisponibilita = array_key_exists('disponibilita', $_POST) ? isset($_POST['disponibilita']) : true;
        ?>
        <label><input type="checkbox" name="disponibilita" value="1" <?= $defaultDisponibilita ? 'checked' : '' ?>> Disponibile</label>

        <label for="idCitta">Città *</label>
        <select name="idCitta" id="idCitta" required>
            <option value="">-- Seleziona --</option>
            <?php foreach ($cities as $c): ?>
                <option value="<?= (int) $c['idCitta'] ?>" <?= (isset($_POST['idCitta']) && (int) $_POST['idCitta'] === (int) $c['idCitta']) ? 'selected' : '' ?>>
                    <?= e($c['nome']) ?> (<?= e($c['provincia']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label>Professioni</label>
        <div class="checkbox-group">
            <?php foreach ($professions as $p): ?>
                <label class="checkbox-item">
                    <input type="checkbox" name="professioni[]" value="<?= (int) $p['idProfessione'] ?>" <?= in_array((int) $p['idProfessione'], array_map('intval', $_POST['professioni'] ?? []), true) ? 'checked' : '' ?>>
                    <?= e($p['nome']) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Inserisci</button>
        </div>
    </form>

    <form method="get" action="" class="filters">
        <div>
            <label for="professione">Professione</label>
            <select name="professione" id="professione">
                <option value="0">Tutte</option>
                <?php foreach ($professions as $p): ?>
                    <option value="<?= (int) $p['idProfessione'] ?>" <?= $filterProfessione === (int) $p['idProfessione'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="citta">Città</label>
            <select name="citta" id="citta">
                <option value="0">Tutte</option>
                <?php foreach ($cities as $c): ?>
                    <option value="<?= (int) $c['idCitta'] ?>" <?= $filterCitta === (int) $c['idCitta'] ? 'selected' : '' ?>><?= e($c['nome']) ?> (<?= e($c['provincia']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="tariffa_max">Tariffa max (€)</label>
            <input type="number" name="tariffa_max" id="tariffa_max" step="0.01" min="0" value="<?= e($filterTariffaMaxRaw ?? '') ?>">
        </div>

        <div style="align-self: end;">
            <label><input type="checkbox" name="solo_disponibili" value="1" <?= $filterDisponibili ? 'checked' : '' ?>> Solo disponibili</label>
        </div>

        <div style="align-self: end; display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">Applica filtri</button>
            <a href="index.php" class="btn btn-secondary" style="text-decoration:none; display:inline-block; text-align:center;">Reset</a>
        </div>
    </form>

    <h2>Risultati</h2>
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Città</th>
                <th>Provincia</th>
                <th>Tariffa (€)</th>
                <th>Disponibile</th>
                <th>Professioni</th>
                <th>Azioni</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($professionisti)): ?>
                <tr><td colspan="7">Nessun professionista trovato.</td></tr>
            <?php else: ?>
                <?php foreach ($professionisti as $prof): ?>
                    <tr>
                        <td><?= e($prof['nome']) ?></td>
                        <td><?= e($prof['citta']) ?></td>
                        <td><?= e($prof['provincia']) ?></td>
                        <td><?= number_format((float) $prof['tariffa_oraria'], 2, ',', '.') ?></td>
                        <td class="availability" style="color: <?= $prof['disponibilita'] ? '#198754' : '#c0392b' ?>;">
                            <?= $prof['disponibilita'] ? 'Sì' : 'No' ?>
                        </td>
                        <td><?= e($prof['professioni'] ?? '') ?></td>
                        <td>
                            <a class="btn-link" href="edit.php?id=<?= (int) $prof['idProfessionista'] ?>">Modifica</a> |
                            <a class="btn-link" href="delete.php?id=<?= (int) $prof['idProfessionista'] ?>" onclick="return confirm('Sei sicuro di voler eliminare questo professionista?');">Elimina</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
