<?php
// obter_pedidos_kanban.php
header('Content-Type: application/json'); // Diz ao navegador que a resposta será em JSON

// 1. Inclua seu arquivo de conexão
// Certifique-se de que o caminho para 'conexao.php' está correto
// Se 'conexao.php' estiver na mesma pasta, isso já está certo.
require_once '../../includes/conexao.php';

$pedidos = []; // Array para guardar os pedidos que vamos buscar

// 2. SQL para buscar os pedidos
// Vamos buscar pedidos que ainda não foram 'entregue' ou 'cancelado'
// A cláusula GROUP_CONCAT junta os nomes dos produtos de um mesmo pedido em uma string.
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
        GROUP BY p.id -- Agrupa para que cada pedido apareça uma vez
        ORDER BY p.data_pedido ASC"; // Os mais antigos primeiro

try {
    // 3. Executa a consulta
    // '$pdo' deve ser a variável que representa sua conexão PDO no arquivo 'conexao.php'
    // Se sua variável de conexão tiver outro nome, ajuste aqui.
    $stmt = $pdo->query($sql); 

    if ($stmt) {
        // 4. Pega todos os resultados como um array associativo
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Se der erro na consulta, preparamos uma mensagem de erro
    // Em um sistema em produção, você não mostraria $e->getMessage() diretamente ao usuário
    // mas sim logaria o erro em um arquivo no servidor.
    $pedidos = ['erro' => true, 'mensagem' => 'Erro ao buscar pedidos: ' . $e->getMessage()];
}

// 5. Converte o array de pedidos (ou o erro) para JSON e envia para o navegador
echo json_encode($pedidos);
?>