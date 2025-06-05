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
    // 1. Buscar os detalhes principais do pedido
    $sqlPedido = "SELECT 
                    p.id,
                    p.id_cliente,
                    p.nome_cliente,
                    p.telefone_cliente,
                    p.endereco_entrega,
                    p.numero_entrega,
                    p.bairro_entrega,
                    p.complemento_entrega,
                    p.referencia_entrega,
                    p.data_pedido,
                    p.total_pedido,
                    p.forma_pagamento,
                    p.troco_para,
                    p.troco,
                    p.observacoes_pedido,
                    p.status
                  FROM pedidos p
                  WHERE p.id = ?";
    
    $stmtPedido = $conn->prepare($sqlPedido);
    if ($stmtPedido === false) {
        throw new Exception("Erro ao preparar consulta do pedido: " . $conn->error);
    }
    $stmtPedido->bind_param('i', $idPedido);
    $stmtPedido->execute();
    $resultPedido = $stmtPedido->get_result();
    
    if ($pedidoDetails = $resultPedido->fetch_assoc()) {
        $response = $pedidoDetails; // Começa a montar a resposta com os detalhes do pedido
        $response['erro'] = false; // Indica que o pedido foi encontrado
        $response['mensagem'] = 'Detalhes do pedido carregados.';
        $response['itens'] = [];

        // 2. Buscar os itens do pedido
        $sqlItens = "SELECT 
                        ip.id as id_item_pedido, 
                        ip.id_produto, 
                        ip.nome_produto, 
                        ip.quantidade, 
                        ip.preco_unitario, 
                        ip.observacao_item
                     FROM itens_pedido ip
                     WHERE ip.id_pedido = ?";
        
        $stmtItens = $conn->prepare($sqlItens);
        if ($stmtItens === false) {
            throw new Exception("Erro ao preparar consulta dos itens: " . $conn->error);
        }
        $stmtItens->bind_param('i', $idPedido);
        $stmtItens->execute();
        $resultItens = $stmtItens->get_result();
        
        $itensDoPedido = [];
        while ($item = $resultItens->fetch_assoc()) {
            $item['adicionais'] = []; // Prepara array para os adicionais deste item

            // 3. Buscar os adicionais para cada item
            $sqlAdicionais = "SELECT 
                                a.nome as nome_adicional,
                                aip.quantidade as quantidade_adicional, /* Pode ser útil no futuro */
                                aip.preco_unitario as preco_adicional 
                              FROM adicionais_item_pedido aip
                              JOIN adicionais a ON aip.id_adicional = a.id
                              WHERE aip.id_item_pedido = ?";
            
            $stmtAdicionais = $conn->prepare($sqlAdicionais);
            if ($stmtAdicionais === false) {
                throw new Exception("Erro ao preparar consulta dos adicionais: " . $conn->error);
            }
            $stmtAdicionais->bind_param('i', $item['id_item_pedido']);
            $stmtAdicionais->execute();
            $resultAdicionais = $stmtAdicionais->get_result();
            
            while ($adicional = $resultAdicionais->fetch_assoc()) {
                $item['adicionais'][] = $adicional;
            }
            $stmtAdicionais->close();
            $itensDoPedido[] = $item;
        }
        $response['itens'] = $itensDoPedido;
        $stmtItens->close();

    } else {
        // Pedido não encontrado, a resposta padrão já está configurada
    }
    $stmtPedido->close();

} catch (Exception $e) {
    // Se qualquer erro ocorrer, captura a exceção
    $response = ['erro' => true, 'mensagem' => $e->getMessage()];
}

$conn->close();
echo json_encode($response);
?>