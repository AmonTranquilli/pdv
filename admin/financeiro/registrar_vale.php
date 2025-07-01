<?php
// Arquivo: /admin/financeiro/registrar_vale.php
session_start();
header('Content-Type: application/json');

// Segurança: Garante que apenas usuários logados acessem
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

require_once '../../includes/conexao.php';

$response = ['sucesso' => false, 'mensagem' => 'Dados inválidos recebidos.'];
// Pega os dados enviados pelo JavaScript
$data = json_decode(file_get_contents('php://input'), true);

// Validação para garantir que os dados necessários foram enviados
if (isset($data['id_funcionario'], $data['valor'])) {
    $id_funcionario = filter_var($data['id_funcionario'], FILTER_VALIDATE_INT);
    $valor_vale = filter_var($data['valor'], FILTER_VALIDATE_FLOAT);
    $motivo = isset($data['motivo']) ? trim($data['motivo']) : null;

    if ($id_funcionario && $valor_vale > 0) {
        try {
            // Prepara o comando INSERT para a nova tabela
            $stmt = $conn->prepare(
                "INSERT INTO vales_funcionarios (id_funcionario, valor, motivo, data_vale) VALUES (?, ?, ?, NOW())"
            );
            $stmt->bind_param("ids", $id_funcionario, $valor_vale, $motivo);
            
            if ($stmt->execute()) {
                $response = ['sucesso' => true, 'mensagem' => 'Vale registrado com sucesso!'];
            } else {
                $response['mensagem'] = "Erro ao registrar o vale no banco de dados: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $response['mensagem'] = "Erro de conexão: " . $e->getMessage();
        }
    } else {
        $response['mensagem'] = 'O valor do vale deve ser maior que zero.';
    }
}

$conn->close();
echo json_encode($response);
?>