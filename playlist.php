<?php

/*
  Integração PHP com YouTube API
  Trabalho desenvolvido por Fernanda Reis e Phyllipe Lima
  Disciplina: Desenvolvimento de Sites Dinâmicos I

  https://developers.google.com/youtube/
*/


header('Content-type: text/html; charset=utf-8');

/* Variável htmlBody que armazena o que será mostrado no corpo da página dependendo das ações do usuário */
$htmlBody = <<<END

<form method="GET">
  <div>
    Pesquisar por: <input type="search" id="q" name ="q" class="searchField" placeholder="Entre com o termo da pesquisa">
  </div>
  <div>
    Número máximo de resultados: <input type="number" id="maxResults" name="maxResults" min="1" max="50" step="1" value ="25">  
  </div>
  <input type="submit" class="button" value="Buscar">
</form>

END;


set_include_path("google-api-php-client-master/src");
require_once 'Google/Client.php';
require_once 'Google/Service/YouTube.php';
session_start();

$DEVELOPER_KEY = 'AIzaSyAR5Zr8-Jl4i9wjD7JGuhxzfMXRe_usADA';

//$DEVELOPER_KEY = 'AIzaSyBI_8wM9zRa--vz8ECemL9leRmq2_CPmv0';

//A parte da playlist requer que isso seja feito.
//Pois os dados de um usuario serão acessados.
$OAUTH2_CLIENT_ID = '185696888891-t944urg1gadpsmfu302are9kjqlvhp47.apps.googleusercontent.com';
$OAUTH2_CLIENT_SECRET = 'JX6QwwLj60wXitmq3gTPDg89';

$client = new Google_Client();
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);
$client->setScopes('https://www.googleapis.com/auth/youtube');
$redirect = filter_var('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
    FILTER_SANITIZE_URL);
$client->setRedirectUri($redirect);
$client->setDeveloperKey($DEVELOPER_KEY);

$client->setAccessType('offline');

// Objeto que fará todos os requests para a API
$youtube = new Google_Service_YouTube($client);

//Parte responsavel pela busca e apresentacao dos resultados.

if(isset($_GET['q']) && isset($_GET['maxResults'])){
  try{
      //Chamada do metodo search.list para pegar os resultados do campo de busca.
    $searchResponse = $youtube->search->listSearch('id,snippet', array(
      'q' => $_GET['q'],
      'maxResults' => $_GET['maxResults'],
    ));

    $videos = <<<END
    <form method="POST">

END;
    //Coloca os resultados na lista apropriada e mostra os resultado. Coloca tambem a opcao de marcar para salvar numa playlist
    foreach ($searchResponse['items'] as $searchResult) {

      switch ($searchResult['id']['kind']) {
        case 'youtube#video':
          $videos.=sprintf('<li>%s</li>',
              "<a href=https://www.youtube.com/embed/".$searchResult['id']['videoId']." target=search_iframe>".$searchResult['snippet']['title']."</a>"); 
          $videoID = $searchResult['id']['videoId'];
          $videos .= <<<END
          <input type="checkBox" name="videosEscolhidos[]" value="$videoID" /><span class="checkbox">Marcar Para a playlist</span>
END;
          break;
        
        default:
          
          break;
      }
    }
    

    //O HTML A SEGUIR MOSTRA O CAMPO PARA CRIAR A OPCAO DA PLAYLIST
    $videos .= <<<END
      <div class="playlist">
        <span>Nome da playlist: </span><input type="search" id="add_playlist" name ="add_playlist" placeholder="Nome da playlist" >
      </div>
      <input type="submit" value="Criar" class="button" class="searchField">
    </form>
    

END;

$htmlBody .= <<<END
    <h3>Videos</h3>
    <ol>$videos</ol>
END;
  } catch (Google_ServiceException $e) {
    $htmlBody .= sprintf('<p>Ocorreu um erro no servidor: <code>%s</code></p>',
      htmlspecialchars($e->getMessage()));
  } catch (Google_Exception $e) {
    $htmlBody .= sprintf('<p>Ocorreu um erro no cliente: <code>%s</code></p>',
      htmlspecialchars($e->getMessage()));
  }

  
}//OS RESULTADOS FORAM MOSTRADOS. 


if (isset($_GET['code'])) {
  if (strval($_SESSION['state']) !== strval($_GET['state'])) {
    die('The session state did not match.');
  }

  $client->authenticate($_GET['code']);
  $_SESSION['token'] = $client->getAccessToken();
  header('Location: ' . $redirect);
}

if (isset($_SESSION['token'])) {
  $client->setAccessToken($_SESSION['token']);
  if ($client->isAccessTokenExpired()) {
    $currentTokenData = json_decode($_SESSION['token']);
    if (isset($currentTokenData->refresh_token)) {
        $client->refreshToken($currentTokenData->refresh_token);
    }
}
  
}

// Verificando se o acesso do token foi ok
if ($client->getAccessToken()) {
  try {
    // Criando uma nova playlist na conta YouTube do usuário e adicionando um vídeo a ela
    if(isset($_POST['add_playlist'])){
      $nomePlaylist = $_POST['add_playlist'];
    }
    if(!empty($nomePlaylist) && isset($_POST['videosEscolhidos'])){//O usuario escreveu o nome de uma nova playlist e selecionou pelo menos 1

        $videosID = $_POST['videosEscolhidos']; //Variavel que armazena o ID dos videos escolhidos.
        $N = count($videosID);//Armazena quantos videos foram escolhidos

        // 1. Cria um snippet para a nova playlist, setando nome e descrição
        $playlistSnippet = new Google_Service_YouTube_PlaylistSnippet();
        $playlistSnippet->setTitle($nomePlaylist . date("Y-m-d H:i:s"));
        $playlistSnippet->setDescription('Playlist criada com a API do youtube para o trabalho da posgrad FAI');

        // 2. Define o status da playlist.
        $playlistStatus = new Google_Service_YouTube_PlaylistStatus();
        $playlistStatus->setPrivacyStatus('private');

        // 3. Cria a playlist e associa com o snippet e o status definidos acima
        $youTubePlaylist = new Google_Service_YouTube_Playlist();
        $youTubePlaylist->setSnippet($playlistSnippet);
        $youTubePlaylist->setStatus($playlistStatus);

        // 4. Insere a playlist na conta do usuário
        $playlistResponse = $youtube->playlists->insert('snippet,status',
            $youTubePlaylist, array());
        $playlistId = $playlistResponse['id'];

        // 5. Adiciona vídeos à playlist
        $resourceId = new Google_Service_YouTube_ResourceId();
        
        for($i=0; $i < $N; $i++)//Itera entre todos os checkboxes marcados para adicionar na playlist
        {
          $resourceId->setVideoId($videosID[$i]);
          $resourceId->setKind('youtube#video');

          // Define um snippet para o vídeo
          $playlistItemSnippet = new Google_Service_YouTube_PlaylistItemSnippet();
          $playlistItemSnippet->setTitle('First video in the test playlist');
          $playlistItemSnippet->setPlaylistId($playlistId);
          $playlistItemSnippet->setResourceId($resourceId);

          // Adiciona o vídeo à playlist
          $playlistItem = new Google_Service_YouTube_PlaylistItem();
          $playlistItem->setSnippet($playlistItemSnippet);
          $playlistItemResponse = $youtube->playlistItems->insert(
              'snippet,contentDetails', $playlistItem, array());
        }
     

        $htmlBody .= "<h3>Playlist criada com sucesso:</h3><ul>";
        $htmlBody .= sprintf('<li>%s</li>',
            $playlistResponse['snippet']['title']);
        $htmlBody .= '</ul>';

    }
    

  } catch (Google_ServiceException $e) {
    $htmlBody .= sprintf('<p>Ocorreu um erro no servidor: <code>%s</code></p>',
        htmlspecialchars($e->getMessage()));
  } catch (Google_Exception $e) {
    $htmlBody .= sprintf('<p>Ocorreu um erro no cliente: <code>%s</code></p>',
        htmlspecialchars($e->getMessage()));
  }

  $_SESSION['token'] = $client->getAccessToken();
} else {
  // Se o usuário ainda não autenticou, pedir autenticação
  $state = mt_rand();
  $client->setState($state);
  $_SESSION['state'] = $state;

  $authUrl = $client->createAuthUrl();
  $htmlBody = <<<END
  <h3>Autenticação necessária</h3>
  <p>Você precisa <a href="$authUrl">autorizar o acesso</a> antes de proceder.<p>
END;
}
?>

<!doctype html>
<html>
  <head>
    <link rel="stylesheet" href="css/style.css">
    <title>YouTube Search</title>
    <link rel="shortcut icon" href="https://s.ytimg.com/yts/img/favicon-vfldLzJxy.ico" type="image/x-icon">
  </head>
  <body>
     <div class="wrapper">
        <div class="subwrapper">
          <div class="content">
     
          <h1>Integração PHP com YouTube API</h1>
          <h2>Desenvolvido por Fernanda Reis e Phyllipe Lima</h2>

           <iframe id="ytplayer" type="text/html" width="640" height="360"
         src="https://www.youtube.com/embed/Xjn3XZ6IA_0" name="search_iframe"
         frameborder="0" allowfullscreen>
          </iframe>
        
        <?=$htmlBody?>
          </div>
        </div>
    </div>
  </body>
</html>