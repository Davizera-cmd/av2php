<?php

$servidor = "localhost"; 
$usuario_bd = "root";    
$senha_bd = "";         
$nome_bd = "meu_ecommerce_db"; 


$conn = new mysqli($servidor, $usuario_bd, $senha_bd, $nome_bd);


if ($conn->connect_error) {
    
    error_log("Falha na conexão com o banco de dados: " . $conn->connect_error);

    die("Desculpe, estamos enfrentando problemas técnicos. Tente novamente mais tarde.");
}


if (!$conn->set_charset("utf8mb4")) {
    error_log("Erro ao definir o charset UTF-8: " . $conn->error);

}

?>
