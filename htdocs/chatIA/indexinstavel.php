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
$status = 10;
$apiKey = 'AIzaSyALZmzCWhNVcEOCZ9Y55plP58tAdA2Jjl0';
$projectId = 'chatbot-418002';

function cosineSimilarity($embedding1, $embedding2) {
  $dotProduct = 0;
  $magnitude1 = 0;
  $magnitude2 = 0;

  for ($i = 0; $i < count($embedding1); $i++) {
      $dotProduct += $embedding1[$i] * $embedding2[$i];
      $magnitude1 += $embedding1[$i] * $embedding1[$i];
      $magnitude2 += $embedding2[$i] * $embedding2[$i];
  }

  $magnitude1 = sqrt($magnitude1);
  $magnitude2 = sqrt($magnitude2);

  return $dotProduct / ($magnitude1 * $magnitude2);
}

function calcularSimilaridade($texto1, $texto2, $apiKey, $projectId) {
  $url = 'https://us-central1-aiplatform.googleapis.com/v1/projects/' . $projectId . '/locations/us-central1/publishers/google/models/gemini:predict';
  $data = [
      'instances' => [
          ['content' => $texto1],
          ['content' => $texto2],
      ],
  ];

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $apiKey,
  ]);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

  $response = curl_exec($ch);
  curl_close($ch);

  $responseData = json_decode($response, true);

  $embedding1 = $responseData['predictions'][0]['embedding'];
  $embedding2 = $responseData['predictions'][1]['embedding'];

  return cosineSimilarity($embedding1, $embedding2);
}

function setTexto($telefone,$texto, $msg, $conn){
  $texto = mysqli_real_escape_string($conn, $texto);
  $msg = mysqli_real_escape_string($conn, $msg);

  // Prepare the SQL statement
  $sql = "UPDATE historico SET `$texto` = '$msg' WHERE telefone = '$telefone'";

  // Execute the query
  $conn->query($sql);

}

function setStatus($telefone, $status, $conn){
  $sql = "UPDATE historico SET status = $status WHERE telefone = '$telefone'";
  $conn->query($sql);
}

function buscaStatus($bu,$telefone, $conn){
  $sql = "SELECT * FROM historico WHERE telefone = '$telefone'";
  $query = mysqli_query($conn,$sql);
  $total = mysqli_num_rows($query);

  while($rows_usuarios = mysqli_fetch_array($query)){
      $status = $rows_usuarios[$bu];
  }
  return $status;
}

function limparDados($telefone, $conn) {
  $sql = "UPDATE historico SET msg = NULL,status = 0,text1 = '', text2=''  WHERE telefone = '$telefone'";
  $conn->query($sql);
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
    // Obtém o historico atual para o número de telefone
    $historico_atual = buscaMSG($telefone, $conn);
    
    // Se já houver um historico para o número de telefone
    if ($historico_atual) {
        // Converte o resultado do banco de dados em um array associativo
        $row = mysqli_fetch_assoc($historico_atual);
        
        // Recupera o historico atual como array JSON
        $historico_array = json_decode($row['msg'], true);
        
        // Adiciona a nova mensagem ao histórico
        $historico_array[] = $msg;
        
        // Converte o novo historico de volta para JSON
        $novo_historico = json_encode($historico_array);
        
        // Atualiza o historico na tabela
        $sql = "UPDATE historico SET msg = ? WHERE telefone = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $novo_historico, $telefone);
        $stmt->execute();
        $stmt->close();
    } else {
        // Se não houver historico para o número de telefone, cria um novo
        $historico_array = [$msg]; // Cria um array contendo a nova mensagem
        $novo_historico = json_encode($historico_array); // Converte o array para JSON
        
        // Insere um novo registro com o historico na tabela
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
// ID DO PROJETO: chatbot-418002

if(!$conn){

    echo("erro conn !");

}else{

    $telefone = $_GET['telefone'];
    $msg = $_GET['msg'];

    //TESTA telefone unico

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

    //LIMPA O HISTORICO
    if($msg == "Regenerar" || $msg == "regenerar" || $msg == "regenera" || $msg == "Regenera"){

      limparDados($telefone, $conn);

      die;

    }







    ////////////
    /////////
    //////              EMBEDIS comparar texto
    ////////
    ///////////

    $status = buscaStatus("status",$telefone, $conn);

    if($msg == "comparar"){
      if ($status == 0){ //Primeira parte ECHO PRIMEIRO TEXTO
        echo "Primeiro texto a ser comparado :";
        setStatus($telefone,1, $conn);//muda mo status pra 1
        exit;
      }
    }

    if ($status != 0){

      if($status == 1){//Segunda Parte RECEBE O TEXTO 1
        setTexto($telefone, "text1", $msg, $conn);
        setStatus($telefone,2, $conn);//muda o status pra 2
        exit;
      }
      else if($status == 2){//Terceira Parte ECHO SEGUNDO TEXTO
        echo "Segundo texto a ser comparado :";
        setStatus($telefone,3, $conn);//muda o status pra 3
        exit;
      }
      else if($status == 3){//Quarta Parte RECEBE O TEXTO 2

        setTexto($telefone, "text2", $msg, $conn);

        $texto1 = buscaStatus("text1",$telefone, $conn);
        $texto2 = buscaStatus("text2",$telefone, $conn);

        $similaridade = calcularSimilaridade($texto1, $texto2, $apiKey, $projectId);
        echo "Similaridade entre os textos: " . $similaridade;

        exit;
      }
    }

    ///////////
    /////////
    //////             EMBEDIS comparar texto
    /////////
    //////////









    $historico = getHistoricoFormatado($telefone, $conn);
    addHistorico($telefone, $msg, $conn);
  
    $client = new Client($apiKey);

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
