<?php
header('Content-Type: application/json');
session_start();
require_once '../../includes/conexao.php';

// ADICIONADO AQUI: A definição da função que estava faltando
function enviarNotificacaoWhatsApp($telefone, $dados) {
    $url = 'http://localhost:3000/enviar-notificacao';
    $postData = ['telefone_destino' => $telefone, 'mensagem' => $dados];
    $payload = json_encode($postData);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)]);
    @curl_exec($ch);
    curl_close($ch);
}

// O resto do seu código, agora com a função disponível
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso não autorizado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$idPedido = isset($input['id_pedido']) ? intval($input['id_pedido']) : null;
$idEntregador = isset($input['id_entregador']) ? intval($input['id_entregador']) : null;

if (!$idPedido || !$idEntregador) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos. Por favor, selecione um entregador.']);
    exit;
}

$conn->begin_transaction();

try {
    $stmt_entregador = $conn->prepare("SELECT id FROM entregadores WHERE id = ? AND ativo = 1");
    if (!$stmt_entregador) throw new Exception("Erro ao preparar a busca do entregador: " . $conn->error);
    
    $stmt_entregador->bind_param("i", $idEntregador);
    $stmt_entregador->execute();
    $result_entregador = $stmt_entregador->get_result();
    
    if ($result_entregador->num_rows === 0) {
        throw new Exception("Entregador não encontrado ou está inativo.");
    }
    $stmt_entregador->close();

    $stmt_update_pedido = $conn->prepare("UPDATE pedidos SET status = 'finalizado', id_entregador = ? WHERE id = ? AND status = 'em_entrega'");
    if (!$stmt_update_pedido) throw new Exception("Erro ao preparar a atualização do pedido: " . $conn->error);

    $stmt_update_pedido->bind_param("ii", $idEntregador, $idPedido);
    $stmt_update_pedido->execute();
    
    if ($stmt_update_pedido->affected_rows > 0) {
        $conn->commit();
        $stmt_update_pedido->close();

        // --- GATILHO DO BOT ---
        $stmt_cliente = $conn->prepare("SELECT nome_cliente, telefone_cliente FROM pedidos WHERE id = ?");
        $stmt_cliente->bind_param("i", $idPedido);
        $stmt_cliente->execute();
        $result_cliente = $stmt_cliente->get_result();
        if ($cliente = $result_cliente->fetch_assoc()) {
        // Prepara os dados para o bot (agora só com 1 variável para o template)
        $dados_para_bot = [
        // O nome do template que o Node.js vai usar
        "template_name" => "pedido_entregue_agradecimento", // Use o nome exato do seu template

        // O único dado que o template precisa
        "nome_cliente"  => explode(' ', $cliente['nome_cliente'])[0] 
    ];

        $telefone_formatado = "55" . preg_replace('/\D/', '', $cliente['telefone_cliente']);
        enviarNotificacaoWhatsApp($telefone_formatado, $dados_para_bot);
}
        $stmt_cliente->close();
        // --- FIM DO GATILHO DO BOT ---

        $_SESSION['mensagem_sucesso'] = "Pedido #" . $idPedido . " finalizado com sucesso!";
        echo json_encode(['sucesso' => true, 'mensagem' => 'Pedido finalizado.']);

    } else {
        $stmt_update_pedido->close();
        throw new Exception("O pedido não pôde ser finalizado. Verifique se o status do pedido ainda é 'Em Entrega'.");
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
} finally {
    if(isset($conn)) {
        $conn->close();
    }
}
?>