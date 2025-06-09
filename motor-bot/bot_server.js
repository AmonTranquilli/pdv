// Importa as bibliotecas
const express = require('express');
const axios = require('axios');

// Cria o servidor
const app = express();
const PORTA = 3000;

// SUAS CREDENCIAIS
const WHATSAPP_TOKEN = 'EAAJtQhSdnSMBO6IIQfMdzonG1J4nTZCcFZBA3gvrDCTWSx7nicds8PMl3p7dISJClhfJBPOGZAE37iUusfsnLsdC9mxCAEBv6s4b7Yi2nEFZCP4ZCSKRFC1VMw0N1eEZBOLHdfI9ZCCn4kGqoRN6laj5nDzFsrbDBZBNCiez5FDl0HxFoimompg7MwvRZA3M1gjdJLohRWuFtMw37qaOB2V59omKeEELWP07XVfCKbdw4tYnlGXkhjx0ZD'; // <- Coloque seu token aqui
const ID_NUMERO_TELEFONE = '686154784576636'; // <- Coloque seu ID aqui

app.use(express.json());

// Rota para receber a notificação do PHP
// ROTA COMPLETA E CORRIGIDA em bot_server.js
app.post('/enviar-notificacao', async (req, res) => {
  console.log('Recebida requisição do PHP:', req.body);

  const { telefone_destino, mensagem } = req.body;

  if (!telefone_destino || !mensagem || !mensagem.template_name) {
    return res.status(400).json({ status: 'erro', mensagem: 'Dados incompletos.' });
  }

  const url = `https://graph.facebook.com/v19.0/${ID_NUMERO_TELEFONE}/messages`;
  let dados_template;

  if (mensagem.template_name === 'confirmacao_com_botao') {
    dados_template = {
      name: mensagem.template_name,
      language: { code: 'pt_BR' },
      components: [
        { type: 'body', parameters: [
            { type: 'text', text: mensagem.nome_cliente }, { type: 'text', text: mensagem.id_pedido },
            { type: 'text', text: mensagem.forma_pagamento }, { type: 'text', text: mensagem.taxa_entrega },
            { type: 'text', text: mensagem.endereco_completo }, { type: 'text', text: mensagem.total_pedido }
        ]},
        { type: 'button', sub_type: 'url', index: '0', parameters: [{ type: 'text', text: mensagem.id_pedido }] }
      ]
    };
  } else if (mensagem.template_name === 'pedido_em_preparacao') {
    dados_template = {
      name: 'pedido_em_preparacao',
      language: { code: 'pt_BR' },
      components: [{ type: 'body', parameters: [{ type: 'text', text: mensagem.nome_cliente }, { type: 'text', text: mensagem.id_pedido }] }]
    };
  } else if (mensagem.template_name === 'pedido_a_caminho') {
    dados_template = {
      name: 'pedido_a_caminho',
      language: { code: 'pt_BR' },
      components: [{ type: 'body', parameters: [{ type: 'text', text: mensagem.id_pedido }] }]
    };
  } else if (mensagem.template_name === 'pedido_entregue_agradecimento') {
    dados_template = {
      name: 'pedido_entregue_agradecimento',
      language: { code: 'pt_BR' },
      components: [{ type: 'body', parameters: [{ type: 'text', text: mensagem.nome_cliente }] }]
    };
  } else if (mensagem.template_name === 'pedido_cancelado') {
    dados_template = {
      name: 'pedido_cancelado',
      language: { code: 'pt_BR' },
      components: [{ type: 'body', parameters: [{ type: 'text', text: mensagem.nome_cliente }, { type: 'text', text: mensagem.id_pedido }] }]
    };
  } else {
    return res.status(400).json({ status: 'erro', mensagem: `Template desconhecido: ${mensagem.template_name}` });
  }

  const dados_mensagem_final = {
    messaging_product: 'whatsapp',
    to: telefone_destino,
    type: 'template',
    template: dados_template
  };

  try {
    const resposta_whatsapp = await axios.post(url, dados_mensagem_final, {
      headers: { 'Authorization': `Bearer ${WHATSAPP_TOKEN}`, 'Content-Type': 'application/json' }
    });
    console.log('MENSAGEM ENVIADA COM SUCESSO!', resposta_whatsapp.data);
    res.status(200).json({ status: 'sucesso', mensagem: 'Notificação enviada com sucesso!' });
  } catch (erro) {
    console.error('ERRO FINAL:', erro.response ? erro.response.data.error : erro.message);
    res.status(500).json({ status: 'erro', mensagem: 'Falha no envio final.', detalhe: erro.response ? erro.response.data.error : null });
  }
});

app.listen(PORTA, () => {
  console.log(`Servidor do Bot pronto na porta ${PORTA}!`);
});