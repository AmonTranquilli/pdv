<?php
session_start();
require_once '../../includes/conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit();
}

// Agora esperamos um ID de categoria, em vez de um termo de busca
$categoria_id = (int)($_GET['categoria_id'] ?? 0);
$exclude_id = (int)($_GET['exclude'] ?? 0);

if ($categoria_id <= 0) {
    echo json_encode([]); // Retorna vazio se nenhuma categoria válida for enviada
    exit();
}

// A consulta SQL agora filtra pela categoria
$stmt = $conn->prepare("SELECT id, nome FROM produtos WHERE id_categoria = ? AND id != ? AND ativo = 1 ORDER BY nome ASC");
$stmt->bind_param("ii", $categoria_id, $exclude_id);
$stmt->execute();
$result = $stmt->get_result();

$produtos = [];
while ($row = $result->fetch_assoc()) {
    // O formato {id, text} é perfeito para o Select2
    $produtos[] = ['id' => $row['id'], 'text' => $row['nome']];
}

$stmt->close();
$conn->close();

echo json_encode($produtos);
?>