<?php
// Arquivo: /admin/financeiro/registrar_falta.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

require_once '../../includes/conexao.php';

$response = ['sucesso' => false, 'mensagem' => 'Dados inválidos.'];
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id_funcionario'], $data['data_falta'], $data['tipo_falta'])) {
    $id_funcionario = filter_var($data['id_funcionario'], FILTER_VALIDATE_INT);
    $data_falta = $data['data_falta'];
    $tipo_falta = $data['tipo_falta'];
    $observacao = $data['observacao'] ?? null;

    // Validação simples
    if ($id_funcionario && !empty($data_falta) && !empty($tipo_falta)) {
        try {
            // Verifica se já não existe uma falta registrada para este funcionário neste dia
            $stmt_check = $conn->prepare("SELECT id FROM registros_diarios_funcionarios WHERE id_funcionario = ? AND data_registro = ?");
            $stmt_check->bind_param("is", $id_funcionario, $data_falta);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $response['mensagem'] = 'Já existe um registro (falta ou presença) para este funcionário nesta data.';
            } else {
                $stmt = $conn->prepare("INSERT INTO registros_diarios_funcionarios (id_funcionario, data_registro, tipo_registro, observacao) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $id_funcionario, $data_falta, $tipo_falta, $observacao);
                
                if ($stmt->execute()) {
                    $response = ['sucesso' => true, 'mensagem' => 'Falta registrada com sucesso!'];
                } else {
                    $response['mensagem'] = "Erro ao registrar falta: " . $stmt->error;
                }
                $stmt->close();
            }
            $stmt_check->close();

        } catch (Exception $e) {
            $response['mensagem'] = "Erro no banco de dados: " . $e->getMessage();
        }
    }
}

$conn->close();
echo json_encode($response);
?>