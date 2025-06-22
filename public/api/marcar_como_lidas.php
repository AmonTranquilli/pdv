<?php
// public/api/marcar_como_lidas.php (VERSÃO FINAL E ROBUSTA)
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

require_once '../../includes/conexao.php';

$response = ['sucesso' => false, 'mensagem' => 'Nenhuma notificação para atualizar.'];

try {
    // A query para atualizar todas as notificações não lidas para lidas
    $sql = "UPDATE notificacoes SET lida = 1 WHERE lida = 0";
    
    // Executa a query
    $conn->query($sql);
    
    // --- VERIFICAÇÃO ADICIONADA ---
    // A propriedade affected_rows nos diz quantas linhas foram realmente alteradas.
    if ($conn->affected_rows > 0) {
        // Se uma ou mais linhas foram atualizadas, a operação foi um sucesso.
        $response = ['sucesso' => true, 'mensagem' => $conn->affected_rows . ' notificação(ões) marcada(s) como lida(s).'];
    } else {
        // Se nenhuma linha foi alterada (ou por já estarem lidas, ou por um erro)
        // consideramos a operação bem-sucedida no sentido de que não há mais não lidas.
        $response = ['sucesso' => true, 'mensagem' => 'Nenhuma notificação nova para marcar como lida.'];
    }

} catch (Exception $e) {
    $response = ['sucesso' => false, 'mensagem' => 'Erro no servidor: ' . $e->getMessage()];
    error_log("Erro em marcar_como_lidas.php: " . $e->getMessage());
}

$conn->close();
echo json_encode($response);
?>