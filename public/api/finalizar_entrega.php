<?php
header('Content-Type: application/json');
session_start();
require_once '../../includes/conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso não autorizado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$idPedido = isset($input['id_pedido']) ? intval($input['id_pedido']) : null;
// CORRIGIDO: Agora espera 'id_entregador' em vez de 'codigo_entregador'
$idEntregador = isset($input['id_entregador']) ? intval($input['id_entregador']) : null;

if (!$idPedido || !$idEntregador) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos. Por favor, selecione um entregador.']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // 1. Apenas verifica se o ID do entregador é válido e se ele está ativo
    $stmt_entregador = $conn->prepare("SELECT id FROM entregadores WHERE id = ? AND ativo = 1");
    if (!$stmt_entregador) throw new Exception("Erro ao preparar a busca do entregador: " . $conn->error);
    
    $stmt_entregador->bind_param("i", $idEntregador);
    $stmt_entregador->execute();
    $result_entregador = $stmt_entregador->get_result();
    
    if ($result_entregador->num_rows === 0) {
        throw new Exception("Entregador não encontrado ou está inativo.");
    }
    $stmt_entregador->close();

    // 2. Atualiza o pedido para 'finalizado' e associa o ID do entregador
    $stmt_update_pedido = $conn->prepare("UPDATE pedidos SET status = 'finalizado', id_entregador = ? WHERE id = ? AND status = 'em_entrega'");
    if (!$stmt_update_pedido) throw new Exception("Erro ao preparar a atualização do pedido: " . $conn->error);

    $stmt_update_pedido->bind_param("ii", $idEntregador, $idPedido);
    $stmt_update_pedido->execute();
    
    if ($stmt_update_pedido->affected_rows > 0) {
        $conn->commit();
        echo json_encode(['sucesso' => true, 'mensagem' => 'Pedido finalizado e entregador associado com sucesso!']);
    } else {
        throw new Exception("O pedido não pôde ser finalizado. Verifique se o status do pedido ainda é 'Em Entrega'.");
    }
    
    $stmt_update_pedido->close();

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}

$conn->close();
?>
