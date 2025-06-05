<?php
// public/api/check_client.php

// Define o cabeçalho para indicar que a resposta será JSON
header('Content-Type: application/json');

// Inclui o arquivo de conexão com o banco de dados
// Certifique-se de que o caminho está correto de acordo com a estrutura do seu projeto
require_once '../../includes/conexao.php';

// Verifica se a requisição é do tipo POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pega o corpo da requisição (JSON)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Verifica se o telefone foi enviado
    if (isset($data['telefone'])) {
        $telefone = trim($data['telefone']); // Remove espaços em branco
        $telefone = preg_replace('/\D/', '', $telefone); // Remove caracteres não numéricos

        // Prepara a consulta SQL para buscar o cliente pelo telefone
        // Removida a coluna 'cidade' da seleção para manter consistência com o checkout.php
        $stmt = $conn->prepare("SELECT nome, telefone, endereco, ncasa, bairro, cep, complemento FROM clientes WHERE telefone = ?");
        
        if ($stmt) {
            $stmt->bind_param("s", $telefone);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Cliente encontrado
                $client_data = $result->fetch_assoc();
                echo json_encode([
                    'success' => true,
                    'client_exists' => true,
                    'client_data' => $client_data
                ]);
            } else {
                // Cliente não encontrado
                echo json_encode([
                    'success' => true,
                    'client_exists' => false,
                    'message' => 'Cliente não encontrado.'
                ]);
            }
            $stmt->close();
        } else {
            // Erro na preparação da consulta
            echo json_encode([
                'success' => false,
                'message' => 'Erro na preparação da consulta SQL: ' . $conn->error
            ]);
        }
    } else {
        // Telefone não fornecido na requisição
        echo json_encode([
            'success' => false,
            'message' => 'Número de telefone não fornecido.'
        ]);
    }
} else {
    // Método de requisição não permitido
    echo json_encode([
        'success' => false,
        'message' => 'Método de requisição não permitido. Use POST.'
    ]);
}

// Fecha a conexão com o banco de dados
$conn->close();
?>
