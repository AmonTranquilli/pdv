<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho para a conexão

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && !empty($_GET['id'])) {
    $pedido_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($pedido_id === false || $pedido_id <= 0) {
        $_SESSION['mensagem_erro'] = "ID de pedido inválido para cancelamento.";
        header("Location: pedidos.php"); // Redireciona de volta para a lista
        exit();
    }

    // Inicia uma transação para garantir a integridade dos dados
    $conn->begin_transaction();

    try {
        // 2. Verifica o status atual do pedido para evitar cancelamentos indevidos
        $stmt_status = $conn->prepare("SELECT status FROM pedidos WHERE id = ? FOR UPDATE"); // FOR UPDATE para lock na linha
        $stmt_status->bind_param("i", $pedido_id);
        $stmt_status->execute();
        $result_status = $stmt_status->get_result();

        if ($result_status->num_rows === 0) {
            throw new Exception("Pedido não encontrado.");
        }
        $current_status = $result_status->fetch_assoc()['status'];
        $stmt_status->close();

        // Evita cancelar pedidos que já estão cancelados ou entregues
        if ($current_status === 'cancelado' || $current_status === 'entregue') {
            throw new Exception("Não é possível cancelar um pedido que já está " . htmlspecialchars($current_status) . ".");
        }

        // 3. Atualiza o status do pedido para 'cancelado'
        $stmt_cancel = $conn->prepare("UPDATE pedidos SET status = 'cancelado' WHERE id = ?");
        $stmt_cancel->bind_param("i", $pedido_id);
        
        if (!$stmt_cancel->execute()) {
            throw new Exception("Erro ao atualizar o status do pedido: " . $stmt_cancel->error);
        }
        $stmt_cancel->close();

        // 4. Devolve os itens ao estoque (se o produto controla estoque)
        // Primeiro, obtenha os itens do pedido
        $stmt_itens = $conn->prepare("SELECT id_produto, quantidade FROM itens_pedido WHERE id_pedido = ?");
        $stmt_itens->bind_param("i", $pedido_id);
        $stmt_itens->execute();
        $result_itens = $stmt_itens->get_result();

        while ($item = $result_itens->fetch_assoc()) {
            $id_produto = $item['id_produto'];
            $quantidade = $item['quantidade'];

            // Atualiza o estoque apenas se o produto controlar estoque
            $stmt_update_estoque = $conn->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ? AND controla_estoque = 1");
            $stmt_update_estoque->bind_param("ii", $quantidade, $id_produto);
            
            if (!$stmt_update_estoque->execute()) {
                throw new Exception("Erro ao devolver item ao estoque para o produto ID " . $id_produto . ": " . $stmt_update_estoque->error);
            }
            $stmt_update_estoque->close();
        }
        $stmt_itens->close();

        // Se tudo ocorreu bem, faz o commit da transação
        $conn->commit();
        $_SESSION['mensagem_sucesso'] = "Pedido #" . $pedido_id . " cancelado com sucesso e estoque devolvido!";

    } catch (Exception $e) {
        // Em caso de erro, faz o rollback da transação
        $conn->rollback();
        $_SESSION['mensagem_erro'] = "Erro ao cancelar pedido: " . $e->getMessage();
    } finally {
        $conn->close(); // Fechar a conexão é importante
    }

    header("Location: detalhes_pedido.php?id=" . $pedido_id);
    exit();

} else {
    $_SESSION['mensagem_erro'] = "Acesso inválido para cancelar pedido.";
    header("Location: pedidos.php");
    exit();
}
?>