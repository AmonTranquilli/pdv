<?php
// Versão Final com Controle de Quantidade de Adicionais
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'includes/conexao.php';

// --- ETAPA 1: BUSCAR E VALIDAR O PRODUTO PRINCIPAL ---
$produto_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$produto_id) {
    header("Location: index.php");
    exit();
}

try {
    $stmt_produto = $conn->prepare("SELECT * FROM produtos WHERE id = ? AND ativo = 1");
    if ($stmt_produto === false) { throw new Exception("Erro ao preparar consulta do produto: " . $conn->error); }
    $stmt_produto->bind_param("i", $produto_id);
    $stmt_produto->execute();
    $result_produto = $stmt_produto->get_result();
    if ($result_produto->num_rows === 0) { header("Location: index.php"); exit(); }
    $produto = $result_produto->fetch_assoc();
    $stmt_produto->close();

    // --- ETAPA 2: BUSCAR AS OPÇÕES DE CUSTOMIZAÇÃO ---
    $adicionais = [];
    $stmt_adicionais = $conn->prepare("SELECT a.id, a.nome, a.preco, a.imagem FROM adicionais a JOIN produto_adicional pa ON a.id = pa.id_adicional WHERE pa.id_produto = ? AND a.ativo = 1 ORDER BY a.nome ASC");
    if ($stmt_adicionais) {
        $stmt_adicionais->bind_param("i", $produto_id);
        $stmt_adicionais->execute();
        $result_adicionais = $stmt_adicionais->get_result();
        while ($row = $result_adicionais->fetch_assoc()) { $adicionais[] = $row; }
        $stmt_adicionais->close();
    }

    $grupos_opcoes = [];
    $stmt_grupos = $conn->prepare("SELECT * FROM grupos_opcoes WHERE id_produto_pai = ? ORDER BY nome_grupo ASC");
    if ($stmt_grupos) {
        $stmt_grupos->bind_param("i", $produto_id);
        $stmt_grupos->execute();
        $result_grupos = $stmt_grupos->get_result();
        while($grupo = $result_grupos->fetch_assoc()){
            $id_grupo = $grupo['id'];
            $grupo['itens'] = [];
            $stmt_itens = $conn->prepare("SELECT ig.*, p.nome as nome_produto_vinculado FROM itens_grupo ig LEFT JOIN produtos p ON ig.id_produto_vinculado = p.id WHERE ig.id_grupo_opcao = ? AND ig.ativo = 1 ORDER BY ig.nome_item ASC");
            if ($stmt_itens) {
                $stmt_itens->bind_param("i", $id_grupo);
                $stmt_itens->execute();
                $result_itens = $stmt_itens->get_result();
                while($item = $result_itens->fetch_assoc()){
                    if (isset($item['tipo']) && $item['tipo'] === 'COMBO') {
                        $item['componentes'] = [];
                        $stmt_componentes = $conn->prepare("SELECT p.nome, igc.quantidade FROM itens_grupo_combo igc JOIN produtos p ON p.id = igc.id_produto_componente WHERE igc.id_item_grupo = ?");
                        if($stmt_componentes){
                           $stmt_componentes->bind_param("i", $item['id']);
                           $stmt_componentes->execute();
                           $result_componentes = $stmt_componentes->get_result();
                           while($componente = $result_componentes->fetch_assoc()){ $item['componentes'][] = $componente; }
                           $stmt_componentes->close();
                        }
                    }
                    $grupo['itens'][] = $item;
                }
                $stmt_itens->close();
            }
            $grupos_opcoes[] = $grupo;
        }
        $stmt_grupos->close();
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    die("Ocorreu um erro ao carregar os dados do produto. Por favor, tente novamente mais tarde.");
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($produto['nome']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
 <style>
    :root {
        --primary-color: #FF5722;
        --text-dark: #2f2f2f;
        --text-light: #6c757d;
        --background-page: #f8f9fa;
        --background-card: #ffffff;
        --border-color: #e9ecef;
        --border-color-light: #f1f3f5;
        --success-color: #28a745;
    }
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        margin: 0;
        background-color: var(--background-page);
        color: var(--text-dark);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    .product-page-container {
        max-width: 800px;
        margin: 0 auto;
        background-color: var(--background-card);
        min-height: 100vh;
        padding-bottom: 120px; /* Espaço para o footer fixo */
    }
    .product-header {
        display: flex;
        align-items: center;
        padding: 15px;
        background-color: #fff;
        position: sticky;
        top: 0;
        z-index: 10;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .back-button {
        font-size: 1.5rem;
        color: var(--text-dark);
        text-decoration: none;
        margin-right: 15px;
    }
    .header-title {
        font-size: 1.1rem;
        font-weight: 600;
    }
    .product-hero-section {
        display: flex;
        align-items: flex-start;
        gap: 20px;
        padding: 20px;
        background-color: #fff;
    }
    .hero-image {
        flex: 0 0 110px;
        width: 110px;
        height: 110px;
    }
    .hero-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 8px;
        display: block;
    }
    .hero-info {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .hero-info h1 {
        margin: 0 0 8px 0;
        font-size: 1.8rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .hero-info .description {
        font-size: 1rem;
        color: var(--text-light);
        line-height: 1.5;
        margin: 0 0 12px 0;
    }
    .hero-info .base-price {
        font-size: 1.6rem;
        font-weight: 600;
        color: var(--text-dark);
    }
    .options-container {
        padding: 0;
    }
    .option-group {
        border-top: 10px solid var(--background-page);
        background-color: #fff;
    }
    .option-group-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        cursor: pointer;
    }
    .option-group-header-title h2 {
        font-size: 1.2rem;
        font-weight: 600;
        margin: 0;
    }
    .option-group-header-title .group-rules {
        font-size: 0.9rem;
        color: var(--text-light);
        margin-top: 4px;
    }
    .option-group-header .chevron-icon {
        font-size: 1rem;
        transition: transform 0.3s ease;
    }
    .option-group-content {
        padding: 0 20px;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s ease-out;
    }
    .option-group.open .option-group-content {
        max-height: 2000px; /* Valor alto para garantir que todo conteúdo caiba */
        padding-bottom: 10px;
    }
    .option-group.open .chevron-icon {
        transform: rotate(180deg);
    }
    .option-item-label, .option-item-adicional {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 0;
        border-top: 1px solid var(--border-color-light);
    }
    .option-item-label { cursor: pointer; }
    
    .option-details-with-image {
        display: flex;
        align-items: center;
        flex-grow: 1;
    }
    .additional-image {
        width: 50px;
        height: 50px;
        border-radius: 6px;
        margin-right: 15px;
        object-fit: cover;
    }
    .option-details {
        display: flex;
        flex-direction: column;
    }
    .option-details .option-name {
        font-size: 1rem;
        font-weight: 500;
    }
    .option-details .option-price {
        font-size: 0.9rem;
        color: var(--primary-color);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .option-input input[type="radio"], .option-input input[type="checkbox"] {
        display: none;
    }
    .custom-radio, .custom-checkbox {
        width: 22px;
        height: 22px;
        border: 2px solid #ccc;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        margin-left: 20px;
    }
    .custom-checkbox, .btn-add-adicional {
        border-radius: 6px;
    }
    input:checked + .custom-radio { border-color: var(--primary-color); background-color: var(--primary-color); }
    input:checked + .custom-radio::after { content: ''; width: 10px; height: 10px; background-color: white; border-radius: 50%; }
    input:checked + .custom-checkbox { border-color: var(--primary-color); background-color: var(--primary-color); }
    input:checked + .custom-checkbox::after { content: '✔'; font-size: 14px; color: #fff; }
    
    textarea.observacoes { width: 100%; box-sizing: border-box; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; font-size: 1rem; resize: vertical; margin-top: 10px; }
    
    .action-footer { position: fixed; bottom: 0; left: 0; right: 0; background-color: #fff; padding: 15px 20px; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between; max-width: 800px; margin: 0 auto; box-sizing: border-box; }
    .quantity-control, .quantity-control-adicional { display: flex; align-items: center; }
    .quantity-control button, .quantity-control-adicional button { background-color: #f0f0f0; border: 1px solid #ddd; width: 38px; height: 38px; font-size: 1.5rem; border-radius: 50%; cursor: pointer; color: var(--primary-color); display: flex; align-items: center; justify-content: center; }
    .quantity-control span, .quantity-control-adicional span { font-size: 1.1rem; font-weight: 700; padding: 0 15px; min-width: 15px; text-align: center; }
    .btn-add-adicional { width: 38px; height: 38px; background-color: #fff; border: 2px solid var(--primary-color); color: var(--primary-color); border-radius: 8px; font-size: 1.5rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1; }
    .add-to-cart-button { background-color: var(--primary-color); color: white; border: none; padding: 15px 25px; border-radius: 30px; font-size: 1rem; font-weight: 600; cursor: pointer; }
</style>
</head>
<body>
    <div class="product-page-container">
        <header class="product-header">
            <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i></a>
            <span class="header-title">Detalhes do produto</span>
        </header>

        <div class="product-hero-section">
            <div class="hero-image"><img src="<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>"></div>
            <div class="hero-info">
                <h1><?= htmlspecialchars($produto['nome']) ?></h1>
                <p class="description"><?= htmlspecialchars($produto['descricao']) ?></p>
                <span class="base-price" id="base-price" data-price="<?= $produto['preco'] ?>">R$ <?= number_format($produto['preco'], 2, ',', '.') ?></span>
            </div>
        </div>

        <div class="options-container">
            <?php if(!empty($grupos_opcoes)): ?>
                <?php foreach($grupos_opcoes as $grupo): ?>
                    <div class="option-group open" data-max-opcoes="<?= htmlspecialchars($grupo['max_opcoes']) ?>">
                        <div class="option-group-header">
                            <div class="option-group-header-title"><h2><?= htmlspecialchars($grupo['nome_grupo']) ?></h2><span class="group-rules"><?= $grupo['tipo_selecao'] == 'UNICO' ? 'Escolha 1' : 'Escolha até ' . $grupo['max_opcoes'] ?></span></div>
                            <i class="fas fa-chevron-down chevron-icon"></i>
                        </div>
                        <div class="option-group-content">
                            <?php foreach($grupo['itens'] as $item): ?>
                                <label class="option-item-label" for="item_<?= $item['id'] ?>">
                                    <div class="option-details"><span class="option-name"><?= htmlspecialchars($item['nome_item']) ?></span><span class="option-price">+ R$ <?= number_format($item['preco_adicional'], 2, ',', '.') ?></span></div>
                                    <div class="option-input">
                                        <?php if($grupo['tipo_selecao'] == 'UNICO'): ?><input type="radio" id="item_<?= $item['id'] ?>" name="grupo_<?= $grupo['id'] ?>" value="<?= $item['id'] ?>" data-price="<?= $item['preco_adicional'] ?>"><div class="custom-radio"></div><?php else: ?><input type="checkbox" id="item_<?= $item['id'] ?>" name="item_<?= $item['id'] ?>" value="<?= $item['id'] ?>" data-price="<?= $item['preco_adicional'] ?>"><div class="custom-checkbox"></div><?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if(!empty($adicionais)): ?>
                <div class="option-group open" data-max-adicionais="<?= $produto['max_adicionais_opcionais'] ?>">
                    <div class="option-group-header">
                        <div class="option-group-header-title"><h2>Adicionais</h2><span class="group-rules">Selecione até 10</span></div>
                        <i class="fas fa-chevron-down chevron-icon"></i>
                    </div>
                    <div class="option-group-content">
                        <?php foreach($adicionais as $adicional): ?>
                            <div class="option-item-adicional" data-id-adicional="<?= $adicional['id'] ?>" data-price="<?= $adicional['preco'] ?>">
                                <div class="option-details-with-image">
                                    <?php if (!empty($adicional['imagem'])): ?><img src="<?= htmlspecialchars($adicional['imagem']) ?>" alt="<?= htmlspecialchars($adicional['nome']) ?>" class="additional-image"><?php endif; ?>
                                    <div class="option-details"><span class="option-name"><?= htmlspecialchars($adicional['nome']) ?></span><span class="option-price">+ R$ <?= number_format($adicional['preco'], 2, ',', '.') ?></span></div>
                                </div>
                                <div class="option-input">
                                    <button type="button" class="btn-add-adicional">+</button>
                                    <div class="quantity-control-adicional" style="display: none;">
                                        <button type="button" class="btn-adicional-decrease">-</button>
                                        <span class="qtd-adicional">1</span>
                                        <button type="button" class="btn-adicional-increase">+</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="option-group open">
                <div class="option-group-header">
                    <div class="option-group-header-title"><h2>Observações</h2></div>
                    <i class="fas fa-chevron-down chevron-icon"></i>
                </div>
                <div class="option-group-content">
                    <textarea class="observacoes" placeholder="Ex: Tirar a cebola, bem passado, etc."></textarea>
                </div>
            </div>
        </div>
    </div>

    <footer class="action-footer">
        <div class="quantity-control">
            <button id="btn-diminuir">-</button>
            <span id="qtd-produto">1</span>
            <button id="btn-aumentar">+</button>
        </div>
        <button class="add-to-cart-button">Adicionar <span id="preco-total-footer">R$ <?= number_format($produto['preco'], 2, ',', '.') ?></span></button>
    </footer>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const produtoId = <?= json_encode($produto['id']) ?>;
    const precoBase = parseFloat(<?= json_encode($produto['preco']) ?>);
    const adicionaisContainer = document.querySelector('[data-max-adicionais]');
    const maxAdicionais = adicionaisContainer ? parseInt(adicionaisContainer.dataset.maxAdicionais, 10) : 99;
    
    const quantidadeElemento = document.getElementById('qtd-produto');
    const precoTotalFooterElemento = document.getElementById('preco-total-footer');

    function calcularPrecoTotal() {
        let precoOpcoes = 0;
        document.querySelectorAll('.option-group input:checked').forEach(input => {
            precoOpcoes += parseFloat(input.dataset.price || 0);
        });
        document.querySelectorAll('.quantity-control-adicional').forEach(control => {
            if (control.style.display !== 'none') {
                const qtd = parseInt(control.querySelector('.qtd-adicional').textContent);
                const price = parseFloat(control.closest('.option-item-adicional').dataset.price);
                precoOpcoes += qtd * price;
            }
        });
        const quantidadeProduto = parseInt(quantidadeElemento.textContent);
        const precoTotalCalculado = (precoBase + precoOpcoes) * quantidadeProduto;
        precoTotalFooterElemento.textContent = `R$ ${precoTotalCalculado.toFixed(2).replace('.', ',')}`;
    }

    function rolarParaProximoGrupo(elementoAtual) {
        const currentGroup = elementoAtual.closest('.option-group');
        if (!currentGroup) return;
        const nextGroup = currentGroup.nextElementSibling;
        if (nextGroup && nextGroup.classList.contains('option-group')) {
            setTimeout(() => {
                nextGroup.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (!nextGroup.classList.contains('open')) {
                    nextGroup.querySelector('.option-group-header').click();
                }
            }, 300);
        }
    }
    
    document.querySelectorAll('.option-group-header').forEach(header => {
        header.addEventListener('click', () => header.parentElement.classList.toggle('open'));
    });
    
    document.querySelectorAll('.option-group input').forEach(input => {
        input.addEventListener('change', calcularPrecoTotal);
    });

    document.querySelectorAll('label.option-item-label').forEach(label => {
        const radio = label.querySelector('input[type="radio"]');
        if (radio) {
            label.addEventListener('click', function(e) {
                e.preventDefault();
                const estavaMarcado = radio.checked;
                if (estavaMarcado) {
                    radio.checked = false;
                } else {
                    document.querySelectorAll(`input[type="radio"][name="${radio.name}"]`).forEach(outroRadio => {
                        outroRadio.checked = false;
                    });
                    radio.checked = true;
                    rolarParaProximoGrupo(radio);
                }
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
    });

    document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('click', function() {
            if (this.checked) {
                const grupo = this.closest('.option-group');
                if (!grupo) return;
                const header = grupo.querySelector('.option-group-header');
                if(!header) return;
                const maxOpcoes = parseInt(header.dataset.maxOpcoes, 10);
                if (maxOpcoes > 0) {
                    const inputsNoGrupo = grupo.querySelectorAll('input[type="checkbox"]:checked');
                    if (inputsNoGrupo.length >= maxOpcoes) {
                        rolarParaProximoGrupo(this);
                    }
                }
            }
        });
    });

    if (adicionaisContainer) {
        adicionaisContainer.addEventListener('click', function(e) {
            const itemAdicional = e.target.closest('.option-item-adicional');
            if (!itemAdicional) return;
            
            const addBtn = itemAdicional.querySelector('.btn-add-adicional');
            const qtyControl = itemAdicional.querySelector('.quantity-control-adicional');
            const qtySpan = qtyControl.querySelector('.qtd-adicional');

            const getTotalAdicionais = () => {
                let total = 0;
                adicionaisContainer.querySelectorAll('.quantity-control-adicional').forEach(control => {
                    if (control.style.display !== 'none') {
                        total += parseInt(control.querySelector('.qtd-adicional').textContent);
                    }
                });
                return total;
            };

            if (e.target.classList.contains('btn-add-adicional')) {
                if (getTotalAdicionais() >= maxAdicionais) {
                    alert(`Você pode selecionar no máximo ${maxAdicionais} adicionais.`);
                    return;
                }
                addBtn.style.display = 'none';
                qtyControl.style.display = 'flex';
                qtySpan.textContent = '1';
            }
            else if (e.target.classList.contains('btn-adicional-increase')) {
                if (getTotalAdicionais() >= maxAdicionais) {
                    alert(`Você pode selecionar no máximo ${maxAdicionais} adicionais.`);
                    return;
                }
                qtySpan.textContent = parseInt(qtySpan.textContent) + 1;
            }
            else if (e.target.classList.contains('btn-adicional-decrease')) {
                let qty = parseInt(qtySpan.textContent);
                if (qty > 1) {
                    qtySpan.textContent = qty - 1;
                } else {
                    addBtn.style.display = 'flex';
                    qtyControl.style.display = 'none';
                    qtySpan.textContent = '0';
                }
            }
            calcularPrecoTotal();
        });
    }
    
    document.getElementById('btn-aumentar').addEventListener('click', () => {
        quantidadeElemento.textContent = parseInt(quantidadeElemento.textContent) + 1;
        calcularPrecoTotal();
    });
    document.getElementById('btn-diminuir').addEventListener('click', () => {
        let quantidade = parseInt(quantidadeElemento.textContent);
        if (quantidade > 1) {
            quantidadeElemento.textContent = quantidade - 1;
            calcularPrecoTotal();
        }
    });

    document.querySelector('.add-to-cart-button').addEventListener('click', () => {
        const opcoesSelecionadas = [];
        
        document.querySelectorAll('.option-group input:checked').forEach(input => {
            const label = input.closest('.option-item-label');
            opcoesSelecionadas.push({
                id: input.value,
                nome: label.querySelector('.option-name').textContent,
                preco: parseFloat(input.dataset.price)
            });
        });

        document.querySelectorAll('.quantity-control-adicional').forEach(control => {
            if (control.style.display !== 'none') {
                const item = control.closest('.option-item-adicional');
                const qtd = parseInt(control.querySelector('.qtd-adicional').textContent);
                const nomeAdicional = item.querySelector('.option-name').textContent;
                const precoAdicional = parseFloat(item.dataset.price);
                
                opcoesSelecionadas.push({
                    id_adicional: item.dataset.idAdicional,
                    nome: `${qtd}x ${nomeAdicional}`,
                    preco_total: precoAdicional * qtd,
                    quantidade: qtd,
                    preco_unitario: precoAdicional
                });
            }
        });

        const precoFinalOpcoes = opcoesSelecionadas.reduce((acc, opt) => acc + (opt.preco_total || opt.preco), 0);
        const precoUnitarioFinal = precoBase + precoFinalOpcoes;

        const itemParaCarrinho = {
            id: produtoId,
            item_carrinho_id: `prod_${produtoId}_${new Date().getTime()}`,
            nome: "<?= htmlspecialchars($produto['nome']) ?>",
            preco_unitario: precoUnitarioFinal,
            preco_base: precoBase,
            quantidade: parseInt(quantidadeElemento.textContent),
            observacao: document.querySelector('.observacoes').value,
            opcoes: opcoesSelecionadas,
            imagem: "<?= htmlspecialchars($produto['imagem']) ?>"
        };
        adicionarItemAoCarrinhoAPI(itemParaCarrinho);
    });
    
    async function adicionarItemAoCarrinhoAPI(item) {
        try {
            const response = await fetch('public/api/carrinho_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add_item', item: item })
            });
            const result = await response.json();
            if (result.success) {
                window.location.href = 'carrinho.php';
            } else {
                alert('Erro ao adicionar item ao carrinho: ' + result.message);
            }
        } catch (error) {
            console.error('Erro de rede:', error);
            alert('Não foi possível conectar ao servidor. Verifique sua conexão.');
        }
    }
});
</script>
</body>
</html>