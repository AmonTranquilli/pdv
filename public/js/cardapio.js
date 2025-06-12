document.addEventListener('DOMContentLoaded', () => {
    // Elementos para busca e exibição de produtos
    const inputPesquisa = document.getElementById('pesquisa-produtos');
    const productCards = document.querySelectorAll('.product-card'); // Seleciona os novos cards de produto
    const categorySections = document.querySelectorAll('.category-section'); // Seleciona as seções de categoria

    // Função para filtrar produtos por nome
    function filtrarProdutos() {
        const termoPesquisa = inputPesquisa ? inputPesquisa.value.toLowerCase() : '';

        productCards.forEach(card => {
            const nomeProduto = card.getAttribute('data-nome').toLowerCase();

            if (nomeProduto.includes(termoPesquisa)) {
                card.style.display = 'flex'; // Mantém o card visível
            } else {
                card.style.display = 'none'; // Oculta o card
            }
        });

        // Opcional: Ocultar seções de categoria vazias após a filtragem
        categorySections.forEach(section => {
            const visibleProducts = section.querySelectorAll('.product-card:not([style*="display: none"])');
            if (visibleProducts.length === 0 && termoPesquisa !== '') { // Se não houver produtos visíveis e houver termo de busca
                section.style.display = 'none';
            } else {
                section.style.display = 'block'; // Garante que a seção apareça se houver produtos ou se a busca estiver vazia
            }
        });
    }

    // Evento input de pesquisa
    if (inputPesquisa) {
        inputPesquisa.addEventListener('input', () => {
            filtrarProdutos();
        });
    }

    // --- Lógica do Carrinho (AGORA COM COMUNICAÇÃO COM O BACKEND) ---
    const cartCountBottom = document.getElementById('cart-count'); // O contador na barra inferior
    
    // Novos elementos para a barra de resumo do carrinho
    const floatingCartSummary = document.getElementById('floating-cart-summary');
    const summaryItemCount = document.getElementById('summary-item-count');
    const summaryTotal = document.getElementById('summary-total');

    // Função para fazer requisições à API do carrinho
    async function gerenciarCarrinhoAPI(action, data = {}) {
        try {
            const response = await fetch('public/api/carrinho_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action, ...data })
            });
            const result = await response.json();
            if (!result.success) {
                console.error('Erro na API do carrinho:', result.message);
                // Você pode exibir uma mensagem de erro para o usuário aqui
            }
            return result;
        } catch (error) {
            console.error('Erro de rede ou servidor ao gerenciar carrinho:', error);
            // Exibir erro de conexão para o usuário
            return { success: false, message: 'Erro de conexão com o servidor.' };
        }
    }

    // Função para atualizar o contador do carrinho na interface e a barra de resumo
    async function atualizarContadorCarrinho() {
        const result = await gerenciarCarrinhoAPI('get_cart');
        if (result.success) {
            const carrinhoDoBackend = result.cart;
            let totalItens = 0;
            let totalValor = 0;
            carrinhoDoBackend.forEach(item => {
    const quantidade = parseInt(item.quantidade) || 0;
    const precoUnitario = parseFloat(item.preco_unitario) || 0;

    const precoTotalItem = item.preco !== undefined
        ? parseFloat(item.preco) || 0
        : precoUnitario * quantidade;

    totalItens += quantidade;
    totalValor += precoTotalItem;
});

            cartCountBottom.textContent = totalItens; // Atualiza o contador na barra inferior

            // Atualiza a barra de resumo flutuante
            if (totalItens > 0) {
                summaryItemCount.textContent = `${totalItens} item${totalItens > 1 ? 's' : ''}`;
                summaryTotal.textContent = `R$ ${totalValor.toFixed(2).replace('.', ',')}`;
                floatingCartSummary.classList.remove('oculto'); // Mostra a barra
            } else {
                floatingCartSummary.classList.add('oculto'); // Oculta a barra se o carrinho estiver vazio
            }
        } else {
            cartCountBottom.textContent = '0';
            floatingCartSummary.classList.add('oculto'); // Oculta em caso de erro
        }
    }

    // Animação para o contador do carrinho
    function animarCarrinhoContador() {
        cartCountBottom.classList.add('cart-bounce');
        setTimeout(() => cartCountBottom.classList.remove('cart-bounce'), 400);
    }

    // Inicializa o contador do carrinho e a barra de resumo ao carregar a página
    atualizarContadorCarrinho();
});
