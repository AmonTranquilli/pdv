<?php
// public/api/obter_pedidos_kanban.php
header('Content-Type: application/json');

// Inclui seu arquivo de conexão (que usa MySQLi e define $conn)
require_once '../../includes/conexao.php'; // Caminho que você confirmou

$pedidos = [];

// REMOVIDA A CLÁUSULA WHERE PARA INCLUIR TODOS OS STATUS
// Se desejar, pode adicionar um filtro de data aqui para não carregar pedidos muito antigos
// Ex: WHERE DATE(p.data_pedido) = CURDATE() (para pedidos de hoje)
// ou WHERE p.data_pedido >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) (para pedidos dos últimos 7 dias)
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
        -- WHERE p.status NOT IN ('finalizado', 'cancelado')  <-- LINHA REMOVIDA/COMENTADA
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
            p.data_pedido ASC"; // Ordena por status e depois por data

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
    error_log("Erro ao buscar pedidos em obter_pedidos_kanban.php: " . $conn->error);
    $pedidos = ['erro' => true, 'mensagem' => 'Erro ao buscar pedidos. Por favor, tente mais tarde.']; 
}

// Fecha a conexão (boa prática)
$conn->close();

echo json_encode($pedidos);
?>