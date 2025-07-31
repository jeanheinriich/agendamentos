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
 * ID's de motoristas cadastrados em duplicidade no equipamento.
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

class DeleteDriversStoredInEquipment
  extends AbstractTask
  implements Task
{
  /**
   * A URI para o serviço que nos permite remover as informações de ID's
   * de motoristas cadastrados em duplicidade no equipamento.
   *
   * @var string
   */
  protected $path = 'ws/device/sgbras/deletedriverid';

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
  protected $taskName = 'Remoção de IDs cadastrados em duplicidade no '
    . 'equipamento'
  ;

  /**
   * Uma mensagem a ser exibida enquanto é aguardado o tempo após a
   * requisição.
   * 
   * @var string
   */
  protected $waitingMessage = 'Aguardando a remoção do ID no '
    . 'equipamento'
  ;

  /**
   * A flag que indica que devemos verificar a execução do comando na
   * fila presente no serviço.
   * 
   * @var boolean
   */
  protected $verifyExecutionInQueue = true;

  /**
   * O ID do motorista que estamos analisando
   * @var integer
   */
  protected $driverID = 0;

  /**
   * A quantidade total de registros sendo processados
   * 
   * @var null|integer
   */
  protected $total = null;

  /**
   * A quantidade de itens já processados.
   * 
   * @var integer
   */
  protected $done = 0;

  /**
   * Prepara os parâmetros de nossa requisição antes do início da
   * tarefa. Neste caso, seleciona o primeiro ID de motorista a ser
   * removido do dispositivo e adiciona aos parâmetros da requisição.
   * Este processo é repetido até que todos os ID's sejam processados.
   *
   * @param array $parameters
   *   Uma matriz com os parâmetros iniciais.
   * @param array $processingData
   *   Uma matriz com os dados de processamento
   *
   * @return array
   *   Os parâmetros para a requisição
   */
  public function prepareParameters(array $parameters = [],
    array $processingData): array
  {
    $this->performProcessing = false;

    // Verificamos se temos ID's de motoristas em duplicidade para serem
    // removidos do equipamento
    if (is_array($processingData['driversToBeRemove']) &&
        count($processingData['driversToBeRemove']) > 0) {
      $this->performProcessing = true;

      if (is_null($this->total)) {
        // Armazenamos a quantidade de dados sendo processados para
        // permitir o controle de avanço
        $this->total = count($processingData['driversToBeRemove']);
        $this->done  = 0;
      }

      // Adicionamos aos parâmetros o primeiro elemento 
      $this->driverID = reset($processingData['driversToBeRemove']);
      $parameters['driverId'] = [ $this->driverID ];
      $this->debug("Iniciando execução do comando de remoção do ID "
        . "de motoristas {driverID} no dispositivo ID {deviceID}",
        [ 'driverID' => $this->driverID,
          'deviceID' => $this->parameters['deviceId'] ]
      );
    }

    // Segue com a preparação normal dos parâmetros
    return parent::prepareParameters($parameters, $processingData);
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

    $this->debug("Aguardando execução do comando {commandID} de "
      . "remoção do ID de motorista {driverID} do dispositivo ID "
      . "{deviceID}",
      [ 'commandID' => $this->queueID,
        'driverID' => $this->driverID,
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

    $this->info("Removido o ID de motorista {driverID} do dispositivo "
      . "ID {deviceID}",
      [ 'driverID' => $this->driverID,
        'deviceID' => $this->parameters['deviceId'] ]
    );

    // Retira o ID que processamos e analisa se temos mais ID's a serem
    // processados
    array_shift($processingData['driversToBeRemove']);
    $this->continueOnLoop =
      (count($processingData['driversToBeRemove']) > 0)
        ? true
        : false
    ;

    // TODO: Precisamos passar, de alguma forma, a informação para que
    // o cliente possa atualizar o conjunto de dados exibidos na tela
    $this->done++;
    $progress($this->done, $this->total, $this->taskName);
  }
}
