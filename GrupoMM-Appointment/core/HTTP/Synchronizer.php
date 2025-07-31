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
 * A interface para o sistema de sincronismo de dados usando uma API de
 * um serviço genérico de um provedor externo.
 */

namespace Core\HTTP;

use Core\HTTP\Filters\DataFilter;

interface Synchronizer
{
  /**
   * O método responsável por identificar o caminho para o recurso na
   * URL de nossa requisição.
   *
   * @param string $path
   *   O caminho para o recurso
   */
  public function setURI(string $path): void;

  /**
   * O método responsável por definir um filtro para limitar os dados
   * em cada requisição.
   *
   * @param DataFilter $filter
   *   Os valores a serem utilizados como filtro para limitar cada
   * requisição.
   */
  public function setFilterParameter(DataFilter $filter): void;

  /**
   * O método responsável por preparar os parâmetros de nossa requisição
   * antes do início do sincronismo.
   *
   * @param array $parameters
   *   Uma matriz com os parâmetros adicionais necessários (Opcional).
   */
  public function prepareParameters(array $parameters = []): void;

  /**
   * O método responsável por recuperar o local onde a API armazena os
   * cookies.
   *
   * @return string
   *   O local (caminho) onde está armazenado os cookies
   */
  public function getCookiePath(): string;

  /**
   * O método responsável por definir um tempo de atraso entre
   * requisições, limitando a quantidade de requisições por minuto.
   *
   * @param int $delay
   *   O tempo de atraso (em segundos) entre cada requisição.
   */
  public function setDelay(int $delay): void;

  /**
   * O método responsável por definir a quantidade máxima de tentativas
   * de uma mesma requisição em caso de erro.
   *
   * @param int $maxTryCount
   *   A quantidade máxima de tentativas.
   */
  public function setMaxTryCount(int $maxTryCount): void;

  /**
   * O método responsável por aguardar um tempo entre requisições, se
   * configurado.
   *
   * @param int $delay
   *   O tempo de atraso entre requisições (em segundos). Se não
   * informado, usará o valor definido em $this->delay.
   */
  public function waitingTimeBetweenRequisitions(int $delay = 0): void;

  /**
   * Atualiza a exibição do progresso do processamento.
   *
   * @param string $message
   *   A mensagem de progresso
   */
  public function updateProgress(string $message): void;

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
   */
  public function sendRequest(string $path, array $params): array;

  /**
   * O método responsável por executar as requisições usando a API do
   * serviço, solicitando os dados.
   */
  public function synchronize(): void;

  /**
   * O método que nos permite adicionar uma função a ser executada na
   * inicialização dos parâmetros a cada nova iteração do filtro.
   *
   * @param callable $callback
   *   A função a ser executada
   */
  public function setOnInitParameters(callable $callback): void;

  /**
   * O método que nos permite adicionar uma função a ser executada antes
   * da requisição dos dados ao serviço STC.
   *
   * @param callable $callback
   *   A função a ser executada
   */
  public function setBeforeRequest(callable $callback): void;

  /**
   * O método que nos permite adicionar uma função a ser executada após
   * o processamento dos dados.
   *
   * @param callable $callback
   *   A função a ser executada
   */
  public function setAfterProcess(callable $callback): void;

  /**
   * O método que nos permite adicionar uma função a ser executada no
   * processamento dos dados recebidos.
   *
   * @param callable $callback
   *   A função a ser executada
   */
  public function setOnDataProcessing(callable $callback): void;
}
