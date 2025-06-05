<?php
session_start();
header('Content-Type: application/json');

// Ajuste o caminho para o seu arquivo de conexão com o banco de dados
require_once '../../includes/conexao.php'; // Se estiver na raiz do projeto
// Se estiver em public/api/, seria: require_once '../../includes/conexao.php';

$response = ['success' => false, 'message' => ''];

// Obtém o corpo da requisição JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data === null) {
    $response['message'] = 'Dados inválidos recebidos.';
    echo json_encode($response);
    exit();
}

// Extrai os dados do pedido
$cliente_data = $data['cliente_data'] ?? [];
$carrinho = $data['carrinho'] ?? [];
$total_pedido = $data['total_pedido'] ?? 0;
$forma_pagamento = $data['forma_pagamento'] ?? 'dinheiro';
$troco_para = $data['troco_para'] ?? null;
$troco = $data['troco'] ?? null;
$observacoes_pedido = $data['observacoes_pedido'] ?? '';

// Validação básica
if (empty($cliente_data) || empty($carrinho) || $total_pedido <= 0) {
    $response['message'] = 'Dados essenciais do pedido estão faltando.';
    echo json_encode($response);
    exit();
}

// Inicia uma transação para garantir a atomicidade das operações
$conn->begin_transaction();

try {
    // 1. Inserir o Pedido Principal na tabela 'pedidos'
    $stmt_pedido = $conn->prepare("INSERT INTO pedidos (
        id_cliente, nome_cliente, telefone_cliente, endereco_entrega, numero_entrega,
        bairro_entrega, complemento_entrega, referencia_entrega, data_pedido,
        total_pedido, forma_pagamento, troco_para, troco, observacoes_pedido, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, 'pendente')");

    // id_cliente pode ser NULL se o cliente não for cadastrado, mas o nome e telefone são obrigatórios
    $id_cliente = $cliente_data['id'] ?? null; // Assume que 'id' do cliente pode vir se for cliente existente

    // Ajuste para lidar com id_cliente que pode ser null
    if ($id_cliente === null) {
        $stmt_pedido->bind_param("isssssssdssss",
            $id_cliente, // Passa null, o 'i' vai tratar como NULL
            $cliente_data['nome'],
            $cliente_data['telefone'],
            $cliente_data['endereco'],
            $cliente_data['numero'],
            $cliente_data['bairro'],
            $cliente_data['complemento'],
            $cliente_data['ponto_referencia'],
            $total_pedido,
            $forma_pagamento,
            $troco_para,
            $troco,
            $observacoes_pedido
        );
    } else {
        $stmt_pedido->bind_param("isssssssdssss",
            $id_cliente,
            $cliente_data['nome'],
            $cliente_data['telefone'],
            $cliente_data['endereco'],
            $cliente_data['numero'],
            $cliente_data['bairro'],
            $cliente_data['complemento'],
            $cliente_data['ponto_referencia'],
            $total_pedido,
            $forma_pagamento,
            $troco_para,
            $troco,
            $observacoes_pedido
        );
    }


    if (!$stmt_pedido->execute()) {
        throw new Exception("Erro ao inserir pedido principal: " . $stmt_pedido->error);
    }
    $pedido_id = $conn->insert_id; // Obtém o ID do pedido recém-inserido
    $stmt_pedido->close();

    // 2. Inserir Itens do Pedido na tabela 'itens_pedido' e atualizar estoque
    $stmt_item = $conn->prepare("INSERT INTO itens_pedido (
        id_pedido, id_produto, nome_produto, quantidade, preco_unitario, observacao_item
    ) VALUES (?, ?, ?, ?, ?, ?)");

    $stmt_update_estoque = $conn->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND controla_estoque = 1");

    foreach ($carrinho as $item) {
        // Obter preco_unitario do item (que já inclui adicionais)
        $preco_unitario_item = (float)($item['preco_unitario'] ?? 0); // Use preco_unitario do item
        $nome_produto_item = $item['nome_produto'] ?? $item['nome']; // Nome do produto
        $observacao_item = $item['obs'] ?? '';
        $produto_id = (int)($item['id'] ?? 0); // ID do produto

        $stmt_item->bind_param("iisids",
            $pedido_id,
            $produto_id,
            $nome_produto_item,
            $item['quantidade'],
            $preco_unitario_item,
            $observacao_item
        );

        if (!$stmt_item->execute()) {
            throw new Exception("Erro ao inserir item do pedido: " . $stmt_item->error);
        }
        $item_pedido_id = $conn->insert_id; // Obtém o ID do item de pedido

        // Atualiza o estoque se o produto controla estoque
        if ($produto_id > 0) { // Garante que é um produto válido
            // Verifica o estoque atual antes de tentar diminuir
            $stmt_check_estoque = $conn->prepare("SELECT estoque, controla_estoque FROM produtos WHERE id = ?");
            $stmt_check_estoque->bind_param("i", $produto_id);
            $stmt_check_estoque->execute();
            $result_check_estoque = $stmt_check_estoque->get_result();
            $produto_estoque_info = $result_check_estoque->fetch_assoc();
            $stmt_check_estoque->close();

            if ($produto_estoque_info && $produto_estoque_info['controla_estoque'] == 1) {
                if ($produto_estoque_info['estoque'] < $item['quantidade']) {
                    throw new Exception("Estoque insuficiente para o produto: " . $nome_produto_item);
                }
                $stmt_update_estoque->bind_param("ii", $item['quantidade'], $produto_id);
                if (!$stmt_update_estoque->execute()) {
                    throw new Exception("Erro ao atualizar estoque do produto: " . $nome_produto_item . " - " . $stmt_update_estoque->error);
                }
            }
        }

        // 3. Inserir Adicionais do Item do Pedido na tabela 'adicionais_item_pedido'
        if (!empty($item['adicionais']) && is_array($item['adicionais'])) {
            $stmt_adicional_item = $conn->prepare("INSERT INTO adicionais_item_pedido (
                id_item_pedido, id_adicional, quantidade, preco_unitario
            ) VALUES (?, ?, ?, ?)");

            foreach ($item['adicionais'] as $adicional) {
                $stmt_adicional_item->bind_param("iiid",
                    $item_pedido_id,
                    (int)$adicional['id'],
                    1, // Quantidade do adicional (geralmente 1 por item)
                    (float)$adicional['preco']
                );
                if (!$stmt_adicional_item->execute()) {
                    throw new Exception("Erro ao inserir adicional do item de pedido: " . $stmt_adicional_item->error);
                }
            }
            $stmt_adicional_item->close();
        }
    }
    $stmt_item->close();
    $stmt_update_estoque->close();

    // Se tudo correu bem, comita a transação
    $conn->commit();

    // Limpa o carrinho da sessão após o sucesso
    unset($_SESSION['carrinho']);
    unset($_SESSION['checkout_cliente_data']); // Limpa também os dados do cliente do checkout

    $response['success'] = true;
    $response['message'] = 'Pedido finalizado com sucesso!';
    $response['pedido_id'] = $pedido_id;

} catch (Exception $e) {
    // Em caso de erro, reverte a transação
    $conn->rollback();
    $response['message'] = 'Erro ao finalizar pedido: ' . $e->getMessage();
    error_log("Erro no processamento do pedido: " . $e->getMessage()); // Loga o erro para depuração
} finally {
    $conn->close();
    echo json_encode($response);
}
?>
