<?php
session_start();
require_once '../../includes/conexao.php';

// 1. Proteção da página
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php");
    exit();
}

$adicional_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$adicional_id) {
    $_SESSION['feedback_mensagem'] = "ID de adicional inválido.";
    $_SESSION['feedback_sucesso'] = false;
    header("Location: adicionais.php");
    exit();
}

// Inicia uma transação para garantir que a imagem só seja apagada se o registro for excluído
$conn->begin_transaction();

try {
    // 2. Primeiro, busca o caminho da imagem antes de apagar o registro
    $stmt_select = $conn->prepare("SELECT imagem FROM adicionais WHERE id = ?");
    $stmt_select->bind_param("i", $adicional_id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    $caminho_imagem_atual = null;
    if ($result->num_rows === 1) {
        $adicional = $result->fetch_assoc();
        $caminho_imagem_atual = $adicional['imagem'];
    }
    $stmt_select->close();

    // 3. Deleta o registro do adicional no banco de dados
    $stmt_delete = $conn->prepare("DELETE FROM adicionais WHERE id = ?");
    $stmt_delete->bind_param("i", $adicional_id);
    
    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            // 4. Se o registro foi apagado do banco, tenta apagar o arquivo da imagem
            if (!empty($caminho_imagem_atual)) {
                // Converte o caminho do DB para um caminho físico no servidor
                $caminho_fisico = str_replace('/pdv/', '../../', $caminho_imagem_atual);
                if (file_exists($caminho_fisico)) {
                    @unlink($caminho_fisico); // O @ suprime erros caso a exclusão do arquivo falhe
                }
            }
            $conn->commit(); // Confirma a transação
            $_SESSION['feedback_mensagem'] = "Adicional excluído com sucesso!";
            $_SESSION['feedback_sucesso'] = true;
        } else {
            throw new Exception("Adicional não encontrado ou já foi excluído.");
        }
    } else {
        throw new Exception("Erro ao executar a exclusão: " . $stmt_delete->error);
    }
    $stmt_delete->close();

} catch (Exception $e) {
    $conn->rollback(); // Desfaz a operação em caso de erro
    $_SESSION['feedback_mensagem'] = "Erro ao excluir o adicional: " . $e->getMessage();
    $_SESSION['feedback_sucesso'] = false;
}

$conn->close();

// 5. Redireciona de volta para a lista com a mensagem de feedback
header("Location: adicionais.php");
exit();
?>