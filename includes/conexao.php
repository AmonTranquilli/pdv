<?php

// Definir as constantes de conexão com o banco de dados
define('DB_SERVER', '127.0.0.1'); // Geralmente 'localhost' para desenvolvimento local
define('DB_USERNAME', 'root');     // Usuário padrão do MySQL no XAMPP é 'root'
define('DB_PASSWORD', '');         // Senha padrão do MySQL no XAMPP é vazia
define('DB_NAME', 'pdv_cardapio'); // Nome do banco de dados que criamos

// Criar a conexão com o banco de dados
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar a conexão
if ($conn->connect_error) {
    die("Erro de conexão com o banco de dados: " . $conn->connect_error);
}

// Definir o charset para UTF-8 para evitar problemas com acentos e caracteres especiais
$conn->set_charset("utf8");

// Opcional: Você pode adicionar uma linha para testar se a conexão foi bem-sucedida
 //echo "Conexão com o banco de dados estabelecida com sucesso!";

?>