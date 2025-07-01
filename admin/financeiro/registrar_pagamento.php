<?php
// Arquivo: /admin/financeiro/registrar_pagamento.php
session_start();
header('Content-Type: application/json');

// Segurança: Garante que apenas usuários logados acessem
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

require_once '../../includes/conexao.php';

$response = ['sucesso' => false, 'mensagem' => 'Dados inválidos recebidos.'];
$data = json_decode(file_get_contents('php://input'), true);

// Validação para garantir que todos os dados necessários foram enviados pelo JavaScript
if (isset(
    $data['id_funcionario'],
    $data['valor_pago'],
    $data['periodo_inicio'],
    $data['periodo_fim'],
    $data['dias_trabalhados'],
    $data['faltas_descontadas']
)) {
    try {
        $stmt = $conn->prepare(
            "INSERT INTO pagamentos_funcionarios 
            (id_funcionario, valor_pago, periodo_inicio, periodo_fim, dias_trabalhados, faltas_descontadas) 
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "idssii",
            $data['id_funcionario'],
            $data['valor_pago'],
            $data['periodo_inicio'],
            $data['periodo_fim'],
            $data['dias_trabalhados'],
            $data['faltas_descontadas']
        );

        if ($stmt->execute()) {
            $response = ['sucesso' => true, 'mensagem' => 'Pagamento registrado com sucesso!'];
        } else {
            $response['mensagem'] = "Erro ao registrar o pagamento no banco de dados: " . $stmt->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $response['mensagem'] = "Erro de conexão: " . $e->getMessage();
    }
}

$conn->close();
echo json_encode($response);
