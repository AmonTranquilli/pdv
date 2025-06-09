<?php
session_start();
header('Content-Type: application/json');
require_once '../../includes/conexao.php';

// A definição da função para enviar a notificação
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

// Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso não autorizado.']);
    exit;
}

$pedido_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$pedido_id) {
    $_SESSION['mensagem_erro'] = "ID de pedido inválido para cancelamento.";
    header("Location: ../pedidos/pedidos.php"); // Redireciona para a lista geral
    exit();
}

$conn->begin_transaction();

try {
    // 1. Busca dados do pedido e trava a linha para a transação
    $stmt_pedido = $conn->prepare("SELECT status, nome_cliente, telefone_cliente FROM pedidos WHERE id = ? FOR UPDATE");
    $stmt_pedido->bind_param("i", $pedido_id);
    $stmt_pedido->execute();
    $result_pedido = $stmt_pedido->get_result();

    if ($result_pedido->num_rows === 0) {
        throw new Exception("Pedido não encontrado.");
    }
    $pedido_data = $result_pedido->fetch_assoc();
    $current_status = $pedido_data['status'];
    $stmt_pedido->close();

    // 2. Evita cancelar pedidos que já estão em estados finais
    if ($current_status === 'finalizado' || $current_status === 'cancelado') {
        throw new Exception("Não é possível cancelar um pedido que já está '" . htmlspecialchars($current_status) . "'.");
    }

    // 3. Devolve itens ao estoque
    $stmt_itens = $conn->prepare("SELECT id_produto, quantidade FROM itens_pedido WHERE id_pedido = ?");
    $stmt_itens->bind_param("i", $pedido_id);
    $stmt_itens->execute();
    $result_itens = $stmt_itens->get_result();
    
    $stmt_update_estoque = $conn->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ? AND controla_estoque = 1");
    while ($item = $result_itens->fetch_assoc()) {
        $stmt_update_estoque->bind_param("ii", $item['quantidade'], $item['id_produto']);
        $stmt_update_estoque->execute();
    }
    $stmt_itens->close();
    $stmt_update_estoque->close();

    // 4. Atualiza o status do pedido para 'cancelado'
    $stmt_cancel = $conn->prepare("UPDATE pedidos SET status = 'cancelado' WHERE id = ?");
    $stmt_cancel->bind_param("i", $pedido_id);
    $stmt_cancel->execute();
    $stmt_cancel->close();

    // 5. Se tudo deu certo, salva no banco
    $conn->commit();
    $_SESSION['mensagem_sucesso'] = "Pedido #" . $pedido_id . " cancelado com sucesso!";

    // --- INÍCIO DO GATILHO DO BOT ---
    $dados_para_bot = [
        "template_name" => "pedido_cancelado",
        "nome_cliente"  => explode(' ', $pedido_data['nome_cliente'])[0],
        "id_pedido"     => strval($pedido_id)
    ];
    $telefone_formatado = "55" . preg_replace('/\D/', '', $pedido_data['telefone_cliente']);
    enviarNotificacaoWhatsApp($telefone_formatado, $dados_para_bot);
    // --- FIM DO GATILHO DO BOT ---

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['mensagem_erro'] = "Erro ao cancelar pedido: " . $e->getMessage();
} finally {
    if(isset($conn)) {
        $conn->close();
    }
}

// Redireciona de volta para a página de detalhes
header("Location: ../pedidos/detalhes_pedido.php?id=" . $pedido_id);
exit();