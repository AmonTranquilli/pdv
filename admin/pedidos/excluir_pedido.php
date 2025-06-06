<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho para a conexão

// Verifica se o usuário está logado
// Adapte esta lógica conforme seu sistema de autenticação
if (!isset($_SESSION['nome_usuario'])) { 
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

// Inicia uma transação para garantir a integridade dos dados
$conn->begin_transaction();

try {
    // --- VERIFICAÇÃO DO STATUS DO PEDIDO ANTES DE EXCLUIR ---
    $stmt_status = $conn->prepare("SELECT status FROM pedidos WHERE id = ?");
    $stmt_status->bind_param("i", $pedido_id);
    $stmt_status->execute();
    $result_status = $stmt_status->get_result();

    if ($result_status->num_rows === 0) {
        throw new Exception("Pedido ID " . $pedido_id . " não encontrado.");
    }

    $pedido = $result_status->fetch_assoc();
    $status_atual = $pedido['status'];
    $stmt_status->close();

    // Define os status que permitem a exclusão
    // CORRIGIDO AQUI: 'concluido' alterado para 'finalizado'
    $status_permitidos_para_exclusao = ['cancelado', 'finalizado']; 

    if (!in_array($status_atual, $status_permitidos_para_exclusao)) {
        throw new Exception("O pedido ID " . $pedido_id . " não pode ser excluído. Status atual: '" . htmlspecialchars($status_atual) . "'. Somente pedidos 'cancelado' ou 'finalizado' podem ser apagados.");
    }
    // --- FIM DA VERIFICAÇÃO DE STATUS ---

    // 1. Excluir o pedido da tabela 'pedidos'. 
    // Como a tabela 'itens_pedido' tem "ON DELETE CASCADE", os itens e seus adicionais (que também têm cascade)
    // serão excluídos automaticamente pelo banco de dados. Isso simplifica o código.
    $stmt_pedido = $conn->prepare("DELETE FROM pedidos WHERE id = ?");
    $stmt_pedido->bind_param("i", $pedido_id);

    if ($stmt_pedido->execute()) {
        if ($stmt_pedido->affected_rows > 0) {
            $conn->commit(); // Confirma a operação
            $_SESSION['mensagem_sucesso'] = "Pedido ID " . $pedido_id . " e seus itens foram excluídos com sucesso!";
        } else {
            // O pedido não foi encontrado no momento da exclusão
            throw new Exception("Pedido ID " . $pedido_id . " não encontrado ou já havia sido excluído.");
        }
    } else {
        throw new Exception("Erro ao excluir o pedido: " . $stmt_pedido->error);
    }
    $stmt_pedido->close();

} catch (Exception $e) {
    $conn->rollback(); // Reverte a transação em caso de qualquer erro
    $_SESSION['mensagem_erro'] = "Erro durante a exclusão: " . $e->getMessage();
}

$conn->close();

// Redireciona de volta para a lista de pedidos para mostrar a mensagem
header("Location: pedidos.php");
exit();
?>