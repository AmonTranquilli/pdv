document.addEventListener('DOMContentLoaded', function() {
    carregarPedidos(); 
    setInterval(carregarPedidos, 10000); 

    const btnFinalizarDia = document.getElementById('btnFinalizarDia');
    if (btnFinalizarDia) {
        btnFinalizarDia.addEventListener('click', finalizarDia);
    }

    const modalOverlay = document.getElementById('modalDetalhesPedido');
    if (modalOverlay) {
        modalOverlay.addEventListener('click', (event) => {
            if (event.target === modalOverlay) fecharModalDetalhes();
        });
    }

    const modalBtnAceitar = document.getElementById('modalBtnAceitar');
    if (modalBtnAceitar) {
        modalBtnAceitar.onclick = function() {
            if (!pedidoIdAtualModal) return;
            showCustomConfirm(`Aceitar o pedido #${pedidoIdAtualModal} e iniciar o preparo?`, 'Confirmar Aceite', () => {
                atualizarStatusPedido(pedidoIdAtualModal, 'preparando', true);
            });
        };
    }

    const modalBtnCancelar = document.getElementById('modalBtnCancelar');
    if (modalBtnCancelar) {
        modalBtnCancelar.onclick = function() {
            if (!pedidoIdAtualModal) return;
            showCustomConfirm(`Tem certeza que deseja CANCELAR o pedido #${pedidoIdAtualModal}?`, 'Confirmar Cancelamento', () => {
                atualizarStatusPedido(pedidoIdAtualModal, 'cancelado', true);
            }, 'error');
        };
    }

    const btnConfirmarFinalizacao = document.getElementById('btnConfirmarFinalizacao');
    if (btnConfirmarFinalizacao) {
        btnConfirmarFinalizacao.addEventListener('click', processarFinalizacaoEntrega);
    }
});

let pedidoIdAtualModal = null; 

function showCustomAlert(message, title = 'Aviso', type = 'info') {
    const modal = document.getElementById('notificationModal');
    const modalContent = modal.querySelector('.modal-content');
    const modalTitle = document.getElementById('notificationTitle');
    const modalMessage = document.getElementById('notificationMessage');
    const modalActions = document.getElementById('notificationActions');
    modalTitle.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-times-circle'}"></i> ${title}`;
    modalMessage.textContent = message;
    modalContent.className = `modal-content notification-modal ${type}`;
    modalActions.innerHTML = '<button class="btn-primary">OK</button>';
    modal.classList.add('ativo');
    modalActions.querySelector('button').onclick = () => modal.classList.remove('ativo');
}

function showCustomConfirm(message, title, onConfirmCallback, type = 'confirm') {
    const modal = document.getElementById('notificationModal');
    const modalContent = modal.querySelector('.modal-content');
    const modalTitle = document.getElementById('notificationTitle');
    const modalMessage = document.getElementById('notificationMessage');
    const modalActions = document.getElementById('notificationActions');
    modalTitle.innerHTML = `<i class="fas ${type === 'error' ? 'fa-exclamation-triangle' : 'fa-question-circle'}"></i> ${title}`;
    modalMessage.textContent = message;
    modalContent.className = `modal-content notification-modal ${type}`;
    modalActions.innerHTML = `<button class="btn-aceitar">Sim</button><button class="btn-secondary">Não</button>`;
    modal.classList.add('ativo');
    const hideModal = () => modal.classList.remove('ativo');
    modalActions.querySelector('.btn-aceitar').onclick = () => { hideModal(); onConfirmCallback(); };
    modalActions.querySelector('.btn-secondary').onclick = hideModal;
}

async function finalizarDia() {
    showCustomConfirm("Tem certeza que deseja tentar finalizar o turno?", "Finalizar Turno", async () => {
        try {
            const response = await fetch('/pdv/public/api/finalizar_dia.php');
            const resultado = await response.json();
            if (!response.ok) throw new Error(resultado.mensagem || 'Erro desconhecido.');
            showCustomAlert(resultado.mensagem, "Resultado", resultado.sucesso ? 'success' : 'error');
            if (resultado.sucesso) carregarPedidos();
        } catch (error) {
            showCustomAlert("Erro: " + error.message, "Erro de Comunicação", "error");
        }
    });
}

async function carregarPedidos() {
    try {
        const response = await fetch('/pdv/public/api/obter_pedidos_kanban.php'); 
        const pedidos = await response.json();
        if (pedidos.erro) { console.error(pedidos.mensagem); return; }
        
        document.querySelectorAll('.cards-container').forEach(c => c.innerHTML = '');
        pedidos.forEach(pedido => {
            const card = criarCardPedido(pedido);
            const colunaDestino = document.getElementById(`container-${pedido.status.toLowerCase()}`);
            if (colunaDestino) colunaDestino.appendChild(card);
        });
    } catch (error) {
        console.error('Falha ao carregar pedidos:', error);
    }
}

function criarCardPedido(pedido) {
    const card = document.createElement('div');
    card.className = 'kanban-card';
    card.setAttribute('data-id-pedido', pedido.id);
    const dataPedido = new Date(pedido.data_pedido);
    const dataFormatada = `${dataPedido.toLocaleDateString('pt-BR')} ${dataPedido.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`;
    let itensHtml = '<p><em>Nenhum item.</em></p>';
    if (pedido.itens_resumo) itensHtml = '<ul>' + pedido.itens_resumo.split('; ').map(item => `<li>${escapeHTML(item)}</li>`).join('') + '</ul>';
    card.innerHTML = `<h4>Pedido: #${pedido.id}</h4><p class="card-data">${dataFormatada}</p><p class="card-cliente">${escapeHTML(pedido.nome_cliente)}</p><p class="card-total">R$ ${parseFloat(pedido.total_pedido).toFixed(2).replace('.', ',')}</p><div class="card-itens"><strong>Itens:</strong>${itensHtml}</div><div class="card-actions">${gerarBotoesAcao(pedido.status, pedido.id)}</div>`;
    return card;
}

function gerarBotoesAcao(statusAtual, idPedido) {
    if (statusAtual === 'pendente') return `<button onclick="abrirModalDetalhes(${idPedido})">Detalhes</button>`;
    if (statusAtual === 'preparando') return `<button onclick="showCustomConfirm('Despachar o pedido #${idPedido}?', 'Confirmar Saída', () => atualizarStatusPedido(${idPedido}, 'em_entrega'))">P/ Entrega</button><button onclick="abrirModalDetalhes(${idPedido})">Detalhes</button>`;
    if (statusAtual === 'em_entrega') return `<button onclick="abrirModalEntregador(${idPedido})">Finalizar</button><button onclick="abrirModalDetalhes(${idPedido})">Detalhes</button>`;
    return '';
}

async function abrirModalEntregador(idPedido) {
    const modal = document.getElementById('modalEntregador');
    const select = document.getElementById('entregadorSelect');
    if (!modal || !select) return;

    pedidoIdAtualModal = idPedido;
    document.getElementById('entregadorModalPedidoId').textContent = `#${idPedido}`;
    select.innerHTML = '<option value="">A carregar entregadores...</option>';
    document.getElementById('entregadorError').style.display = 'none';
    modal.classList.add('ativo');
    select.focus(); // Foco no select em vez do input

    try {
        const response = await fetch('/pdv/public/api/obter_entregadores.php');
        const entregadores = await response.json();
        if (entregadores.erro) throw new Error(entregadores.mensagem);
        
        select.innerHTML = '<option value="">-- Selecione o entregador --</option>';
        entregadores.forEach(entregador => {
            const option = document.createElement('option');
            option.value = entregador.id;
            option.textContent = `${entregador.nome} (${entregador.codigo_entregador})`;
            select.appendChild(option);
        });
    } catch (error) {
        select.innerHTML = '<option value="">Erro ao carregar</option>';
        const errorDiv = document.getElementById('entregadorError');
        errorDiv.textContent = error.message;
        errorDiv.style.display = 'block';
    }
}

function fecharModalEntregador() {
    const modal = document.getElementById('modalEntregador');
    if (modal) modal.classList.remove('ativo');
    pedidoIdAtualModal = null;
}

async function processarFinalizacaoEntrega() {
    if (!pedidoIdAtualModal) return;
    const select = document.getElementById('entregadorSelect');
    const idEntregador = select.value;
    const errorDiv = document.getElementById('entregadorError');

    if (!idEntregador) {
        errorDiv.textContent = 'Por favor, selecione um entregador.';
        errorDiv.style.display = 'block';
        return;
    }
    errorDiv.style.display = 'none';

    try {
        const response = await fetch('/pdv/public/api/finalizar_entrega.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id_pedido: pedidoIdAtualModal,
                id_entregador: idEntregador
            })
        });
        const resultado = await response.json();
        if (resultado.sucesso) {
            fecharModalEntregador();
            showCustomAlert(resultado.mensagem, "Sucesso", "success");
            carregarPedidos();
        } else {
            errorDiv.textContent = resultado.mensagem;
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        errorDiv.textContent = 'Erro de comunicação com o servidor.';
        errorDiv.style.display = 'block';
    }
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

// FUNÇÃO ATUALIZADA E INTELIGENTE
async function atualizarStatusPedido(idPedido, novoStatus, fecharModal = false) {
    // SE a ação for cancelar, nós chamamos o script especialista
    if (novoStatus === 'cancelado') {
        // Mostra uma confirmação extra, pois esta ação é importante
        showCustomConfirm(`Tem certeza que deseja CANCELAR o pedido #${idPedido}? O estoque dos itens será devolvido.`, 'Confirmar Cancelamento', async () => {
            try {
                // Chama a API de cancelamento correta, que devolve o estoque e envia o bot
                const response = await fetch(`/pdv/admin/pedidos/cancelar_pedido.php?id=${idPedido}`); 
                
                // O script de cancelamento lida com o redirecionamento e a mensagem de sessão,
                // mas podemos recarregar os pedidos aqui para atualizar o Kanban na hora.
                showCustomAlert("Pedido cancelado. O Kanban será atualizado.", "Sucesso", "success");
                carregarPedidos();
                if (fecharModal) fecharModalDetalhes();

            } catch (error) {
                showCustomAlert('Erro de comunicação ao tentar cancelar o pedido.', "Erro", "error");
            }
        }, 'error'); // Usar o tipo 'error' para o popup de confirmação de cancelamento

    } 
    // SENÃO, para qualquer outra mudança de status, usamos a API genérica de sempre
    else {
        try {
            const response = await fetch('/pdv/public/api/atualizar_status_pedido.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id_pedido: idPedido, novo_status: novoStatus })
            });
            const resultado = await response.json();
            if (resultado.sucesso) {
                if (resultado.mensagem.includes("Nenhuma alteração")) {
                    showCustomAlert(resultado.mensagem, "Informação", "info");
                }
                carregarPedidos(); // Atualiza o Kanban
                if (fecharModal) fecharModalDetalhes();
            } else {
                showCustomAlert('Falha ao atualizar status: ' + resultado.mensagem, "Erro", "error");
            }
        } catch (error) {
            showCustomAlert('Erro de comunicação ao atualizar o status.', "Erro", "error");
        }
    }
}

function escapeHTML(str) {
    if (!str) return '';
    return str.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
