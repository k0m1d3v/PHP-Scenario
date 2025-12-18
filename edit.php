<?php
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    die('ID professionista mancante o non valido.');
}

$errors = [];

$cities = $pdo->query('SELECT idCitta, nome, provincia FROM citta ORDER BY nome')->fetchAll();
$professions = $pdo->query('SELECT idProfessione, nome FROM professione ORDER BY nome')->fetchAll();

$profStmt = $pdo->prepare('SELECT * FROM professionista WHERE idProfessionista = :id');
$profStmt->execute([':id' => $id]);
$professionista = $profStmt->fetch();

if (!$professionista) {
    die('Professionista non trovato.');
}

$selectedProfessionIds = $pdo->prepare('SELECT idProfessione FROM professionista_professione WHERE idProfessionista = :id');
$selectedProfessionIds->execute([':id' => $id]);
$selectedProfessionIds = array_map('intval', $selectedProfessionIds->fetchAll(PDO::FETCH_COLUMN));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $descrizione = trim($_POST['descrizione'] ?? '');
    $tariffaRaw = $_POST['tariffa_oraria'] ?? '';
    $tariffa = is_numeric($tariffaRaw) ? (float) $tariffaRaw : null;
    $disponibilita = isset($_POST['disponibilita']) ? 1 : 0;
    $idCitta = isset($_POST['idCitta']) ? (int) $_POST['idCitta'] : 0;
    $selectedProfessionIds = array_map('intval', $_POST['professioni'] ?? []);

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
    $selectedProfessionIds = array_values(array_intersect($selectedProfessionIds, $allowedProfessionIds));

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $update = $pdo->prepare('UPDATE professionista SET nome = :nome, telefono = :telefono, email = :email, descrizione = :descrizione, tariffa_oraria = :tariffa, disponibilita = :disponibilita, idCitta = :idCitta WHERE idProfessionista = :id');
            $update->execute([
                ':nome' => $nome,
                ':telefono' => $telefono !== '' ? $telefono : null,
                ':email' => $email !== '' ? $email : null,
                ':descrizione' => $descrizione !== '' ? $descrizione : null,
                ':tariffa' => $tariffa,
                ':disponibilita' => $disponibilita,
                ':idCitta' => $idCitta,
                ':id' => $id,
            ]);

            $pdo->prepare('DELETE FROM professionista_professione WHERE idProfessionista = :id')->execute([':id' => $id]);

            if (!empty($selectedProfessionIds)) {
                $ppStmt = $pdo->prepare('INSERT INTO professionista_professione (idProfessionista, idProfessione) VALUES (:idProfessionista, :idProfessione)');
                foreach ($selectedProfessionIds as $idProfessione) {
                    $ppStmt->execute([
                        ':idProfessionista' => $id,
                        ':idProfessione' => $idProfessione,
                    ]);
                }
            }

            $pdo->commit();
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Errore durante l\'aggiornamento: ' . $e->getMessage();
        }
    }
} else {
    // Pre-popola i campi con i dati attuali
    $nome = $professionista['nome'];
    $telefono = $professionista['telefono'] ?? '';
    $email = $professionista['email'] ?? '';
    $descrizione = $professionista['descrizione'] ?? '';
    $tariffa = $professionista['tariffa_oraria'];
    $disponibilita = $professionista['disponibilita'];
    $idCitta = $professionista['idCitta'];
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica professionista</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f8f9fb; }
        form { background: #fff; padding: 16px; border: 1px solid #ddd; border-radius: 8px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input[type="text"], input[type="email"], input[type="number"], textarea, select { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        textarea { resize: vertical; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px; }
        .checkbox-item { background: #f2f2f2; padding: 6px 10px; border-radius: 6px; }
        .actions { margin-top: 12px; display: flex; gap: 8px; align-items: center; }
        .btn { padding: 8px 14px; border: none; border-radius: 6px; cursor: pointer; }
        .btn-primary { background-color: #007bff; color: #fff; }
        .btn-secondary { background-color: #6c757d; color: #fff; text-decoration: none; display: inline-block; }
        .error { color: #b30000; margin-bottom: 12px; }
    </style>
</head>
<body>
    <h1>Modifica professionista</h1>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <label for="nome">Nome *</label>
        <input type="text" name="nome" id="nome" required value="<?= e($nome ?? '') ?>">

        <label for="telefono">Telefono</label>
        <input type="text" name="telefono" id="telefono" value="<?= e($telefono ?? '') ?>">

        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="<?= e($email ?? '') ?>">

        <label for="descrizione">Descrizione</label>
        <textarea name="descrizione" id="descrizione" rows="3"><?= e($descrizione ?? '') ?></textarea>

        <label for="tariffa_oraria">Tariffa oraria (€) *</label>
        <input type="number" name="tariffa_oraria" id="tariffa_oraria" step="0.01" min="0" required value="<?= e((string) ($tariffa ?? '')) ?>">

        <label><input type="checkbox" name="disponibilita" value="1" <?= !empty($disponibilita) ? 'checked' : '' ?>> Disponibile</label>

        <label for="idCitta">Città *</label>
        <select name="idCitta" id="idCitta" required>
            <option value="">-- Seleziona --</option>
            <?php foreach ($cities as $c): ?>
                <option value="<?= (int) $c['idCitta'] ?>" <?= ((int) ($idCitta ?? 0) === (int) $c['idCitta']) ? 'selected' : '' ?>>
                    <?= e($c['nome']) ?> (<?= e($c['provincia']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label>Professioni</label>
        <div class="checkbox-group">
            <?php foreach ($professions as $p): ?>
                <label class="checkbox-item">
                    <input type="checkbox" name="professioni[]" value="<?= (int) $p['idProfessione'] ?>" <?= in_array((int) $p['idProfessione'], $selectedProfessionIds, true) ? 'checked' : '' ?>>
                    <?= e($p['nome']) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Salva modifiche</button>
            <a href="index.php" class="btn btn-secondary">Annulla</a>
        </div>
    </form>
</body>
</html>
