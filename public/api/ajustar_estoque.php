<?php
// Arquivo: /public/api/ajustar_estoque.php
session_start();
header('Content-Type: application/json');

// Segurança
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}
require_once '../../includes/conexao.php';

$response = ['sucesso' => false, 'mensagem' => 'Dados inválidos.'];
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id_produto'], $data['quantidade'], $data['motivo'])) {
    $id_produto = filter_var($data['id_produto'], FILTER_VALIDATE_INT);
    $quantidade = filter_var($data['quantidade'], FILTER_VALIDATE_INT);
    $motivo = trim($data['motivo']);
    $id_usuario = $_SESSION['usuario_id'] ?? null; // Pega o ID do usuário logado

    if ($id_produto && $quantidade != 0 && !empty($motivo)) {
        try {
            // A coluna id_produto na tabela de movimentações será usada para produtos e adicionais
            $id_para_registrar = $id_produto;
            
            $stmt = $conn->prepare(
                "INSERT INTO movimentacoes_estoque (id_produto, quantidade, tipo_movimento, observacao, id_usuario) VALUES (?, ?, 'AJUSTE_MANUAL', ?, ?)"
            );
            $stmt->bind_param("iisi", $id_para_registrar, $quantidade, $motivo, $id_usuario);
            
            if ($stmt->execute()) {
                $response = ['sucesso' => true, 'mensagem' => 'Ajuste de estoque registrado com sucesso!'];
            } else {
                $response['mensagem'] = "Erro ao registrar ajuste: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $response['mensagem'] = "Erro no banco de dados: " . $e->getMessage();
        }
    } else {
        $response['mensagem'] = 'Todos os campos são obrigatórios e a quantidade não pode ser zero.';
    }
}

$conn->close();
echo json_encode($response);
?>