<?php
require_once '../includes/conexao.php'; // Inclui o arquivo de conexão com o banco de dados

$mensagem = '';
$sucesso = false;

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome_usuario = $_POST['nome_usuario'];
    $senha_digitada = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    // Validações básicas
    if (empty($nome_usuario) || empty($senha_digitada) || empty($confirmar_senha)) {
        $mensagem = "Todos os campos são obrigatórios.";
    } elseif ($senha_digitada !== $confirmar_senha) {
        $mensagem = "As senhas não coincidem.";
    } elseif (strlen($senha_digitada) < 5) {
        $mensagem = "A senha deve ter no mínimo 5 caracteres.";
    } else {
        // Verifica se o nome de usuário já existe
        $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE nome_usuario = ?");
        $stmt_check->bind_param("s", $nome_usuario);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $mensagem = "Nome de usuário já existe. Escolha outro.";
        } else {
            // Gera o hash da senha
            $senha_hash = password_hash($senha_digitada, PASSWORD_DEFAULT);

            // Insere o novo usuário no banco de dados
            $stmt_insert = $conn->prepare("INSERT INTO usuarios (nome_usuario, senha, nivel_acesso) VALUES (?, ?, ?)");
            $nivel_acesso = 'admin'; // Por padrão, este script cria um usuário admin
            $stmt_insert->bind_param("sss", $nome_usuario, $senha_hash, $nivel_acesso);

            if ($stmt_insert->execute()) {
                $mensagem = "Usuário '$nome_usuario' criado com sucesso! Agora você pode <a href='admin/login.php'>fazer login</a>.";
                $sucesso = true;
            } else {
                $mensagem = "Erro ao criar usuário: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}

$conn->close(); // Fecha a conexão com o banco de dados
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Usuário Administrador</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 350px;
            text-align: center;
        }
        h2 {
            margin-bottom: 20px;
            color: #333;
        }
        input[type="text"],
        input[type="password"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .mensagem {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
        }
        .mensagem.erro {
            background-color: #ffe6e6;
            color: #cc0000;
            border: 1px solid #cc0000;
        }
        .mensagem.sucesso {
            background-color: #e6ffe6;
            color: #008000;
            border: 1px solid #008000;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Criar Usuário Administrador</h2>
        <?php if (!empty($mensagem)) : ?>
            <p class="mensagem <?php echo $sucesso ? 'sucesso' : 'erro'; ?>"><?php echo $mensagem; ?></p>
        <?php endif; ?>
        <form action="criar_admin.php" method="POST">
            <input type="text" name="nome_usuario" placeholder="Nome de Usuário" required>
            <input type="password" name="senha" placeholder="Senha" required>
            <input type="password" name="confirmar_senha" placeholder="Confirmar Senha" required>
            <button type="submit">Criar Usuário Admin</button>
        </form>
    </div>
</body>
</html>