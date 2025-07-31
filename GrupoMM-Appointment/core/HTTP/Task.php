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
 * A interface para uma tarefa, que permite executar uma parte de um
 * processo sequencial. Várias tarefas são encadeadas para que o
 * processo possa ser executado.
 */

namespace Core\HTTP;

interface Task
{
  /**
   * Recupera o nome desta tarefa.
   *
   * @return string
   *   A descrição
   */
  public function getName(): string;

  /**
   * Recupera a mensagem a ser exibida quando estiver sendo aguardado o
   * tempo após requisição.
   *
   * @return string
   *   A descrição
   */
  public function getWaitingMessage(): string;

  /**
   * Recupera o caminho para o serviço a ser executado nesta tarefa.
   *
   * @return string
   *   O caminho para o serviço
   */
  public function getPath(): string;

  /**
   * Recupera o caminho para a verificação da execução do comando na
   * fila presente na API do serviço
   *
   * @return string
   *   O caminho para o serviço de verificação do comando
   */
  public function getQueuePath(): string;

  /**
   * Prepara os parâmetros de nossa requisição antes do início da
   * tarefa. Tarefas que dependam de informações adicionais provenientes
   * de tarefas anteriores fazem uso do parâmetro $processingData para
   * receber estes dados.
   *
   * @param array $parameters
   *   Uma matriz com os parâmetros iniciais.
   * @param array $processingData
   *   Uma matriz com os dados de processamento proveniente de tarefas
   *   anteriores
   *
   * @return array
   *   Os parâmetros para a requisição
   */
  public function prepareParameters(array $parameters = [],
    array $processingData): array;

  /**
   * Prepara os parâmetros de nossa verificação na fila antes de sua
   * solicitação.
   *
   * @param array $parameters
   *   Uma matriz com os parâmetros iniciais.
   *
   * @return array
   *   Os parâmetros para a requisição de verificação da fila
   */
  public function prepareQueueParameters(array $parameters = []): array;

  /**
   * Recupera a indicação se a tarefa necessita ser processada. É usado
   * em caso de uma tarefa depender de tarefas anteriores para as quais
   * decidiremos se esta tarefa precisa ser executada.
   * 
   * @return bool
   */
  public function mustBeProcessed(): bool;

  /**
   * Recupera a indicação se a tarefa necessita de uma verificação da
   * execução do comando na fila de comandos do serviço.
   * 
   * @return bool
   */
  public function mustBeVerifyExecutionInQueue(): bool;

  /**
   * Recupera o tempo a ser aguardado depois de executarmos a requisição
   * desta tarefa.
   *
   * @result int
   *   Um tempo (em segundos) a ser aguardado após ser realizada a
   *   requisição (0 não aguarda)
   */
  public function getWaitingTimeAfterRequisition(): int;

  /**
   * Determina se a tarefa deve ser executada novamente.
   * 
   * @return bool
   */
  public function reexecute(): bool;

  /**
   * Obtém informações sobre a execução do comando caso a resposta seja
   * válida.
   *
   * @param mixed $response
   *   O conteúdo da resposta à nossa requisição
   * @param callable $progress
   *   A rotina de atualização do progresso do processamento
   */
  public function handleRequestResponse($response,
    callable $progress): void;

  /**
   * Processa os dados caso a resposta seja válida.
   *
   * @param mixed $response
   *   O conteúdo da resposta à nossa requisição
   * @param callable $progress
   *   A rotina de atualização do progresso do processamento
   * @param array $processingData
   *   Uma matriz com os dados de processamento
   */
  public function process($response, callable $progress,
    array &$processingData): void;
}
