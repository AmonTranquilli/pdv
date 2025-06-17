<?php
// pdv/public/api/obter_detalhes_pedido.php
header('Content-Type: application/json');
require_once '../../includes/conexao.php'; // Garanta que o caminho para conexao.php está correto

$idPedido = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idPedido <= 0) {
    echo json_encode(['erro' => true, 'mensagem' => 'ID do pedido inválido.']);
    exit;
}

$response = ['erro' => true, 'mensagem' => 'Pedido não encontrado.']; // Resposta padrão

try {
    // --- CONSULTA SQL CORRIGIDA COM LEFT JOIN ---
    // Esta consulta agora busca os dados do pedido e os dados mais recentes do cliente.
    $sqlPedido = "SELECT 
                    p.id,
                    p.id_cliente,
                    
                    -- Usa o dado atualizado da tabela 'clientes', se não encontrar, usa o que foi salvo no pedido.
                    COALESCE(c.nome, p.nome_cliente) as nome_cliente,
                    COALESCE(c.telefone, p.telefone_cliente) as telefone_cliente,
                    COALESCE(c.endereco, p.endereco_entrega) as endereco_entrega,
                    COALESCE(c.ncasa, p.numero_entrega) as numero_entrega,
                    COALESCE(c.bairro, p.bairro_entrega) as bairro_entrega,
                    COALESCE(c.complemento, p.complemento_entrega) as complemento_entrega,
                    COALESCE(c.ponto_referencia, p.referencia_entrega) as referencia_entrega,
                    
                    -- Campos originais do pedido
                    p.data_pedido,
                    p.total_pedido,
                    p.forma_pagamento,
                    p.troco_para,
                    p.troco,
                    p.observacoes_pedido,
                    p.status
                  FROM pedidos p
                  LEFT JOIN clientes c ON p.id_cliente = c.id
                  WHERE p.id = ?";
    
    $stmtPedido = $conn->prepare($sqlPedido);
    if ($stmtPedido === false) {
        throw new Exception("Erro ao preparar consulta do pedido: " . $conn->error);
    }
    $stmtPedido->bind_param('i', $idPedido);
    $stmtPedido->execute();
    $resultPedido = $stmtPedido->get_result();
    
    if ($pedidoDetails = $resultPedido->fetch_assoc()) {
        $response = $pedidoDetails;
        $response['erro'] = false;
        $response['mensagem'] = 'Detalhes do pedido carregados.';
        $response['itens'] = [];

        // Buscar os itens do pedido (lógica mantida, pois já está correta)
        $sqlItens = "SELECT 
                        id as id_item_pedido, 
                        id_produto, 
                        nome_produto, 
                        quantidade, 
                        preco_unitario, 
                        observacao_item,
                        detalhes_opcoes
                     FROM itens_pedido
                     WHERE id_pedido = ?";
        
        $stmtItens = $conn->prepare($sqlItens);
        if ($stmtItens === false) {
            throw new Exception("Erro ao preparar consulta dos itens: " . $conn->error);
        }
        $stmtItens->bind_param('i', $idPedido);
        $stmtItens->execute();
        $resultItens = $stmtItens->get_result();
        
        $response['itens'] = $resultItens->fetch_all(MYSQLI_ASSOC);
        $stmtItens->close();

    }
    
    $stmtPedido->close();

} catch (Exception $e) {
    // Se qualquer erro ocorrer, captura a exceção
    $response = ['erro' => true, 'mensagem' => $e->getMessage()];
}

$conn->close();
echo json_encode($response);
?>