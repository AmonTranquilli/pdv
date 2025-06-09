<?php
// Valores padrão
$page_title = $page_title ?? 'Painel Administrativo';
$nome_usuario = $_SESSION['nome_usuario'] ?? 'Usuário';
$nivel_acesso = $_SESSION['nivel_acesso'] ?? 'N/A';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="/pdv/public/css/admin.css"> 
    <link rel="stylesheet" href="/pdv/public/css/clientes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="sidebar" id="sidebar">
        <h2>Painel Admin</h2>
        <ul>
            <li class="menu-header"><span>OPERAÇÕES</span></li>
            <li><a href="/pdv/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="/pdv/admin/gestor_pedidos/index.php"><i class="fas fa-tasks"></i> <span>Gestor de Pedidos</span></a></li>
            <li><a href="/pdv/admin/pedidos/pedidos.php"><i class="fas fa-history"></i> <span>Histórico de Pedidos</span></a></li>

            <li class="menu-header"><span>CARDÁPIO</span></li>
            <li class="has-submenu">
                <a href="#"><i class="fas fa-book-open"></i> <span>Gerenciar Itens</span> <i class="fas fa-chevron-down arrow"></i></a>
                <ul class="submenu">
                    <li><a href="/pdv/admin/cardapio/index.php">Organizar Cardápio</a></li>
                    <li><a href="/pdv/admin/produtos/produtos.php">Produtos</a></li>
                    <li><a href="/pdv/admin/categorias/categorias.php">Categorias</a></li>
                    <li><a href="/pdv/admin/adicionais/adicionais.php">Adicionais</a></li>
                </ul>
            </li>

            <li class="menu-header"><span>PESSOAS</span></li>
            <li class="has-submenu">
                <a href="#"><i class="fas fa-users"></i> <span>Usuários</span> <i class="fas fa-chevron-down arrow"></i></a>
                <ul class="submenu">
                    <li><a href="/pdv/admin/clientes/clientes.php">Clientes</a></li>
                    <li><a href="/pdv/admin/entregadores/index.php">Entregadores</a></li>
                    <li><a href="/pdv/admin/usuarios.php">Usuários do Painel</a></li>
                </ul>
            </li>

            <li class="menu-header"><span>MARKETING</span></li>
            <li><a href="/pdv/admin/cupons.php"><i class="fas fa-gift"></i> <span>Cupons de Desconto</span></a></li>
        </ul>
    </div>

    <div class="main-content" id="main-content">
        <header class="header">
            <button class="toggle-btn" id="toggle-sidebar"><i class="fas fa-bars"></i></button>
            <div class="logo-text">Painel Administrativo</div>
            <div class="user-info">
                <span>Olá, <?= htmlspecialchars($nome_usuario); ?>!</span>
                <a href="/pdv/admin/logout.php" class="logout-btn">Sair <i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

        <div class="page-content">
            <?= $page_content ?? ''; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleButton = document.getElementById('toggle-sidebar');

        // Lógica do botão de recolher/expandir
        if (toggleButton) {
            toggleButton.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });
        }
        
        // --- JAVASCRIPT CORRIGIDO E FINAL PARA O MENU ---

        // Lógica para abrir/fechar os submenus
        sidebar.querySelectorAll('.has-submenu > a').forEach(function(menuLink) {
            menuLink.addEventListener('click', function(e) {
                e.preventDefault();
                // Fecha outros submenus abertos para ter um efeito "sanfona"
                sidebar.querySelectorAll('.has-submenu.open').forEach(function(openSubmenu) {
                    if (openSubmenu !== menuLink.parentElement) {
                        openSubmenu.classList.remove('open');
                    }
                });
                // Abre ou fecha o submenu atual
                this.parentElement.classList.toggle('open');
            });
        });

        // Lógica para manter o menu da página atual aberto e ativo
        const currentPage = window.location.pathname;
        sidebar.querySelectorAll('.submenu a').forEach(function(itemLink) {
            if (itemLink.getAttribute('href') === currentPage) {
                itemLink.classList.add('active'); // Destaca o item atual
                let parentSubmenu = itemLink.closest('.has-submenu');
                if (parentSubmenu) {
                    parentSubmenu.classList.add('open'); // Abre o submenu pai
                    // Opcional: Adiciona 'active' ao link principal também
                    parentSubmenu.querySelector('a').classList.add('active');
                }
            }
        });

        // --- LÓGICA ANTIGA DO ESTOQUE (MANTIDA) ---
        const controlaEstoqueCheckbox = document.getElementById('controla_estoque');
        const estoqueGroup = document.getElementById('estoque_group');
        const estoqueInput = document.getElementById('estoque');
        const ativoCheckbox = document.getElementById('ativo');

        function toggleEstoqueField() {
            if (controlaEstoqueCheckbox && estoqueGroup && estoqueInput && ativoCheckbox) {
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
        if (controlaEstoqueCheckbox) {
            toggleEstoqueField();
            controlaEstoqueCheckbox.addEventListener('change', toggleEstoqueField);
            estoqueInput.addEventListener('input', toggleEstoqueField);
        }

    });
    </script>
</body>
</html>