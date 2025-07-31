<?php
/*
 * This file is part of STC Integration Library.
 *
 * (c) Emerson Cavalcanti
 *
 * LICENSE: The MIT License (MIT)
 * Permission is hereby granted, free of charge, to any person obtaining
 *
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
 * Tarefa que realiza uma requisição à API do sistema STC, eliminando
 * todos os ID's de motoristas cadastrados no equipamento.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * API STC
 *
 * http://ap1.stc.srv.br/docs/
 *
 * Copyright (c) 2017 - STC Tecnologia <www.stctecnologia.com.br>
 */

namespace App\Providers\STC\Tasks;

use Core\HTTP\AbstractTask;
use Core\HTTP\QueueResponseStatus;
use Core\HTTP\Task;
use RuntimeException;

class DeleteAllDriversStoredInEquipment
  extends AbstractTask
  implements Task
{
  /**
   * A URI para o serviço que nos permite remover todas as informações
   * de ID's de motoristas cadastrados no equipamento.
   *
   * @var string
   */
  protected $path = 'ws/device/sgbras/deletealldriverid';

  /**
   * O tempo de espera em segundos a ser aguardado depois da requisição
   * ser realizada. Em média o recebimento dos dados leva em torno de
   * 1 minuto. Então o próximo serviço somente é executado após
   * decorrido este intervalo.
   * 
   * @var integer
   */
  protected $waitingTimeAfterRequisition = 1 * 60;

  /**
   * A URI para o serviço que nos permite verificar a execução do
   * comando de remoção.
   *
   * @var string
   */
  protected $queuePath = 'ws/device/sgbras/getcommandpendent';

  /**
   * O nome desta tarefa.
   * 
   * @var string
   */
  protected $taskName = 'Remoção de todos os IDs cadastrados no '
    . 'equipamento'
  ;

  /**
   * Uma mensagem a ser exibida enquanto é aguardado o tempo após a
   * requisição.
   * 
   * @var string
   */
  protected $waitingMessage = "Aguardando a remoção dos ID's no "
    . "equipamento"
  ;

  /**
   * A flag que indica que devemos verificar a execução do comando na
   * fila presente no serviço.
   * 
   * @var boolean
   */
  protected $verifyExecutionInQueue = false;

  /**
   * A quantidade total de registros sendo processados
   * 
   * @var null|integer
   */
  protected $total = 1;

  /**
   * A quantidade de itens já processados.
   * 
   * @var integer
   */
  protected $done = 0;

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
    // Adiciona aos parâmetros enviados o ID do comando na fila
    $this->parameters = array_merge(
      [ 'commandId' => $this->queueID ],
      $parameters
    );
    
    return $this->parameters;
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
    // Na resposta, obtemos o ID do comando, que precisa ser tratado
    $this->queueID = $response['data'][0];

    $this->debug("Aguardando execução do comando {commandID} para "
      . "remoção de todos os ID's de motoristas do dispositivo ID "
      . "{deviceID}",
      [ 'commandID' => $this->queueID,
        'deviceID' => $this->parameters['deviceId'] ]
    );
    $progress($this->done, $this->total, $this->taskName);
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
  public function process($response, callable $progress,
    array &$processingData): void
  {
    $progress($this->done, $this->total, $this->taskName);

    $this->info("Removido todos os ID's de motorista do dispositivo "
      . "ID {deviceID}",
      [ 'deviceID' => $this->parameters['deviceId'] ]
    );

    // TODO: Precisamos passar, de alguma forma, a informação para que
    // o cliente possa atualizar o conjunto de dados exibidos na tela
    $this->done++;
    $progress($this->done, $this->total, $this->taskName);
  }
}
