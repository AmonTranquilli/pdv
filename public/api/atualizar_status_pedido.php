<?php
// pdv/public/api/atualizar_status_pedido.php
header('Content-Type: application/json');

// Inclui seu arquivo de conexão (que usa MySQLi e define $conn)
require_once '../../includes/conexao.php'; // Ajuste se o caminho para conexao.php for diferente

// Pega os dados da requisição (espera-se JSON no corpo da requisição POST)
$input = json_decode(file_get_contents('php://input'), true);

$idPedido = $input['id_pedido'] ?? null;
$novoStatus = $input['novo_status'] ?? null;

// Validação básica dos dados recebidos
$statusPermitidos = ['pendente', 'preparando', 'em_entrega', 'entregue', 'cancelado'];
if (!$idPedido || !is_numeric($idPedido) || !$novoStatus || !in_array($novoStatus, $statusPermitidos)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos fornecidos. Verifique o ID do pedido e o novo status.']);
    exit;
}

try {
    // Prepara a query para evitar SQL Injection
    $sql = "UPDATE pedidos SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        // Erro na preparação da query
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao preparar a consulta: ' . $conn->error]);
        exit;
    }

    // Binda os parâmetros
    $stmt->bind_param('si', $novoStatus, $idPedido); // 's' para string (status), 'i' para integer (id)

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['sucesso' => true, 'mensagem' => 'Status do pedido atualizado com sucesso!']);
        } else {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum pedido encontrado com este ID para atualizar ou o status já é o mesmo.']);
        }
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao executar a atualização do status do pedido: ' . $stmt->error]);
    }

    $stmt->close();
} catch (Exception $e) { 
    // Captura exceções gerais, embora com MySQLi os erros são mais frequentemente verificados nos retornos das funções
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro no servidor: ' . $e->getMessage()]);
}

$conn->close();
?>