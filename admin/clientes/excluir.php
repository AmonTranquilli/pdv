<?php
session_start();
require_once '../../includes/conexao.php'; // Caminho para a conexão

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: ../login.php"); // Redireciona para o login
    exit();
}

$mensagem = '';
$sucesso = false;

// 2. Verifica se um ID de cliente foi passado via GET para exclusão
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $cliente_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($cliente_id === false || $cliente_id <= 0) {
        $mensagem = "ID de cliente inválido para exclusão.";
        $sucesso = false;
    } else {
        // Prepara a query de exclusão
        // A query DELETE não precisa saber sobre as colunas de endereço, apenas o ID.
        $stmt_cliente = $conn->prepare("DELETE FROM clientes WHERE id = ?");

        if ($stmt_cliente === false) {
            $mensagem = "Erro na preparação do DELETE de cliente: " . $conn->error;
            $sucesso = false;
        } else {
            $stmt_cliente->bind_param("i", $cliente_id);

            if ($stmt_cliente->execute()) {
                $mensagem = "Cliente excluído com sucesso!";
                $sucesso = true;
            } else {
                $mensagem = "Erro ao excluir cliente: " . $stmt_cliente->error;
                $sucesso = false;
            }
            $stmt_cliente->close();
        }
    }
} else {
    $mensagem = "Nenhum ID de cliente fornecido para exclusão.";
    $sucesso = false;
}

$conn->close(); // Fecha a conexão com o banco de dados

// 3. Redireciona de volta para a página de listagem de clientes
// com uma mensagem (usando SESSION flash message)
$_SESSION['feedback_mensagem'] = $mensagem;
$_SESSION['feedback_sucesso'] = $sucesso;

header("Location: clientes.php");
exit();
?>
