<?php
// Arquivo: meus-pedidos.php (Versão Final Completa)
session_start();
require_once 'includes/conexao.php';

// --- ESTADO INICIAL ---
$step = 'ask_phone'; // A etapa inicial é sempre pedir o telefone
$pedidos = [];
$cliente_encontrado = null;
$telefone_pesquisado = '';

// --- LÓGICA DO POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $telefone_limpo = preg_replace('/\D/', '', $_POST['telefone']);
    $telefone_pesquisado = htmlspecialchars($_POST['telefone']);

    // ETAPA 1: Usuário enviou o telefone para verificação
    if ($_POST['action'] === 'check_phone') {
        if (!empty($telefone_limpo)) {
            $stmt = $conn->prepare("SELECT id, nome, telefone FROM clientes WHERE telefone = ?");
            $stmt->bind_param("s", $telefone_limpo);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Cliente encontrado
                $cliente_encontrado = $result->fetch_assoc();
            } else {
                // Nenhum cliente encontrado, cria um array temporário
                $cliente_encontrado = [
                    'nome' => 'Cliente Novo',
                    'telefone' => $telefone_limpo
                ];
            }
            $stmt->close();
            // Em ambos os casos, a próxima etapa é confirmar
            $step = 'confirm_phone'; 
        }
    } 
    // ETAPA 2: Usuário confirmou no modal, agora busca o histórico
    elseif ($_POST['action'] === 'show_history') {
        if (!empty($telefone_limpo)) {
            $stmt = $conn->prepare(
                "SELECT id, data_pedido, total_pedido, status 
                 FROM pedidos 
                 WHERE telefone_cliente = ? 
                 ORDER BY data_pedido DESC"
            );
            $stmt->bind_param("s", $telefone_limpo);
            $stmt->execute();
            $result = $stmt->get_result();
            $pedidos = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
        $step = 'show_history'; // Etapa final: mostrar o histórico
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Pedidos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="public/css/cardapio.css"> <style>
        /* CSS Completo e Autocontido para a página */
        :root {
            --primary-color: #FF5722;
            --secondary-color: #6c757d;
            --background-color: #f0f2f5;
            --container-bg-color: #ffffff;
            --text-color: #333;
            --label-color: #555;
            --border-color: #ccc;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--background-color);
            margin: 0;
            padding: 20px 0 100px 0; /* Espaço para o rodapé */
        }
        .container {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            padding: 0 15px;
        }
        .card {
            background-color: var(--container-bg-color);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        .card-header .back-link { text-decoration: none; color: var(--text-color); font-size: 1.5rem; margin-right: 15px; }
        .card-header h1 { font-size: 1.3rem; font-weight: 600; margin: 0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--label-color); font-size: 0.95em; }
        input[type="tel"] { width: 100%; padding: 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1.1em; box-sizing: border-box; }
        input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(255, 87, 34, 0.2); }
        .btn { display: block; width: 100%; padding: 15px; border: none; border-radius: 8px; font-size: 1.05em; font-weight: bold; cursor: pointer; text-decoration: none; text-align: center; }
        .btn-primary { background-color: var(--primary-color); color: white; margin-top: 10px; }
        .btn-secondary { background-color: var(--secondary-color); color: white; margin-top: 10px; }
        
        /* Estilos para a lista de histórico */
        .results-header { text-align: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .order-history-list { list-style: none; padding: 0; }
        .order-card { border: 1px solid #eee; border-radius: 8px; margin-bottom: 15px; padding: 15px; background-color: #f9f9f9; }
        .order-header { display: flex; justify-content: space-between; align-items: center; font-weight: bold; flex-wrap: wrap; }
        .order-status { padding: 4px 10px; border-radius: 20px; font-size: 0.8em; color: white; text-transform: capitalize; }
        .status-finalizado { background-color: #28a745; } .status-pendente { background-color: #ffc107; color: #333; } .status-preparando { background-color: #17a2b8; } .status-em_entrega { background-color: #007bff; } .status-cancelado { background-color: #dc3545; }
        .order-body { margin-top: 10px; font-size: 0.95em; color: #666; }
        .order-actions { margin-top: 15px; }
        .btn-details { width: 100%; padding: 10px; font-weight: 600; background-color: #fff; border: 1px solid var(--primary-color); color: var(--primary-color); border-radius: 8px; cursor: pointer; }
        
        /* Estilos para os detalhes do item */
        .order-item-details-container { margin-top: 15px; border-top: 1px dashed #ccc; padding-top: 15px; }
        .item-detail-list { list-style: none; padding: 0; }
        .item-detail-list li { padding: 5px 0; border-bottom: 1px dotted #eee; }
        .item-detail-list li:last-child { border-bottom: none; }
        .item-options-list { font-size: 0.9em; color: #555; padding-left: 15px; line-height: 1.4; margin-top: 4px; }
        .item-obs { font-size: 0.9em; color: #777; font-style: italic; margin-top: 4px; }

        /* Estilos do Modal de Confirmação */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-overlay.visible { display: flex; }
        .modal-content { background-color: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2); text-align: center; max-width: 400px; width: 90%; }
        .modal-content h2 { color: var(--primary-color); margin-top: 0; margin-bottom: 25px; }
        .modal-content p { font-size: 1.1em; margin-bottom: 10px; color: #555; }
        .modal-content p strong { color: #333; }
        .modal-actions { display: flex; flex-direction: column; gap: 10px; margin-top: 25px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <?php if ($step !== 'show_history'): ?>
            <header class="card-header">
                <a href="index.php" class="back-link" aria-label="Voltar"><i class="fas fa-arrow-left"></i></a>
                <h1>Identifique-se</h1>
            </header>
            <form method="POST" action="meus-pedidos.php">
                <input type="hidden" name="action" value="check_phone">
                <div class="form-group">
                    <label for="telefone">Seu número de WhatsApp é:</label>
                    <input type="tel" id="telefone" name="telefone" placeholder="(__) _____-____" required>
                </div>
                <button type="submit" class="btn btn-primary">Avançar</button>
            </form>
        
        <?php else: ?>
            <header class="card-header">
                 <a href="meus-pedidos.php" class="back-link" aria-label="Voltar"><i class="fas fa-arrow-left"></i></a>
                <h1>Seu Histórico</h1>
            </header>
            <div class="results-container">
                <p>Exibindo pedidos para o telefone: <strong><?= htmlspecialchars($telefone_pesquisado) ?></strong></p>
                <?php if (!empty($pedidos)): ?>
                    <ul class="order-history-list">
                        <?php foreach ($pedidos as $pedido): ?>
                            <li class="order-card">
                                <div class="order-header">
                                    <span>Pedido #<?= $pedido['id'] ?></span>
                                    <span class="order-status status-<?= str_replace(' ', '_', $pedido['status']) ?>"><?= ucfirst(str_replace('_', ' ', $pedido['status'])) ?></span>
                                </div>
                                <div class="order-body">
                                    <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?></p>
                                    <p><strong>Total:</strong> R$ <?= number_format($pedido['total_pedido'], 2, ',', '.') ?></p>
                                </div>
                                <div class="order-actions">
                                    <button class="btn-details" data-pedido-id="<?= $pedido['id'] ?>">Ver Detalhes</button>
                                </div>
                                <div class="order-item-details-container" id="details-<?= $pedido['id'] ?>" style="display: none;"></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Nenhum pedido encontrado para este número.</p>
                <?php endif; ?>
                 <a href="meus-pedidos.php" class="btn btn-primary" style="background-color: var(--secondary-color);">Buscar Outro Número</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="confirmationModal" class="modal-overlay">
    <div class="modal-content">
        <h2>Confirmar Telefone</h2>
        <p>Encontramos um cadastro para este número:</p>
        <p><strong>Telefone:</strong> <span id="modalTelefone"></span></p>
        <p><strong>Nome:</strong> <span id="modalNome"></span></p>
        <div class="modal-actions">
            <form method="POST" action="meus-pedidos.php" style="width:100%;">
                <input type="hidden" name="action" value="show_history">
                <input type="hidden" name="telefone" id="modalHiddenTelefone">
                <button type="submit" class="btn btn-primary">Confirmar</button>
            </form>
            <button type="button" id="btnEditarTelefone" class="btn btn-secondary">Editar Telefone</button>
        </div>
    </div>
</div>

<nav class="bottom-nav">
    <a href="index.php" class="nav-item"><i class="fas fa-home"></i><span>Início</span></a>
    <a href="meus-pedidos.php" class="nav-item active"><i class="fas fa-clipboard-list"></i><span>Pedidos</span></a>
    <a href="#" class="nav-item"><i class="fas fa-tags"></i><span>Promoções</span></a>
    <a href="carrinho.php" class="nav-item"><i class="fas fa-shopping-cart"></i><span>Carrinho</span></a>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const phoneInput = document.getElementById('telefone');

        // --- NOVO: LÓGICA PARA PREENCHIMENTO AUTOMÁTICO DO TELEFONE ---
        if (phoneInput) {
            // 1. Puxa o telefone salvo no cache do navegador (localStorage)
            const savedPhone = localStorage.getItem('clientPhone');

            // 2. Se um telefone foi encontrado, formata e preenche o campo
            if (savedPhone) {
                let value = savedPhone.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);
                let formattedValue = '';
                if (value.length > 0) { formattedValue = '(' + value.substring(0, 2); }
                if (value.length > 2) { formattedValue += ') ' + value.substring(2, 7); }
                if (value.length > 7) { formattedValue += '-' + value.substring(7, 11); }
                phoneInput.value = formattedValue;
            }
            // --- FIM DO NOVO CÓDIGO ---

            // Adiciona a máscara de telefone
            phoneInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);
                let formattedValue = '';
                if (value.length > 0) formattedValue = '(' + value.substring(0, 2);
                if (value.length > 2) formattedValue += ') ' + value.substring(2, 7);
                if (value.length > 7) formattedValue += '-' + value.substring(7, 11);
                e.target.value = formattedValue;
            });
        }

        // Lógica para exibir o modal de confirmação
        const shouldShowModal = <?= $step === 'confirm_phone' ? 'true' : 'false' ?>;
        if (shouldShowModal) {
            const modal = document.getElementById('confirmationModal');
            const cliente = <?= json_encode($cliente_encontrado) ?>;
            
            let telefoneLimpo = cliente.telefone;
            let telefoneFormatado = '';
            if (telefoneLimpo.length === 11) {
                telefoneFormatado = `(${telefoneLimpo.substring(0, 2)}) ${telefoneLimpo.substring(2, 7)}-${telefoneLimpo.substring(7)}`;
            } else if (telefoneLimpo.length === 10) {
                 telefoneFormatado = `(${telefoneLimpo.substring(0, 2)}) ${telefoneLimpo.substring(2, 6)}-${telefoneLimpo.substring(6)}`;
            } else {
                telefoneFormatado = telefoneLimpo;
            }

            document.getElementById('modalTelefone').textContent = telefoneFormatado;
            document.getElementById('modalNome').textContent = cliente.nome;
            document.getElementById('modalHiddenTelefone').value = telefoneFormatado;

            modal.classList.add('visible');
        }

        // Lógica para os botões do modal
        document.getElementById('btnEditarTelefone')?.addEventListener('click', () => {
            window.location.href = 'meus-pedidos.php';
        });

        // Lógica para "Ver Detalhes"
        document.querySelectorAll('.btn-details').forEach(button => {
            button.addEventListener('click', async (e) => {
                const pedidoId = e.target.getAttribute('data-pedido-id');
                const detailsContainer = document.getElementById(`details-${pedidoId}`);
                
                if (detailsContainer.style.display === 'block') {
                    detailsContainer.style.display = 'none';
                    e.target.textContent = 'Ver Detalhes';
                    return;
                }

                detailsContainer.style.display = 'block';
                detailsContainer.innerHTML = '<p>Carregando...</p>';
                e.target.textContent = 'Ocultar Detalhes';

                try {
                    const response = await fetch(`public/api/obter_detalhes_pedido.php?id=${pedidoId}`);
                    const data = await response.json();

                    if (data.erro) { throw new Error(data.mensagem); }

                    let itemsHtml = '<ul class="item-detail-list">';
                    if (data.itens && data.itens.length > 0) {
                        data.itens.forEach(item => {
                            itemsHtml += `<li><strong>${item.quantidade}x ${item.nome_produto}</strong>`;
                            if (item.detalhes_opcoes) { itemsHtml += `<div class="item-options-list">${nl2br(item.detalhes_opcoes)}</div>`; }
                            if (item.observacao_item) { itemsHtml += `<div class="item-obs">Obs: ${item.observacao_item}</div>`; }
                            itemsHtml += `</li>`;
                        });
                    } else {
                        itemsHtml += '<li>Nenhum item detalhado encontrado.</li>';
                    }
                    itemsHtml += '</ul>';
                    detailsContainer.innerHTML = itemsHtml;
                } catch (error) {
                    detailsContainer.innerHTML = `<p style="color: red;">Erro ao buscar detalhes.</p>`;
                    console.error('Erro no fetch:', error);
                }
            });
        });
        
        // Função JS para replicar o nl2br do PHP
        function nl2br(str) {
            if (typeof str === 'undefined' || str === null) {
                return '';
            }
            return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
        }
    });
</script>

</body>
</html>