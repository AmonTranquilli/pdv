document.addEventListener('DOMContentLoaded', function() {
    carregarPedidos(); 

    setInterval(carregarPedidos, 10000); 

    const btnFinalizarDia = document.getElementById('btnFinalizarDia');
    if (btnFinalizarDia) {
        btnFinalizarDia.addEventListener('click', finalizarDia);
    }

    const modalOverlay = document.getElementById('modalDetalhesPedido');
    if (modalOverlay) {
        modalOverlay.addEventListener('click', function(event) {
            if (event.target === modalOverlay) {
                fecharModalDetalhes();
            }
        });
    }

    const modalBtnAceitar = document.getElementById('modalBtnAceitar');
    if (modalBtnAceitar) {
        modalBtnAceitar.onclick = function() {
            if (!pedidoIdAtualModal) return;
            const msg = `Aceitar o pedido #${pedidoIdAtualModal} e iniciar o preparo?`;
            showCustomConfirm(msg, 'Confirmar Aceite', () => {
                atualizarStatusPedido(pedidoIdAtualModal, 'preparando', true);
            });
        };
    }

    const modalBtnCancelar = document.getElementById('modalBtnCancelar');
    if (modalBtnCancelar) {
        modalBtnCancelar.onclick = function() {
            if (!pedidoIdAtualModal) return;
            const msg = `Tem certeza que deseja CANCELAR o pedido #${pedidoIdAtualModal}? Esta ação não pode ser desfeita.`;
            showCustomConfirm(msg, 'Confirmar Cancelamento', () => {
                atualizarStatusPedido(pedidoIdAtualModal, 'cancelado', true);
            }, 'error');
        };
    }
});

let pedidoIdAtualModal = null; 

// --- NOVAS FUNÇÕES DE MODAL ---

function showCustomAlert(message, title = 'Aviso', type = 'info') {
    const modal = document.getElementById('notificationModal');
    const modalContent = modal.querySelector('.modal-content');
    const modalTitle = document.getElementById('notificationTitle');
    const modalMessage = document.getElementById('notificationMessage');
    const modalActions = document.getElementById('notificationActions');

    modalTitle.textContent = title;
    modalMessage.textContent = message;

    modalContent.classList.remove('success', 'error', 'info', 'confirm');
    modalContent.classList.add(type);
    
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle' };
    modalTitle.innerHTML = `<i class="fas ${icons[type]}"></i> ${title}`;

    modalActions.innerHTML = '<button class="btn-primary">OK</button>';
    modal.classList.add('ativo');

    modalActions.querySelector('button').onclick = function() {
        modal.classList.remove('ativo');
    };
}

function showCustomConfirm(message, title, onConfirmCallback, type = 'confirm') {
    const modal = document.getElementById('notificationModal');
    const modalContent = modal.querySelector('.modal-content');
    const modalTitle = document.getElementById('notificationTitle');
    const modalMessage = document.getElementById('notificationMessage');
    const modalActions = document.getElementById('notificationActions');
    
    modalTitle.textContent = title;
    modalMessage.textContent = message;

    modalContent.classList.remove('success', 'error', 'info', 'confirm');
    modalContent.classList.add(type);
    
    const icons = { confirm: 'fa-question-circle', error: 'fa-exclamation-triangle' };
    modalTitle.innerHTML = `<i class="fas ${icons[type] || icons.confirm}"></i> ${title}`;

    modalActions.innerHTML = `
        <button id="confirmBtnYes" class="btn-aceitar">Sim</button>
        <button id="confirmBtnNo" class="btn-secondary">Não</button>
    `;
    modal.classList.add('ativo');

    const hideModal = () => modal.classList.remove('ativo');

    document.getElementById('confirmBtnYes').onclick = function() {
        hideModal();
        onConfirmCallback();
    };
    document.getElementById('confirmBtnNo').onclick = hideModal;
}

// --- FUNÇÕES PRINCIPAIS ADAPTADAS ---

async function finalizarDia() {
    const msg = "Tem certeza que deseja tentar finalizar o turno de trabalho?";
    showCustomConfirm(msg, "Finalizar Turno", async () => {
        try {
            const response = await fetch('/pdv/public/api/finalizar_dia.php');
            const resultado = await response.json();
            
            if (!response.ok) {
                throw new Error(resultado.mensagem || 'Erro desconhecido do servidor.');
            }
    
            const type = resultado.sucesso ? 'success' : 'error';
            showCustomAlert(resultado.mensagem, "Resultado do Fecho", type);

            if (resultado.sucesso) {
                carregarPedidos();
            }
        } catch (error) {
            console.error("Falha na função finalizarDia:", error);
            showCustomAlert("Ocorreu um erro ao tentar finalizar o turno: " + error.message, "Erro de Comunicação", "error");
        }
    });
}

async function carregarPedidos() {
    try {
        const response = await fetch('/pdv/public/api/obter_pedidos_kanban.php'); 
        const pedidos = await response.json();

        if (pedidos.erro) {
            console.error('Erro no backend (obter_pedidos_kanban):', pedidos.mensagem);
            return;
        }
        
        document.querySelectorAll('.cards-container').forEach(container => container.innerHTML = '');

        pedidos.forEach(pedido => {
            const card = criarCardPedido(pedido);
            const colunaId = `container-${pedido.status.toLowerCase()}`; 
            const colunaDestino = document.getElementById(colunaId);
            
            if (colunaDestino) {
                colunaDestino.appendChild(card);
            } else {
                 console.warn(`[AVISO] Container para status '${pedido.status}' (ID: ${colunaId}) não encontrado.`);
            }
        });
    } catch (error) {
        console.error('Falha ao carregar pedidos do Kanban:', error);
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
        <div class="card-itens"><strong>Itens:</strong>${itensHtml}</div>
        <div class="card-actions">${gerarBotoesAcao(pedido.status, pedido.id)}</div>
    `;
    return card;
}

function gerarBotoesAcao(statusAtual, idPedido) {
    let botoesHtml = '';
    if (statusAtual === 'pendente') {
        botoesHtml += `<button onclick="abrirModalDetalhes(${idPedido})" class="btn-detalhes" title="Ver Detalhes do Pedido">Detalhes</button>`;
    } else if (statusAtual === 'preparando') {
        // ALTERADO AQUI: Adicionada confirmação ao clicar em "P/ Entrega"
        botoesHtml += `<button onclick="showCustomConfirm('Despachar o pedido #${idPedido} a entrega?', 'Confirmar Saída', () => atualizarStatusPedido(${idPedido}, 'em_entrega'))" title="Mover para Em Entrega">P/ Entrega</button>`;
        botoesHtml += `<button onclick="abrirModalDetalhes(${idPedido})" class="btn-detalhes" title="Ver Detalhes do Pedido">Detalhes</button>`;
    } else if (statusAtual === 'em_entrega') {
        botoesHtml += `<button onclick="atualizarStatusPedido(${idPedido}, 'finalizado')" title="Marcar como Finalizado">Finalizar</button>`;
        botoesHtml += `<button onclick="abrirModalDetalhes(${idPedido})" class="btn-detalhes" title="Ver Detalhes do Pedido">Detalhes</button>`;
    }
    return botoesHtml;
}

async function abrirModalDetalhes(idPedido) {
    pedidoIdAtualModal = idPedido; 
    const modal = document.getElementById('modalDetalhesPedido');
    const modalBody = document.getElementById('modalCorpoDetalhes');
    if (!modal || !modalBody) return;
    
    modalBody.innerHTML = '<p>A carregar...</p>';
    modal.classList.add('ativo'); 

    try {
        const response = await fetch(`/pdv/public/api/obter_detalhes_pedido.php?id=${idPedido}`);
        const detalhes = await response.json();

        if (detalhes.erro) throw new Error(detalhes.mensagem);
        
        const dataPedidoObj = new Date(detalhes.data_pedido);
        let enderecoCompleto = `${escapeHTML(detalhes.endereco_entrega || '')}, ${escapeHTML(detalhes.numero_entrega || 'S/N')}`;
        if (detalhes.bairro_entrega) enderecoCompleto += `, Bairro: ${escapeHTML(detalhes.bairro_entrega)}`;
        if (detalhes.complemento_entrega) enderecoCompleto += ` - ${escapeHTML(detalhes.complemento_entrega)}`;
        if (detalhes.referencia_entrega) enderecoCompleto += ` (Ref: ${escapeHTML(detalhes.referencia_entrega)})`;
        
        let itensHtml = '<li>Nenhum item encontrado.</li>';
        if (detalhes.itens && detalhes.itens.length > 0) {
            itensHtml = detalhes.itens.map(item => {
                let html = `<li>${escapeHTML(item.quantidade)}x ${escapeHTML(item.nome_produto)} - R$ ${parseFloat(item.preco_unitario).toFixed(2).replace('.', ',')}`;
                if (item.observacao_item) html += `<br><small><em>Obs: ${escapeHTML(item.observacao_item)}</em></small>`;
                if (item.adicionais && item.adicionais.length > 0) {
                    html += `<br><small>Adicionais: ${item.adicionais.map(ad => `${escapeHTML(ad.nome_adicional)}`).join(', ')}</small>`;
                }
                html += `</li>`;
                return html;
            }).join('');
        }

        document.getElementById('modalPedidoId').textContent = `#${idPedido}`;
        modalBody.innerHTML = `
            <p><strong>Cliente:</strong> ${escapeHTML(detalhes.nome_cliente || 'N/A')}</p>
            <p><strong>Telefone:</strong> ${escapeHTML(detalhes.telefone_cliente || 'N/A')}</p>
            <p><strong>Endereço:</strong> ${enderecoCompleto}</p>
            <p><strong>Data:</strong> ${dataPedidoObj.toLocaleDateString('pt-BR')} ${dataPedidoObj.toLocaleTimeString('pt-BR')}</p>
            <p><strong>Total:</strong> R$ ${parseFloat(detalhes.total_pedido || 0).toFixed(2).replace('.', ',')}</p>
            <p><strong>Pagamento:</strong> ${escapeHTML(detalhes.forma_pagamento || 'N/A')}</p>
            <p id="paragrafoTrocoPara" style="display:none;"><strong>Troco Para:</strong> <span id="modalTrocoPara"></span></p>
            <p><strong>Observações:</strong> ${escapeHTML(detalhes.observacoes_pedido || 'Nenhuma.')}</p>
            <p><strong>Itens:</strong></p>
            <ul id="modalListaItens">${itensHtml}</ul>
        `;
        
        if (detalhes.troco_para > 0 && detalhes.forma_pagamento.toLowerCase() === 'dinheiro') {
            document.getElementById('paragrafoTrocoPara').style.display = 'block';
            document.getElementById('modalTrocoPara').textContent = `R$ ${parseFloat(detalhes.troco_para).toFixed(2).replace('.', ',')}`;
        }

        const modalBtnAceitar = document.getElementById('modalBtnAceitar');
        const modalBtnCancelar = document.getElementById('modalBtnCancelar');
        if(modalBtnAceitar && modalBtnCancelar) {
            modalBtnAceitar.style.display = detalhes.status === 'pendente' ? 'inline-block' : 'none';
            // ALTERADO AQUI: Permite cancelar o pedido também se estiver em 'em_entrega'
            modalBtnCancelar.style.display = ['pendente', 'preparando', 'em_entrega'].includes(detalhes.status) ? 'inline-block' : 'none';
        }

    } catch (error) {
        console.error("Erro ao carregar detalhes do pedido:", error);
        modalBody.innerHTML = `<p style="color: red;">Não foi possível carregar os detalhes: ${error.message}</p>`;
    }
}

function fecharModalDetalhes() {
    const modal = document.getElementById('modalDetalhesPedido');
    if (modal) modal.classList.remove('ativo');
    pedidoIdAtualModal = null; 
}

async function atualizarStatusPedido(idPedido, novoStatus, fecharModalAposSucesso = false) {
    try {
        const response = await fetch('/pdv/public/api/atualizar_status_pedido.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id_pedido: idPedido, novo_status: novoStatus })
        });
        const resultado = await response.json();
        if (resultado.sucesso) {
            if (resultado.mensagem.includes("Nenhuma alteração necessária")) {
                showCustomAlert(resultado.mensagem, "Informação", "info");
            }
            carregarPedidos(); 
            if (fecharModalAposSucesso) fecharModalDetalhes();
        } else {
            showCustomAlert('Falha ao atualizar status: ' + (resultado.mensagem || 'Erro desconhecido.'), "Erro", "error");
        }
    } catch (error) {
        console.error("Erro na comunicação para atualizar status:", error);
        showCustomAlert('Erro de comunicação ao tentar atualizar o status.', "Erro de Comunicação", "error");
    }
}

function escapeHTML(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
