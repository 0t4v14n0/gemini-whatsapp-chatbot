<?php

$servidor = 'localhost';
$usuario  = 'root';
$senha    = '';
$banco    = 'gemini';
$conn     = new mysqli($servidor, $usuario, $senha, $banco);

require "vendor/autoload.php";

use GeminiAPI\Client;
use GeminiAPI\SafetySetting;
use GeminiAPI\GenerationConfig;
use GeminiAPI\Enums\HarmCategory;
use GeminiAPI\Resources\Parts\TextPart;
use GeminiAPI\Resources\GenerationRequest;
use GeminiAPI\Enums\HarmBlockThreshold;

//inicializando
$history = [];

function limparDados($telefone, $conn) {
  $sql = "UPDATE historico SET msg = NULL WHERE telefone = '$telefone'";
  $conn->query($sql);
  $conn->close();
}

//formatar o historico
function getHistoricoFormatado($telefone, $conn) {
    $historico_result = buscaMSG($telefone, $conn);
    $historico_formatado = "";
  
    while ($row = $historico_result->fetch_assoc()) {
      $mensagens = json_decode($row['msg'], true);
      if($mensagens != null){
        foreach ($mensagens as $mensagem) {
          $historico_formatado .= "Usuário: " . $mensagem . "\n";
        }
      }
    }
  
    return $historico_formatado;
  }

//busca o historico de msg
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


if(!$conn){

    echo("erro conn !");

}else{

    $telefone = $_GET['telefone'];
    $msg = $_GET['msg'];

    //LIMPA O HISTORICO
    if($msg == "Regenerar" || $msg == "regenerar"){

      limparDados($telefone, $conn);
      die;

    }

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

    $historico = getHistoricoFormatado($telefone, $conn);
    addHistorico($telefone, $msg, $conn);
  
    $client = new Client("SUA API");

    //evitar discurso de odio
    $safetySetting = new SafetySetting(
      HarmCategory::HARM_CATEGORY_HATE_SPEECH,
      HarmBlockThreshold::BLOCK_LOW_AND_ABOVE,
    );

    //configurcoes de geracao
    $generationConfig = (new GenerationConfig())
        ->withCandidateCount(1)
        ->withMaxOutputTokens(40)
        ->withTemperature(0.5)
        ->withTopK(40)
        ->withTopP(0.6)
        ->withStopSequences(['STOP']);

    // Contexto para o Gemini Pro
    $texto_contextualizado = $historico . "Usuário: " . $msg . "\nIA: ";

    try {

      //gera a resposta
      $response = $client->geminiPro()
      ->withAddedSafetySetting($safetySetting)
      //->withGenerationConfig($generationConfig)
      ->generateContent(
          new TextPart($texto_contextualizado)
      );
      
      // Removendo "IA: " da resposta
      $resposta_final = str_replace("IA: ", "", $response->text());
      
      //mostra a resposta
      echo $resposta_final;
      
      // Adicionando a resposta da IA ao histórico
      addHistorico($telefone, $resposta_final, $conn);
      
    } catch (Exception $e) {
      die("Erro na chamada da API: " . $e->getMessage());
    }

}

$conn->close();

?>
