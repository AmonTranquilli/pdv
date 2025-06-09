<?php
header('Content-Type: application/json');
require_once '../../includes/conexao.php'; // Garanta que o caminho está correto

// Função para enviar notificações para o nosso motor do bot
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

session_start();
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso não autorizado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$idPedido = isset($input['id_pedido']) ? intval($input['id_pedido']) : null;
$novoStatus = isset($input['novo_status']) ? trim($input['novo_status']) : null;
$statusPermitidos = ['pendente', 'preparando', 'em_entrega', 'finalizado', 'cancelado'];

if (!$idPedido || !$novoStatus || !in_array($novoStatus, $statusPermitidos)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos fornecidos.']);
    exit;
}

// Inicia a transação
$conn->begin_transaction();

try {
    // Atualiza o status do pedido no banco de dados
    $stmt = $conn->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
    if ($stmt === false) {
        throw new Exception("Erro ao preparar a atualização do pedido.");
    }
    $stmt->bind_param('si', $novoStatus, $idPedido);
    $stmt->execute();
    
    // Verifica se alguma linha foi realmente alterada
    if ($stmt->affected_rows > 0) {
        // Se foi alterada, comita a transação para salvar permanentemente
        $conn->commit();
        $stmt->close(); // Fecha o statement após o commit

        // SÓ DEPOIS DE TER CERTEZA QUE FOI SALVO, disparamos a notificação
        // Bloco Novo e Melhorado

// Disparamos a notificação para 'preparando'
if ($novoStatus === 'preparando') {
    $stmt_cliente = $conn->prepare("SELECT nome_cliente, telefone_cliente FROM pedidos WHERE id = ?");
    $stmt_cliente->bind_param("i", $idPedido);
    $stmt_cliente->execute();
    $result_cliente = $stmt_cliente->get_result();
    if ($cliente = $result_cliente->fetch_assoc()) {
        $dados_para_bot = [
            "template_name" => "pedido_em_preparacao",
            "nome_cliente"  => explode(' ', $cliente['nome_cliente'])[0],
            "id_pedido"     => strval($idPedido)
        ];
        $telefone_formatado = "55" . preg_replace('/\D/', '', $cliente['telefone_cliente']);
        enviarNotificacaoWhatsApp($telefone_formatado, $dados_para_bot);
    }
    $stmt_cliente->close();
} 
// OU disparamos a notificação para 'em_entrega'
else if ($novoStatus === 'em_entrega') { 
    $stmt_cliente = $conn->prepare("SELECT telefone_cliente FROM pedidos WHERE id = ?");
    $stmt_cliente->bind_param("i", $idPedido);
    $stmt_cliente->execute();
    $result_cliente = $stmt_cliente->get_result();
    if ($cliente = $result_cliente->fetch_assoc()) {
        $dados_para_bot = [
            "template_name" => "pedido_a_caminho", // Novo template
            "id_pedido"     => strval($idPedido)     // Apenas o ID é necessário
        ];
        $telefone_formatado = "55" . preg_replace('/\D/', '', $cliente['telefone_cliente']);
        enviarNotificacaoWhatsApp($telefone_formatado, $dados_para_bot);
    }
    $stmt_cliente->close();
}
        
        echo json_encode(['sucesso' => true, 'mensagem' => 'Status do pedido atualizado com sucesso!']);

    } else {
        // Se nenhuma linha foi afetada, não precisa fazer commit nem rollback
        $stmt->close();
        echo json_encode(['sucesso' => true, 'mensagem' => 'Nenhuma alteração necessária, o pedido já estava com este status.']);
    }

} catch (Exception $e) {
    // Se qualquer erro ocorrer, desfaz todas as operações
    $conn->rollback();
    error_log("Erro em atualizar_status_pedido.php: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao processar a solicitação: ' . $e->getMessage()]);

} finally {
    // Fecha a conexão no final de tudo
    if (isset($conn)) {
        $conn->close();
    }
}
?>