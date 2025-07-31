<?php
/*
 * This file is part of Extension Library.
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
 * Um invólucro para a extensão cURL, com suporte para Cookies e
 * depuração da comunicação.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\HTTP;

use Carbon\Carbon;
use Core\Exceptions\cURLException;
use Core\Helpers\Path;
use Core\Exceptions\HTTPException;
use InvalidArgumentException;
use RuntimeException;
use SplFileObject;

class HTTPClient
{
  /**
   * A flag indicativa do modo de depuração. Se ativo, as respostas cURL
   * são armazenadas em arquivos de depuração. Estes arquivos precisam
   * ser apagados manualmente.
   *
   * @var boolean
   */
  protected $debug = false;

  /**
   * A flag indicativa do uso de cookies.
   *
   * @var boolean
   */
  protected $enableCookies = true;

  /**
   * O caminho para o local de armazenamento dos arquivos de cookie.
   *
   * @var Path
   */
  protected $cookiePath;

  /**
   * A identificação de agente de usuário.
   *
   * @var string
   */
  protected $userAgent = 'Mozilla/5.0 (X11; Linux x86_64; rv:57.0) '
    . 'Gecko/20100101 Firefox/57.0'
  ;

  /**
   * A identificação da codificação.
   *
   * @var string
   */
  protected $encoding = '';

  /**
   * O tempo de limite de espera da conexão (em segundos).
   *
   * @var integer
   */
  protected $timeout = 30;

  /**
   * Número máximo de redirecionamentos permitidos (se '0', não permite
   * redirecionamentos).
   *
   * @var integer
   */
  protected $maxRedirects = 3;

  /**
   * A flag indicativa de modo detalhado, permitindo a depuração de
   * conexões TLS, cabeçalhos, etc.
   *
   * @var boolean
   */
  protected $verboseMode = false;

  /**
   * A flag indicativa de verificação do host SSL
   *
   * @var boolean
   */
  protected $verifySSL = false;

  /**
   * O arquivo contendo o certificado para o servidor da conexão SSL
   *
   * @var Core\Traits\Path
   */
  protected $ca_file = '';

  /**
   * Cabeçalhos da requisição HTTP customizados.
   *
   * @var array
   */
  protected $headers = [];

  /**
   * Os métodos HTTP disponíveis para uma requisição cURL.
   *
   * @var array
   */
  protected $methods = [
    'DELETE',
    'GET',
    'HEAD',
    'PATCH',
    'POST',
    'PUT'
  ];

  /**
   * O construtor do sistema de requisição por HTTP.
   *
   * @param string $path
   *   O caminho onde serão armazenados os cookies
   */
  public function __construct(string $path)
  {
    $this->cookiePath = new Path($path);
  }


  /* =================================[ Manipulação dos parâmetros ]====

  /**
   * Seta uma identificação de agente de usuário.
   *
   * @param string $value
   *   O agente de usuário
   */
  public function setUserAgent(string $value): void
  {
    $this->userAgent = $value;
  }

  /**
   * Seta o número máximo de redirecionamentos possíveis.
   *
   * @param int $value
   *   O número máximo de redirecionamentos
   */
  public function setMaxRedirects(int $value): void
  {
    if ($value >= 0) {
      $this->maxRedirects = $value;
    } else {
      $this->maxRedirects = 0;
    }
  }

  /**
   * Seta o uso de cookies.
   *
   * @param bool $value
   *   O indicativo se devemos ou não utilizar cookies
   */
  public function setCookies(bool $value): void
  {
    $this->enableCookies = $value;
  }

  /**
   * Seta a depuração.
   *
   * @param bool $value
   *   O indicativo se devemos ou não habilitar a depuração
   */
  public function setDebug(bool $value): void
  {
    $this->debug = $value;
  }

  /**
   * Seta o modo detalhado.
   *
   * @param bool $value
   *   O indicativo se devemos ou não habilitar o modo detalhado
   */
  public function setVerbose(bool $value): void
  {
    $this->verboseMode = $value;
  }

  /**
   * Seta a codificação através do cabeçalho 'Accept-Encoding', por
   * exemplo: gzip.
   *
   * @param string $value
   *   A codificação
   */
  public function setEncoding(string $value): void
  {
    $this->encoding = $value;
  }

  /**
   * Seta o tempo limite para obtermos uma resposta de nossa requisição,
   * em segundos.
   *
   * @param string $value
   *   O tempo limite (em segundos)
   */
  public function setTimeout(string $value): void
  {
    $this->timeout = $value;
  }

  /**
   * Seta um certificado SSL do servidor ao qual iremos fazer a conexão.
   *
   * @param string $value
   *   O nome do arquivo que contém o certificado
   */
  public function setCertificateFile(string $value): void
  {
    if (!empty($value)) {
      $this->verifySSL = true;
      $this->ca_file = new Path($value);
    }
  }

  /**
   * Este método estabelece um valor de cabeçalho. Ele substitui
   * quaisquer valores que já possam existir para o nome do cabeçalho.
   *
   * @param string $key
   *   O nome do cabeçalho não sensível a maiúsculas e minúsculas
   * @param string $value
   *   O valor do cabeçalho
   */
  public function addHeader(string $key, string $value): void
  {
    $key = $this->normalizeKey($key);
    $this->headers[$key] = $value;
  }

  /**
   * Formata uma URL com dados para o método GET.
   *
   * @param string $url
   *   A URL original
   * @param array  $data
   *   Os dados para construir a consulta
   *
   * @return string
   *   A URL com os dados
   */
  protected static function formatURLToGet(string $url,
    array $data): string
  {
    if (!empty($data)) {
      // Primeiramente, identificamos as partes da URL original
      $urlParts = parse_url($url);

      // Verifica se a URL continha dados de consulta já adicionados
      if (empty($urlParts['query'])) {
        // Não temos dados de consulta na URL original
        $query = '';
      } else {
        // Recuperamos os dados de consulta previamente formatados
        $query = $urlParts['query'];
      }

      // Adicionamos os dados novos de consulta
      $query .= '&' . http_build_query($data, '', '&');
      $query = trim($query, '&');

      // Agora montamos a URL final
      if (empty($urlParts['query'])) {
        $url .= '?' . $query;
      }
      else {
        $url = str_replace($urlParts['query'], $query, $url);
      }
    }

    return $url;
  }

  /* ====================================[ Manipulação dos Cookies ]====

  /**
   * Retorna o nome do arquivo de cookie para uma URL informada.
   *
   * @param string $url
   *   A URL da requisição
   *
   * @return string
   *   O nome do arquivo de cookie
   */
  protected function getCookieFileName(string $url): string
  {
    $parts = parse_url($url);

    return ''
      . $this->cookiePath . '/'
      . urlencode(strtolower($parts['host']))
      .'.cookie'
    ;
  }

  /* =========================================[ Métodos Auxiliares ]====

  /**
   * Este método corrige o nome de uma chave para um cabeçalho HTTP.
   *
   * @param string $key
   *   O nome da chave que identifica um cabeçalho
   *
   * @return string
   *   A chave normalizada
   */
  public function normalizeKey(string $key): string
  {
    // Primeiramente convertemos tudo para minúscula
    $key = strtr(strtolower($key), '_', '-');
    if (strpos($key, 'http-') === 0) {
      $key = substr($key, 5);
    }

    // Agora convertemos apenas a primeira letra para maiúscula
    $key = ucwords($key, "-");

    return $key;
  }

  /**
   * Monta uma string com os métodos suportados.
   *
   * @param array  $allowedHttpMethods
   *   A matriz com os métodos HTTP suportados
   *
   * @return string
   *   Os métodos suportados numa string separada por virgulas
   */
  protected function buildMethodsString(array $allowedHttpMethods): string
  {
    $last  = array_slice($allowedHttpMethods, -1);
    $first = join(', ', array_slice($allowedHttpMethods, 0, -1));
    $both  = array_filter(array_merge(array($first), $last), 'strlen');

    return join(' e ', $both);
  }

  /**
   * Monta uma matriz com os valores de cabeçalhos.
   *
   * @return array
   *   Uma matriz com todos os cabeçalhos definidos
   */
  protected function getAllHeaders(): array
  {
    $result = [];

    foreach ($this->headers as $key => $value) {
      $result[] = sprintf("%s: %s", $key, $value);
    }

    return $result;
  }

  /**
   * Converte todos os tipos de "fim de linha" para o formato UNIX.
   *
   * @param string $content
   *   O conteúdo que precisa ser normalizado
   *
   * @return string
   *   O conteúdo normalizado
   */
  protected function normalize(string $content): string
  {
    // Convertemos todos os fim-de-linha para o formato UNIX
    $content = str_replace(array("\r\n", "\r", "\n"), "\n", $content);

    // Não permite excesso de linhas em branco
    $content = preg_replace("/\n{3,}/", "\n\n", $content);

    return $content;
  }

  /**
   * Recupera o tempo decorrido (em segundos) de uma forma legível ao
   * usuário.
   *
   * @param int $seconds
   *   O tempo em segundos
   *
   * @return string
   *   O tempo de maneira legível
   */
  protected function getHumanTime(int $seconds): string
  {
    $humanTime = '0';

    if ($seconds > 0) {
      // Convertemos o tempo (em segundos) em um valor
      // legível ao usuário, ignorando segundos neste cálculo
      $now      = Carbon::now()->locale('pt_BR');
      $interval = Carbon::now()->locale('pt_BR')
        ->addSeconds($seconds)
      ;
      $humanTime = $now
        ->longAbsoluteDiffForHumans($interval, 4)
      ;
    }

    return $humanTime;
  }


  /* ====================================[ Métodos para requisição ]====

  /**
   * Realiza uma requisição HTTP.
   *
   * @param string $url
   *   A URL para a qual faremos a requisição
   * @param string $method
   *   O método HTTP a ser utilizado
   * @param array $params
   *   Os parâmetros a serem enviados na requisição
   *
   * @return array
   *   Uma matriz com o conteúdo da resposta
   *
   * @throws cURLException
   *   Em caso de falhas no cURL
   * @throws HTTPException
   *   Em caso de falhas HTTP
   * @throws InvalidArgumentException
   *   Em caso de argumentos inválidos
   * @throws RuntimeException
   *   Em caso de erros de execução
   */
  public function sendRequest(string $url, string $method = 'GET',
    array $params = []): array
  {
    // Registramos o horário de início para determinar o tempo total
    // de nossa requisição
    $startTime = microtime(true);

    // Retiramos caracteres inválidos de nossa URL
    $url = filter_var($url, FILTER_SANITIZE_URL);

    // Determinamos se a URL é um endereço válido
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      // A URL é inválida

      // Dispara uma exceção
      throw new InvalidArgumentException(sprintf("O endereço '%s' não "
        . "é uma URL válida", $url)
      );
    }

    // Determinamos se o método é válido
    $method = strtoupper($method);
    if (!in_array($method, $this->methods))
    {
      // O método é inválido

      // Monta uma string com os métodos suportados
      $methods = $this->buildMethodsString($this->methods);

      // Dispara uma exceção
      throw new InvalidArgumentException(sprintf("O método '%s' não é "
        . "válido. Os métodos disponíveis são: %s", $method, $methods)
      );
    }

    // Verificamos se precisamos lidar com cookies
    if ($this->enableCookies) {
      // Os cookies estão ativos

      if (!$this->cookiePath->exists()) {
        if (!$this->cookiePath->createDirectory()) {
          // Dispara uma exceção
          throw new InvalidArgumentException(sprintf("O caminho '%s' "
            . "para armazenamento dos cookies não existe e não pode "
            . "ser criado.",
            $this->cookiePath)
          );
        }
      }

      if (!$this->cookiePath->isWritable()) {
        // Dispara uma exceção
        throw new InvalidArgumentException(sprintf("O caminho '%s' "
          . "para armazenamento dos cookies não permite a gravação.",
          $this->cookiePath)
        );
      }
    }

    // Lidamos com os parâmetros para o método GET
    if ( ($method === 'GET') && (count($params) > 0) ) {
      // Adicionamos os parâmetros em nossa requisição
      $url = $this->formatURLToGet($url, $params);
    }

    // Inicializamos os dados de nossa requisição
    $request = [
      'method'          => $method,
      'url'             => $url,
      'user-agent'      => $this->userAgent,
      'headers'         => $this->getAllHeaders(),
      'accept-encoding' => $this->encoding,
      'data'            => [],
      'cookiefile'      => '',
      'ssl'             => false,
      'cainfo'          => ''
    ];

    if (!extension_loaded('curl')) {
      throw new RuntimeException('A extensão cURL não está presente');
    }

    // Inicializamos o cURL
    $curlHandler = curl_init($url);

    if ($curlHandler == false)
    {
      throw new RuntimeException("Não foi possível inicializar o cURL");
    }

    // Adicionamos os parâmetros de nossa requisição

    // O método da requisição
    curl_setopt($curlHandler, CURLOPT_CUSTOMREQUEST, $method);

    // Conforme o método, adicionamos parâmetros adicionais
    switch ($method) {
      case 'GET':
        curl_setopt($curlHandler, CURLOPT_HTTPGET, true);

        // Criamos o cabeçalho 'Accept-Encoding'
        curl_setopt($curlHandler, CURLOPT_ENCODING, $this->encoding);
        $request['Accept-Encoding'] = $this->encoding;

        break;
      case 'HEAD':
        // Método HEAD
        curl_setopt($curlHandler, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curlHandler, CURLOPT_NOBODY, true);
        break;
      default:
        // Lidamos com os demais métodos (POST, PUT, DELETE e PATCH)
        if ($method === 'POST') {
          curl_setopt($curlHandler, CURLOPT_POST, true);
        } else {
          curl_setopt($curlHandler, CURLOPT_CUSTOMREQUEST, $method);
        }

        if (count($params) > 0) {
          // Convertemos os parâmetros de nossa requisição
          if (array_key_exists('Content-Type', $this->headers)) {
            $contentType = strtolower($this->headers['Content-Type']);

            if ($contentType === 'application/json') {
              // Se a requisição é para um JSON, os dados devem ser
              // enviados também como JSON
              $data = json_encode($params);
            } else {
              $data = http_build_query($params);
            }
          } else {
            $data = http_build_query($params);
          }

          curl_setopt($curlHandler, CURLOPT_POSTFIELDS, $data);
          $request['data'] = $data;
        }

        // Criamos o cabeçalho 'Accept-Encoding'
        curl_setopt($curlHandler, CURLOPT_ENCODING, $this->encoding);
    }

    // Seta as opções comuns, independente do tipo de requisição

    // Retorna o conteúdo da requisição
    curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);

    // Inclui os cabeçalhos na requisição
    curl_setopt($curlHandler, CURLOPT_HEADER, true);

    // Forçar a conexão a fechar explicitamente quando tiver terminado o
    // processamento, e não ser agrupada para reutilização
    curl_setopt($curlHandler, CURLOPT_FORBID_REUSE, true);

    // Determina o tempo limite para obtermos uma resposta da requisição
    curl_setopt($curlHandler, CURLOPT_CONNECTTIMEOUT, $this->timeout);

    // Determina a identificação de agente de usuário
    curl_setopt($curlHandler, CURLOPT_USERAGENT, $this->userAgent);

    // Determina o comportamento para redirecionamentos
    if ($this->maxRedirects === 0) {
      // Não seguir redirecionamentos
      curl_setopt($curlHandler, CURLOPT_FOLLOWLOCATION, false);
    } else {
      // Seguir redirecionamentos até o limite informado
      curl_setopt($curlHandler, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($curlHandler, CURLOPT_MAXREDIRS, $this->maxRedirects);
    }

    // Determina se devemos detalhar informações da requisição
    if ($this->verboseMode) {
      // Criamos um arquivo no mesmo local onde armazenamos os cookies
      $detailsTemporaryFile = sprintf('%s/details_%s.tmp',
        $this->cookiePath,
        sha1($method . $url . microtime(true) . mt_rand())
      );
      $detailsStream = fopen($detailsTemporaryFile, 'w+');
      flock($detailsStream, LOCK_EX);

      // Habilitamos o modo verboso
      curl_setopt($curlHandler, CURLOPT_VERBOSE, true);
      curl_setopt($curlHandler, CURLOPT_STDERR,  $detailsStream);
    }

    // Determina se devemos habilitar o uso de cookies
    if ($this->enableCookies) {
      // Criamos um arquivo de cookies para a URL
      $cookieFileName = $this->getCookieFileName($url);
      $request['cookiefile'] = $cookieFileName;

      // Definimos os cookies
      curl_setopt($curlHandler, CURLOPT_COOKIEJAR, $cookieFileName);
      curl_setopt($curlHandler, CURLOPT_COOKIEFILE, $cookieFileName);
    }

    // Seta outras opções para lidarmos com cabeçalhos

    // Determina se devemos incluir cabeçalhos adicionais
    if (!empty($this->headers)) {
      curl_setopt($curlHandler, CURLOPT_HTTPHEADER,
        $this->getAllHeaders()
      );
    }

    // Determina se devemos incluir parâmetros adicionais para
    // requisições por HTTPS
    if (preg_match('/^https:/', $url)) {
      $request['ssl'] = true;
      // A versão da conexão SSL (seta o padrão)
      curl_setopt($curlHandler, CURLOPT_SSLVERSION,
        CURL_SSLVERSION_DEFAULT
      );

      // A verificação de certificado SSL
      if ($this->verifySSL) {
        curl_setopt($curlHandler, CURLOPT_SSL_VERIFYPEER, true);
        // SSL_VERIFYHOST
        // - 1 to check the existence of a common name in the SSL peer
        //   certificate
        // - 2 to check the existence of a common name and also verify
        //   that it matches the hostname provided. 0 to not check the
        //   names. In production environments the value of this option
        //   should be kept at 2 (default value).
        curl_setopt($curlHandler, CURLOPT_SSL_VERIFYHOST, 2);
        if (!$this->ca_file->exists()) {
          // Dispara uma exceção
          curl_close($curlHandler);
          throw new InvalidArgumentException("O arquivo de certificado "
            . $this->ca_file . " é inválido ou inexistente."
          );
        }
        curl_setopt($curlHandler, CURLOPT_CAINFO, $this->ca_file);
        $request['cainfo'] = $this->ca_file;
      } else {
        // Não valida certificados SSL
        curl_setopt($curlHandler, CURLOPT_SSL_VERIFYPEER, false);
      }
    }

    // Realizamos a requisição e obtemos a resposta
    $rawResponse = curl_exec($curlHandler);

    // Recuperamos quaisquer mensagens de erro do cURL
    $curlErrorCode    = curl_errno($curlHandler);
    $curlErrorMessage = curl_error($curlHandler);
    $curlError        = $curlErrorCode !== 0;

    // Recuperamos o tempo após a execução do sincronismo
    $endTime = microtime(true);

    // Calculamos o tempo de execução
    $executionTime = $endTime - $startTime;

    // Inclui informações adicionais do código de erro na mensagem de
    // erro, quando possível
    if ($curlError && function_exists('curl_strerror')) {
      $curlErrorMessage = curl_strerror($curlErrorCode)
        . (
          empty($curlErrorMessage) ? '' : ': ' . $curlErrorMessage
      );
    }

    // Obtemos informações de erros HTTP
    $httpStatusCode = curl_getinfo($curlHandler, CURLINFO_HTTP_CODE);
    $httpError = HTTPStatusCodes::isError($httpStatusCode);
    //$httpErrorMessage = HTTPStatusCodes::getMessageForCode($httpStatusCode);

    // Determinamos de uma maneira geral se ocorreram erros na requisição
    $hasError = $curlError || $httpError;
    //$errorCode = $hasError
    //  ? ($curlError ? $curlErrorCode : $httpStatusCode)
    //  : 0
    //;

    $details = [];
    if ($this->debug) {
      // Verifica se está habilitado o modo de detalhamento
      if ($this->verboseMode === true) {
        // Incluímos o detalhamento da conexão
        rewind($detailsStream);
        $details = stream_get_contents($detailsStream);
        unlink($detailsTemporaryFile);
        flock($detailsStream, LOCK_UN);
        fclose($detailsStream);

        // Corrige o fim de linha
        $details = $this->normalize($details);

        // Converte em uma matriz
        $details = explode("\n", $details);
      }
    }

    // Obtemos o tamanho do cabeçalho HTTP
    $headerSize = curl_getinfo($curlHandler, CURLINFO_HEADER_SIZE);

    // Sempre encerramos o handler cURL
    curl_close($curlHandler);

    // Lidamos com os cookies
    if ($this->enableCookies) {
      // Obtemos o nome do arquivo em função da URL
      $cookieFileName = $this->getCookieFileName($url);

      if (!file_exists($cookieFileName)) {
        // Criamos um arquivo de cookies para a URL
        try {
          $oldMask = umask(0);
          touch($cookieFileName);
          chmod($cookieFileName, 0774);
        } finally {
          umask($oldMask);
        }
      }
    }

    // Verificamos através do código de status HTTP se temos conteúdo na
    // resposta
    if (HTTPStatusCodes::canHaveBody($httpStatusCode)) {
      // Separamos os cabeçalhos e o corpo da resposta
      $headers = substr($rawResponse, 0, $headerSize);
      $body    = substr($rawResponse, $headerSize);

      // Obtemos uma matriz com os cabeçalhos
      $headers = explode("\r\n\r\n", $headers);
      $headers = reset($headers);
      $headers = explode("\r\n", $headers);
    } else {
      $headers = [];
      $body    = '';
    }

    // Montamos o conteúdo da resposta
    $result = [
      'timestamp' => time(),
      'exectime'  => $this->getHumanTime((int) round($executionTime)),
      'http_code' => $httpStatusCode,
      'request'   => $request,
      'response'  => [
        'headers' => $headers,
        'body'    => $body
      ],
      'details'     => $details
    ];

    if ($this->debug) {
      $parts = parse_url($url);
      $debugFileName = ''
        . $this->cookiePath . '/'
        . urlencode(strtolower($parts['host']))
        . '_' . time()
        .'.debug'
      ;

      $debugFile = new SplFileObject($debugFileName, 'w');

      while (!$debugFile->flock(LOCK_EX)) {
        usleep(1);
      }

      $debugFile->fwrite(
        json_encode($result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
      );
      $debugFile->flock(LOCK_UN);
    }

    if ($hasError) {
      if ($httpError) {
        // Dispara uma exceção HTTP em função do código de status
        throw new HTTPException($url, $method, $httpStatusCode);
      } else {
        // Dispara uma exceção
        throw new cURLException($url, $method, $curlErrorCode);
      }
    }

    // A requisição foi realizada com sucesso, então retornamos o
    // resultado
    return $result;
  }
}
