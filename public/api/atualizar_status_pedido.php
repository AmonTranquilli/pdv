<?php // GARANTA QUE NÃO HÁ NADA ANTES DESTA LINHA

// pdv/public/api/atualizar_status_pedido.php
// Defina o header JSON o mais cedo possível
header('Content-Type: application/json');

// Habilitar display de erros para depuração (REMOVA OU COMENTE EM PRODUÇÃO)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Inclui seu arquivo de conexão. Se houver erro aqui, pode quebrar tudo.
// Envolva em um try-catch ou verifique se $conn é criado.
$connection_error = null;
try {
    require_once '../../includes/conexao.php'; // Garanta que o caminho para conexao.php está correto
    if (!isset($conn) || $conn->connect_error) {
        $connection_error = isset($conn) ? $conn->connect_error : "Variável \$conn não definida em conexao.php";
        throw new Exception("Erro de conexão: " . $connection_error);
    }
} catch (Exception $e) {
    error_log("Falha crítica ao incluir conexao.php ou conectar ao BD em atualizar_status_pedido.php: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro crítico de conexão com o banco de dados. Verifique os logs do servidor.']);
    exit;
}


// Pega os dados da requisição (espera-se JSON no corpo da requisição POST)
$raw_input = file_get_contents('php://input');
// error_log("atualizar_status_pedido.php - Input recebido: " . $raw_input); // Descomente para logar o input bruto
$input = json_decode($raw_input, true);

// Verifique se o json_decode funcionou
if (json_last_error() !== JSON_ERROR_NONE && !empty($raw_input)) {
    error_log("atualizar_status_pedido.php - Erro ao decodificar JSON: " . json_last_error_msg() . " - Input: " . $raw_input);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Formato de dados inválido. Esperado JSON.']);
    exit;
}


$idPedido = isset($input['id_pedido']) ? intval($input['id_pedido']) : null;
$novoStatus = isset($input['novo_status']) ? trim($input['novo_status']) : null;

$statusPermitidos = ['pendente', 'preparando', 'em_entrega', 'finalizado', 'cancelado']; 

if (!$idPedido || $idPedido <= 0 || !$novoStatus || !in_array($novoStatus, $statusPermitidos)) {
    error_log("atualizar_status_pedido.php - Dados inválidos: idPedido={$idPedido}, novoStatus={$novoStatus}");
    echo json_encode(['sucesso' => false, 'mensagem' => "Dados inválidos fornecidos. ID Pedido: '{$idPedido}', Novo Status: '{$novoStatus}'. Status permitidos: " . implode(', ', $statusPermitidos)]);
    exit;
}

try {
    $sql = "UPDATE pedidos SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        error_log("Erro ao preparar a consulta em atualizar_status_pedido.php: " . $conn->error);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno ao processar a solicitação (preparação).']);
        exit;
    }

    $stmt->bind_param('si', $novoStatus, $idPedido);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['sucesso' => true, 'mensagem' => 'Status do pedido atualizado com sucesso!']);
        } else {
            $checkSql = "SELECT status FROM pedidos WHERE id = ?";
            $checkStmt = $conn->prepare($checkSql);
            if($checkStmt){
                $checkStmt->bind_param('i', $idPedido);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if ($row['status'] === $novoStatus) {
                        echo json_encode(['sucesso' => true, 'mensagem' => 'O status do pedido já é ' . htmlspecialchars($novoStatus) . '. Nenhuma alteração necessária.']);
                    } else {
                         echo json_encode(['sucesso' => false, 'mensagem' => 'Pedido encontrado, mas o status não pôde ser atualizado (possivelmente para o mesmo valor).']);
                    }
                } else {
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum pedido encontrado com este ID.']);
                }
                $checkStmt->close();
            } else {
                 echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao verificar o pedido existente.']);
            }
        }
    } else {
        error_log("Erro ao executar a atualização do status do pedido: " . $stmt->error);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno ao atualizar o status do pedido (execução).']);
    }

    $stmt->close();
} catch (Exception $e) { 
    error_log("Exceção em atualizar_status_pedido.php: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro excepcional no servidor: ' . $e->getMessage()]);
}

if (isset($conn) && !$conn->connect_error) { // Verifica se $conn existe e não tem erro de conexão
    $conn->close();
}
?>