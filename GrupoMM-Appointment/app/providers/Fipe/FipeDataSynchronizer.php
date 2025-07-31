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
 * Realiza as requisições de dados usando a API da Fipe, permitindo a
 * obtenção de informações de marcas, modelos e valores de veículos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * API Fipe
 * https://deividfortuna.github.io/fipe/
 *
 * Copyright (c) 2015 - Deivid Fortuna <deividfortuna@gmail.com>
 */

namespace App\Providers\Fipe;

use Core\HTTP\AbstractSynchronizer;
use Core\HTTP\Synchronizer;

class FipeDataSynchronizer
  extends AbstractSynchronizer
  implements Synchronizer
{
  /**
   * O campo que armazena os dados a serem processados na resposta.
   *
   * @var string
   */
  protected $fieldData = '';

  /**
   * Analisa a resposta e determina uma ação.
   *
   * @param mixed $response
   *   O conteúdo da resposta à nossa requisição
   *
   * @return array
   *   Uma matriz contendo a ação a ser tomada e uma mensagem indicando
   * o que ocorreu. As respostas possíveis são:
   *   - Abort: interrompe todo o processamento;
   *   - GoNext: avança para o próximo parâmetro de filtragem
   *   - Process: processa o conteúdo recebido;
   *   - TryAgain: repete a mesma requisição para tentar obter os dados;
   */
  protected function handleResponse($response): array
  {
    $action = 'Abort';
    $message = "A requisição não retornou uma resposta válida";

    if (is_array($response)) {
      if (empty($this->fieldData)) {
        if (count($response) > 0) {
          $action = "Process";
          $message = "A requisição foi bem-sucedida.";
        } else {
          $action = 'GoNext';
          $message = "A requisição não retornou dados para processar. "
            . "Ignorando esta solicitação."
          ;
        }
      } else {
        if (array_key_exists($this->fieldData, $response)) {
          if (count($response[$this->fieldData]) > 0) {
            $action = "Process";
            $message = "A requisição foi bem-sucedida.";
          } else {
            $action = 'GoNext';
            $message = "A requisição não retornou dados para "
              . "processar. Ignorando esta solicitação."
            ;
          }
        }
      }
    }

    // Registra no log
    $this->info($message, [ ]);

    return [
      $action, $message
    ];
  }

  /**
   * Processa os dados caso a resposta seja válida.
   *
   * @param mixed $response
   *   O conteúdo da resposta à nossa requisição
   * @param int $filterCount
   *   O número de iterações em nosso filtro
   * @param array $filter
   *   O conteúdo do filtro aplicado
   */
  protected function process($response, int $filterCount,
    array $filter): void
  {
    // Obtém os dados
    if (empty($this->fieldData)) {
      $data = $response;
    } else {
      $data = $response[$this->fieldData];
    }

    // Determina a quantidade de registros recuperados
    $amount = count($data);

    // Indica que não devemos repetir
    $this->repeat = false;

    if ($amount === 0) {
      $this->debug("Não foram recuperados registros nesta "
        . "requisição.",
        [  ]
      );

      // Aguarda um tempo entre requisições, se necessário
      $this->waitingTimeBetweenRequisitions();

      return;
    }

    // Determinamos a forma de cálculo do progresso
    $calcAsFractionalPortion = false;
    if ($this->startedProgress) {
      // Estes dados representam uma fração do que estamos processando
      // e precisamos calcular o progresso como uma fração
      $calcAsFractionalPortion = true;
    } else {
      // Iniciamos a análise de progresso, pois não estamos usando um
      // filtro de dados e o que iremos processar corresponde ao total
      // do que irá ser processado
      $this->total = floatval($amount);
      $this->initProgress('Iniciando...');
    }

    $this->info("Recebido(s) {amount} registro(s) para processar.",
      [ 'amount' => $amount ]
    );

    // Percorremos os dados a serem processados
    foreach ($data as $row => $value) {
      // Verificamos se temos uma função para processamento dos dados
      if (isset($this->onDataProcessing)) {
        // Executamos a função que manipula os dados a serem
        // processados
        call_user_func($this->onDataProcessing, $value, $filter);
      }

      // Atualiza o progresso
      if ($calcAsFractionalPortion) {
        // Estes dados representam uma fração do que estamos
        // processando, então calcula
        $this->done = $filterCount
          + ((($row + 1) * 100) / $amount) / 100;
      } else {
        // O dado sendo processado corresponde à quantidade de dados
        // já processados
        $this->done = $row;
      }
      $this->updateProgress("Processando...");
    }

    // Atualizamos o progresso no final
    if ($calcAsFractionalPortion) {
      $this->done = ($filterCount + 1);
    } else {
      $this->done = $amount;
    }
    $this->updateProgress("Processando...");
  }


  // ==========================[ Métodos para lidar com Callbacks ]=====

  /**
   * O método que nos permite adicionar o nome do campo que contém os
   * dados a serem processados.
   *
   * @param string $field
   *   O nome do campo que armazena os dados a serem processados
   */
  public function setFieldData(string $field): void
  {
    $this->fieldData = $field;
  }
}
