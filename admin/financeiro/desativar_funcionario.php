<?php
// Arquivo: /admin/financeiro/desativar_funcionario.php
session_start();
header('Content-Type: application/json');

// Segurança: Garante que apenas usuários logados acessem
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

require_once '../../includes/conexao.php';

$response = ['sucesso' => false, 'mensagem' => 'ID do funcionário não fornecido.'];

// Verifica se o ID do funcionário foi enviado via POST
if (isset($_POST['id']) && filter_var($_POST['id'], FILTER_VALIDATE_INT)) {
    $funcionario_id = $_POST['id'];

    try {
        // Prepara o comando UPDATE para mudar a coluna 'ativo' para 0
        $stmt = $conn->prepare("UPDATE funcionarios SET ativo = 0 WHERE id = ?");
        $stmt->bind_param("i", $funcionario_id);

        if ($stmt->execute()) {
            // Verifica se alguma linha foi realmente alterada
            if ($stmt->affected_rows > 0) {
                $response = ['sucesso' => true, 'mensagem' => 'Funcionário desativado com sucesso!'];
            } else {
                $response['mensagem'] = 'Nenhum funcionário encontrado com este ID.';
            }
        } else {
            $response['mensagem'] = "Erro ao desativar funcionário: " . $stmt->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $response['mensagem'] = "Erro no banco de dados: " . $e->getMessage();
    }
}

$conn->close();
echo json_encode($response);
