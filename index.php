<?php
// index.php (Cardápio Digital)

include 'includes/conexao.php';

// Define o caminho base do projeto.
// ATENÇÃO: Se o seu projeto não está em um subdiretório como 'pdv' (ex: acessa direto por http://localhost/),\
// Mude esta variável para $basePath = '/';
$basePath = '/pdv/'; // <--- AJUSTE ESTA LINHA SE NECESSÁRIO

// --- Lógica para buscar configurações da loja do banco de dados ---
$nome_hamburgueria = "Minha Hamburgueria"; // Valor padrão
$horario_funcionamento_descricao = "Horário não definido"; // Descrição textual
$pedido_minimo_valor = "0,00";
$hora_abertura_db = "00:00:00";
$hora_fechamento_db = "23:59:59";
$dias_abertura_db = "";

$sqlConfig = "SELECT nome_hamburgueria, horario_funcionamento, pedido_minimo, hora_abertura, hora_fechamento, dias_abertura FROM configuracoes_loja WHERE id = 1";
$resultConfig = $conn->query($sqlConfig);
if ($resultConfig && $resultConfig->num_rows > 0) {
    $config = $resultConfig->fetch_assoc();
    $nome_hamburgueria = htmlspecialchars($config['nome_hamburgueria']);
    $horario_funcionamento_descricao = htmlspecialchars($config['horario_funcionamento']);
    $pedido_minimo_valor = htmlspecialchars(number_format($config['pedido_minimo'], 2, ',', '.'));
    $hora_abertura_db = $config['hora_abertura'];
    $hora_fechamento_db = $config['hora_fechamento'];
    $dias_abertura_db = $config['dias_abertura'];
}

// --- Lógica para determinar o status da loja (Aberta/Fechada) ---
date_default_timezone_set('America/Sao_Paulo'); // Defina seu fuso horário local
$current_time = new DateTime(); // Horário atual
$current_day_of_week = (int)$current_time->format('N'); // 1 (para Segunda-feira) a 7 (para Domingo)

$loja_status = "Loja Fechada"; // Status padrão
$loja_status_class = "fechada";

$dias_abertos_array = explode(',', $dias_abertura_db);

if (in_array($current_day_of_week, $dias_abertos_array)) {
    // A loja está configurada para abrir hoje
    $open_time = DateTime::createFromFormat('H:i:s', $hora_abertura_db);
    $close_time = DateTime::createFromFormat('H:i:s', $hora_fechamento_db);

    // Se o horário de fechamento for menor que o de abertura (ex: fecha na manhã do dia seguinte)
    if ($close_time < $open_time) {
        // Se a hora atual for maior que a de abertura OU menor que a de fechamento (passou da meia-noite)
        if ($current_time->format('H:i:s') >= $open_time->format('H:i:s') || $current_time->format('H:i:s') < $close_time->format('H:i:s')) {
            $loja_status = "Loja Aberta";
            $loja_status_class = "aberta";
        }
    } else {
        // Horário de fechamento no mesmo dia
        if ($current_time->format('H:i:s') >= $open_time->format('H:i:s') && $current_time->format('H:i:s') < $close_time->format('H:i:s')) {
            $loja_status = "Loja Aberta";
            $loja_status_class = "aberta";
        }
    }
}


// Busca todas as categorias, agora ordenando pela coluna 'ordem'
$sqlCategorias = "SELECT id, nome FROM categorias ORDER BY ordem ASC, nome ASC";
$resultCategorias = $conn->query($sqlCategorias);
$categorias = [];
if ($resultCategorias) {
    while ($cat = $resultCategorias->fetch_assoc()) {
        $categorias[] = $cat;
    }
}

// Busca todos os produtos
$sqlProdutos = "SELECT id, id_categoria, nome, descricao, preco, ativo, imagem, controla_estoque, estoque FROM produtos ORDER BY id_categoria, nome";
$resultProdutos = $conn->query($sqlProdutos);
$produtosPorCategoria = [];

// Organiza os produtos por categoria para exibição
if ($resultProdutos) {
    while ($produto = $resultProdutos->fetch_assoc()) {
        $produtosPorCategoria[$produto['id_categoria']][] = $produto;
    }
}

// Fechar a conexão com o banco de dados
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Cardápio - <?= $nome_hamburgueria ?></title>
    <link rel="stylesheet" href="public/css/cardapio.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>

<header class="top-bar">
    <div class="top-bar-content">
        <div class="restaurant-name"><?= $nome_hamburgueria ?></div>
        <div class="top-bar-icons">
            <button class="search-icon" aria-label="Buscar"><i class="fas fa-search"></i></button>
        </div>
    </div>
</header>

<div class="store-info-bar">
    <span class="store-info-description"><?= $horario_funcionamento_descricao ?></span>
    <span class="store-status-text <?= $loja_status_class ?>"><?= $loja_status ?></span>
    <span class="min-order-text">Min. R$ <?= $pedido_minimo_valor ?></span>
    </div>

<main class="main-content-area">
    <?php if (empty($categorias)): ?>
        <p class="no-content-message">Nenhuma categoria cadastrada ainda.</p>
    <?php else: ?>
        <?php foreach ($categorias as $categoria): ?>
            <?php
            $produtosDaCategoria = $produtosPorCategoria[$categoria['id']] ?? [];
            if (!empty($produtosDaCategoria)): // Só exibe a seção se houver produtos nela
            ?>
                <section class="category-section" data-category-id="<?= htmlspecialchars($categoria['id']) ?>">
                    <h2 class="category-title"><?= htmlspecialchars($categoria['nome']) ?></h2>
                    <div class="products-grid">
                        <?php foreach ($produtosDaCategoria as $produto):
                            // Verifica se o produto está ativo e se o estoque permite
                            $indisponivel = (!$produto['ativo'] || ($produto['controla_estoque'] && $produto['estoque'] <= 0)) ? 'indisponivel' : '';
                            $nomeProduto = htmlspecialchars($produto['nome']);
                            if ($indisponivel) {
                                $nomeProduto .= ' (Indisponível)';
                            }

                            // --- LÓGICA PARA O CAMINHO DA IMAGEM ---
                            $caminhoImagemProduto = '';
                            if (!empty($produto['imagem'])) {
                                if (strpos($produto['imagem'], 'http://') === 0 || strpos($produto['imagem'], 'https://') === 0 || strpos($produto['imagem'], '/') === 0) {
                                    $caminhoImagemProduto = $produto['imagem'];
                                } else {
                                    $caminhoImagemProduto = $basePath . $produto['imagem'];
                                }
                            } else {
                                $caminhoImagemProduto = $basePath . 'public/img/default-product.png'; 
                            }
                            // --- FIM DA LÓGICA DA IMAGEM ---
                        ?>
                            <div class="product-card <?= $indisponivel ?>"
                                data-categoria="<?= htmlspecialchars($produto['id_categoria']) ?>"
                                data-id="<?= htmlspecialchars($produto['id']) ?>"
                                data-nome="<?= htmlspecialchars($produto['nome']) ?>"
                                data-descricao="<?= htmlspecialchars($produto['descricao']) ?>"
                                data-preco="<?= htmlspecialchars($produto['preco']) ?>"
                                data-img="<?= htmlspecialchars($caminhoImagemProduto) ?>"
                                data-controla-estoque="<?= $produto['controla_estoque'] ? 'true' : 'false' ?>"
                                data-estoque="<?= htmlspecialchars($produto['estoque']) ?>"
                                data-ativo="<?= $produto['ativo'] ? 'true' : 'false' ?>">
                                <img src="<?= htmlspecialchars($caminhoImagemProduto) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" class="product-image" onerror="this.onerror=null;this.src='<?= $basePath ?>public/img/default-product.png';" />
                                <div class="product-info">
                                    <h3 class="product-name"><?= $nomeProduto ?></h3>
                                    <p class="product-description"><?= htmlspecialchars(mb_strimwidth($produto['descricao'], 0, 70, '...')) ?></p>
                                    <div class="product-actions"> <span class="product-price">R$ <?= number_format($produto['preco'], 2, ',', '.') ?></span>
                                        </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<div id="floating-cart-summary" class="floating-cart-summary oculto">
    <div class="summary-info">
        <span id="summary-item-count">0 item</span>
        <span id="summary-total">R$ 0,00</span>
    </div>
    <a href="carrinho.php" class="btn-view-cart">Ver carrinho</a>
</div>

<nav class="bottom-nav">
    <a href="index.php" class="nav-item active">
        <i class="fas fa-home"></i>
        <span>Início</span>
    </a>
    <a href="carrinho.php" class="nav-item">
        <i class="fas fa-clipboard-list"></i>
        <span>Pedidos</span>
    </a>
    <a href="#" class="nav-item">
        <i class="fas fa-tags"></i>
        <span>Promoções</span>
    </a>
    <a href="carrinho.php" class="nav-item cart-nav-item">
        <i class="fas fa-shopping-cart"></i>
        <span>Carrinho</span>
        <span class="cart-count-bottom" id="cart-count">0</span>
    </a>
</nav>

<div id="modal-produto" class="carrinho-modal oculto">
  <div class="card-produto">
    <button id="fechar-modal-produto" class="btn-fechar">✕</button>
    <img id="modal-img" src="" alt="Imagem do Produto" class="img-produto-modal">
    
    <div class="info-produto">
      <h2 id="modal-nome"></h2>
      <p id="modal-descricao" class="descricao-produto"></p>

      <label for="observacoes">Observações:</label>
      <textarea id="observacoes" rows="2" placeholder="Ex: Tirar cebola, bem passado..." class="input-observacoes"></textarea>

      <div id="area-adicionais" class="area-adicionais">
        <h3>Adicionais:</h3>
        </div>

      <div class="controle-quantidade">
        <button id="diminuir">−</button>
        <span id="quantidade">1</span>
        <button id="aumentar">+</button>
      </div>

      <button id="confirmar-adicao" class="btn-confirmar">Adicionar ao carrinho</button>
    </div>
  </div>
</div>

<div id="modal-carrinho" class="carrinho-modal oculto">
    <div class="carrinho-conteudo">
        <h2>Seu Carrinho</h2>
        <ul id="lista-carrinho"></ul>
        <p>Total: R$ <span id="total-carrinho">0,00</span></p>
        <a href="carrinho.php" class="btn-ver-carrinho-modal">Ver Carrinho Completo</a>
        <button id="fechar-carrinho">Fechar</button>
    </div>
</div>

<script src="public/js/cardapio.js"></script>
</body>
</html>