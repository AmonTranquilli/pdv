<?php
// Arquivo: /admin/operacional/inventario.php (VERSÃO SUPER COMPLETA)
session_start();
$page_title = 'Inventário de Estoque';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: /pdv/admin/login.php");
    exit;
}

require_once '../../includes/conexao.php';

$itens_estoque = [];
try {
    $sql = "
        SELECT 
            id, 
            nome, 
            tipo,
            (estoque_inicial + COALESCE(movimentacao_total, 0)) as estoque_atual
        FROM (
            SELECT 
                p.id, p.nome, 'Produto' as tipo, p.estoque as estoque_inicial,
                (SELECT SUM(quantidade) FROM movimentacoes_estoque WHERE id_produto = p.id) as movimentacao_total
            FROM produtos p WHERE p.controla_estoque = 1
            UNION ALL
            SELECT 
                a.id, a.nome, 'Adicional' as tipo, a.estoque as estoque_inicial,
                (SELECT SUM(quantidade) FROM movimentacoes_estoque WHERE id_produto = a.id) as movimentacao_total
            FROM adicionais a WHERE a.controla_estoque = 1
        ) as inventario_unificado
        ORDER BY nome ASC";

    $result = $conn->query($sql);
    if ($result) {
        $itens_estoque = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erro ao buscar inventário: " . $e->getMessage());
}
$conn->close();

ob_start();
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .header-actions {
        display: flex;
        gap: 10px;
    }

    .btn-new,
    .btn-secondary {
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        cursor: pointer;
    }

    .btn-new {
        background-color: #28a745;
        color: white;
    }

    .btn-secondary {
        background-color: #ffc107;
        color: #333;
    }

    .table-container {
        background-color: #fff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        overflow-x: auto;
    }

    .styled-table {
        width: 100%;
        border-collapse: collapse;
    }

    .styled-table thead th {
        background-color: #f8f9fa;
        text-align: left;
        padding: 12px 15px;
    }

    .styled-table tbody td {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
    }

    .stock-level-badge {
        padding: 5px 12px;
        border-radius: 20px;
        color: white;
        font-weight: bold;
        font-size: 0.9em;
    }

    .badge-ok {
        background-color: #28a745;
    }

    .badge-low {
        background-color: #ffc107;
        color: #333;
    }

    .badge-critical {
        background-color: #dc3545;
    }

    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.6);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 1050;
    }

    .modal-content {
        background-color: #fff;
        padding: 30px;
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        position: relative;
    }

    .modal-close-btn {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 2em;
        cursor: pointer;
        color: #aaa;
        border: none;
        background: none;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 1em;
        box-sizing: border-box;
    }

    .btn-success {
        background-color: var(--primary-color, #FF5722);
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
    }
</style>

<div class="page-header">
    <h1><i class="fas fa-boxes"></i> Inventário</h1>
    <div class="header-actions">
        <button type="button" class="btn-secondary" id="btnAjusteEstoque"><i class="fas fa-edit"></i> Fazer Ajuste Manual</button>
        <a href="/pdv/admin/operacional/entradas.php" class="btn-new"><i class="fas fa-plus"></i> Registrar Entrada de Compra</a>
    </div>
</div>

<div class="table-container">
    <table class="styled-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Tipo</th>
                <th style="text-align: center;">Estoque Atual</th>
                <th style="text-align: center;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens_estoque as $item):
                $estoque = (int)$item['estoque_atual'];
                $status_class = 'badge-ok';
                $status_text = 'OK';
                if ($estoque <= 1) {
                    $status_class = 'badge-critical';
                    $status_text = 'Crítico';
                } elseif ($estoque <= 5) {
                    $status_class = 'badge-low';
                    $status_text = 'Atenção';
                }
            ?>
                <tr>
                    <td><?= htmlspecialchars($item['nome']) ?></td>
                    <td><?= htmlspecialchars($item['tipo']) ?></td>
                    <td style="text-align: center; font-weight: bold; font-size: 1.2em;"><?= $estoque ?></td>
                    <td style="text-align: center;"><span class="stock-level-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="modalAjuste" class="modal-overlay">
    <div class="modal-content">
        <button type="button" class="modal-close-btn">&times;</button>
        <h2>Ajuste Manual de Estoque</h2>
        <form id="formAjuste">
            <div class="form-group">
                <label for="item_ajuste">Produto/Item</label>
                <select id="item_ajuste" required>
                    <option value="">-- Selecione um item --</option>
                    <?php foreach ($itens_estoque as $item): ?>
                        <option value="<?= $item['id'] ?>|<?= $item['tipo'] ?>"><?= htmlspecialchars($item['nome']) ?> (<?= $item['tipo'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="quantidade_ajuste">Quantidade do Ajuste</label>
                <input type="number" id="quantidade_ajuste" required placeholder="Ex: -2 para perda, 10 para entrada">
                <small>Use valores negativos para saídas/perdas e positivos para entradas.</small>
            </div>
            <div class="form-group">
                <label for="motivo_ajuste">Motivo do Ajuste (Obrigatório)</label>
                <textarea id="motivo_ajuste" rows="3" required placeholder="Ex: Perda por produto vencido, contagem..."></textarea>
            </div>
            <button type="submit" class="btn-success">Salvar Ajuste</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnAjusteEstoque = document.getElementById('btnAjusteEstoque');
        const modalAjuste = document.getElementById('modalAjuste');
        const formAjuste = document.getElementById('formAjuste');
        const btnCloseModal = modalAjuste?.querySelector('.modal-close-btn');

        btnAjusteEstoque?.addEventListener('click', () => {
            if (formAjuste) formAjuste.reset();
            if (modalAjuste) modalAjuste.style.display = 'flex';
        });
        btnCloseModal?.addEventListener('click', () => {
            if (modalAjuste) modalAjuste.style.display = 'none';
        });
        modalAjuste?.addEventListener('click', (e) => {
            if (e.target === modalAjuste) modalAjuste.style.display = 'none';
        });

        formAjuste?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const itemSelect = document.getElementById('item_ajuste');
            const quantidadeInput = document.getElementById('quantidade_ajuste');
            const motivoInput = document.getElementById('motivo_ajuste');

            if (!itemSelect.value || !quantidadeInput.value || !motivoInput.value.trim()) {
                alert('Todos os campos são obrigatórios e a quantidade não pode ser zero.');
                return;
            }

            const [id_item, tipo_item] = itemSelect.value.split('|');

            const dados = {
                id_item: id_item,
                tipo_item: tipo_item,
                quantidade: quantidadeInput.value,
                motivo: motivoInput.value
            };

            try {
                const response = await fetch('/pdv/public/api/ajustar_estoque.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(dados)
                });
                const result = await response.json();

                alert(result.mensagem);
                if (result.sucesso) {
                    window.location.reload();
                }
            } catch (error) {
                alert('Erro de conexão ao salvar o ajuste.');
                console.error('Erro:', error);
            }
        });
    });
</script>

<?php
$page_content = ob_get_clean();
include '../template_admin.php';
?>