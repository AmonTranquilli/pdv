<?php
// public/api/get_notificacoes.php
session_start();
header('Content-Type: application/json');

// Simples verificação de segurança para garantir que apenas usuários logados acessem
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

require_once '../../includes/conexao.php';

try {
    // 1. Conta quantas notificações NÃO LIDAS existem
    $sql_count = "SELECT COUNT(id) as nao_lidas FROM notificacoes WHERE lida = 0";
    $result_count = $conn->query($sql_count);
    $contagem = $result_count->fetch_assoc();
    $nao_lidas_count = $contagem['nao_lidas'] ?? 0;

    // 2. Busca as 10 notificações mais recentes para exibir no painel
    $sql_notificacoes = "SELECT id, tipo, mensagem, link, data_criacao 
                         FROM notificacoes 
                         ORDER BY data_criacao DESC 
                         LIMIT 10";
    $result_notificacoes = $conn->query($sql_notificacoes);
    $notificacoes = $result_notificacoes->fetch_all(MYSQLI_ASSOC);

    // 3. Monta a resposta final em JSON
    $response = [
        'sucesso' => true,
        'nao_lidas' => (int)$nao_lidas_count,
        'notificacoes' => $notificacoes
    ];

} catch (Exception $e) {
    $response = ['sucesso' => false, 'mensagem' => 'Erro no banco de dados: ' . $e->getMessage()];
}

$conn->close();
echo json_encode($response);
?>