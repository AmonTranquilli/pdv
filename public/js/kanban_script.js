document.addEventListener('DOMContentLoaded', function() {
    carregarPedidos(); 
    setInterval(carregarPedidos, 10000); 

    // Botão Finalizar Dia
    const btnFinalizarDia = document.getElementById('btnFinalizarDia');
    if (btnFinalizarDia) {
        btnFinalizarDia.addEventListener('click', finalizarDia);
    }

    // Modal de Detalhes
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

    // NOVO: Event listener para o botão de confirmação no modal do entregador
    const btnConfirmarFinalizacao = document.getElementById('btnConfirmarFinalizacao');
    if (btnConfirmarFinalizacao) {
        btnConfirmarFinalizacao.addEventListener('click', processarFinalizacaoEntrega);
    }
    
    // NOVO: Permite pressionar Enter no campo de código para confirmar
    const inputCodigoEntregador = document.getElementById('codigoEntregadorInput');
    if (inputCodigoEntregador) {
        inputCodigoEntregador.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault(); // Impede o comportamento padrão do Enter (ex: submeter um formulário)
                processarFinalizacaoEntrega();
            }
        });
    }
});

let pedidoIdAtualModal = null; 

// --- FUNÇÕES DE NOTIFICAÇÃO (sem alterações) ---
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
    modalActions.querySelector('button').onclick = () => modal.classList.remove('ativo');
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
    modalActions.innerHTML = `<button id="confirmBtnYes" class="btn-aceitar">Sim</button><button id="confirmBtnNo" class="btn-secondary">Não</button>`;
    modal.classList.add('ativo');
    const hideModal = () => modal.classList.remove('ativo');
    document.getElementById('confirmBtnYes').onclick = () => { hideModal(); onConfirmCallback(); };
    document.getElementById('confirmBtnNo').onclick = hideModal;
}

// --- FUNÇÕES PRINCIPAIS (com alterações) ---

async function finalizarDia() {
    showCustomConfirm("Tem certeza que deseja tentar finalizar o turno?", "Finalizar Turno", async () => {
        try {
            const response = await fetch('/pdv/public/api/finalizar_dia.php');
            const resultado = await response.json();
            if (!response.ok) throw new Error(resultado.mensagem || 'Erro desconhecido.');
            const type = resultado.sucesso ? 'success' : 'error';
            showCustomAlert(resultado.mensagem, "Resultado do Fecho", type);
            if (resultado.sucesso) carregarPedidos();
        } catch (error) {
            showCustomAlert("Ocorreu um erro: " + error.message, "Erro de Comunicação", "error");
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
            if (colunaDestino) {
                colunaDestino.appendChild(card);
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
    let itensHtml = '<p><em>Nenhum item.</em></p>';
    if (pedido.itens_resumo) {
        itensHtml = '<ul>' + pedido.itens_resumo.split('; ').map(item => `<li>${escapeHTML(item)}</li>`).join('') + '</ul>';
    }
    card.innerHTML = `<h4>Pedido: #${pedido.id}</h4><p class="card-data">${dataFormatada}</p><p class="card-cliente">${escapeHTML(pedido.nome_cliente)}</p><p class="card-total">R$ ${parseFloat(pedido.total_pedido).toFixed(2).replace('.', ',')}</p><div class="card-itens"><strong>Itens:</strong>${itensHtml}</div><div class="card-actions">${gerarBotoesAcao(pedido.status, pedido.id)}</div>`;
    return card;
}

function gerarBotoesAcao(statusAtual, idPedido) {
    if (statusAtual === 'pendente') return `<button onclick="abrirModalDetalhes(${idPedido})" class="btn-detalhes">Detalhes</button>`;
    if (statusAtual === 'preparando') return `<button onclick="showCustomConfirm('Despachar o pedido #${idPedido} para entrega?', 'Confirmar Saída', () => atualizarStatusPedido(${idPedido}, 'em_entrega'))" title="Mover para Em Entrega">P/ Entrega</button><button onclick="abrirModalDetalhes(${idPedido})" class="btn-detalhes">Detalhes</button>`;
    if (statusAtual === 'em_entrega') {
        // ALTERADO: Agora chama o modal do entregador
        return `<button onclick="abrirModalEntregador(${idPedido})" title="Finalizar Entrega">Finalizar</button><button onclick="abrirModalDetalhes(${idPedido})" class="btn-detalhes">Detalhes</button>`;
    }
    return '';
}

// --- NOVAS FUNÇÕES PARA O MODAL DO ENTREGADOR ---

function abrirModalEntregador(idPedido) {
    const modal = document.getElementById('modalEntregador');
    if (!modal) return;
    pedidoIdAtualModal = idPedido;
    document.getElementById('entregadorModalPedidoId').textContent = `#${idPedido}`;
    const inputCodigo = document.getElementById('codigoEntregadorInput');
    inputCodigo.value = '';
    document.getElementById('entregadorError').style.display = 'none';
    modal.classList.add('ativo');
    inputCodigo.focus();
}

function fecharModalEntregador() {
    const modal = document.getElementById('modalEntregador');
    if (modal) modal.classList.remove('ativo');
    pedidoIdAtualModal = null;
}

async function processarFinalizacaoEntrega() {
    if (!pedidoIdAtualModal) return;
    const codigoEntregador = document.getElementById('codigoEntregadorInput').value.trim().toUpperCase();
    const errorDiv = document.getElementById('entregadorError');
    if (codigoEntregador === '') {
        errorDiv.textContent = 'Por favor, insira o código do entregador.';
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
                codigo_entregador: codigoEntregador
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

// --- FUNÇÕES DE MODAL DE DETALHES E ATUALIZAÇÃO DE STATUS (com pequenas alterações) ---

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
        let endereco = `${escapeHTML(detalhes.endereco_entrega || '')}, ${escapeHTML(detalhes.numero_entrega || 'S/N')}`;
        let itensHtml = '<li>Nenhum item.</li>';
        if (detalhes.itens && detalhes.itens.length > 0) {
            itensHtml = detalhes.itens.map(item => `<li>${escapeHTML(item.quantidade)}x ${escapeHTML(item.nome_produto)}</li>`).join('');
        }
        document.getElementById('modalPedidoId').textContent = `#${idPedido}`;
        modalBody.innerHTML = `<p><strong>Cliente:</strong> ${escapeHTML(detalhes.nome_cliente)}</p><p><strong>Endereço:</strong> ${endereco}</p><ul>${itensHtml}</ul>`;

        const btnAceitar = document.getElementById('modalBtnAceitar');
        const btnCancelar = document.getElementById('modalBtnCancelar');
        if(btnAceitar && btnCancelar) {
            btnAceitar.style.display = detalhes.status === 'pendente' ? 'inline-block' : 'none';
            btnCancelar.style.display = ['pendente', 'preparando', 'em_entrega'].includes(detalhes.status) ? 'inline-block' : 'none';
        }
    } catch (error) {
        modalBody.innerHTML = `<p style="color: red;">${error.message}</p>`;
    }
}

function fecharModalDetalhes() {
    const modal = document.getElementById('modalDetalhesPedido');
    if (modal) modal.classList.remove('ativo');
    pedidoIdAtualModal = null; 
}

async function atualizarStatusPedido(idPedido, novoStatus, fecharModal = false) {
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
            carregarPedidos(); 
            if (fecharModal) fecharModalDetalhes();
        } else {
            showCustomAlert('Falha ao atualizar status: ' + resultado.mensagem, "Erro", "error");
        }
    } catch (error) {
        showCustomAlert('Erro de comunicação ao atualizar o status.', "Erro", "error");
    }
}

function escapeHTML(str) {
    if (!str) return '';
    return str.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
