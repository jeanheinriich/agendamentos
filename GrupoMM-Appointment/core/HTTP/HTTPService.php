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
 * Um sistema de conexão com uma API externa através do protocolo HTTP.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\HTTP;

use Core\Exceptions\JSONException;

class HTTPService
  implements APIInterface
{
  /**
   * O sistema de requisições através do protocolo HTTP.
   *
   * @var HTTPClient
   */
  protected $http;

  /**
   * A URL base para acesso ao serviço.
   *
   * @var string
   */
  protected $baseURL;

  /**
   * O método HTTP para acesso ao serviço.
   *
   * @var string
   */
  protected $method;

  /**
   * O caminho onde serão armazenados os cookies.
   *
   * @var string
   */
  protected $path;

  /**
   * O construtor do serviço de conexão com a API
   *
   * @param string $url
   *   A URL base para acesso à API do serviço
   * @param string $method
   *   O método HTTP a ser utilizado
   * @param string $path
   *   O caminho onde serão armazenados os cookies
   */
  public function __construct(string $URL, string $method, string $path)
  {
    $this->baseURL = $URL;
    $this->method  = $method;
    $this->path    = $path;

    $this->http = new HTTPClient($path);
  }

  /**
   * Define a URI de requisição através da junção da URL base com o
   * caminho para o recurso solicitado dentro deste serviço.
   *
   * @param string $path
   *   O caminho para o recurso desejado
   *
   * @return string
   *   A URI de requisição
   */
  public function getURI(string $path): string
  {
    return sprintf("%s/%s", $this->baseURL, $path);
  }

  /**
   * Recupera o caminho para armazenamento dos cookies.
   *
   * @return string
   */
  public function getCookiePath(): string
  {
    return $this->path;
  }

  /**
   * Envia uma requisição por HTTP para o serviço desejado.
   *
   * @param string $path
   *   O caminho para o recurso
   * @param array $params
   *   Os parâmetros enviados com nossa requisição
   *
   * @return array
   *   A matriz com os valores obtidos
   *
   * @throws cURLException
   *   Em caso de falhas no cURL
   * @throws HTTPException
   *   Em caso de falhas HTTP
   * @throws InvalidArgumentException
   *   Em caso de argumentos inválidos
   * @throws RuntimeException
   *   Em caso de erros de execução
   * @throws JSONException
   *   Em caso de erros no JSON
   */
  public function sendRequest(string $path, array $params = []): array
  {
    // Informa que não teremos cache, já que estamos fazendo uma
    // solicitação para um serviço WEB
    $this->http->addHeader('Cache-Control', 'no-cache');

    // Informa que estamos requisitando dados no formato JSON
    $this->http->addHeader('Content-Type', 'application/json');

    // Habilita a depuração. Neste caso, o conteúdo da requisição é
    // colocado em um arquivo na mesma pasta onde o cookie é armazenado
    //$this->http->setDebug(true);

    // Habilita o modo verboso. Neste caso o detalhamento da conexão é
    // colocado em um arquivo na mesma pasta onde o cookie é armazenado
    // $this->http->setVerbose(true);

    // Determina a URI da requisição
    $uri = $this->getURI($path);

    // Realiza a requisição
    $result = $this->http->sendRequest($uri, $this->method, $params);

    // Convertemos os dados recebidos
    $data = json_decode($result['response']['body'], true);

    // Lidamos com os erros de JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new JSONException(json_last_error());
    }

    return $data;
  }
}
