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
 * Realiza as requisições de dados usando a API da STC, permitindo a
 * obtenção e o sincronismo de informações e o envio de comandos.
 */

/**
 * API STC
 *
 * http://ap1.stc.srv.br/docs/
 *
 * Copyright (c) 2017 - STC Tecnologia <www.stctecnologia.com.br>
 */

namespace App\Providers\STC;

use Core\HTTP\AbstractSynchronizer;
use Core\HTTP\Synchronizer;

class STCDataSynchronizer
  extends AbstractSynchronizer
  implements Synchronizer
{
  /**
   * A chave de acesso de cliente ao sistema STC
   *
   * @var string
   */
  protected $key;

  /**
   * A flag indicativa de que devemos exibir o progresso durante o
   * processamento.
   *
   * @var boolean
   */
  protected $showProgressDuringProcessing = true;

  /**
   * A flag indicativa de que devemos lidar com a resposta de forma
   * paginada.
   *
   * @var boolean
   */
  protected $handlePages = false;

  /**
   * O contador indicativo da página que estamos processando.
   *
   * @var int
   */
  protected $pageCount = 0;

  /**
   * O tamanho de cada página sendo processada.
   *
   * @var int
   */
  protected $pageSize = 0;

  /**
   * O número total de páginas sendo processadas.
   *
   * @var int
   */
  protected $totalOfPages = 0;

  /**
   * O número total de registros sendo processados em todas as páginas.
   *
   * @var int
   */
  protected $totalOfData = 0;

  /**
   * A flag indicativa de que devemos repetir a requisição quando os
   * dados recebidos completarem o valor máximo por requisição,
   * indicando que podemos ter novas informações ainda a serem
   * recebidas, já que o sistema limita nossa requisição a um valor
   * fixo.
   *
   * @var bool
   */
  protected $repeatRequestIfNecessary = false;


  /**
   * O método responsável por definir a chave de acesso de cliente ao
   * sistema STC.
   *
   * @param string $key
   *   A chave de acesso
   */
  public function setKey(string $key): void
  {
    $this->key = $key;
  }

  /**
   * O método responsável por desabilitar o progresso durante o
   * processamento. Isto se faz necessário em algumas circunstâncias em
   * que o processamento está sendo realizado pela Web e temos uma
   * velocidade de processamento grande para um volume grande de dados,
   * e que não seriam corretamente exibidos no modo callback
   */
  public function disableProgressDuringProcessing(): void
  {
    $this->showProgressDuringProcessing = false;
  }

  /**
   * O método responsável por definir se devemos lidar com a paginação
   * do conteúdo de nossa resposta.
   *
   * @param bool $handlePages
   *   O indicativo se devemos (ou não), lidar com a paginação do
   * resultado
   */
  public function setHandlePages(bool $handlePages): void
  {
    $this->handlePages = $handlePages;
  }

  /**
   * O método responsável por indicar que devemos repetir a requisição
   * quando a quantidade de dados recebidos completarem o valor máximo
   * por requisição, indicando que podemos ter mais dados armazenados no
   * servidor.
   *
   * @param bool $value
   *   O indicativo se devemos repetir a requisição
   */
  public function setRepeatRequest(bool $value): void
  {
    $this->repeatRequestIfNecessary = $value;
  }

  /**
   * O método que nos permite setar manualmente o tamanho de uma página
   * e é utilizado em conjunto com 'repeatRequestIfNecessary' para
   * determinar se a solicitação obteve uma quantidade de dados limitada
   * ao tamanho de uma página.
   *
   * @param int $value
   *   O valor máximo de registros obtidos por requisição
   */
  public function setPageSize(int $value): void
  {
    $this->pageSize = $value;
  }

  /**
   * Interpola valores de contexto nos espaços reservados do caminho da
   * solicitação.
   *
   * @param array $context
   *   Os valores de contexto
   *
   * @return string
   *   O caminho com os valores de contexto substituídos.
   */
  protected function buildPath(array $context = []): string
  {
    // Prepara normalmente os parâmetros
    $path = parent::buildPath($context);

    if ($this->handlePages) {
      $this->calcProgressAsFractionalPortion = true;

      if ($this->pageCount > 1) {
        // Precisamos lidar com a paginação na requisição, acrescentando
        // o número da página requisitada
        $path .= sprintf("?page=%d", $context['page']);
      }
    }

    return $path;
  }

  /**
   * O método responsável por preparar os parâmetros de nossa requisição
   * antes do início do sincronismo.
   *
   * @param array $parameters
   *   Uma matriz com os parâmetros adicionais necessários (Opcional).
   */
  public function prepareParameters(array $parameters = []): void
  {
    // Sempre adiciona aos parâmetros a chave de cliente STC
    $this->parameters['key'] = $this->key;

    // Prepara normalmente os parâmetros
    parent::prepareParameters($parameters);
  }

  /**
   * Verifica se a matriz informada possui a estrutura de uma resposta
   * da API da STC.
   *
   * @param array $response
   *   O conteúdo da resposta à nossa requisição
   *
   * @return bool
   *   O indicativo de que nossa resposta é válida
   */
  protected function validateSTCResponse(array $response): bool
  {
    // 1. Valida se temos as chaves necessárias
    if ( !array_key_exists('success', $response) ||
         !array_key_exists('error', $response) ) {
      return false;
    }

    // 2. Validamos o campo success
    if (!is_bool($response['success'])) {
      return false;
    }

    // 3. Validamos o campo error
    if (!is_int($response['error'])) {
      return false;
    }

    // 4. Validamos o campo msg
    if ($response['error'] > 0) {
      if ( !is_string($response['msg']) &&
           !is_array($response['msg']) ) {
        return false;
      }
    }

    // 4. Validamos o campo data
    if ($response['error'] === 0) {
      if (!is_array($response['data'])) {
        return false;
      }
    }

    return true;
  }

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
      if (count($response) > 0) {
        // A API retornou uma matriz como resposta e que contém valores
        if ($this->validateSTCResponse($response)) {
          // O conteúdo retornado é uma resposta STC válida, então
          // analisamos o valor do campo 'success'
          if ($response['success'] === true) {
            $action = "Process";
            $message = "A requisição foi bem-sucedida.";
          } else {
            if ($response['error'] > 0) {
              // Ocorreu algum erro na requisição, e a mensagem contém o
              // possível erro
              if (is_array($response['msg'])) {
                $concatenatedMessage = '';
                foreach ($response['msg'] as $message) {
                  $concatenatedMessage .= ' ' . $message;
                }

                $errorMsg = trim($concatenatedMessage);
              } else {
                $errorMsg = trim($response['msg']);
              }

              // Analisa qual o erro ocorrido
              switch ($errorMsg) {
                case 'Limite de acessos atingido (20 segundos)':
                case 'Limite de acessos atingido (1 minutos)':
                  $action = 'TryAgain';
                  $message = $errorMsg . ". Repetindo requisição.";

                  break;
                default:
                  // Executa a função para os casos de nenhum dado
                  // recebido
                  $action = 'GoNext';
                  $message = "Ocorreu um erro na requisição: "
                    . $errorMsg . ". Ignorando esta solicitação."
                  ;

                  break;
              }
            }
          }
        }
      }
    }

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
    if ($this->handlePages) {
      // O conteúdo requisitado será devolvido no modo paginado, então
      // lidamos com isto
      $info = $response['data'];

      $this->pageCount = intval($info["current_page"]);

      if ($this->pageCount === 1) {
        // Armazenamos as informações de paginação para determinar o
        // progresso
        $this->pageSize = intval($info["per_page"]);
        $this->totalOfPages = intval($info["last_page"]);
        $this->totalOfData = intval($info["total"]);
      }

      $data = $info['data'];

      // Determina a quantidade de registros recuperados
      $amount = count($data);

      $this->debug("Processando página {page} de {pages}.",
        [ 'page' => $this->pageCount,
          'pages' => $this->totalOfPages ]
      );
      $this->info("Recebido(s) {amount} registro(s) de um total "
        . "de {total} para processar.",
        [ 'amount' => $amount,
          'total' => $this->totalOfData ]
      );
    } else {
      $data = $response['data'];

      // Determina a quantidade de registros recuperados
      $amount = count($data);

      $this->debug("Recebido(s) {amount} registro(s) para "
        . "processar.",
        [ 'amount' => $amount ]
      );
    }

    // Indica que não devemos repetir, inicialmente
    $this->repeat = false;

    if (!$this->startedProgress) {
      // Iniciamos a exibição do progresso, pois não estamos usando um
      // filtro de dados e o que iremos processar corresponde ao total
      // do que irá ser processado
      $this->debug("Iniciando progresso...");
      if ($this->handlePages) {
        $this->total = floatval($this->totalOfData);
      } else {
        $this->total = floatval($amount);
      }
      $this->done = 0.0;
      $this->initProgress('Iniciando...');
    }

    // Percorremos os dados a serem processados
    foreach ($data as $row => $value) {
      // Verificamos se temos uma função para processamento dos dados
      if (isset($this->onDataProcessing)) {
        // Executamos a função que manipula os dados a serem
        // processados
        call_user_func($this->onDataProcessing, $value, $filter);
      }

      // Atualiza o progresso
      if ($this->calcProgressAsFractionalPortion === true) {
        // Estes dados representam uma fração do que estamos
        // processando, então calcula
        if ($this->handlePages === true) {
          // Devemos levar em consideração o tamanho dos registros em
          // todas as páginas
          $this->done = ($this->pageSize * ($this->pageCount - 1))
            + $row + 1;
        } else {
          if ($this->repeatRequestIfNecessary) {
            // Como não sabemos quantas páginas iremos requisitar para
            // um mesmo filtro, então não temos como determinar o quanto
            // já foi processado, então considera apenas a posição no
            // filtro
            $this->done = $filterCount;
          } else {
            // Estes dados representam uma fração do que estamos
            // processando, então calcula
            $this->done = $filterCount
              + ((($row + 1) * 100) / $amount) / 100
            ;
          }
        }
      } else {
        // O dado sendo processado corresponde à quantidade de dados
        // já processados
        $this->done = $row;
      }

      if ($this->showProgressDuringProcessing) {
        $this->updateProgress("Processando...");
      }
    }

    if ($this->handlePages) {
      // Precisamos analisar se precisamos repetir o processamento para
      // recuperar dados das demais páginas
      if ($this->pageCount < $this->totalOfPages) {
        // Ajusta os parâmetros para a próxima requisição
        $this->parameters['page'] = $this->pageCount + 1;
        $this->debug("Avançando para a página {page}.",
          [ 'page' => $this->parameters['page'] ])
        ;

        // Repetimos à requisição para obter os dados restantes
        $this->repeat = true;
      }
    }

    // Verifica se devemos repetir a requisição, se necessário
    if ($this->repeatRequestIfNecessary) {
      // Verifica se a quantidade recebida for igual ou
      // superior à 50
      if ($amount >= $this->pageSize) {
        // Repetimos à requisição para obter os dados restantes
        $this->repeat = true;
        $this->debug("Repetindo a requisição para obter o "
          . "restante dos registros.", [ ])
        ;
      }
    }

    // Atualizamos o progresso no final
    if ($this->calcProgressAsFractionalPortion) {
      if ($this->handlePages) {
        $this->done = ($this->pageSize * ($this->pageCount - 1))
          + $amount;
      } else {
        if (($this->repeatRequestIfNecessary) &&
            ($this->repeat === true)) {
          $this->done = $filterCount;
        } else {
          $this->done = ($filterCount + 1);
        }
      }
    } else {
      $this->done = $amount;
    }
    $this->updateProgress("Processando...");
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
  public function sendRequest(string $path, array $params): array
  {
    // Sempre adiciona aos parâmetros a chave de cliente STC
    $params['key'] = $this->key;

    // Realiza normalmente a requisição
    $response = parent::sendRequest($path, $params);

    return $response;
  }
}
