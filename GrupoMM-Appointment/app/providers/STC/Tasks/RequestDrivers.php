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
 * todos os motoristas cadastrados no sistema. Esta tarefa está
 * parcialmente executada, apenas com as informações de requisição. Não
 * esquecer de que precisa do usuário e senha do cliente. Todavia,
 * precisa incluir os campos de login e senha de sub-conta quando o
 * cliente tiver isto definido para permitir a correta obtenção de dados
 * dos motoristas cadastrados. Usar o CPF como campo de busca.
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

class RequestDrivers
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
  protected $path = 'ws/motorist/getallmotorists';

  /**
   * Uma breve descrição do que esta tarefa faz
   * 
   * @var string
   */
  protected $descr = "Sincronismo de informações de motoristas "
    . "cadastrados no sistema"
  ;

  /**
   * O nome desta tarefa.
   * 
   * @var string
   */
  protected $taskName = 'Sincronismo de motoristas cadastrados no '
    . 'sistema'
  ;

  /**
   * Prepara os parâmetros de nossa requisição antes do início da
   * tarefa. Neste caso, seleciona todos os ID's de motorista a ser
   * inseridos no dispositivo e adiciona aos parâmetros da requisição.
   * Este processo é realizado uma única vez.
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
    $this->performProcessing = true;

    // Adicionamos aos parâmetros
    $parameters['user'] = 'bandeira';
    $parameters['pass'] = md5('ba0159');

    // Segue com a preparação normal dos parâmetros
    return parent::prepareParameters($parameters, $processingData);
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
    $this->debug("Analisando os dados de motoristas armazenados no "
      . "sistema e determinando as modificações necessárias.", [ ]
    );

    // Os dados do dispositivo estão armazenados em 'data'
    $deviceData = $response['data'];

    var_dump($deviceData); exit();
  }
}
