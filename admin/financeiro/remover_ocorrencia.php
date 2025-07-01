<?php
// Arquivo: /admin/financeiro/remover_ocorrencia.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

require_once '../../includes/conexao.php';

$response = ['sucesso' => false, 'mensagem' => 'Dados inválidos.'];
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id'], $data['tipo'])) {
    $id = filter_var($data['id'], FILTER_VALIDATE_INT);
    $tipo = $data['tipo'];

    if ($id && ($tipo === 'falta' || $tipo === 'vale')) {
        try {
            // Escolhe a tabela e a coluna correta com base no tipo
            $tabela = ($tipo === 'falta') ? 'registros_diarios_funcionarios' : 'vales_funcionarios';

            // Apenas permite apagar vales que AINDA NÃO foram descontados
            $extra_condition = ($tipo === 'vale') ? ' AND id_pagamento_descontado IS NULL' : '';

            $stmt = $conn->prepare("DELETE FROM $tabela WHERE id = ?$extra_condition");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response = ['sucesso' => true, 'mensagem' => 'Registro removido com sucesso!'];
                } else {
                    $response['mensagem'] = 'Não foi possível remover o registro. Ele pode já ter sido processado ou não foi encontrado.';
                }
            } else {
                $response['mensagem'] = "Erro ao remover registro: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $response['mensagem'] = "Erro no banco de dados: " . $e->getMessage();
        }
    }
}

$conn->close();
echo json_encode($response);
