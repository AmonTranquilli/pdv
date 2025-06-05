// public/js/kanban_script.js

document.addEventListener('DOMContentLoaded', function() {
    carregarPedidos(); // Carrega os pedidos ao iniciar

    // Recarregar os pedidos a cada 10 segundos
    setInterval(carregarPedidos, 10000); 

    // Esconder o modal se o usuário clicar fora do conteúdo do modal
    const modalOverlay = document.getElementById('modalDetalhesPedido');
    if (modalOverlay) {
        modalOverlay.addEventListener('click', function(event) {
            if (event.target === modalOverlay) {
                fecharModalDetalhes();
            }
        });
    }
});

let pedidoIdAtualModal = null; // Variável global para guardar o ID do pedido no modal

async function carregarPedidos() {
    try {
        const response = await fetch('/pdv/public/api/obter_pedidos_kanban.php'); 
        if (!response.ok) {
            throw new Error(`Erro HTTP ao buscar pedidos: ${response.status}`);
        }
        const pedidos = await response.json();

        if (pedidos.erro) {
            console.error('Erro retornado pelo backend ao buscar pedidos:', pedidos.mensagem);
            return;
        }
        
        document.querySelectorAll('.cards-container').forEach(container => container.innerHTML = '');

        if (pedidos.length === 0) {
            console.log('Nenhum pedido ativo para exibir no Kanban.');
            // Você pode adicionar mensagens como:
            // document.getElementById('container-pendente').innerHTML = '<p class="text-center p-3 text-muted">Nenhum pedido pendente.</p>';
            return;
        }

        pedidos.forEach(pedido => {
            const card = criarCardPedido(pedido);
            const colunaId = `container-${pedido.status.toLowerCase().replace('_', '-')}`;
            const colunaDestino = document.getElementById(colunaId);
            
            if (colunaDestino) {
                colunaDestino.appendChild(card);
            } else {
                console.warn(`Container de cards para status '${pedido.status}' (ID: ${colunaId}) não encontrado.`);
            }
        });

    } catch (error) {
        console.error('Falha ao carregar ou processar pedidos:', error);
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
        // O botão de aceitar foi movido para o modal, mas pode haver um direto se preferir.
        // Para manter o fluxo solicitado, o "Aceitar" principal virá do modal.
        // Se quiser um "Aceitar" rápido aqui, descomente a linha abaixo e ajuste o CSS se necessário:
        // botoesHtml += `<button onclick="confirmarAceiteRapido(${idPedido})" class="btn-aceitar-card" title="Aceitar e Mover para Preparando">Aceitar Rápido</button>`;
    } else if (statusAtual === 'preparando') {
        botoesHtml += `<button onclick="atualizarStatusPedido(${idPedido}, 'em_entrega')" title="Mover para Em Entrega">P/ Entrega</button>`;
        botoesHtml += `<button onclick="abrirModalDetalhes(${idPedido})" class="btn-detalhes" title="Ver Detalhes do Pedido">Detalhes</button>`;
    } else if (statusAtual === 'em_entrega') {
        botoesHtml += `<button onclick="atualizarStatusPedido(${idPedido}, 'entregue')" title="Marcar como Entregue">Entregue</button>`;
        botoesHtml += `<button onclick="abrirModalDetalhes(${idPedido})" class="btn-detalhes" title="Ver Detalhes do Pedido">Detalhes</button>`;
    }
    return botoesHtml;
}

// Função de aceite rápido (opcional, se quiser um botão direto no card "pendente")
/*
function confirmarAceiteRapido(idPedido) {
    // Implementar uma UI de confirmação personalizada aqui em vez de confirm()
    if (confirm(`Aceitar o pedido #${idPedido} e mover para preparação?`)) {
        atualizarStatusPedido(idPedido, 'preparando');
    }
}
*/

async function abrirModalDetalhes(idPedido) {
    pedidoIdAtualModal = idPedido; // Armazena o ID do pedido para uso nos botões do modal
    const modal = document.getElementById('modalDetalhesPedido');
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
    modalPedidoId.textContent = `#${idPedido}`;
    modalClienteNome.textContent = 'Carregando...';
    modalClienteTelefone.textContent = 'Carregando...';
    modalClienteEndereco.textContent = 'Carregando...';
    modalDataPedido.textContent = 'Carregando...';
    modalTotalPedido.textContent = 'Carregando...';
    modalFormaPagamento.textContent = 'Carregando...';
    modalListaItens.innerHTML = '<li>Carregando...</li>';
    modalObservacoesPedido.textContent = 'Carregando...';
    paragrafoTrocoPara.style.display = 'none';
    modalTrocoPara.textContent = '';


    modal.classList.add('ativo'); // Adiciona classe para mostrar o modal com transição CSS

    try {
        // **PRÓXIMO PASSO: Criar o script /pdv/public/api/obter_detalhes_pedido.php**
        // Este script PHP deverá buscar todos os detalhes do pedido, incluindo itens e adicionais.
        const response = await fetch(`/pdv/public/api/obter_detalhes_pedido.php?id=${idPedido}`);
        if (!response.ok) {
            throw new Error(`Erro HTTP ao buscar detalhes do pedido: ${response.status}`);
        }
        const detalhes = await response.json();

        if (detalhes.erro) {
            throw new Error(detalhes.mensagem || 'Erro ao buscar detalhes.');
        }
        
        // Preencher o modal com os dados recebidos
        modalClienteNome.textContent = escapeHTML(detalhes.nome_cliente || 'N/A');
        modalClienteTelefone.textContent = escapeHTML(detalhes.telefone_cliente || 'N/A');
        
        let enderecoCompleto = `${escapeHTML(detalhes.endereco_entrega || '')}, ${escapeHTML(detalhes.numero_entrega || 'S/N')}`;
        if (detalhes.bairro_entrega) enderecoCompleto += `, ${escapeHTML(detalhes.bairro_entrega)}`;
        if (detalhes.complemento_entrega) enderecoCompleto += ` - ${escapeHTML(detalhes.complemento_entrega)}`;
        if (detalhes.referencia_entrega) enderecoCompleto += ` (Ref: ${escapeHTML(detalhes.referencia_entrega)})`;
        modalClienteEndereco.textContent = enderecoCompleto;

        const dataPedidoObj = new Date(detalhes.data_pedido);
        modalDataPedido.textContent = `${dataPedidoObj.toLocaleDateString('pt-BR')} ${dataPedidoObj.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`;
        
        modalTotalPedido.textContent = `R$ ${parseFloat(detalhes.total_pedido || 0).toFixed(2).replace('.', ',')}`;
        modalFormaPagamento.textContent = escapeHTML(detalhes.forma_pagamento || 'N/A');
        
        if (detalhes.troco_para && parseFloat(detalhes.troco_para) > 0) {
            paragrafoTrocoPara.style.display = 'block';
            modalTrocoPara.textContent = `R$ ${parseFloat(detalhes.troco_para).toFixed(2).replace('.', ',')}`;
        } else {
            paragrafoTrocoPara.style.display = 'none';
        }

        modalObservacoesPedido.textContent = escapeHTML(detalhes.observacoes_pedido || 'Nenhuma.');

        if (detalhes.itens && detalhes.itens.length > 0) {
            modalListaItens.innerHTML = detalhes.itens.map(item => {
                let htmlItem = `<li>${escapeHTML(item.quantidade)}x ${escapeHTML(item.nome_produto)} - R$ ${parseFloat(item.preco_unitario).toFixed(2).replace('.', ',')}`;
                if (item.observacao_item) {
                    htmlItem += `<br><small style="padding-left: 15px;"><em>Obs: ${escapeHTML(item.observacao_item)}</em></small>`;
                }
                if (item.adicionais && item.adicionais.length > 0) {
                    htmlItem += `<br><small style="padding-left: 15px;">Adicionais: ${item.adicionais.map(ad => `${escapeHTML(ad.nome_adicional)} (+R$ ${parseFloat(ad.preco_adicional).toFixed(2).replace('.',',')})`).join(', ')}</small>`;
                }
                htmlItem += `</li>`;
                return htmlItem;
            }).join('');
        } else {
            modalListaItens.innerHTML = '<li>Nenhum item encontrado para este pedido.</li>';
        }

        // Mostrar/esconder botão de Aceitar dependendo do status atual do pedido
        if (detalhes.status === 'pendente') {
            modalBtnAceitar.style.display = 'inline-block';
            modalBtnCancelar.style.display = 'inline-block'; // Manter cancelar para pendentes
        } else {
            modalBtnAceitar.style.display = 'none';
            // Para outros status, o botão de cancelar pode ou não estar disponível.
            // Se o pedido já está "preparando", talvez ainda possa cancelar.
            // Se "em_entrega", "entregue", "cancelado", provavelmente não mostra "cancelar".
            if (detalhes.status === 'preparando') {
                 modalBtnCancelar.style.display = 'inline-block';
            } else {
                 modalBtnCancelar.style.display = 'none';
            }
        }


    } catch (error) {
        console.error("Erro ao carregar detalhes do pedido:", error);
        modalCorpoDetalhes.innerHTML = `<p class="text-danger">Não foi possível carregar os detalhes do pedido. Tente novamente mais tarde.</p>`;
    }
}


function fecharModalDetalhes() {
    const modal = document.getElementById('modalDetalhesPedido');
    if (modal) {
        modal.classList.remove('ativo');
         // Limpar id para evitar ações acidentais se modal for reaberto rapidamente por outro card
        pedidoIdAtualModal = null;
    }
}

// Configurar botões do modal (fazemos isso uma vez no DOMContentLoaded)
document.addEventListener('DOMContentLoaded', function() {
    const modalBtnAceitar = document.getElementById('modalBtnAceitar');
    const modalBtnCancelar = document.getElementById('modalBtnCancelar');

    if (modalBtnAceitar) {
        modalBtnAceitar.onclick = function() {
            if (pedidoIdAtualModal) {
                // Implementar uma UI de confirmação personalizada aqui em vez de confirm()
                if (confirm(`Aceitar o pedido #${pedidoIdAtualModal} e iniciar o preparo?`)) {
                    atualizarStatusPedido(pedidoIdAtualModal, 'preparando', true); // true para fechar modal
                }
            }
        };
    }

    if (modalBtnCancelar) {
        modalBtnCancelar.onclick = function() {
            if (pedidoIdAtualModal) {
                 // Implementar uma UI de confirmação personalizada aqui em vez de confirm()
                if (confirm(`Tem certeza que deseja CANCELAR o pedido #${pedidoIdAtualModal}? Esta ação não pode ser desfeita.`)) {
                    atualizarStatusPedido(pedidoIdAtualModal, 'cancelado', true); // true para fechar modal
                }
            }
        };
    }
});


async function atualizarStatusPedido(idPedido, novoStatus, fecharModalAposSucesso = false) {
    console.log(`Tentando atualizar pedido ${idPedido} para ${novoStatus}`);
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
            throw new Error(`Erro HTTP ao atualizar status: ${response.status}. Detalhes: ${errorData}`);
        }

        const resultado = await response.json();

        if (resultado.sucesso) {
            carregarPedidos(); 
            if (fecharModalAposSucesso) {
                fecharModalDetalhes();
            }
            // alert(resultado.mensagem); // Opcional
        } else {
            alert('Falha ao atualizar status do pedido: ' + (resultado.mensagem || 'Erro desconhecido.'));
        }
    } catch (error) {
        console.error('Erro na função atualizarStatusPedido:', error);
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