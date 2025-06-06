<?php
header('Content-Type: application/json');
require_once '../../includes/conexao.php';

session_start();
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['erro' => true, 'mensagem' => 'Acesso não autorizado.']);
    exit;
}

$pedidos = [];
$sql = "SELECT 
            p.id, p.nome_cliente, p.total_pedido, p.status, p.data_pedido,
            p.observacoes_pedido,
            GROUP_CONCAT(DISTINCT ip.nome_produto, ' (', ip.quantidade, 'x)' SEPARATOR '; ') as itens_resumo
        FROM pedidos p
        LEFT JOIN itens_pedido ip ON p.id = ip.id_pedido
        WHERE p.arquivado = 0
        GROUP BY p.id
        ORDER BY 
            CASE p.status
                WHEN 'pendente' THEN 1
                WHEN 'preparando' THEN 2
                WHEN 'em_entrega' THEN 3
                WHEN 'finalizado' THEN 4
                WHEN 'cancelado' THEN 5
                ELSE 6
            END, 
            p.data_pedido ASC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pedidos[] = $row;
    }
} else {
    error_log("Erro em obter_pedidos_kanban.php: " . $conn->error);
    $pedidos = ['erro' => true, 'mensagem' => 'Erro ao buscar pedidos.']; 
}

$conn->close();
echo json_encode($pedidos);
?>
