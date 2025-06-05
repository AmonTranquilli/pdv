<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho para a conexão

// Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    $_SESSION['mensagem_erro'] = "Você precisa estar logado para acessar esta página.";
    header("Location: ../login.php");
    exit();
}

// Verifica se o ID do pedido foi fornecido via GET
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['mensagem_erro'] = "ID do pedido não fornecido.";
    header("Location: pedidos.php"); // Redireciona de volta para a lista de pedidos
    exit();
}

$pedido_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

// Valida se o ID é um inteiro positivo
if ($pedido_id === false || $pedido_id <= 0) {
    $_SESSION['mensagem_erro'] = "ID do pedido inválido.";
    header("Location: pedidos.php");
    exit();
}

// --- NOVO: VERIFICAÇÃO DO STATUS DO PEDIDO ---
$stmt_status = $conn->prepare("SELECT status FROM pedidos WHERE id = ?");
$stmt_status->bind_param("i", $pedido_id);
$stmt_status->execute();
$result_status = $stmt_status->get_result();

if ($result_status->num_rows === 0) {
    $_SESSION['mensagem_erro'] = "Pedido ID " . $pedido_id . " não encontrado.";
    header("Location: pedidos.php");
    exit();
}

$pedido = $result_status->fetch_assoc();
$status_atual = $pedido['status'];
$stmt_status->close();

// Define os status que permitem a exclusão
$status_permitidos_para_exclusao = ['cancelado', 'concluido']; // 'concluido' é o mesmo que 'finalizado'

if (!in_array($status_atual, $status_permitidos_para_exclusao)) {
    $_SESSION['mensagem_erro'] = "O pedido ID " . $pedido_id . " não pode ser excluído. Status atual: '" . htmlspecialchars($status_atual) . "'. Somente pedidos 'cancelado' ou 'concluido' podem ser apagados.";
    header("Location: pedidos.php");
    exit();
}
// --- FIM DA VERIFICAÇÃO DE STATUS ---


// Inicia uma transação para garantir a integridade dos dados
$conn->begin_transaction();

try {
    // 1. Excluir os itens associados a este pedido na tabela 'itens_pedido'
    $stmt_itens = $conn->prepare("DELETE FROM itens_pedido WHERE id_pedido = ?");
    $stmt_itens->bind_param("i", $pedido_id);
    
    if (!$stmt_itens->execute()) {
        throw new Exception("Erro ao excluir itens do pedido: " . $stmt_itens->error);
    }
    $stmt_itens->close();

    // 2. Excluir o pedido da tabela 'pedidos'
    $stmt_pedido = $conn->prepare("DELETE FROM pedidos WHERE id = ?");
    $stmt_pedido->bind_param("i", $pedido_id);

    if ($stmt_pedido->execute()) {
        // Se a exclusão do pedido foi bem-sucedida
        if ($stmt_pedido->affected_rows > 0) {
            $conn->commit(); // Confirma as operações
            $_SESSION['mensagem_sucesso'] = "Pedido ID " . $pedido_id . " e seus itens foram excluídos com sucesso!";
        } else {
            // O pedido não foi encontrado (talvez já tenha sido excluído por outra pessoa)
            $conn->rollback(); // Reverte qualquer operação
            $_SESSION['mensagem_erro'] = "Pedido ID " . $pedido_id . " não encontrado ou já excluído.";
        }
    } else {
        throw new Exception("Erro ao excluir o pedido: " . $stmt_pedido->error);
    }
    $stmt_pedido->close();

} catch (Exception $e) {
    $conn->rollback(); // Reverte em caso de qualquer erro
    $_SESSION['mensagem_erro'] = "Erro durante a exclusão do pedido: " . $e->getMessage();
}

$conn->close();

header("Location: pedidos.php");
exit();
?>