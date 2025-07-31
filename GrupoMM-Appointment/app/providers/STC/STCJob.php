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
 * Classe responsável pela execução de um determinado processamento que
 * é dividido em pequenas tarefas executadas em séria, onde cada uma das
 * tarefas precisa realizar uma ou mais requisições usando a API da STC.
 */

/**
 * API STC
 *
 * http://ap1.stc.srv.br/docs/
 *
 * Copyright (c) 2017 - STC Tecnologia <www.stctecnologia.com.br>
 */

namespace App\Providers\STC;

use Core\HTTP\AbstractJob;
use Core\HTTP\Job;
use Core\HTTP\QueueResponseStatus;
use Carbon\Carbon;

class STCJob
  extends AbstractJob
  implements Job
{
  /**
   * A chave de acesso de cliente ao sistema STC
   *
   * @var string
   */
  protected $key;

  /**
   * A ID do dispositivo formatada para o qual iremos fazer as
   * requisições.
   * 
   * @var string
   */
  protected $deviceID = '';

  /**
   * Uma matriz com a lista de dispositivos para os quais enviaremos as
   * requisições
   * 
   * @var array|null
   */
  protected $devices = null;

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
   * Seta o ID do dispositivo para o qual faremos a requisição.
   *
   * @param int $deviceID
   *   O ID do dispositivo
   */
  public function setDevice(int $deviceID): void
  {
    if (strlen(strval($deviceID)) > 6) {
      $this->deviceID = sprintf("%09d", $deviceID);
    } else {
      $this->deviceID = sprintf("%06d", $deviceID);
    }
  }

  /**
   * Seta uma matriz de ID's de dispositivos para os quais faremos as
   * requisições.
   *
   * @param array $devices
   *   As ID's de dispositivos
   */
  public function setDevices(array $devices): void
  {
    $this->devices = $devices;
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
    $parameters['key'] = $this->key;

    if (is_array($this->devices)) {
      // Vamos para o primeiro dispositivo informado
      reset($this->devices);

      // Definimos ele como sendo o dispositivo atual
      $this->setDevice(current($this->devices));
      $this->debug("O ID do dispositivo corrente é " . $this->deviceID);
    }

    $parameters['deviceId'] = $this->deviceID;

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
    $this->debug('Response: ' . json_encode($response));

    // 1. Valida se temos as chaves necessárias
    if ( !array_key_exists('success', $response) ||
         !array_key_exists('error', $response) ) {
      $this->debug('Resposta não contém retorno de sucesso ou erro');
      return false;
    }

    // 2. Validamos o campo success
    if (!is_bool($response['success'])) {
      $this->debug('Retorno de sucesso na resposta não é booleano');
      return false;
    }

    // 3. Validamos o campo error
    if (!is_int($response['error'])) {
      $this->debug('Retorno de erro na resposta não é inteiro');
      return false;
    }

    // 4. Validamos o campo msg
    if ($response['error'] > 0) {
      $this->debug('Temos erro');
      if ( !is_string($response['msg']) &&
           !is_array($response['msg']) ) {
        $this->debug('Mensagem de erro da resposta está vazia');
        return false;
      }
    }

    // 4. Validamos o campo data
    if ($response['error'] === 0) {
      if (array_key_exists('data', $response)) {
        if (!is_array($response['data']) &&
            !is_string($response['data']) &&
            !is_integer($response['data'])) {
          $this->debug('Campo data da resposta inválido');

          return false;
        }
      }
    }
    
    $this->debug('Resposta de retorno é válida');

    return true;
  }

  /**
   * Analisa a resposta do comando de fila e determina uma ação.
   *
   * @param mixed $response
   *   O conteúdo da resposta à nossa requisição
   *
   * @return int
   *   Retorna um valor possível de QueueResponseStatus
   */
  protected function handleQueueResponse($response): int
  {
    $this->debug("Resposta contém: " . json_encode($response));

    if (is_array($response)) {
      if (count($response) > 0) {
        // A API retornou uma matriz como resposta e que contém valores
        if ($this->validateSTCResponse($response)) {
          // O conteúdo retornado é uma resposta STC válida, então
          // analisamos o valor do campo 'success'
          if ($response['success'] === true) {
            // Analisamos se o comando já foi enviado
            $data = $response['data'];

            if (is_array($data)) {
              if (count($data) > 0) {
                $data = $data[0];
                $requestDate = $this->getDateTime($data['requestDate']);

                if (is_null($data['sendDate'])) {
                  // O comando ainda não foi enviado, então devemos
                  // aguardar um tempo indefinido
                  $this->debug("Repetindo...");
                  return QueueResponseStatus::REPEAT;
                  //$now = Carbon::now();
                  //$this->debug($now->floatDiffInMinutes($requestDate->copy()));
                  //if ($now->floatDiffInMinutes($requestDate->copy()) < 15.0) {
                  //  // Devemos aguardar o envio novamente
                  //  $this->debug("Repetindo...");
                  //  return QueueResponseStatus::REPEAT;
                  //} else {
                  //  // Interrompemos, pois o carro deve estar fora da área
                  //  // de alcance
                  //  $this->debug("Abortando pois passou de 15 minutos...");
                  //  return QueueResponseStatus::BREAK;
                  //}
                } else {
                  $sendDate = $this->getDateTime($data['sendDate']);

                  if (is_null($data['confirmDate'])) {
                    // O comando ainda não foi confirmado, então devemos
                    // aguardar no máximo 2 minuto após o envio
                    $now = Carbon::now();
                    if ($now->floatDiffInMinutes($sendDate->copy()) < 2) {
                      // Devemos aguardar o envio novamente
                      return QueueResponseStatus::REPEAT;
                    } else {
                      // Interrompemos, pois o carro deve estar fora da área
                      // de alcance
                      return QueueResponseStatus::BREAK;
                    }
                  }

                  $this->debug("Comando confirmado às {time}",
                    [ 'time' => $data['confirmDate'] ]
                  );

                  return QueueResponseStatus::CONTINUE;
                }


              } else {
                // O comando já foi concluído
                return QueueResponseStatus::CONTINUE;
              }
            }
          } else {
            if (($response['error'] === true) &&
                ($response['error'] === "Limite de acessos atingido (1 minutos)")) {
              // Devemos aguardar o envio novamente
              return QueueResponseStatus::REPEAT;
            }
          }
        }
      }
    }

    return QueueResponseStatus::BREAK;
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
      } else {
        $this->debug('Resposta sem retorno');
      }
    } else {
      $this->debug($response);
    }

    return [
      $action, $message
    ];
  }

  /**
   * Analisa se precisamos repetir a sequência de comandos com novos
   * parâmetros.
   *
   * @return bool
   */
  protected function hasMore(): bool
  {
    if ($this->hasNextDevice()) {
      // Definimos o próximo dispositivo como sendo o dispositivo atual
      $this->setDevice(current($this->devices));
      $this->parameters['deviceId'] = $this->deviceID;

      $this->debug("Repetindo execução das tarefas para o dispositivo "
        . "{deviceID}...",
        [ 'deviceID' => $this->deviceID ]
      );

      return true;
    }

    return false;
  }

  /**
   * Verifica se temos outro dispositivo para realizar a mesma
   * requisição.
   *
   * @return boolean
   */
  protected function hasNextDevice(): bool
  {
    if (is_array($this->devices)) {
      if (next($this->devices) === false) {
        return false;
      } else {
        return true;
      }
    } else {
      return false;
    }
  }

  /**
   * Recupera a data/hora à partir de um valor numa string.
   * 
   * @param string $dateTimeStr
   *   A string que contém a data/hora
   * 
   * @return Carbon
   *   A data/hora
   */
  protected function getDateTime(string $dateTimeStr): Carbon
  {
    return Carbon::createFromFormat('d/m/y G:i:s', $dateTimeStr);
  }
}
