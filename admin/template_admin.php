<?php
// Este arquivo é o template do painel administrativo.
// Ele deve ser incluído por outras páginas (ex: dashboard.php, categorias.php).
// As variáveis $page_title, $nome_usuario e $nivel_acesso devem ser definidas
// NA PÁGINA QUE INCLUI ESTE TEMPLATE.

// Valores padrão caso as variáveis não sejam definidas na página de inclusão (segurança)
$page_title = $page_title ?? 'Painel Administrativo';
$nome_usuario = $_SESSION['nome_usuario'] ?? 'Usuário';
$nivel_acesso = $_SESSION['nivel_acesso'] ?? 'N/A';
?>

<!DOCTYPE html>
<html lang="pt-BR">
    
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="/pdv/public/css/admin.css"> 
    <link rel="stylesheet" href="/pdv/public/css/clientes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<body>
    <div class="sidebar" id="sidebar">
        <h2>Painel Admin</h2>
        <ul>
            <li><a href="/pdv/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="/pdv/admin/cardapio/index.php"><i class="fas fa-book-open"></i> <span>Gerenciar Cardápio</span></a></li>
            <li><a href="/pdv/admin/pedidos/pedidos.php"><i class="fas fa-clipboard-list"></i> <span>Gerenciar Pedidos</span></a></li>
            <li><a href="/pdv/admin/categorias/categorias.php"><i class="fas fa-tags"></i> <span>Gerenciar Categorias</span></a></li>
            <li><a href="/pdv/admin/produtos/produtos.php"><i class="fas fa-box-open"></i> <span>Gerenciar Produtos</span></a></li>
            <li><a href="/pdv/admin/adicionais/adicionais.php"><i class="fas fa-plus-circle"></i> <span>Gerenciar Adicionais</span></a></li>
            <li><a href="/pdv/admin/clientes/clientes.php"><i class="fas fa-users"></i> <span>Gerenciar Clientes</span></a></li>
            <li><a href="/pdv/admin/cupons.php"><i class="fas fa-gift"></i> <span>Gerenciar Cupons</span></a></li>
            <li><a href="/pdv/admin/usuarios.php"><i class="fas fa-user-shield"></i> <span>Gerenciar Usuários</span></a></li>
        </ul>
    </div>

    <div class="main-content" id="main-content">
        <header class="header">
            <button class="toggle-btn" id="toggle-sidebar">
                <i class="fas fa-bars"></i> </button>
            <div class="logo-text">Painel Administrativo</div>
            <div class="user-info">
                <span>Olá, <?php echo htmlspecialchars($nome_usuario); ?>!</span>
                <a href="/pdv/admin/logout.php" class="logout-btn">Sair <i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

        <div class="page-content">
            <?php
            echo $page_content ?? '';
            ?>
        </div>
    </div>

    <script>
        const toggleButton = document.getElementById('toggle-sidebar');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');

        toggleButton.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });
        // Função para confirmar a exclusão de pedidos
        function confirmarExclusaoPedido(idPedido) {
            // A função `confirm()` do JavaScript exibe uma caixa de diálogo com "OK" e "Cancelar".
            // Ela retorna `true` se o usuário clicar em "OK" e `false` se clicar em "Cancelar".
            return confirm("Tem certeza que deseja apagar o pedido ID " + idPedido + "?\nEsta ação é irreversível e removerá também todos os itens associados a este pedido.");
        }

        // --- Código do toggleEstoqueField (se estiver no template_admin.php) ---
        // Se você já tem este código aqui, mantenha-o. Caso contrário, ignore.
        const controlaEstoqueCheckbox = document.getElementById('controla_estoque');
        const estoqueGroup = document.getElementById('estoque_group');
        const estoqueInput = document.getElementById('estoque');
        const ativoCheckbox = document.getElementById('ativo');

        function toggleEstoqueField() {
            if (controlaEstoqueCheckbox && estoqueGroup && estoqueInput && ativoCheckbox) { // Verifica se os elementos existem
                if (controlaEstoqueCheckbox.checked) {
                    estoqueGroup.style.display = 'block';
                    estoqueInput.setAttribute('required', 'required');
                    if (parseInt(estoqueInput.value) <= 0) {
                        ativoCheckbox.checked = false;
                        ativoCheckbox.disabled = true;
                    } else {
                        ativoCheckbox.disabled = false;
                    }
                } else {
                    estoqueGroup.style.display = 'none';
                    estoqueInput.removeAttribute('required');
                    estoqueInput.value = '0';
                    ativoCheckbox.disabled = false;
                }
            }
        }

        // Chama a função uma vez ao carregar a página para definir o estado inicial
        if (controlaEstoqueCheckbox) { // Apenas se o checkbox de estoque existir
            toggleEstoqueField();
            controlaEstoqueCheckbox.addEventListener('change', toggleEstoqueField);
            estoqueInput.addEventListener('input', toggleEstoqueField);
        }
        // --- Fim do código do toggleEstoqueField ---

    </script>
</body>
</html>
