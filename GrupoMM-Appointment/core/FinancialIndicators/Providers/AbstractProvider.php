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
 * A interface para um provedor de indicadores financeiros.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\FinancialIndicators\Providers;

use Core\FinancialIndicators\Indicators\Indicator;
use Core\HTTP\HTTPClient;

abstract class AbstractProvider
{
  /**
   * O nome do provedor de indicadores financeiros.
   *
   * @var string
   */
  protected $name = 'unknown';

  /**
   * A URL base para acesso ao provedor.
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
   * O sistema de requisições através do protocolo HTTP.
   *
   * @var HTTPClient
   */
  protected $http;

  /**
   * O caminho onde serão armazenados os cookies.
   *
   * @var string
   */
  protected $path;

  /**
   * O cache com os dados do provedor
   *
   * @var array
   */
  private $cacheData = [];

  /**
   * O construtor do provedor de indicadores financeiros.
   * 
   * @param string $path
   *   O caminho onde serão armazenados os cookies
   */
  public function __construct(string $path)
  {
    $this->path = $path;
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
   * Obtém o nome do provedor de indicadores financeiros.
   *
   * @return string
   */
  public function getProviderName(): string
  {
    return $this->name;
  }

  /**
   * Obtém os dados do provedor de indicadores financeiros.
   *
   * @param string $URL
   *   A URL base para acesso aos dados deste provedor
   * 
   * @return array
   */
  public function getProviderContent(string $URL): array
  {
    // Informa que não teremos cache, já que estamos fazendo uma
    // solicitação para um serviço WEB
    $this->http->addHeader('Cache-Control', 'no-cache');

    if (!array_key_exists($URL, $this->cacheData)) {
      // Realiza a requisição
      $response = $this->http->sendRequest($URL);

      $this->cacheData[$URL] = $this->parse($response['response']['body']);
    }

    return $this->cacheData[$URL];
  }

  /**
   * Faz o parse do conteúdo deste provedor.
   *
   * @param string $content
   *   O conteúdo obtido à partir da página do provedor.
   *
   * @throws UnexpectedValueException
   *   Em caso de algum problema na estrutura do documento
   *
   * @return array
   *   Os dados obtidos.
   */
  abstract protected function parse(string $content): array;

  /**
   * Obtém os valores mais recentes de cada indicador financeiro.
   *
   * @return array
   */
  abstract public function getLatestIndicators(): array;

  /**
   * Obtém os indicadores para o mês atual.
   *
   * @return array
   */
  abstract public function getIndicatorsForCurrentMonth(): array;
  
  /**
   * Obtém os índices disponíveis através do código do indicador
   * financeiro.
   *
   * @param int $indicatorCode
   *   O código do indicador financeiro
   *
   * @return array
   *   Os índices deste indicador financeiro
   */
  abstract public function getIndexesFromIndicatorCode(
    int $indicatorCode
  ): array;

  /**
   * Obtém o índice atual através do código do indicador
   * financeiro.
   *
   * @param int $indicatorCode
   *   O código do indicador financeiro
   *
   * @return null|Indicator
   *   O índice atual deste indicador financeiro, ou nulo se o mesmo não
   *   estiver disponível
   */
  abstract public function getCurrentIndexFromIndicatorCode(
    int $indicatorCode
  ): ?Indicator;
}
