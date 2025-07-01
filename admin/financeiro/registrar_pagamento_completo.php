<?php
// Arquivo: /admin/financeiro/registrar_pagamento_completo.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logado'])) { echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']); exit; }
require_once '../../includes/conexao.php';

$response = ['sucesso' => false, 'mensagem' => 'Dados inválidos.'];
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id_funcionario'])) {
    $conn->begin_transaction();
    try {
        // 1. Insere o novo registro de pagamento
        $stmt_pagamento = $conn->prepare("INSERT INTO pagamentos_funcionarios (id_funcionario, valor_pago, periodo_inicio, periodo_fim, dias_trabalhados, faltas_descontadas) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_pagamento->bind_param("idssii", $data['id_funcionario'], $data['valor_pago'], $data['periodo_inicio'], $data['periodo_fim'], $data['dias_trabalhados'], $data['faltas_descontadas']);
        $stmt_pagamento->execute();
        $id_novo_pagamento = $conn->insert_id;
        $stmt_pagamento->close();

        // 2. Atualiza os vales que foram descontados, ligando-os a este pagamento
        if (!empty($data['ids_vales_descontados'])) {
            $ids_vales = $data['ids_vales_descontados'];
            $placeholders = implode(',', array_fill(0, count($ids_vales), '?'));
            $types = str_repeat('i', count($ids_vales));

            $stmt_update_vales = $conn->prepare("UPDATE vales_funcionarios SET id_pagamento_descontado = ? WHERE id IN ($placeholders)");
            $params = array_merge([$id_novo_pagamento], $ids_vales);
            $stmt_update_vales->bind_param("i" . $types, ...$params);
            $stmt_update_vales->execute();
            $stmt_update_vales->close();
        }
        
        $conn->commit();
        $response = ['sucesso' => true, 'mensagem' => 'Pagamento registrado com sucesso!'];

    } catch (Exception $e) {
        $conn->rollback();
        $response['mensagem'] = "Erro no banco de dados: " . $e->getMessage();
    }
}
$conn->close();
echo json_encode($response);
?>