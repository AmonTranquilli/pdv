<?php
// Arquivo: /admin/financeiro/funcionarios.php (Versão Final Completa)
session_start();

$page_title = 'Gestão de Funcionários';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: /pdv/admin/login.php");
    exit;
}

require_once '../../includes/conexao.php';

// --- LÓGICA PARA BUSCAR OS FUNCIONÁRIOS ---
$funcionarios = [];
try {
    $sql = "SELECT id, nome, cargo, valor_diaria FROM funcionarios WHERE ativo = 1 ORDER BY nome ASC";
    $result = $conn->query($sql);
    if ($result) {
        $funcionarios = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erro ao buscar funcionários: " . $e->getMessage());
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

    .btn-new {
        background-color: var(--primary-color);
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
    }

    .table-container {
        background-color: #fff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .styled-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95em;
    }

    .styled-table thead th {
        background-color: #f8f9fa;
        color: #6c757d;
        text-align: left;
        padding: 12px 15px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .styled-table tbody td {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
    }

    .styled-table tbody tr:last-of-type td {
        border-bottom: none;
    }

    .actions-cell {
        display: flex;
        gap: 8px;
    }

    .actions-cell .btn-action {
        padding: 6px 10px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 0.85em;
        font-weight: 600;
        border: none;
        cursor: pointer;
    }

    .btn-action.edit {
        background-color: #ffc107;
        color: #333;
    }

    .btn-action.register-absence {
        background-color: #17a2b8;
        color: white;
    }

    .btn-action.desativar {
        background-color: #dc3545;
        color: white;
    }

    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
        text-align: center;
        font-weight: 600;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
    }

    /* Estilos do Modal */
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
    }

    .modal-actions {
        margin-top: 1.5rem;
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
    }

    .btn-success {
        background-color: var(--secondary-color, #28a745);
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
    }
</style>

<div class="page-header">
    <h1><i class="fas fa-users-cog"></i> Gestão de Funcionários</h1>
    <a href="/pdv/admin/financeiro/cadastrar_funcionario.php" class="btn-new"><i class="fas fa-plus"></i> Cadastrar Novo</a>
</div>

<?php
if (isset($_SESSION['mensagem_sucesso'])) {
    echo '<div class="alert alert-success">' . $_SESSION['mensagem_sucesso'] . '</div>';
    unset($_SESSION['mensagem_sucesso']);
}
?>

<div class="table-container">
    <table class="styled-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Cargo</th>
                <th>Valor da Diária</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($funcionarios)): ?>
                <?php foreach ($funcionarios as $funcionario): ?>
                    <tr>
                        <td><?= htmlspecialchars($funcionario['nome']) ?></td>
                        <td><?= htmlspecialchars($funcionario['cargo']) ?></td>
                        <td>R$ <?= number_format($funcionario['valor_diaria'], 2, ',', '.') ?></td>
                        <td class="actions-cell">
                            <a href="editar_funcionario.php?id=<?= $funcionario['id'] ?>" class="btn-action edit" title="Editar Cadastro"><i class="fas fa-edit"></i></a>
                            <button type="button" class="btn-action register-absence" title="Registrar Falta" data-id="<?= $funcionario['id'] ?>" data-nome="<?= htmlspecialchars($funcionario['nome']) ?>"><i class="fas fa-calendar-times"></i></button>
                            <button type="button" class="btn-action desativar" title="Desativar Funcionário" data-id="<?= $funcionario['id'] ?>" data-nome="<?= htmlspecialchars($funcionario['nome']) ?>"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px;">Nenhum funcionário cadastrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="modalFalta" class="modal-overlay">
    <div class="modal-content">
        <span class="modal-close-btn">&times;</span>
        <h2>Registrar Falta</h2>
        <p>Funcionário: <strong id="modalFuncionarioNome"></strong></p>
        <form id="formFalta">
            <input type="hidden" id="modalFuncionarioId" name="id_funcionario">
            <div class="form-group">
                <label for="data_falta">Data da Falta:</label>
                <input type="date" id="data_falta" name="data_falta" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label for="tipo_falta">Tipo de Falta:</label>
                <select id="tipo_falta" name="tipo_falta">
                    <option value="FALTA_INJUSTIFICADA">Falta Injustificada (desconta do pagamento)</option>
                    <option value="FALTA_JUSTIFICADA">Falta Justificada (não desconta)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="observacao_falta">Observação (Opcional):</label>
                <textarea id="observacao_falta" name="observacao" rows="3"></textarea>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn-success">Salvar Registro</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- LÓGICA UNIFICADA PARA OS BOTÕES DE AÇÃO ---

        // Elementos do Modal de Falta
        const modalFalta = document.getElementById('modalFalta');
        const modalFuncionarioNome = document.getElementById('modalFuncionarioNome');
        const modalFuncionarioId = document.getElementById('modalFuncionarioId');
        const formFalta = document.getElementById('formFalta');
        const btnCloseModal = modalFalta.querySelector('.modal-close-btn');

        // Adiciona o evento de clique na tabela inteira (delegação de eventos)
        document.querySelector('.styled-table tbody').addEventListener('click', function(e) {
            const target = e.target.closest('.btn-action');
            if (!target) return;

            const funcionarioId = target.getAttribute('data-id');
            const funcionarioNome = target.getAttribute('data-nome');

            // Lógica para DESATIVAR
            if (target.classList.contains('desativar')) {
                if (confirm(`Tem certeza que deseja desativar o funcionário "${funcionarioNome}"?`)) {
                    const formData = new FormData();
                    formData.append('id', funcionarioId);
                    fetch('desativar_funcionario.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            alert(data.mensagem);
                            if (data.sucesso) {
                                window.location.reload();
                            }
                        })
                        .catch(error => alert('Erro de conexão.'));
                }
            }

            // Lógica para REGISTRAR FALTA
            if (target.classList.contains('register-absence')) {
                modalFuncionarioNome.textContent = funcionarioNome;
                modalFuncionarioId.value = funcionarioId;
                modalFalta.style.display = 'flex';
            }
        });

        // Eventos para fechar o modal de falta
        btnCloseModal.addEventListener('click', () => {
            modalFalta.style.display = 'none';
        });
        modalFalta.addEventListener('click', (e) => {
            if (e.target === modalFalta) {
                modalFalta.style.display = 'none';
            }
        });

        // Evento para salvar a falta ao submeter o formulário
        formFalta.addEventListener('submit', async function(e) {
            e.preventDefault();

            const dadosFalta = {
                id_funcionario: document.getElementById('modalFuncionarioId').value,
                data_falta: document.getElementById('data_falta').value,
                tipo_falta: document.getElementById('tipo_falta').value,
                observacao: document.getElementById('observacao_falta').value
            };

            try {
                const response = await fetch('registrar_falta.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(dadosFalta)
                });
                const result = await response.json();
                alert(result.mensagem); // Mostra sucesso ou erro
                if (result.sucesso) {
                    modalFalta.style.display = 'none';
                }
            } catch (error) {
                alert('Erro de conexão. Tente novamente.');
            }
        });
    });
</script>

<?php
$page_content = ob_get_clean();
include '../template_admin.php';
?>