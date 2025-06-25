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
            <li class="has-submenu">
                <a href="#"><i class="fas fa-chart-line"></i> <span>Financeiro</span> <i class="fas fa-chevron-down arrow"></i></a>
                <ul class="submenu">
                    <li><a href="/pdv/admin/financeiro/dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a></li>
                    <li><a href="/pdv/admin/financeiro/metas.php"><i class="fas fa-bullseye"></i> Metas</a></li>
                    <li><a href="/pdv/admin/financeiro/funcionarios.php"><i class="fas fa-users-cog"></i> Funcionários</a></li>
                    <li><a href="/pdv/admin/financeiro/contas.php"><i class="fas fa-file-invoice-dollar"></i> Contas a Pagar</a></li>
                </ul>
            </li>
            <li><a href="/pdv/admin/pedidos/pedidos.php"><i class="fas fa-history"></i> <span>Histórico de Pedidos</span></a></li>
            <li><a href="/pdv/admin/cardapio/index.php"><i class="fa-solid fa-gear"></i>Configurações da loja</a></li>


            <li class="menu-header"><span>CARDÁPIO</span></li>
            <li class="has-submenu">
                <a href="#"><i class="fas fa-book-open"></i> <span>Gerenciar Itens</span> <i class="fas fa-chevron-down arrow"></i></a>
                <ul class="submenu">
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
        // =============================================================
        // === SCRIPT FINAL E CORRIGIDO PARA TEMPLATE_ADMIN.PHP ===
        // =============================================================

        // --- Variáveis de controle para as Notificações (Escopo Global) ---
        // Colocamos aqui para que as funções abaixo possam acessá-las
        let unreadCount = 0;
        let isPrimeiraCarga = true;
        let idsNotificacoesAnteriores = new Set();

        // --- Funções de Notificação (Escopo Global) ---
        async function fetchNotifications() {
            const notificationCount = document.getElementById('notificationCount');
            const notificationsPanel = document.getElementById('notificationsPanel');
            if (!notificationCount || !notificationsPanel) return;

            try {
                const response = await fetch('/pdv/public/api/get_notificacoes.php');
                const data = await response.json();

                if (data.sucesso) {
                    // --- Lógica de Som (agora simplificada e correta) ---
                    let tocarSomDePedido = false;
                    if (!isPrimeiraCarga) {
                        const idsAtuais = new Set(data.notificacoes.map(n => n.id));
                        // Itera sobre os IDs atuais para ver se algum é novo
                        idsAtuais.forEach(id => {
                            if (!idsNotificacoesAnteriores.has(id)) {
                                // Encontra a notificação completa para checar o tipo
                                const novaNotificacao = data.notificacoes.find(n => n.id === id);
                                // Toca o som apenas se a nova notificação for do tipo 'novo_pedido'
                                if (novaNotificacao && novaNotificacao.tipo === 'novo_pedido') {
                                    tocarSomDePedido = true;
                                }
                            }
                        });
                    }

                    if (tocarSomDePedido) {
                        const som = document.getElementById('somNovoPedido'); // ID correto do áudio
                        if (som) {
                            som.play().catch(e => console.error("Erro ao tocar som de novo pedido:", e));
                        }
                    }

                    // Atualiza a memória de notificações para a próxima verificação
                    idsNotificacoesAnteriores = new Set(data.notificacoes.map(n => n.id));
                    isPrimeiraCarga = false;

                    // --- Lógica Visual ---
                    unreadCount = data.nao_lidas;
                    if (unreadCount > 0) {
                        notificationCount.textContent = unreadCount > 9 ? '9+' : unreadCount;
                        notificationCount.style.display = 'flex';
                    } else {
                        notificationCount.style.display = 'none';
                    }

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

        async function markNotificationsAsRead() {
            try {
                await fetch('/pdv/public/api/marcar_como_lidas.php', {
                    method: 'POST'
                });
                document.getElementById('notificationCount').style.display = 'none';
                unreadCount = 0;
            } catch (error) {
                console.error('Erro ao marcar notificações como lidas:', error);
            }
        }


        // --- LÓGICA QUE EXECUTA QUANDO A PÁGINA ESTÁ PRONTA ---
        document.addEventListener('DOMContentLoaded', function() {

            // --- LÓGICA DO MENU LATERAL ---
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const toggleButton = document.getElementById('toggle-sidebar');
            if (toggleButton) {
                toggleButton.addEventListener('click', () => {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                });
            }
            sidebar.querySelectorAll('.has-submenu > a').forEach(function(menuLink) {
                menuLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.querySelectorAll('.has-submenu.open').forEach(function(openSubmenu) {
                        if (openSubmenu !== menuLink.parentElement) {
                            openSubmenu.classList.remove('open');
                        }
                    });
                    this.parentElement.classList.toggle('open');
                });
            });
            const currentPage = window.location.pathname;
            sidebar.querySelectorAll('.submenu a').forEach(function(itemLink) {
                if (itemLink.getAttribute('href') === currentPage) {
                    itemLink.classList.add('active');
                    let parentSubmenu = itemLink.closest('.has-submenu');
                    if (parentSubmenu) {
                        parentSubmenu.classList.add('open');
                        parentSubmenu.querySelector('a').classList.add('active');
                    }
                }
            });

            // --- LÓGICA DO FORMULÁRIO DE ESTOQUE ---
            const controlaEstoqueCheckbox = document.getElementById('controla_estoque');
            const estoqueGroup = document.getElementById('estoque_group');
            const estoqueInput = document.getElementById('estoque');
            const ativoCheckbox = document.getElementById('ativo');

            function toggleEstoqueField() {
                if (controlaEstoqueCheckbox && estoqueGroup && estoqueInput && ativoCheckbox) {
                    if (controlaEstoqueCheckbox.checked) {
                        estoqueGroup.style.display = 'block';
                        estoqueInput.setAttribute('required', 'required');
                        if (parseInt(estoqueInput.value, 10) <= 0) {
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

            // --- LÓGICA DE CLIQUE PARA AS NOTIFICAÇÕES ---
            const notificationBell = document.getElementById('notificationBell');
            const notificationsPanel = document.getElementById('notificationsPanel');

            if (notificationBell) {
                notificationBell.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isVisible = window.getComputedStyle(notificationsPanel).display === 'block';
                    notificationsPanel.style.display = isVisible ? 'none' : 'block';
                    if (!isVisible && unreadCount > 0) {
                        markNotificationsAsRead();
                    }
                });
            }

            document.addEventListener('click', (e) => {
                if (notificationsPanel && !notificationsPanel.contains(e.target) && !notificationBell.contains(e.target)) {
                    notificationsPanel.style.display = 'none';
                }
            });

            // --- INICIALIZAÇÃO ---
            // Inicia o ciclo de busca de notificações assim que a página carrega
            fetchNotifications();
            setInterval(fetchNotifications, 15000);
        });
    </script>
</body>

</html>