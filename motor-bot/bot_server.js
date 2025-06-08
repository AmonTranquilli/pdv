// Importa as bibliotecas
const express = require('express');
const axios = require('axios');

// Cria o servidor
const app = express();
const PORTA = 3000;

// SUAS CREDENCIAIS
const WHATSAPP_TOKEN = 'EAAJtQhSdnSMBO3tVN48DPS15QKbEWvMu3FTwnrEfFZBRr8SMQa9Or4dZC0xidAZCDa9XBvWHdUDVbZCJJlaUhdP2IUu82fhBSIewl4DJxZBMoewZB2ZB1vKQ66ZAuEufl6476ZADaI6aFVWSzDZAGiQwGQSnck3yjknxvS7KI9iEZBt5ZCk9LDJhAn2HKkpTkZBQxF6yYwhefZBVEJCAM7OE2EwgZBqQZAjA3j4AuzX2LYNdDXccfuiI4r2yjaYp'; // <- Verifique se seu token ainda está válido ou gere um novo
const ID_NUMERO_TELEFONE = '686154784576636';

app.use(express.json());

// Rota para receber a notificação do PHP
app.post('/enviar-notificacao', async (req, res) => {
  console.log('Recebida requisição do PHP:', req.body);

  // Pega os dados dinâmicos vindos do PHP
  const { telefone_destino, mensagem } = req.body;

  if (!telefone_destino || !mensagem) {
    return res.status(400).json({ status: 'erro', mensagem: 'Dados incompletos recebidos do PHP.' });
  }
  
  const url = `https://graph.facebook.com/v19.0/${ID_NUMERO_TELEFONE}/messages`;

// TRECHO NOVO E SIMPLIFICADO em bot_server.js

const dados_mensagem = {
    messaging_product: 'whatsapp',
    to: telefone_destino,
    type: 'template',
    template: {
      // Coloque aqui o nome do seu template final com 6 variáveis no corpo e 1 botão
      name: 'confirmacao_com_botao', 
      language: { code: 'pt_BR' },
      components: [
        {
          // Componente 1: O CORPO da mensagem
          type: 'body',
          parameters: [
            { type: 'text', text: mensagem.nome_cliente },      // Para {{1}} do corpo
            { type: 'text', text: mensagem.id_pedido },         // Para {{2}} do corpo
            { type: 'text', text: mensagem.forma_pagamento },   // Para {{3}} do corpo
            { type: 'text', text: mensagem.taxa_entrega },      // Para {{4}} do corpo
            { type: 'text', text: mensagem.endereco_completo },  // Para {{5}} do corpo
            { type: 'text', text: mensagem.total_pedido }       // Para {{6}} do corpo
          ]
        },
        {
          // Componente 2: O BOTÃO
          type: 'button',
          sub_type: 'url',
          index: '0', // O primeiro botão
          parameters: [
            {
              type: 'text',
              // Este é o valor que substituirá o {{1}} no LINK do botão
              text: mensagem.id_pedido 
            }
          ]
        }
      ]
    }
  };

  try {
    const resposta_whatsapp = await axios.post(url, dados_mensagem, {
      headers: {
        'Authorization': `Bearer ${WHATSAPP_TOKEN}`,
        'Content-Type': 'application/json'
      }
    });
    console.log('MENSAGEM ENVIADA COM SUCESSO!', resposta_whatsapp.data);
    res.status(200).json({ status: 'sucesso', mensagem: 'Notificação enviada com sucesso!' });
  } catch (erro) {
    console.error('ERRO FINAL:', erro.response ? erro.response.data : erro.message);
    res.status(500).json({ status: 'erro', mensagem: 'Falha no envio final.', detalhe: erro.response.data });
  }
});

app.listen(PORTA, () => {
  console.log(`Servidor do Bot pronto na porta ${PORTA}!`);
});