<?php
// public/api/obter_entregadores.php
header('Content-Type: application/json');
require_once '../../includes/conexao.php';

session_start();
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // É importante retornar um JSON de erro para que o JavaScript possa tratar
    echo json_encode(['erro' => true, 'mensagem' => 'Acesso não autorizado.']);
    exit;
}

$entregadores = [];
// Seleciona apenas os entregadores que estão marcados como "ativo = 1"
$sql = "SELECT id, nome, codigo_entregador FROM entregadores WHERE ativo = 1 ORDER BY nome ASC";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $entregadores[] = $row;
    }
    // Retorna a lista de entregadores em formato JSON
    echo json_encode($entregadores);
} else {
    // Em caso de erro na consulta, retorna uma mensagem de erro em JSON
    echo json_encode(['erro' => true, 'mensagem' => 'Erro ao buscar a lista de entregadores.']);
}

$conn->close();
?>
