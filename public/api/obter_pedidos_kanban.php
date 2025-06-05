<?php
// public/api/obter_pedidos_kanban.php
header('Content-Type: application/json');

// Inclui seu arquivo de conexão (que usa MySQLi e define $conn)
require_once '../../includes/conexao.php'; // Caminho que você confirmou

$pedidos = [];

$sql = "SELECT 
            p.id, 
            p.nome_cliente, 
            p.total_pedido, 
            p.status, 
            p.data_pedido,
            p.observacoes_pedido,
            GROUP_CONCAT(DISTINCT ip.nome_produto, ' (', ip.quantidade, 'x)' SEPARATOR '; ') as itens_resumo
        FROM pedidos p
        LEFT JOIN itens_pedido ip ON p.id = ip.id_pedido
        WHERE p.status NOT IN ('entregue', 'cancelado') 
        GROUP BY p.id
        ORDER BY p.data_pedido ASC";

// Usando MySQLi ($conn)
$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        // Pega todos os resultados como um array associativo
        while ($row = $result->fetch_assoc()) {
            $pedidos[] = $row;
        }
    }
    // Não é necessário um 'else' aqui, se não houver resultados, $pedidos continuará sendo um array vazio.
} else {
    // Se der erro na consulta
    $pedidos = ['erro' => true, 'mensagem' => 'Erro ao buscar pedidos: ' . $conn->error];
}

// Fecha a conexão (boa prática)
$conn->close();

echo json_encode($pedidos);
?>