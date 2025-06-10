<?php
// ... seu código PHP do topo (sem alterações) ...
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    </head>
<body>
    <script>
    const produtoData = <?= json_encode($produto); ?>;
    document.addEventListener('DOMContentLoaded', () => {
        // ... (funções renderizarOpcoes, calcularPrecoTotal, validarOpcoes - sem alterações) ...

        document.getElementById('confirmar-adicao-carrinho').addEventListener('click', async () => {
            if (!validarOpcoes()) { return; }

            let obs = document.getElementById('observacoes').value.trim();
            let todasAsOpcoes = [];
            let precoOpcionais = 0;

            // Calcula o preço das opções e monta a descrição
            document.querySelectorAll('.adicional-checkbox:checked').forEach(el => {
                todasAsOpcoes.push(el.dataset.nome);
                precoOpcionais += parseFloat(el.dataset.preco);
            });
            document.querySelectorAll('.opcao-item-quant').forEach(el => {
                const quant = parseInt(el.querySelector('.quant-item').textContent);
                if (quant > 0) {
                    todasAsOpcoes.push(`${quant}x ${el.dataset.nome}`);
                    precoOpcionais += parseFloat(el.dataset.preco) * quant;
                }
            });
            document.querySelectorAll('.grupo-opcao-input:checked').forEach(el => {
                todasAsOpcoes.push(el.dataset.nome);
                precoOpcionais += parseFloat(el.dataset.preco);
            });

            if (todasAsOpcoes.length > 0) {
                obs = (obs ? obs + '; ' : '') + todasAsOpcoes.join(', ');
            }
            
            // Calcula o preço final unitário
            const precoFinalUnitario = parseFloat(produtoData.preco) + precoOpcionais;

            // Monta o objeto para a API com o preço final calculado
            const itemParaApi = {
                action: 'add_item',
                id: produtoData.id,
                quantidade: parseInt(document.getElementById('quantidade-geral').textContent),
                obs: obs,
                precoFinal: precoFinalUnitario // **Enviando o preço final correto!**
            };

            // Envia para a API
            try {
                const response = await fetch('public/api/carrinho_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(itemParaApi)
                });
                const result = await response.json();
                if (result.success) {
                    alert(`${produtoData.nome} foi adicionado ao carrinho!`);
                    window.location.href = 'index.php';
                } else {
                    alert('Erro ao adicionar item: ' + (result.message || 'Tente novamente.'));
                }
            } catch (error) {
                alert('Erro de conexão ao adicionar o item.');
            }
        });

        // ... (resto do seu script de inicialização) ...
    });
    </script>
</body>
</html>