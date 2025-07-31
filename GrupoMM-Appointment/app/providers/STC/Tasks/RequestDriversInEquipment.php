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
 * Tarefa que realiza uma requisição à API do sistema STC, requisitando
 * todos os motoristas cadastrados no equipamento.
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
use Core\HTTP\Task;
use RuntimeException;

class RequestDriversInEquipment
  extends AbstractTask
  implements Task
{
  /**
   * A URI para o serviço que nos permite requisitar à STC que atualize
   * as informações de ID's de motoristas cadastrados no equipamento e
   * as armazene para consulta. Este serviço não retorna nada e dispende
   * um tempo para ser executado, já que envia ao rastreador o pedido e
   * aguarda a transmissão dos dados nele armazenados.
   *
   * @var string
   */
  protected $path = 'ws/device/sgbras/getalldriverid';

  /**
   * O tempo de espera em segundos a ser aguardado depois da requisição
   * ser realizada. Em média o recebimento dos dados leva em torno de
   * 4 minutos (acrescentamos mais 2 minutos por tolerância). Então o
   * próximo serviço somente é executado após decorrido este intervalo.
   *
   * Atenção: Retirei 2 minutos em 01/06/2022
   * 
   * @var integer
   */
  protected $waitingTimeAfterRequisition = 4 * 60;

  /**
   * O nome desta tarefa.
   * 
   * @var string
   */
  protected $taskName = 'Requisição dos dados cadastrados no '
    . 'equipamento'
  ;

  /**
   * Uma mensagem a ser exibida enquanto é aguardado o tempo após a
   * requisição.
   * 
   * @var string
   */
  protected $waitingMessage = "Aguardando o sistema obter os dados "
    . "cadastrados neste equipamento"
  ;

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
    // Como apenas requisita os dados, e a resposta apenas indica se
    // tivemos sucesso na requisição, então apenas prossegue normalmente
    $this->debug("Requisitados os dados de motoristas ao dispositivo "
      . "ID {deviceID}",
      [ 'deviceID' => $this->parameters['deviceId'] ]
    );
  }
}
