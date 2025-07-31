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
 * Classe responsável pela abstração da execução de um determinado
 * trabalho (processo) que é dividido em pequenas tarefas executadas em
 * séria, onde cada uma das tarefas precisa realizar uma ou mais
 * requisições usando uma API de um serviço genérico de um provedor
 * externo.
 */

namespace Core\HTTP;

use Core\Exceptions\cURLException;
use Core\Exceptions\HTTPException;
use Core\Exceptions\JSONException;
use Core\HTTP\Task;
use Core\HTTP\Progress\ProgressInterface;
use Core\Logger\LoggerTrait;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

abstract class AbstractJob
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
   * A interface que nos permite fazer as requisições à API externa.
   *
   * @var APIInterface
   */
  protected $api;

  /**
   * O tempo de atraso (em segundos) entre requisições.
   *
   * @var integer
   */
  protected $delay = 0;

  /**
   * O valor total de itens para processar e que permite determinar o
   * nosso progresso.
   *
   * @var float
   */
  protected $total = 0.0;

  /**
   * A quantidade de itens processados e que indicam o progresso.
   *
   * @var float
   */
  protected $done = 0.0;

  /**
   * A flag que indica se já inicializamos nossa barra de progresso
   *
   * @var bool
   */
  protected $startedProgress = false;

  /**
   * Os parâmetros de nossa requisição.
   *
   * @var array  Os parâmetros de nossa requisição.
   */
  protected $parameters = [];

  /**
   * As tarefas a serem executados a cada iteração.
   *
   * @var array
   */
  protected $tasks = [];

  /**
   * O divisor do tempo de espera. Para o modo console, atualizamos o
   * progresso 10 vezes mais rápido do que no ambiente Web para que o
   * usuário tenha a sensação de fluidez.
   * 
   * @var integer
   */
  protected $dividerTime = 1;

  /**
   * A instância do sistema de envio do progresso ao cliente.
   *
   * @var ProgressInterface
   */
  protected $progress;

  /**
   * O construtor do sistema de execução de um trabalho.
   *
   * @param APIInterface $api
   *   O sistema para requisição dos dados à API
   * @param LoggerInterface $logger
   *   O sistema de registro de logs
   * @param ProgressInterface $progress
   *   O sistema para envio do progresso ao cliente
   */
  public function __construct(
    APIInterface $api,
    LoggerInterface $logger,
    ProgressInterface $progress
  )
  {
    $this->api = $api;
    $this->logger = $logger;
    $this->progress = $progress;
    unset($api);
    unset($logger);
    unset($progress);

    // Estabelece um divisor de tempo para a taxa de atualização da tela
    // em função do modo que estamos
    if (PHP_SAPI == 'cli') {
      // Estamos em modo console, então a taxa de atualização da tela
      // é maior
      $this->dividerTime = 10;
    } else {
      // Estamos em modo web, então a taxa de atualização é mantida em
      // uma vez por segundo
      $this->dividerTime = 1;
    }
  }

  // ========================================[ Manipulação da URL ]=====

  /*
   * Interpola valores de contexto nos espaços reservados do caminho da
   * solicitação.
   *
   * @param string $path
   *   O caminho para o serviço
   * @param array $context
   *   Os valores de contexto
   *
   * @return string
   *   O caminho com os valores de contexto substituídos.
   */
  protected function buildPath(string $path,
    array $context = []): string
  {
    // Constrói uma matriz de substituição com chaves ao redor das
    // chaves de contexto
    $replace = [];

    foreach ($context as $key => $val) {
      // Verifica se o valor pode ser convertido em string
      if (!is_array($val) && (!is_object($val) ||
        method_exists($val, '__toString'))) {
        $replace['{' . $key . '}'] = $val;
      }
    }

    // Interpola os valores de substituição no caminho e retorna
    return strtr($path, $replace);
  }


  // ================================[ Manipulação dos parâmetros ]=====

  /**
   * O método responsável por adicionar uma nova tarefa a ser executada
   * e que será responsável por qualquer requisição e a respectiva
   * análise dos resultados.
   *
   * @param Task $task
   *   A tarefa a ser executada
   */
  public function addTask(Task $task): void
  {
    $this->tasks[] = $task;
  }

  /**
   * O método responsável por preparar os parâmetros de nossa requisição
   * antes do início da execução das nossas tarefas.
   *
   * @param array $parameters
   *   Uma matriz com os parâmetros adicionais necessários (Opcional).
   */
  public function prepareParameters(array $parameters = []): void
  {
    // Adiciona os parâmetros enviados aos parâmetros deste trabalho
    $this->parameters = array_merge($this->parameters, $parameters);
  }

  /**
   * O método responsável por recuperar o local onde a API armazena os
   * cookies.
   *
   * @return string
   *   O local (caminho) onde está armazenado os cookies
   */
  public function getCookiePath(): string
  {
    return $this->api->getCookiePath();
  }


  // ======================================[ Manipulação do fluxo ]=====

  /**
   * O método responsável por definir um tempo de atraso entre
   * requisições, limitando a quantidade de requisições por minuto.
   *
   * @param int $delay
   *   O tempo de atraso (em segundos) entre cada requisição.
   */
  public function setDelay(int $delay): void
  {
    $this->delay = $delay;
  }

  /**
   * Realiza um atraso em milissegundos.
   *
   * @param int $delay
   *   O tempo (em milissegundos) de atraso
   */
  protected function delayTime(int $delay): void
  {
    usleep($delay * 1000);
  }

  /**
   * O método responsável por aguardar um tempo após a requisição, se
   * configurado.
   *
   * @param int $delay
   *   O tempo de espera após a requisição (em segundos). Se não
   * informado, usará o valor definido em $this->delay.
   * 
   * @param string $label
   *   Um rótulo a ser utilizado para informar o que estamos aguardando
   */
  public function waitingTimeAfterRequisition(int $delay = 0,
    string $label): void
  {
    // Determinamos o tempo de espera em função dos parâmetros
    $waitTime = ($delay > 0) ? $delay : $this->delay;

    if ($waitTime > 0) {
      // Precisamos realizar um atraso.
      
      // Realiza o loop que decrementa o tempo de atraso. Durante este
      // período, o sistema irá enviar mensagens atualizando o progresso
      // para que o cliente não pense que a execução 'travou'.
      for ($count = $waitTime; $count > 0; $count--) { 
        // Repetimos a quantidade de vezes necessárias por segundo de
        // acordo com o divisor de tempo
        for ($dividerCount = 0; $dividerCount < $this->dividerTime; $dividerCount++) { 
          $this->updateProgress("{$label} (Aguardando {$count} seg.)...");
          $this->delayTime(1000 / $this->dividerTime);
        }
      }
    }
  }


  // ========================[ Métodos para exibição do progresso ]=====

  /**
   * Inicializa a exibição do progresso do processamento.
   *
   * @param string $message
   *   A mensagem de inicialização
   */
  protected function initProgress(string $message): void
  {
    $this->progress->send('START', $this->done, $this->total, $message);
    $this->startedProgress = true;
  }

  /**
   * Avança no progresso para a próxima tarefa
   *
   * @param string $message
   *   A mensagem de progresso
   */
  public function goNextProgress(string $message): void
  {
    $this->progress->send('NEXT', $this->done, $this->total,
      $message
    );
  }

  /**
   * Atualiza a exibição do progresso do processamento.
   *
   * @param string $message
   *   A mensagem de progresso
   */
  public function updateProgress(string $message): void
  {
    $this->progress->send('PROGRESS', $this->done, $this->total,
      $message
    );
  }

  /**
   * Atualiza a exibição do progresso em caso de erro.
   *
   * @param string $message
   *   A mensagem de erro
   */
  protected function errorProgress(string $message): void
  {
    $this->progress->send('ERROR', $this->done, $this->total, $message);
  }

  /**
   * Finaliza a exibição do progresso.
   *
   * @param string $message
   *   A mensagem de inicialização
   */
  protected function endProgress(string $message): void
  {
    $this->progress->send('END', $this->done, $this->total, $message);
  }

  /**
   * Lida com a exibição do progresso dentro de uma ação.
   * 
   * @param int $done
   *   O quanto progrediu dentro da ação
   * @param int $total
   *   O total de itens dentro da ação
   * @param array $label
   *   O rótulo a ser exibido
   * @param array $data
   *   Os dados a serem enviados (opcional)
   */
  public function handleProgress(int $done, int $total, string $label,
    array $data = []): void
  {
    $done = $this->done + ((($done + 1) * 100) / $total) / 100;
    $this->progress->send('PROGRESS', $done, $this->total,
      $label . '...', $data
    );
  }

  // ===================================[ Manipulação da resposta ]=====

  /**
   * Recupera um valor da resposta, se existir, se não retorna o valor
   * padrão informado.
   *
   * @param array $response
   *   O conteúdo de nossa resposta
   * @param string $key
   *   O parâmetro desejado
   * @param mixed $default
   *   O valor padrão, caso o mesmo não esteja definido
   *
   * @return mixed  O valor desejado
   */
  protected function getResponseValue(array $response, string $key,
    $default)
  {
    if (array_key_exists($key, $response)) {
      return $response[$key];
    }

    return $default;
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
    if (is_array($response)) {
      if (count($response) > 0) {
        return QueueResponseStatus::CONTINUE;
      }
    }

    return QueueResponseStatus::BREAK;
  }

  /**
   * Analisa se precisamos repetir a sequência de comandos com novos
   * parâmetros.
   *
   * @return bool
   */
  protected function hasMore(): bool
  {
    return false;
  }

  /**
   * O método responsável por executar cada tarefa definida.
   */
  public function execute(): void
  {
    // Iniciamos nossas variáveis de controle da percentagem concluída.
    // A percentagem concluída possui uma parte inteira e uma parte
    // fracionária. A parte inteira corresponde ao progresso da execução
    // das tarefas executadas e a parte fracionária corresponde ao
    // progresso dos itens dentro de uma mesma tarefa.
    $this->done = 0.0;
    $this->startedProgress = false;
    $lastTask = 'Iniciando...';
    $processingData = [];

    $this->debug("Iniciando execução das tarefas", [ ]);

    $this->total = count($this->tasks);
    $this->debug("Temos {count} tarefas a serem executadas", [
      'count' => $this->total
    ]);
    $this->initProgress('Iniciando...');

    do {
      // Executa estas tarefas ao menos uma vez. Ao final, verificamos
      // a necessidade de executar novamente com novos parâmetros

      // Percorremos todas as tarefas a serem executadas
      foreach ($this->tasks as $taskCount => $task) {
        if ($task instanceof Task) {
          // Inicializamos os parâmetros de processamento
          $taskName = $task->getName();
          $this->goNextProgress('Iniciando ' . lcfirst($taskName) . '...');
          $tryCount = 0;

          // Repetimos a tarefa quantas vezes forem necessárias
          do {
            $repeat = false;
            try {
              // Executamos a função que inicializa os parâmetros desta
              // tarefa passando as informações de parâmetros armazenados
              // e os dados de processamento que são acumulados entre as
              // diversas tarefas
              $this->debug("Executando função de inicialização dos "
                . "parâmetros para a tarefa '{name}'.",
                [ 'name' => $taskName ]
              );
              $context = $task->prepareParameters($this->parameters,
                $processingData
              );

              if ($task->mustBeProcessed()) {
                // A tarefa deve ser processada, então realizamos a
                // requisição
                $path = $this->buildPath($task->getPath(), $context);
                $this->debug("Realizando a requisição da tarefa "
                  . "'{name}' em '{path}'.",
                  [ 'name' => $taskName,
                    'path' => $path ]
                );
                $response = $this->api->sendRequest($path, $context);

                // Validamos nossa resposta para determinar qual a ação a
                // ser tomada
                list($action, $message) = $this->handleResponse($response);
                switch ($action) {
                  case 'Process':
                    if ($task->mustBeVerifyExecutionInQueue()) {
                      // Precisamos verificar a condição do comando na
                      // fila de execução
                      
                      // Primeiramente, solicitamos à tarefa para lidar
                      // com a resposta, para obter o ID do comando na
                      // fila
                      $task->handleRequestResponse($response,
                        array($this, 'handleProgress')
                      );

                      // Agora lidamos com a análise da fila
                      do {
                        $repeatQueue = false;
                        $queueContext = $task->prepareQueueParameters(
                          $this->parameters
                        );

                        // A fila de comandos deve ser analisada, então
                        // realizamos a requisição para obter a situação
                        // do comando na fila
                        $path = $this->buildPath($task->getQueuePath(), $queueContext);
                        $this->debug("Verificando a execução da "
                          . "requisição da tarefa '{name}' na fila em "
                          . "'{path}'.",
                          [ 'name' => $taskName,
                            'path' => $path ]
                        );
                        $response = $this->api->sendRequest($path, $queueContext);

                        switch ($this->handleQueueResponse($response)) {
                          case QueueResponseStatus::REPEAT:
                            // Repetimos a consulta à situação do comando
                            // na fila
                            $repeatQueue = true;

                            $this->waitingTimeAfterRequisition(
                              61, $task->getWaitingMessage()
                            );

                            break;
                          case QueueResponseStatus::BREAK:
                            // Devemos interromper esta tarefa e todo o
                            // processamento pois o dispositivo não está
                            // comunicando
                            $this->error("Interrompendo {name}. {error}",
                              [ 'name' => lcfirst($taskName),
                                'error' => 'Dispositivo não está '
                                  . 'comunicando' ]
                            );
                            $this->errorProgress("Erro: Dispositivo não "
                              . "está comunicando");

                            return;
                            
                            break;
                          default:
                            // Continuamos o processamento
                            $repeatQueue = false;
                        }
                      } while ($repeatQueue);
                    }

                    // Executa o processamento
                    $this->updateProgress("Processando " .
                      lcfirst($taskName) . "..."
                    );
                    $task->process(
                      $response,
                      array($this, 'handleProgress'),
                      $processingData
                    );

                    // Verificamos se precisamos aguardar um tempo após a
                    // requisição e processamento desta tarefa
                    $waitingTime = $task
                      ->getWaitingTimeAfterRequisition()
                    ;
                    $this->waitingTimeAfterRequisition(
                      $waitingTime, $task->getWaitingMessage()
                    );

                    if ($task->reexecute()) {
                      // Repetimos a mesma requisição após um tempo
                      $repeat = true;

                      continue 2;
                    }

                    // A percentagem concluída é igual à quantidade de
                    // tarefas já executadas
                    $this->done = $taskCount + 1;

                    break;
                  case 'Abort':
                    // Devemos interromper todo o processo
                    $this->error("Interrompendo {name}. {error}",
                      [ 'name' => lcfirst($taskName),
                        'error' => $message ]
                    );
                    $this->errorProgress("Erro: " . $message);

                    return;

                    break;
                  default:
                    // Apenas avança normalmente para a próxima tarefa
                    
                    // A percentagem concluída é igual à quantidade de
                    // tarefas já executadas
                    $this->done = $taskCount + 1;
                    $this->debug("Avançando para a próxima tarefa.",
                      [ ]
                    );
                    $this->updateProgress($message);

                    break;
                }
              }
            }
            catch(Exception $exception){
              // Ocorreu um erro interno no aplicativo, então atualiza o
              // progresso, informando o erro, e interrompe
              $this->errorProgress("Erro interno: "
                . $exception->getMessage()
              );
              $this->error("Erro interno do aplicativo: {error}",
                [ 'error' => $exception->getMessage() ]
              );

              return;
            }
            catch(RuntimeException $exception){
              // Ocorreu um erro de execução no aplicativo, então atualiza o
              // progresso, informando o erro, e interrompe
              $this->errorProgress("Erro na execução: "
                . $exception->getMessage()
              );
              $this->error("Erro na execução do aplicativo: {error}",
                [ 'error' => $exception->getMessage() ]
              );

              return;
            }
            catch(InvalidArgumentException $exception){
              // Ocorreu um erro em algum dos argumentos, então atualiza o
              // progresso, informando o erro, e interrompe
              $this->errorProgress("Erro na execução: "
                . $exception->getMessage()
              );
              $this->error("Erro interno do aplicativo: {error}",
                [ 'error' => $exception->getMessage() ]
              );

              return;
            }
            catch(cURLException $exception) {
              // Ocorreu um erro na requisição através do cURL, então
              // registra o erro e analisa o código de retorno
              $this->error("Erro na requisição usando o cURL: [{code}] "
                . "{message}",
                [ 'code' => $exception->getCode(),
                  'message' => $exception->getMessage() ]
              );

              switch ($exception->getCode()) {
                case 28:
                  // Tempo da requisição esgotado, então devemos tentar
                  // novamente. Analisamos a quantidade de tentativas já
                  // realizadas
                  if ($tryCount < 3) {
                    // Incrementamos o contador de tentativas
                    $tryCount++;
                    $this->debug("Realizando {count}ª nova tentativa "
                      . "para a requisição da tarefa",
                      [ 'count' => $tryCount ]
                    );

                    // Repetimos a mesma requisição após um tempo
                    $repeat = true;
                  }

                  break;
                default:
                  // Ocorreu um erro na requisição, então atualiza o
                  // progresso, informando o erro, e interrompe
                  $this->errorProgress("Erro na requisição: "
                    . $exception->getMessage()
                  );

                  return;
              }
            }
            catch(HTTPException $exception){
              // Ocorreu um erro na requisição HTTP, então registra o erro,
              // atualiza o progresso, informando o erro, e interrompe
              $this->error("Erro na requisição HTTP: [{code}] {message}",
                [ 'code' => $exception->getCode(),
                  'message' => $exception->getMessage() ]
              );
              $this->errorProgress("Erro na requisição: "
                . $exception->getMessage()
              );

              return;
            }
            catch(JSONException $exception){
              // Ocorreu um erro na resposta JSON, então registra o erro,
              // atualiza o progresso, informando o erro, e interrompe
              $this->error("Erro na resposta JSON: [{code}] {message}",
                [ 'code' => $exception->getCode(),
                  'message' => $exception->getMessage() ]
              );
              $this->errorProgress("Erro na requisição: "
                . $exception->getMessage()
              );

              return;
            }
          } while ($repeat);
        }
      }

      // Finaliza o progresso
      $this->done = $this->total;
      $this->endProgress('Concluído');
      $this->debug("A execução das tarefas foi concluída", [ ]);
    } while ($this->hasMore());
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
  abstract protected function handleResponse($response): array;
}
