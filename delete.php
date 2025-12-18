<?php
require_once __DIR__ . '/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    die('ID non valido.');
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM professionista_professione WHERE idProfessionista = :id')->execute([':id' => $id]);
    $pdo->prepare('DELETE FROM professionista WHERE idProfessionista = :id')->execute([':id' => $id]);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die('Errore durante l\'eliminazione: ' . $e->getMessage());
}

header('Location: index.php');
exit;
