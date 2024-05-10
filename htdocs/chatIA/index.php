<?php

$servidor = 'localhost';
$usuario  = 'root';
$senha    = '';
$banco    = 'gemini';
$conn     = new mysqli($servidor, $usuario, $senha, $banco);

require "vendor/autoload.php";

use GeminiAPI\Client;
use GeminiAPI\Resources\Parts\TextPart;

//inicializando
$history = [];

function buscaMSG($telefone, $conn){
    $sql = "SELECT msg FROM historico WHERE telefone = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $telefone);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result;
}

function addHistorico($telefone, $msg, $conn){
    // Obtém o histórico atual para o número de telefone
    $historico_atual = buscaMSG($telefone, $conn);
    
    // Se já houver um histórico para o número de telefone
    if ($historico_atual) {
        // Converte o resultado do banco de dados em um array associativo
        $row = mysqli_fetch_assoc($historico_atual);
        
        // Recupera o histórico atual como array JSON
        $historico_array = json_decode($row['msg'], true);
        
        // Adiciona a nova mensagem ao histórico
        $historico_array[] = $msg;
        
        // Converte o novo histórico de volta para JSON
        $novo_historico = json_encode($historico_array);
        
        // Atualiza o histórico na tabela
        $sql = "UPDATE historico SET msg = ? WHERE telefone = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $novo_historico, $telefone);
        $stmt->execute();
        $stmt->close();
    } else {
        // Se não houver histórico para o número de telefone, cria um novo
        $historico_array = [$msg]; // Cria um array contendo a nova mensagem
        $novo_historico = json_encode($historico_array); // Converte o array para JSON
        
        // Insere um novo registro com o histórico na tabela
        $sql = "INSERT INTO historico (telefone, msg) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $telefone, $novo_historico);
        $stmt->execute();
        $stmt->close();
    }
}

//cadastra uma ves so o numero do clkiente
function numeroJaExistente($telefone, $conn) {
    $sql = "SELECT * FROM historico WHERE telefone = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $telefone);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// API KAY : AIzaSyALZmzCWhNVcEOCZ9Y55plP58tAdA2Jjl0

if(!$conn){

    echo("erro conn !");

}else{

    $telefone = $_GET['telefone'];
    $msg = $_GET['msg'];

    //TESTA CONEC COM O BD

    if (numeroJaExistente($telefone, $conn)) {

    }
    else{

        // Insere o número na tabela do banco de dados usando prepared statement
        $sql = "INSERT INTO historico (telefone) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $telefone);
        if ($stmt->execute()) {
            //echo "Número de telefone adicionado com sucesso.\n";
        } else {
            //echo "Erro ao adicionar número de telefone: " . $conn->error . "\n";
        }

    }

    $historico = buscaMSG($telefone, $conn);
    addHistorico($telefone, $msg, $conn);
    
    $client = new Client("AIzaSyALZmzCWhNVcEOCZ9Y55plP58tAdA2Jjl0");
    
    $response = $client->geminiPro()->generateContent(
        new TextPart($msg),
    );
    
    echo $response->text();

}

$conn->close();

?>
