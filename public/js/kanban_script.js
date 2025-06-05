// public/js/kanban_script.js

document.addEventListener('DOMContentLoaded', function() {
    console.log("Kanban JS carregado e DOM pronto.");
    carregarPedidos(); // Carrega os pedidos ao iniciar

    // Recarregar os pedidos a cada 10 segundos
    setInterval(carregarPedidos, 10000); 

    // Esconder o modal se o usuário clicar fora do conteúdo do modal
    const modalOverlay = document.getElementById('modalDetalhesPedido');
    if (modalOverlay) {
        modalOverlay.addEventListener('click', function(event) {
            // Verifica se o clique foi no overlay e não em um filho (o modal-content)
            if (event.target === modalOverlay) {
                console.log("Clique no overlay do modal detectado.");
                fecharModalDetalhes();
            }
        });
    } else {
        console.error("Elemento do modal overlay (modalDetalhesPedido) não encontrado no DOM.");
    }

    // Configurar botões do modal (Aceitar e Cancelar)
    const modalBtnAceitar = document.getElementById('modalBtnAceitar');
    const modalBtnCancelar = document.getElementById('modalBtnCancelar');

    if (modalBtnAceitar) {
        modalBtnAceitar.onclick = function() {
            if (pedidoIdAtualModal) {
                // Substitua confirm() por uma UI de confirmação personalizada em produção
                if (confirm(`Aceitar o pedido #${pedidoIdAtualModal} e iniciar o preparo?`)) {
                    console.log(`Botão 'Aceitar Pedido' clicado para o pedido ID: ${pedidoIdAtualModal}`);
                    atualizarStatusPedido(pedidoIdAtualModal, 'preparando', true); // true para fechar modal
                }
            } else {
                console.warn("modalBtnAceitar clicado, mas pedidoIdAtualModal é nulo.");
            }
        };
    } else {
        console.error("Botão 'modalBtnAceitar' não encontrado no DOM.");
    }

    if (modalBtnCancelar) {
        modalBtnCancelar.onclick = function() {
            if (pedidoIdAtualModal) {
                // Substitua confirm() por uma UI de confirmação personalizada em produção
                if (confirm(`Tem certeza que deseja CANCELAR o pedido #${pedidoIdAtualModal}? Esta ação não pode ser desfeita.`)) {
                    console.log(`Botão 'Cancelar Pedido' clicado para o pedido ID: ${pedidoIdAtualModal}`);
                    atualizarStatusPedido(pedidoIdAtualModal, 'cancelado', true); // true para fechar modal
                }
            } else {
                console.warn("modalBtnCancelar clicado, mas pedidoIdAtualModal é nulo.");
            }
        };
    } else {
        console.error("Botão 'modalBtnCancelar' não encontrado no DOM.");
    }
});

let pedidoIdAtualModal = null; // Variável global para guardar o ID do pedido no modal

async function carregarPedidos() {
    // console.log("carregarPedidos chamado..."); // Log para verificar o polling
    try {
        const response = await fetch('/pdv/public/api/obter_pedidos_kanban.php'); 
        if (!response.ok) {
            throw new Error(`Erro HTTP ao buscar pedidos do kanban: ${response.status}`);
        }
        const pedidos = await response.json();

        if (pedidos.erro) {
            console.error('Erro retornado pelo backend (obter_pedidos_kanban):', pedidos.mensagem);
            return;
        }
        
        document.querySelectorAll('.cards-container').forEach(container => container.innerHTML = '');

        if (pedidos.length === 0) {
            // console.log('Nenhum pedido ativo para exibir no Kanban.');
            return;
        }

        pedidos.forEach(pedido => {
            const card = criarCardPedido(pedido);
            // O ID do container deve corresponder ao status vindo do banco em minúsculas
            // Ex: se o status for 'finalizado', o ID do container será 'container-finalizado'
            const colunaId = `container-${pedido.status.toLowerCase()}`; 
            const colunaDestino = document.getElementById(colunaId);
            
            if (colunaDestino) {
                colunaDestino.appendChild(card);
            } else {
                 console.warn(`[AVISO] Container de cards para status '${pedido.status}' (ID: ${colunaId}) não encontrado no DOM.`);
            }
        });

    } catch (error) {
        console.error('Falha ao carregar ou processar pedidos do Kanban:', error);
    }
}

function criarCardPedido(pedido) {
    const card = document.createElement('div');
    card.className = 'kanban-card';
    card.setAttribute('data-id-pedido', pedido.id);

    const dataPedido = new Date(pedido.data_pedido);
    const dataFormatada = `${dataPedido.toLocaleDateString('pt-BR')} ${dataPedido.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`;

    let itensHtml = '<p><em>Nenhum item encontrado.</em></p>';
    if (pedido.itens_resumo && pedido.itens_resumo.trim() !== '') {
        itensHtml = '<ul>' + pedido.itens_resumo.split('; ').map(item => `<li>${escapeHTML(item)}</li>`).join('') + '</ul>';
    }

    card.innerHTML = `
        <h4>Pedido: <span class="card-id">#${pedido.id}</span></h4>
        <p class="card-data">Data: ${dataFormatada}</p>
        <p class="card-cliente">Cliente: ${escapeHTML(pedido.nome_cliente)}</p>
        <p class="card-total">Total: R$ ${parseFloat(pedido.total_pedido).toFixed(2).replace('.', ',')}</p>
        ${pedido.observacoes_pedido ? `<p><strong>Obs:</strong> ${escapeHTML(pedido.observacoes_pedido)}</p>` : ''}
        <div class="card-itens">
            <strong>Itens:</strong>
            ${itensHtml}
        </div>
        <div class="card-actions">
            ${gerarBotoesAcao(pedido.status, pedido.id)}
        </div>
    `;
    return card;
}

function gerarBotoesAcao(statusAtual, idPedido) {
    let botoesHtml = '';
    if (statusAtual === 'pendente') {
        botoesHtml += `<button onclick="abrirModalDetalhes(${idPedido})" class="btn-detalhes" title="Ver Detalhes do Pedido">Detalhes</button>`;
    } else if (statusAtual === 'preparando') {
        botoesHtml += `<button onclick="atualizarStatusPedido(${idPedido}, 'em_entrega')" title="Mover para Em Entrega">P/ Entrega</button>`;
        botoesHtml += `<button onclick="abrirModalDetalhes(${idPedido})" class="btn-detalhes" title="Ver Detalhes do Pedido">Detalhes</button>`;
    } else if (statusAtual === 'em_entrega') {
        // ALTERADO: Botão "Entregue" agora é "Finalizar" e envia status "finalizado"
        botoesHtml += `<button onclick="atualizarStatusPedido(${idPedido}, 'finalizado')" title="Marcar como Finalizado">Finalizar</button>`;
        botoesHtml += `<button onclick="abrirModalDetalhes(${idPedido})" class="btn-detalhes" title="Ver Detalhes do Pedido">Detalhes</button>`;
    }
    // Pedidos com status 'finalizado' ou 'cancelado' não terão botões de ação nesta lógica.
    return botoesHtml;
}


async function abrirModalDetalhes(idPedido) {
    console.log(`[LOG] abrirModalDetalhes chamada para o pedido ID: ${idPedido}`);
    pedidoIdAtualModal = idPedido; 
    const modal = document.getElementById('modalDetalhesPedido');
    
    if (!modal) {
        console.error("[ERRO] Elemento do modal (modalDetalhesPedido) não encontrado ao tentar abrir.");
        return;
    }

    // Selecionar elementos do modal
    const modalPedidoId = document.getElementById('modalPedidoId');
    const modalClienteNome = document.getElementById('modalClienteNome');
    const modalClienteTelefone = document.getElementById('modalClienteTelefone');
    const modalClienteEndereco = document.getElementById('modalClienteEndereco');
    const modalDataPedido = document.getElementById('modalDataPedido');
    const modalTotalPedido = document.getElementById('modalTotalPedido');
    const modalFormaPagamento = document.getElementById('modalFormaPagamento');
    const modalListaItens = document.getElementById('modalListaItens');
    const modalObservacoesPedido = document.getElementById('modalObservacoesPedido');
    const paragrafoTrocoPara = document.getElementById('paragrafoTrocoPara');
    const modalTrocoPara = document.getElementById('modalTrocoPara');
    const modalBtnAceitar = document.getElementById('modalBtnAceitar');
    const modalBtnCancelar = document.getElementById('modalBtnCancelar');

    // Resetar para "Carregando..."
    if (modalPedidoId) modalPedidoId.textContent = `#${idPedido}`;
    else console.warn("[AVISO] modalPedidoId não encontrado no DOM.");
    
    if (modalClienteNome) modalClienteNome.textContent = 'Carregando...';
    else console.warn("[AVISO] modalClienteNome não encontrado no DOM.");

    if (modalClienteTelefone) modalClienteTelefone.textContent = 'Carregando...';
    else console.warn("[AVISO] modalClienteTelefone não encontrado no DOM.");

    if (modalClienteEndereco) modalClienteEndereco.textContent = 'Carregando...';
    else console.warn("[AVISO] modalClienteEndereco não encontrado no DOM.");

    if (modalDataPedido) modalDataPedido.textContent = 'Carregando...';
    else console.warn("[AVISO] modalDataPedido não encontrado no DOM.");

    if (modalTotalPedido) modalTotalPedido.textContent = 'Carregando...';
    else console.warn("[AVISO] modalTotalPedido não encontrado no DOM.");

    if (modalFormaPagamento) modalFormaPagamento.textContent = 'Carregando...';
    else console.warn("[AVISO] modalFormaPagamento não encontrado no DOM.");

    if (modalListaItens) modalListaItens.innerHTML = '<li>Carregando...</li>';
    else console.warn("[AVISO] modalListaItens não encontrado no DOM.");

    if (modalObservacoesPedido) modalObservacoesPedido.textContent = 'Carregando...';
    else console.warn("[AVISO] modalObservacoesPedido não encontrado no DOM.");

    if (paragrafoTrocoPara) paragrafoTrocoPara.style.display = 'none';
    else console.warn("[AVISO] paragrafoTrocoPara não encontrado no DOM.");

    if (modalTrocoPara) modalTrocoPara.textContent = '';
    else console.warn("[AVISO] modalTrocoPara não encontrado no DOM.");


    console.log("[LOG] Adicionando classe 'ativo' ao modal para mostrar.");
    modal.classList.add('ativo'); 

    try {
        console.log(`[LOG] Buscando detalhes para o pedido ID: ${idPedido} via fetch.`);
        const response = await fetch(`/pdv/public/api/obter_detalhes_pedido.php?id=${idPedido}`);
        if (!response.ok) {
            const errorText = await response.text();
            console.error(`[ERRO] HTTP ${response.status} ao buscar detalhes: ${errorText}`);
            throw new Error(`Erro HTTP ${response.status} ao buscar detalhes do pedido: ${errorText}`);
        }
        const detalhes = await response.json();
        console.log("[LOG] Detalhes recebidos do backend:", detalhes);

        if (detalhes.erro) {
            console.error("[ERRO] Backend retornou erro:", detalhes.mensagem);
            throw new Error(detalhes.mensagem || 'Erro ao buscar detalhes do pedido no backend.');
        }
        
        // Preencher o modal com os dados recebidos
        if (modalClienteNome) modalClienteNome.textContent = escapeHTML(detalhes.nome_cliente || 'N/A');
        if (modalClienteTelefone) modalClienteTelefone.textContent = escapeHTML(detalhes.telefone_cliente || 'N/A');
        
        let enderecoCompleto = `${escapeHTML(detalhes.endereco_entrega || '')}`;
        if(detalhes.numero_entrega && detalhes.numero_entrega.toUpperCase() !== 'S/N' && detalhes.numero_entrega.trim() !== '') {
            enderecoCompleto += `, ${escapeHTML(detalhes.numero_entrega)}`;
        } else if (detalhes.numero_entrega) { // Para manter "S/N" se for o caso, ou vazio
             enderecoCompleto += `, ${escapeHTML(detalhes.numero_entrega)}`;
        } else {
            enderecoCompleto += `, S/N`;
        }

        if (detalhes.bairro_entrega) enderecoCompleto += `, Bairro: ${escapeHTML(detalhes.bairro_entrega)}`;
        if (detalhes.complemento_entrega) enderecoCompleto += ` - ${escapeHTML(detalhes.complemento_entrega)}`;
        if (detalhes.referencia_entrega) enderecoCompleto += ` (Ref: ${escapeHTML(detalhes.referencia_entrega)})`;
        if (modalClienteEndereco) modalClienteEndereco.textContent = enderecoCompleto.startsWith(',') ? enderecoCompleto.substring(1).trim() : enderecoCompleto.trim();


        const dataPedidoObj = new Date(detalhes.data_pedido);
        if (modalDataPedido) modalDataPedido.textContent = `${dataPedidoObj.toLocaleDateString('pt-BR')} ${dataPedidoObj.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`;
        
        if (modalTotalPedido) modalTotalPedido.textContent = `R$ ${parseFloat(detalhes.total_pedido || 0).toFixed(2).replace('.', ',')}`;
        if (modalFormaPagamento) modalFormaPagamento.textContent = escapeHTML(detalhes.forma_pagamento || 'N/A');
        
        if (detalhes.troco_para && parseFloat(detalhes.troco_para) > 0 && detalhes.forma_pagamento && detalhes.forma_pagamento.toLowerCase() === 'dinheiro') {
            if (paragrafoTrocoPara) paragrafoTrocoPara.style.display = 'block';
            if (modalTrocoPara) modalTrocoPara.textContent = `R$ ${parseFloat(detalhes.troco_para).toFixed(2).replace('.', ',')}`;
        } else {
            if (paragrafoTrocoPara) paragrafoTrocoPara.style.display = 'none';
        }

        if (modalObservacoesPedido) modalObservacoesPedido.textContent = escapeHTML(detalhes.observacoes_pedido || 'Nenhuma.');

        if (modalListaItens) {
            if (detalhes.itens && detalhes.itens.length > 0) {
                modalListaItens.innerHTML = detalhes.itens.map(item => {
                    let htmlItem = `<li>${escapeHTML(item.quantidade)}x ${escapeHTML(item.nome_produto)} - R$ ${parseFloat(item.preco_unitario).toFixed(2).replace('.', ',')}`;
                    if (item.observacao_item && item.observacao_item.trim() !== '') {
                        htmlItem += `<br><small style="padding-left: 15px; color: #555;"><em>Obs: ${escapeHTML(item.observacao_item)}</em></small>`;
                    }
                    if (item.adicionais && item.adicionais.length > 0) {
                        htmlItem += `<br><small style="padding-left: 15px; color: #555;">Adicionais: ${item.adicionais.map(ad => `${escapeHTML(ad.nome_adicional)} (+R$ ${parseFloat(ad.preco_adicional).toFixed(2).replace('.',',')})`).join(', ')}</small>`;
                    }
                    htmlItem += `</li>`;
                    return htmlItem;
                }).join('');
            } else {
                modalListaItens.innerHTML = '<li>Nenhum item encontrado para este pedido.</li>';
            }
        }

        // Mostrar/esconder botão de Aceitar/Cancelar dependendo do status atual do pedido
        if (modalBtnAceitar && modalBtnCancelar) {
            console.log(`[LOG] Status do pedido para botões do modal: ${detalhes.status}`);
            if (detalhes.status === 'pendente') {
                modalBtnAceitar.style.display = 'inline-block';
                modalBtnCancelar.style.display = 'inline-block'; 
            } else if (detalhes.status === 'preparando') {
                modalBtnAceitar.style.display = 'none';
                modalBtnCancelar.style.display = 'inline-block'; 
            }
            else { // Inclui em_entrega, entregue/finalizado, cancelado
                modalBtnAceitar.style.display = 'none';
                modalBtnCancelar.style.display = 'none';
            }
        } else {
            console.warn("[AVISO] Botões Aceitar ou Cancelar do modal não encontrados no DOM para definir visibilidade.");
        }

    } catch (error) {
        console.error("[ERRO] Erro ao carregar e preencher detalhes do pedido:", error);
        if (document.getElementById('modalCorpoDetalhes')) { 
             document.getElementById('modalCorpoDetalhes').innerHTML = `<p style="color: red;">Não foi possível carregar os detalhes do pedido: ${error.message}</p>`;
        }
    }
}


function fecharModalDetalhes() {
    const modal = document.getElementById('modalDetalhesPedido');
    if (modal) {
        modal.classList.remove('ativo');
        console.log("[LOG] Modal fechado.");
        pedidoIdAtualModal = null; 
    } else {
        console.warn("[AVISO] Tentativa de fechar modal, mas elemento 'modalDetalhesPedido' não encontrado.");
    }
}

async function atualizarStatusPedido(idPedido, novoStatus, fecharModalAposSucesso = false) {
    console.log(`[LOG] Tentando atualizar pedido ${idPedido} para ${novoStatus}`);
    try {
        const response = await fetch('/pdv/public/api/atualizar_status_pedido.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id_pedido: idPedido, novo_status: novoStatus })
        });

        if (!response.ok) {
            const errorData = await response.text();
            console.error(`[ERRO] HTTP ${response.status} ao atualizar status: ${errorData}`);
            throw new Error(`Erro HTTP ${response.status} ao atualizar status: ${errorData}`);
        }

        const resultado = await response.json();
        console.log("[LOG] Resultado da atualização de status:", resultado);

        if (resultado.sucesso) {
            carregarPedidos(); 
            if (fecharModalAposSucesso) {
                fecharModalDetalhes();
            }
        } else {
            console.error("[ERRO] Falha ao atualizar status do pedido (backend):", resultado.mensagem);
            alert('Falha ao atualizar status do pedido: ' + (resultado.mensagem || 'Erro desconhecido do backend.'));
        }
    } catch (error) {
        console.error('[ERRO] Erro na função atualizarStatusPedido:', error);
        alert('Erro de comunicação ao tentar atualizar o status do pedido. Verifique o console para detalhes.');
    }
}

function escapeHTML(str) {
    if (str === null || str === undefined) return '';
    return str.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}