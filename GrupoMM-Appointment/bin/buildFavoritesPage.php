<?php
/*
 * This file is part of Slim Framework 3 skeleton application.
 *
 * (c) Emerson Cavalcanti
 *
 * LICENSE: The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the 
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject
 * to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * Este é um aplicativo que gera uma página com informações de favoritos
 * para facilitar o atendimento.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 * @author Igor Gaffling <https://gaffling.com>
 */

$Sites = [
  [
    'name' => 'Sistema da Prefeitura de Franco da Rocha',
    'url' => 'http://servicos2.francodarocha.sp.gov.br:8080/tbw/loginNFEContribuinte.jsp?execobj=NFERelacionados'
  ],
  [
    'name' => 'Sistema SuHai',
    'url' => 'http://www.suhaitelematica.com.br/localizenovosistema/'
  ],
  [
    'name' => 'Sistema Mirus',
    'url' => 'http://www.olharfixo.com.br/LoginFrm.aspx?ReturnUrl=%2f'
  ],
  [
    'name' => 'Sistema Contratos',
    'url' => 'https://app.clicksign.com/accounts/4659/folders/43282'
  ],
  [
    'name' => 'Sistema Velox',
    'url' => 'http://velox.iclubbrasil.com.br/sgva/source/principal.asp'
  ],
  [
    'name' => 'Sistema Allcom',
    'url' => 'http://allmanager.allcomtelecom.com/login'
  ],
  [
    'name' => 'Sistema Lote TMData',
    'url' => 'http://www.porthoslat.com.br/ihm/index.aspx'
  ],
  [
    'name' => 'Sistema PABX',
    'url' => 'https://pbx.pabx.digital/pabx/'
  ],
  [
    'name' => 'Sistema Chat Bot',
    'url' => 'https://app.realezap.com.br/login.html'
  ],
  [
    'name' => 'Sistema NF produto',
    'url' => 'https://nfe.singlenfe.com.br/'
  ],
  [
    'name' => 'Sistema STC Admin',
    'url' => 'http://ap.stc.srv.br/admin/mmrastreamento/'
  ],
  [
    'name' => 'Sistema STC WebClient',
    'url' => 'http://ap3.stc.srv.br/webcliente/mmrastreamento'
  ],
  [
    'name' => 'Sistema SGR Hinova',
    'url' => 'https://sgr2.hinova.com.br/sgr/sgrv2/#/access/signin'
  ],
  [
    'name' => 'Sistema SGA Hinova MaxPro',
    'url' => 'https://taiga.hinova.com.br/sga/sgav4_maxpro/v5/login.php'
  ],
  [
    'name' => 'Sistema Getrak Faca',
    'url' => 'https://sis.getrak.com/faca'
  ],
];

// Percorre todos os links, obtendo para cada um o favicon
echo "Obtendo os favicons e montando a lista de links:\n";
$result = "<div class='ui list'>\n";
foreach ($Sites as $key => $site) {
  $grap_favicon = array(
    // URL da página da qual queremos obter o favicon
    'URL' => $site['url'],
    // Grava o favicon em um diretório local
    'SAVE'=> true,
    // O local onde a cópia do favicon será armazenada
    'DIR' => '../public/images/site/favicons/',
    // O modo de obtenção do favicon:
    //   - Tente obter o Favicon da página (verdadeiro) ou
    //   - use apenas as APIs (falso)
    'TRY' => false,
    // Ontênha todas as mensagens de depuração ('depurar') ou apenas
    // faça o trabalho (nulo)
    'DEV' => null
  );
  echo " - {$site['name']}...\n";
  $favicon = grap_favicon($grap_favicon);
  $favicon = str_replace('../public/', '/', $favicon);

  $result .= ""
    . "  <div class='item'>\n"
    . "    <img title='{$site['name']}'\n"
    . "         style='width:48px; padding-right:48px;'\n"
    . "         src='{$favicon}'>\n"
    . "    <div class='content'>\n"
    . "      <a href='{$site['url']}'>\n"
    . "        {$site['name']}\n"
    . "      </a>\n"
    . "    </div>\n"
    . "  </div>\n"
  ;

}
$result .= "</div>\n";

// Atualiza o conteúdo da página de links
echo "\n\nAtualizando lista de links\n";
$fp = fopen('../app/views/site/listOfLinks.twig', 'w');
fwrite($fp, $result);
fclose($fp);

echo "Pronto\n";

function grap_favicon( $options=array() ) {
  // Avoid script runtime timeout
  $max_execution_time = ini_get("max_execution_time");
  set_time_limit(0); // 0 = no timelimit

  // Ini Vars
  $url = (isset($options['URL']))
    ? $options['URL']
    :'gaffling.com'
  ;
  $save = (isset($options['SAVE']))
    ? $options['SAVE']
    : true
  ;
  $directory = (isset($options['DIR']))
    ? $options['DIR']
    : './'
  ;
  $trySelf = (isset($options['TRY']))
    ? $options['TRY']
    : true
  ;
  $DEBUG = (isset($options['DEV']))
    ? $options['DEV']
    : null
  ;

  // URL to lower case
  $url = strtolower($url);

  // Get the Domain from the URL
  $domain = parse_url($url, PHP_URL_HOST);

  // Check Domain
  $domainParts = explode('.', $domain);
  if(count($domainParts) == 3 and $domainParts[0]!='www') {
    // With Subdomain (if not www)
    $domain = $domainParts[0].'.'.
              $domainParts[count($domainParts)-2].'.'.$domainParts[count($domainParts)-1];
  } else if (count($domainParts) >= 2) {
    // Without Subdomain
    $domain = $domainParts[count($domainParts)-2].'.'.$domainParts[count($domainParts)-1];
  } else {
    // Without http(s)
    $domain = $url;
  }

  // FOR DEBUG ONLY
  if($DEBUG=='debug') {
    print('<b style="color:red;">Domain</b> #'.@$domain.'#<br>');
  }

  // Make Path & Filename
  $filePath = preg_replace('#\/\/#', '/', $directory.'/'.$domain.'.png');
  // change save path & filename of icons ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ 

  // If Favicon not already exists local
  if ( !file_exists($filePath) or @filesize($filePath)==0 ) {

    // If $trySelf == TRUE ONLY USE APIs
    if ( isset($trySelf) and $trySelf == TRUE ) {  

      // Load Page
      $html = load($url, $DEBUG);

      // Find Favicon with RegEx
      $regExPattern = '/((<link[^>]+rel=.(icon|shortcut icon|alternate icon)[^>]+>))/i';
      if ( @preg_match($regExPattern, $html, $matchTag) ) {
        $regExPattern = '/href=(\'|\")(.*?)\1/i';
        if ( isset($matchTag[1]) and @preg_match($regExPattern, $matchTag[1], $matchUrl)) {
          if ( isset($matchUrl[2]) ) {
            
            // Build Favicon Link
            $favicon = rel2abs(trim($matchUrl[2]), 'http://'.$domain.'/');
            
            // FOR DEBUG ONLY
            if($DEBUG=='debug') {
              print('<b style="color:red;">Match</b> #'.@$favicon.'#<br>');
            }
          }
        }
      }
      
      // If there is no Match: Try if there is a Favicon in the Root of the Domain
      if ( empty($favicon) ) { 
        $favicon = 'http://'.$domain.'/favicon.ico';

        // Try to Load Favicon
        if ( !@getimagesize($favicon) ) {
          unset($favicon);
        }
      }

    } // END If $trySelf == TRUE ONLY USE APIs
        
    // If nothink works: Get the Favicon from API
    if ( !isset($favicon) or empty($favicon) ) {

      // Select API by Random
      $random = rand(1,3);

      // Faviconkit API
      if ($random == 1 or empty($favicon)) {
        $favicon = 'https://api.faviconkit.com/'.$domain.'/16';
      }

      // Favicongrabber API
      if ($random == 2 or empty($favicon)) {
        $echo = json_decode(load('http://favicongrabber.com/api/grab/'.$domain,FALSE),TRUE);
        
        // Get Favicon URL from Array out of json data (@ if something went wrong)
        $favicon = @$echo['icons']['0']['src'];

      }

      // Google API (check also md5() later)
      if ($random == 3) {
        $favicon = 'http://www.google.com/s2/favicons?domain='.$domain;
      } 
      
      // FOR DEBUG ONLY
      if($DEBUG=='debug') {
        print('<b style="color:red;">'.$random.'. API</b> #'.@$favicon.'#<br>');
      }

    } // END If nothink works: Get the Favicon from API

    // Write Favicon local
    $filePath = preg_replace('#\/\/#', '/', $directory.'/'.$domain.'.png');

    // If Favicon should be saved
    if ( isset($save) and $save == TRUE ) {
      
      //  Load Favicon
      $content = load($favicon, $DEBUG);

      // If Google API don't know and deliver a default Favicon (World)
      if ( isset($random) and $random == 3 and 
           md5($content) == '3ca64f83fdcf25135d87e08af65e68c9' ) {
        $domain = 'default'; // so we don't save a default icon for every domain again

        // FOR DEBUG ONLY
        if($DEBUG=='debug') {
          print('<b style="color:red;">Google</b> #use default icon#<br>');
        }
      }

      // Write 
      $fh = @fopen($filePath, 'wb');
      fwrite($fh, $content);
      fclose($fh);

      // FOR DEBUG ONLY
      if($DEBUG=='debug') {
        print('<b style="color:red;">Write-File</b> #'.@$filePath.'#<br>');
      }
    } else {
      // Don't save Favicon local, only return Favicon URL
      $filePath = $favicon;
    }

  } // END If Favicon not already exists local

  // FOR DEBUG ONLY
  if ($DEBUG=='debug') {
    // Load the Favicon from local file
    if ( !function_exists('file_get_contents') ) {
      $fh = @fopen($filePath, 'r');
      while (!feof($fh)) {
        $content .= fread($fh, 128); // Because filesize() will not work on URLS?
      }
      fclose($fh);
    } else {
      $content = file_get_contents($filePath);
    }
    print('<b style="color:red;">Image</b> <img style="width:32px;" 
           src="data:image/png;base64,'.base64_encode($content).'"><hr size="1">');
  }
  
  // reset script runtime timeout
  set_time_limit($max_execution_time); // set it back to the old value

  // Return Favicon Url
  return $filePath;

} // END MAIN Function

/* HELPER load use curl or file_get_contents (both with user_agent) and fopen/fread as fallback */
function load($url, $DEBUG) {
  if ( function_exists('curl_version') ) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'FaviconBot/1.0 (+http://www.grupomm.srv.br/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $content = curl_exec($ch);
    if ( $DEBUG=='debug' ) { // FOR DEBUG ONLY
      $http_code = curl_getinfo($ch);
      print('<b style="color:red;">cURL</b> #'.$http_code['http_code'].'#<br>');
    }
    curl_close($ch);
    unset($ch);
  } else {
    $context = array ( 'http' => array (
        'user_agent' => 'FaviconBot/1.0 (+http://www.grupomm.srv.br/)'),
    );
    $context = stream_context_create($context);
    if ( !function_exists('file_get_contents') ) {
      $fh = fopen($url, 'r', FALSE, $context);
      $content = '';
      while (!feof($fh)) {
        $content .= fread($fh, 128); // Because filesize() will not work on URLS?
      }
      fclose($fh);
    } else {
      $content = file_get_contents($url, NULL, $context);
    }
  }
  return $content;
}

/* HELPER: Change URL from relative to absolute */
function rel2abs( $rel, $base ) {
  extract( parse_url( $base ) );
  if ( strpos( $rel,"//" ) === 0 ) return $scheme . ':' . $rel;
  if ( parse_url( $rel, PHP_URL_SCHEME ) != '' ) return $rel;
  if ( $rel[0] == '#' or $rel[0] == '?' ) return $base . $rel;
  $path = preg_replace( '#/[^/]*$#', '', $path);
  if ( $rel[0] ==  '/' ) $path = '';
  $abs = $host . $path . "/" . $rel;
  $abs = preg_replace( "/(\/\.?\/)/", "/", $abs);
  $abs = preg_replace( "/\/(?!\.\.)[^\/]+\/\.\.\//", "/", $abs);
  return $scheme . '://' . $abs;
}