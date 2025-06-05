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
            // const estaDisponivel = !card.classList.contains('indisponivel'); // Comentado, pois a classe 'indisponivel' já controla o display do botão

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

    // Modal de Detalhes do Produto
    const modalProduto = document.getElementById('modal-produto');
    const modalImg = document.getElementById('modal-img');
    const modalNome = document.getElementById('modal-nome');
    const modalDescricao = document.getElementById('modal-descricao');
    const observacoes = document.getElementById('observacoes');
    const quantidadeSpan = document.getElementById('quantidade');
    const btnAumentar = document.getElementById('aumentar');
    const btnDiminuir = document.getElementById('diminuir');
    const btnConfirmar = document.getElementById('confirmar-adicao');
    const btnFecharModalProduto = document.getElementById('fechar-modal-produto');

    let produtoSelecionadoNoModal = null; // Armazena o produto que está sendo configurado no modal
    let quantidadeSelecionadaNoModal = 1;

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
                totalItens += item.quantidade;
                totalValor += item.preco; // CORRIGIDO AQUI: Apenas soma item.preco, que já é o subtotal do item
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

    // Eventos de clique para abrir o modal de detalhes do produto (clique no card inteiro)
    productCards.forEach(card => {
        card.addEventListener('click', (event) => {
            // Evita abrir o modal se o botão "Adicionar" dentro do card foi clicado diretamente
            if (event.target.classList.contains('btn-add-to-cart') || card.classList.contains('indisponivel')) {
                return;
            }

            // Pega os dados do produto do atributo data-* do card
            produtoSelecionadoNoModal = {
                id: card.getAttribute('data-id'),
                nome: card.getAttribute('data-nome'),
                descricao: card.getAttribute('data-descricao') || '',
                preco: parseFloat(card.getAttribute('data-preco')),
                imagem: card.getAttribute('data-img')
            };

            // Preenche o modal com os dados do produto
            modalImg.src = produtoSelecionadoNoModal.imagem;
            modalNome.textContent = produtoSelecionadoNoModal.nome;
            modalDescricao.textContent = produtoSelecionadoNoModal.descricao;
            observacoes.value = ''; // Limpa observações anteriores
            quantidadeSelecionadaNoModal = 1; // Reseta quantidade
            quantidadeSpan.textContent = quantidadeSelecionadaNoModal;

            // Carrega adicionais ao abrir o modal
            carregarAdicionaisProduto(produtoSelecionadoNoModal.id);

            modalProduto.classList.remove('oculto'); // Exibe o modal
        });
    });

    // Eventos para controle de quantidade no modal de detalhes do produto
    btnAumentar.addEventListener('click', () => {
        quantidadeSelecionadaNoModal++;
        quantidadeSpan.textContent = quantidadeSelecionadaNoModal;
    });

    btnDiminuir.addEventListener('click', () => {
        if (quantidadeSelecionadaNoModal > 1) {
            quantidadeSelecionadaNoModal--;
            quantidadeSpan.textContent = quantidadeSelecionadaNoModal;
        }
    });

    // Evento para confirmar adição ao carrinho no modal de detalhes do produto
    btnConfirmar.addEventListener('click', async () => {
        if (!produtoSelecionadoNoModal) return;

        const adicionaisSelecionados = [];
        document.querySelectorAll('#area-adicionais .adicional-check:checked').forEach(checkbox => {
            adicionaisSelecionados.push({
                id: checkbox.getAttribute('data-id'),
                nome: checkbox.getAttribute('data-nome'),
                preco: parseFloat(checkbox.getAttribute('data-preco'))
            });
        });

        const itemParaAdicionar = {
            id: produtoSelecionadoNoModal.id,
            nome: produtoSelecionadoNoModal.nome,
            preco: produtoSelecionadoNoModal.preco,
            quantidade: quantidadeSelecionadaNoModal,
            obs: observacoes.value.trim(),
            adicionais: adicionaisSelecionados // Inclui os adicionais
        };

        const result = await gerenciarCarrinhoAPI('add_item', itemParaAdicionar);
        if (result.success) {
            atualizarContadorCarrinho(); // Atualiza o contador e a barra de resumo
            modalProduto.classList.add('oculto'); // Fecha o modal
            animarCarrinhoContador(); // Anima o contador
        }
    });

    // Evento para fechar o modal de detalhes do produto
    btnFecharModalProduto.addEventListener('click', () => {
        modalProduto.classList.add('oculto');
    });

    // Função para carregar adicionais do produto
    async function carregarAdicionaisProduto(idProduto) {
        const areaAdicionais = document.getElementById("area-adicionais");
        if (!areaAdicionais) {
            console.warn("Elemento #area-adicionais não encontrado. Adicionais não serão exibidos.");
            return;
        }
        areaAdicionais.innerHTML = "<p>Carregando adicionais...</p>";

        try {
            const response = await fetch(`public/api/get_adicionais_produto.php?id=${idProduto}`);
            const adicionais = await response.json();

            areaAdicionais.innerHTML = "";

            if (adicionais.success && adicionais.adicionais.length > 0) {
                adicionais.adicionais.forEach(ad => {
                    const div = document.createElement("div");
                    div.className = "adicional-item";
                    div.innerHTML = `
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" class="adicional-check form-checkbox h-5 w-5 text-blue-600 rounded" data-id="${ad.id}" data-nome="${ad.nome}" data-preco="${parseFloat(ad.preco).toFixed(2)}">
                            <span class="text-gray-700">${ad.nome} (+R$ ${parseFloat(ad.preco).toFixed(2).replace('.', ',')})</span>
                        </label>
                    `;
                    areaAdicionais.appendChild(div);
                });
            } else {
                areaAdicionais.innerHTML = "<p>Nenhum adicional disponível para este produto.</p>";
            }
        } catch (error) {
            console.error('Erro ao carregar adicionais:', error);
            areaAdicionais.innerHTML = "<p>Erro ao carregar adicionais.</p>";
        }
    }

    // Inicializa o contador do carrinho e a barra de resumo ao carregar a página
    atualizarContadorCarrinho();
});
