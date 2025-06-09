    <?php
    session_start(); // Inicia a sessão para gerenciar o estado do login
    require_once '../includes/conexao.php'; // Inclui o arquivo de conexão com o banco de dados

    $mensagem_erro = '';

    // Verifica se o formulário foi enviado
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $nome_usuario = $_POST['nome_usuario'];
        $senha = $_POST['senha'];

        // Prepara a consulta SQL para buscar o usuário
        $stmt = $conn->prepare("SELECT id, nome_usuario, senha, nivel_acesso FROM usuarios WHERE nome_usuario = ?");
        $stmt->bind_param("s", $nome_usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows == 1) {
            // Usuário encontrado, verifica a senha
            $usuario = $resultado->fetch_assoc();
            // password_verify é a função correta para verificar senhas hasheadas
            if (password_verify($senha, $usuario['senha'])) {
                // Senha correta, inicia a sessão
                $_SESSION['logado'] = true;
                $_SESSION['id_usuario'] = $usuario['id'];
                $_SESSION['nome_usuario'] = $usuario['nome_usuario'];
                $_SESSION['nivel_acesso'] = $usuario['nivel_acesso'];

                // Redireciona para o dashboard admin
                header("Location: dashboard.php");
                exit();
            } else {
                $mensagem_erro = "Senha incorreta.";
            }
        } else {
            $mensagem_erro = "Nome de usuário não encontrado...";
        }
        $stmt->close();
    }

    $conn->close(); // Fecha a conexão com o banco de dados
    ?>

    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Painel Administrativo</title>
        <link rel="stylesheet" href="../public/css/style.css"> <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }
            .login-container {
                background-color: #fff;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                width: 300px;
                text-align: center;
            }
            .login-container h2 {
                margin-bottom: 20px;
                color: #333;
            }
            .login-container input[type="text"],
            .login-container input[type="password"] {
                width: calc(100% - 20px);
                padding: 10px;
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .login-container button {
                width: 100%;
                padding: 10px;
                background-color: #28a745;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
            }
            .login-container button:hover {
                background-color: #218838;
            }
            .mensagem-erro {
                color: red;
                margin-bottom: 10px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Login Administrativo</h2>
            <?php if (!empty($mensagem_erro)) : ?>
                <p class="mensagem-erro"><?php echo $mensagem_erro; ?></p>
            <?php endif; ?>
            <form action="login.php" method="POST">
                <input type="text" name="nome_usuario" placeholder="Nome de Usuário" required>
                <input type="password" name="senha" placeholder="Senha" required>
                <button type="submit">Entrar</button>
            </form>
        </div>
    </body>
    </html>