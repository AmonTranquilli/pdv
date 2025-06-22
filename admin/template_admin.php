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
    <audio id="somNotificacao" src="/pdv/public/sounds/notificacao.mp3" preload="auto"></audio>
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
                <div class="notification-area">
                    <button id="notificationBell" class="notification-bell" aria-label="Notificações">
                        <i class="fas fa-bell"></i>
                        <span id="notificationCount" class="badge" style="display: none;"></span>
                    </button>
                    <div id="notificationsPanel" class="notifications-panel">
                        <p>Nenhuma notificação nova.</p>
                    </div>
                </div>
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
        const notificationBell = document.getElementById('notificationBell');
        const notificationCount = document.getElementById('notificationCount');
        const notificationsPanel = document.getElementById('notificationsPanel');
        let unreadCount = 0; // Variável para guardar a contagem de não lidas

        // Função para buscar e atualizar as notificações
        async function fetchNotifications() {
            try {
                const response = await fetch('/pdv/public/api/get_notificacoes.php');
                const data = await response.json();

                if (data.sucesso) {
                    // Atualiza a bolha vermelha (badge)
                    if (data.nao_lidas > 0) {
                        notificationCount.textContent = data.nao_lidas > 9 ? '9+' : data.nao_lidas;
                        notificationCount.style.display = 'flex';
                    } else {
                        notificationCount.style.display = 'none';
                    }
                    unreadCount = data.nao_lidas; // Armazena a contagem para uso posterior

                    // Preenche o painel de notificações
                    if (data.notificacoes.length > 0) {
                        notificationsPanel.innerHTML = data.notificacoes.map(n => {
                            const link = n.link ? `href="${n.link}"` : 'href="#" style="cursor:default;"';
                            return `<a ${link} class="notification-item">
                                <p class="notification-message">${n.mensagem}</p>
                                <small class="notification-date">${new Date(n.data_criacao).toLocaleString('pt-BR')}</small>
                            </a>`;
                        }).join('');
                    } else {
                        notificationsPanel.innerHTML = '<div class="notification-item"><p>Nenhuma notificação.</p></div>';
                    }
                }
            } catch (error) {
                console.error('Erro ao buscar notificações:', error);
            }
        }

        // Função para marcar notificações como lidas
        async function markNotificationsAsRead() {
            try {
                await fetch('/pdv/public/api/marcar_como_lidas.php', {
                    method: 'POST'
                });
                notificationCount.style.display = 'none'; // Esconde a bolha imediatamente
                unreadCount = 0;
            } catch (error) {
                console.error('Erro ao marcar notificações como lidas:', error);
            }
        }

        // Evento para mostrar/esconder o painel ao clicar no sino
        notificationBell.addEventListener('click', (e) => {
            e.stopPropagation(); // Impede que o clique se propague para o document
            const isVisible = notificationsPanel.style.display === 'block';
            notificationsPanel.style.display = isVisible ? 'none' : 'block';

            // Se o painel está sendo aberto E existem notificações não lidas
            if (!isVisible && unreadCount > 0) {
                markNotificationsAsRead();
            }
        });

        // Evento para fechar o painel se clicar em qualquer outro lugar da página
        document.addEventListener('click', (e) => {
            if (!notificationsPanel.contains(e.target) && e.target !== notificationBell) {
                notificationsPanel.style.display = 'none';
            }
        });


        // Chama a função pela primeira vez quando a página carrega
        fetchNotifications();
        // E depois a cada 15 segundos para manter atualizado
        setInterval(fetchNotifications, 15000);
    </script>
</body>

</html>