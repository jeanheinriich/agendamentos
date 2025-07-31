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

use Core\HTTP\Progress\ProgressInterface;
use Core\Logger\LoggerTrait;
use Psr\Log\LoggerInterface;

abstract class AbstractTask
{
  /**
   * Os métodos para registro de eventos em Logs
   */
  use LoggerTrait;

  /**
   * A instância do sistema de logs.
   *
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * O caminho para o nosso serviço à partir da URL presente na API e
   * que permite a execução desta tarefa.
   *
   * @var string
   */
  protected $path;

  /**
   * O caminho para nosso serviço de verificação do comando na fila de
   * execuções.
   *
   * @var string
   */
  protected $queuePath;

  /**
   * Os parâmetros de nossa requisição.
   *
   * @var array  Os parâmetros de nossa requisição.
   */
  protected $parameters = [];

  /**
   * A identificação do comando na fila de comandos do serviço
   *
   * @var mixed
   */
  protected $queueID;

  /**
   * O tempo de espera em segundos a ser aguardado após a requisição
   * ser realizada. 
   * 
   * @var integer
   */
  protected $waitingTimeAfterRequisition = 0;

  /**
   * O nome desta tarefa.
   * 
   * @var string
   */
  protected $taskName = 'Tarefa genérica';

  /**
   * Uma mensagem a ser exibida enquanto é aguardado o tempo após a
   * requisição.
   * 
   * @var string
   */
  protected $waitingMessage = 'Aguardando tempo de espera';

  /**
   * A flag que indica que devemos executar o processamento. Por padrão
   * sempre executa o processamento. Caso uma tarefa precise, deve
   * modificar esta flag durante a preparação dos parâmetros.
   * 
   * @var boolean
   */
  protected $performProcessing = true;

  /**
   * A flag que indica que devemos permanecer no loop pois precisamos
   * reexecutar esta mesma tarefa.
   * 
   * @var boolean
   */
  protected $continueOnLoop = false;

  /**
   * A flag que indica que devemos verificar a execução do comando na
   * fila presente no serviço.
   * 
   * @var boolean
   */
  protected $verifyExecutionInQueue = false;

  /**
   * O construtor da tarefa.
   *
   * @param LoggerInterface $logger
   *   O sistema de registro de logs
   */
  public function __construct(LoggerInterface $logger)
  {
    $this->logger = $logger;
    unset($logger);
  }

  /**
   * Recupera o nome desta tarefa.
   *
   * @return string
   *   A descrição
   */
  public function getName(): string
  {
    return $this->taskName;
  }

  /**
   * Recupera a mensagem a ser exibida quando estiver sendo aguardado o
   * tempo após requisição.
   *
   * @return string
   *   A descrição
   */
  public function getWaitingMessage(): string
  {
    return $this->waitingMessage;
  }

  /**
   * Recupera o caminho para o serviço a ser executado nesta tarefa.
   *
   * @return string
   *   O caminho para o serviço
   */
  public function getPath(): string
  {
    return $this->path;
  }

  /**
   * Recupera o caminho para a verificação da execução do comando na
   * fila presente na API do serviço
   *
   * @return string
   *   O caminho para o serviço de verificação do comando
   */
  public function getQueuePath(): string
  {
    return $this->queuePath;
  }

  /**
   * Prepara os parâmetros de nossa requisição antes de sua solicitação.
   * Tarefas que dependam de informações adicionais provenientes de
   * tarefas anteriores fazem uso do parâmetro $processingData para
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
    array $processingData): array
  {
    // Adiciona os parâmetros enviados com os parâmetros desta tarefa
    $this->parameters = array_merge($this->parameters, $parameters);
    
    return $this->parameters;
  }

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
  public function prepareQueueParameters(array $parameters = []): array
  {
    // Adiciona os parâmetros enviados com os parâmetros desta tarefa
    $this->parameters = array_merge($this->parameters, $parameters);
    
    return $this->parameters;
  }

  /**
   * Recupera a indicação se a tarefa necessita ser processada. É usado
   * em caso de uma tarefa depender de tarefas anteriores para as quais
   * decidiremos se esta tarefa precisa ser executada.
   * 
   * @return bool
   */
  public function mustBeProcessed(): bool
  {
    return $this->performProcessing;
  }

  /**
   * Recupera a indicação se a tarefa necessita de uma verificação da
   * execução do comando na fila de comandos do serviço.
   * 
   * @return bool
   */
  public function mustBeVerifyExecutionInQueue(): bool
  {
    return $this->verifyExecutionInQueue;
  }

  /**
   * Recupera o tempo a ser aguardado depois de executarmos a requisição
   * desta tarefa.
   *
   * @result int
   *   Um tempo (em segundos) a ser aguardado após ser realizada a
   *   requisição (0 não aguarda)
   */
  public function getWaitingTimeAfterRequisition(): int
  {
    return $this->waitingTimeAfterRequisition;
  }

  /**
   * Determina se a tarefa deve ser executada novamente.
   * 
   * @return bool
   */
  public function reexecute(): bool
  {
    return $this->continueOnLoop;
  }

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
    callable $progress): void
  {
    // Normalmente não faz nada, pois os comandos comuns não lidam com
    // uma fila de execução. Apenas comandos que precisem aguardar a
    // comunicação com o dispositivo dependem deste trabalho
  }

  /**
   * Lida com a resposta do comando presente na fila. Irá repetir a
   * requisição enquanto a resposta for ResponseStatus::REPEAT
   *
   * @param mixed $response
   *   O conteúdo da resposta à nossa requisição
   * @param callable $progress
   *   A rotina de atualização do progresso do processamento
   *
   * @return QueueResponseStatus
   *   Retorna um valor possível de QueueResponseStatus
   */
  public function handleQueueResponse($response,
    callable $progress): int
  {
    // Normalmente não faz nada, e apenas prossegue o processamento
    return QueueResponseStatus::CONTINUE;
  }

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
  abstract public function process($response, callable $progress,
    array &$processingData): void;
}
