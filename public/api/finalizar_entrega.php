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
$codigoEntregador = isset($input['codigo_entregador']) ? trim(strtoupper($input['codigo_entregador'])) : null;

if (!$idPedido || !$codigoEntregador) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos. ID do pedido e código do entregador são necessários.']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // 1. Validar o código do entregador e obter o ID
    $stmt_entregador = $conn->prepare("SELECT id FROM entregadores WHERE codigo_entregador = ? AND ativo = 1");
    if (!$stmt_entregador) throw new Exception("Erro ao preparar a busca do entregador: " . $conn->error);
    
    $stmt_entregador->bind_param("s", $codigoEntregador);
    $stmt_entregador->execute();
    $result_entregador = $stmt_entregador->get_result();
    
    if ($result_entregador->num_rows === 0) {
        throw new Exception("Código de entregador inválido ou o entregador está inativo.");
    }
    
    $entregador = $result_entregador->fetch_assoc();
    $idEntregador = $entregador['id'];
    $stmt_entregador->close();

    // 2. Atualizar o pedido para 'finalizado' e associar o entregador
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
