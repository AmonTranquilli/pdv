<?php
header('Content-Type: application/json');
session_start();
require_once '../../includes/conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso não autorizado.']);
    exit;
}

try {
    $conn->begin_transaction();

    $status_ativos = ['pendente', 'preparando', 'em_entrega'];
    $placeholders = implode(',', array_fill(0, count($status_ativos), '?'));
    
    $sql_check = "SELECT COUNT(*) as total_ativos FROM pedidos WHERE status IN ($placeholders) AND arquivado = 0";
    $stmt_check = $conn->prepare($sql_check);
    $types = str_repeat('s', count($status_ativos));
    $stmt_check->bind_param($types, ...$status_ativos);
    $stmt_check->execute();
    $pedidos_ativos = (int)$stmt_check->get_result()->fetch_assoc()['total_ativos'];
    $stmt_check->close();

    if ($pedidos_ativos > 0) {
        throw new Exception("Ainda existem " . $pedidos_ativos . " pedido(s) em andamento. Por favor, resolva todos os pedidos antes de finalizar o turno.");
    }
    
    $sql_archive = "UPDATE pedidos SET arquivado = 1 WHERE status IN ('finalizado', 'cancelado') AND arquivado = 0";
    $stmt_archive = $conn->prepare($sql_archive);
    
    if ($stmt_archive === false) {
        throw new Exception("Erro ao preparar a query de arquivamento.");
    }

    $stmt_archive->execute();
    $pedidos_arquivados = $stmt_archive->affected_rows;
    $stmt_archive->close();
    
    $conn->commit();
    
    echo json_encode(['sucesso' => true, 'mensagem' => "Turno finalizado! " . $pedidos_arquivados . " pedido(s) foram arquivados com sucesso."]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Erro em finalizar_dia.php: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}

$conn->close();
?>
