<?php
session_start();
require_once '../../includes/conexao.php';

// Proteção da página: verifica se o utilizador está autenticado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Validação do ID do entregador recebido via GET
$entregador_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$entregador_id) {
    $_SESSION['mensagem_erro'] = "ID do entregador inválido.";
    header("Location: index.php");
    exit();
}

// Prepara e executa a query de exclusão
$stmt = $conn->prepare("DELETE FROM entregadores WHERE id = ?");
$stmt->bind_param("i", $entregador_id);

if ($stmt->execute()) {
    // Verifica se alguma linha foi de facto afetada/excluída
    if ($stmt->affected_rows > 0) {
        $_SESSION['mensagem_sucesso'] = "Entregador ID " . $entregador_id . " excluído com sucesso. O histórico de pedidos foi mantido.";
    } else {
        $_SESSION['mensagem_erro'] = "Nenhum entregador encontrado com o ID " . $entregador_id . " ou já havia sido excluído.";
    }
} else {
    // Trata erros de execução da query, como restrições de chave estrangeira
    if ($conn->errno == 1451) { // Código de erro específico para violação de chave estrangeira
        $_SESSION['mensagem_erro'] = "Não é possível excluir o entregador ID " . $entregador_id . " porque ele já está associado a um ou mais pedidos. Considere desativar o entregador em vez de o excluir.";
    } else {
        $_SESSION['mensagem_erro'] = "Erro ao excluir o entregador. (Código: " . $conn->errno . ")";
    }
    // Para depuração, pode ser útil logar o erro completo: error_log($stmt->error);
}

$stmt->close();
$conn->close();

// Redireciona de volta para a página principal de entregadores
header("Location: index.php");
exit();
?>
