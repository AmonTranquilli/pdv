<?php
session_start(); // Inicia a sessão (opcional para esta API, mas boa prática)
header('Content-Type: application/json'); // Define o cabeçalho para retorno JSON

require_once '../../includes/conexao.php'; // Caminho para a conexão com o banco de dados

$response = ['success' => false, 'message' => '', 'adicionais' => []];

// Verifica se o ID do produto foi fornecido via GET
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $response['message'] = 'ID do produto não fornecido.';
    echo json_encode($response);
    exit();
}

$produtoId = filter_var($_GET['id'], FILTER_VALIDATE_INT);

// Valida se o ID é um inteiro positivo
if ($produtoId === false || $produtoId <= 0) {
    $response['message'] = 'ID do produto inválido.';
    echo json_encode($response);
    exit();
}

try {
    // Consulta para buscar os adicionais associados a um produto específico
    // Faz um JOIN entre 'produto_adicional' e 'adicionais'
    // E filtra por adicionais que estão ativos
    $stmt = $conn->prepare("
        SELECT
            a.id,
            a.nome,
            a.descricao,
            a.preco
        FROM
            adicionais a
        JOIN
            produto_adicional pa ON a.id = pa.id_adicional
        WHERE
            pa.id_produto = ? AND a.ativo = 1
        ORDER BY
            a.nome ASC
    ");

    if ($stmt === false) {
        throw new Exception("Erro na preparação da consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $produtoId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $response['adicionais'][] = $row;
        }
        $response['success'] = true;
        $response['message'] = 'Adicionais encontrados.';
    } else {
        $response['message'] = 'Nenhum adicional encontrado para este produto ou adicionais inativos.';
        $response['success'] = true; // Ainda é um sucesso, apenas não há adicionais
    }

    $stmt->close();

} catch (Exception $e) {
    $response['message'] = "Erro ao buscar adicionais: " . $e->getMessage();
    $response['success'] = false;
} finally {
    $conn->close(); // Garante que a conexão seja fechada
}

echo json_encode($response);
exit();
?>
