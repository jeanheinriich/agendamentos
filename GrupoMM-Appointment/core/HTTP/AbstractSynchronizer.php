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
 * Classe responsável pela abstração do sincronismo de dados usando uma
 * API de um serviço genérico de um provedor externo.
 */

namespace Core\HTTP;

use Core\Exceptions\cURLException;
use Core\Exceptions\HTTPException;
use Core\Exceptions\JSONException;
use Core\Helpers\FormatterTrait;
use Core\HTTP\Filters\DataFilter;
use Core\HTTP\Filters\DataFilterIterator;
use Core\HTTP\Progress\ProgressInterface;
use Core\Logger\LoggerTrait;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

abstract class AbstractSynchronizer
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
   * O caminho para o recurso dentro da URL do serviço
   *
   * @var string
   */
  protected $path;

  /**
   * O tempo de atraso (em segundos) entre requisições.
   *
   * @var integer
   */
  protected $delay = 0;

  /**
   * A flag indicadora de que devemos repetir a requisição.
   *
   * @var bool
   */
  protected $repeat = false;

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
   * Se habilitado calcula o progresso do processamento dos dados de
   * cada requisição como uma fração do total, pois temos parâmetros de
   * filtragem que devem ser levados em consideração
   *
   * @var boolean
   */
  protected $calcProgressAsFractionalPortion = false;

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
   * Os dados que serão utilizados para limitar as informações
   * requisitadas
   *
   * @var DataFilter
   */
  protected $filter;

  /**
   * A quantidade máxima de vezes em que o sistema tentará realizar uma
   * requisição em caso de erro.
   *
   * @var int
   */
  protected $maxTryCount = 3;

  /**
   * O divisor do tempo de espera. Para o modo console, atualizamos o
   * progresso 10 vezes mais rápido do que no ambiente Web para que o
   * usuário tenha a sensação de fluidez.
   * 
   * @var integer
   */
  protected $dividerTime = 1;

  /**
   * A função a ser executada na inicialização dos parâmetros.
   *
   * @var callable
   */
  protected $onInitParameters;

  /**
   * A função a ser executada antes da requisição dos dados.
   *
   * @var callable
   */
  protected $beforeRequest;

  /**
   * A função a ser executada após o processamento dos dados.
   *
   * @var callable
   */
  protected $afterProcess;

  /**
   * A função a ser executada no processamento de dados.
   *
   * @var callable
   */
  protected $onDataProcessing;

  /**
   * A instância do sistema de envio do progresso ao cliente.
   *
   * @var ProgressInterface
   */
  protected $progress;

  /**
   * O construtor do sistema de requisições à uma API.
   *
   * @param APIInterface $api
   *   O sistema para requisição dos dados à API
   * @param LoggerInterface $logger
   *   O sistema para registro de logs
   * @param ProgressInterface $progress
   *   O sistema para envio do progresso ao cliente
   */
  public function __construct(APIInterface $api,
    LoggerInterface $logger, ProgressInterface $progress)
  {
    // Armazena nosso acesso à API
    $this->api = $api;
    unset($api);

    // Armazena nosso acesso ao logger
    $this->logger = $logger;
    unset($logger);

    // Armazena a instância do sistema de envio do progresso ao cliente
    $this->progress = $progress;
    unset($progress);

    // Cria um filtro em branco
    $this->filter = new DataFilterIterator();

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


  // ========================================[ Métodos auxiliares ]=====

  /**
   * Recupera a porção anterior ao caractere informado da string.
   *
   * @param string $find
   *   O caractere limitador
   * @param string $inThat
   *   A string onde será localizada
   *
   * @return string
   *   A porção à esquerda do caractere na string
   */
  protected function before(string $find, string $inThat): string
  {
    if (strpos($inThat, $find) !== false) {
      return substr($inThat, 0, strpos($inThat, $find));
    }

    return $inThat;
  }

  /**
   * Recupera a porção posterior ao caractere informado da string.
   *
   * @param string $find
   *   O caractere limitador
   * @param string $inThat
   *   A string onde será localizada
   *
   * @return string
   *   A porção à direita do caractere na string
   */
  protected function after($find, $inThat): string
  {
    if (strpos($inThat, $find) !== false) {
      return substr($inThat, strpos($inThat, $find) + strlen($find));
    }

    return $inThat;
  }


  // ========================================[ Manipulação da URL ]=====

  /**
   * O método responsável por identificar o caminho para o recurso na
   * URL de nossa requisição.
   *
   * @param string $path
   *   O caminho para o recurso
   */
  public function setURI(string $path): void
  {
    $this->path = $path;
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
    return strtr($this->path, $replace);
  }


  // ================================[ Manipulação dos parâmetros ]=====

  /**
   * O método responsável por definir um filtro para limitar os dados
   * em cada requisição.
   *
   * @param DataFilter $filter
   *   Os valores a serem utilizados como filtro para limitar cada
   * requisição.
   */
  public function setFilterParameter(DataFilter $filter): void
  {
    $this->filter = $filter;
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
    // Adiciona os parâmetros enviados
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
   * O método responsável por definir a quantidade máxima de tentativas
   * de uma mesma requisição em caso de erro.
   *
   * @param int $maxTryCount
   *   A quantidade máxima de tentativas.
   */
  public function setMaxTryCount(int $maxTryCount): void
  {
    $this->maxTryCount = $maxTryCount;
  }

  /**
   * O método responsável por aguardar um tempo entre requisições, se
   * configurado.
   *
   * @param int $delay
   *   O tempo de atraso entre requisições (em segundos). Se não
   * informado, usará o valor definido em $this->delay.
   */
  public function waitingTimeBetweenRequisitions(int $delay = 0): void
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
          $this->updateProgress("Aguardando {$count}...");
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


  // =======================================[ Requisições manuais ]=====

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
   */
  public function sendRequest(string $path, array $params): array
  {
    try {
      $response = $this->api->sendRequest($path, $params);
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

      // Responde com uma mensagem de erro
      $response = [
        'success' => false,
        'error' => $exception->getCode(),
        'msg' => $exception->getMessage()
      ];
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

      // Responde com uma mensagem de erro
      $response = [
        'success' => false,
        'error' => $exception->getCode(),
        'msg' => $exception->getMessage()
      ];
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

      // Responde com uma mensagem de erro
      $response = [
        'success' => false,
        'error' => $exception->getCode(),
        'msg' => $exception->getMessage()
      ];
    }
    catch(cURLException $exception) {
      // Ocorreu um erro na requisição através do cURL, então
      // registra o erro e analisa o código de retorno
      $this->errorProgress("Erro na requisição: "
        . $exception->getMessage()
      );
      $this->error("Erro na requisição usando o cURL: "
        . "[{code}] {message}",
        [ 'code' => $exception->getCode(),
          'message' => $exception->getMessage() ]
      );

      // Responde com uma mensagem de erro
      $response = [
        'success' => false,
        'error' => $exception->getCode(),
        'msg' => $exception->getMessage()
      ];
    }
    catch(HTTPException $exception){
      // Ocorreu um erro na requisição HTTP, então registra o erro,
      // atualiza o progresso, informando o erro, e interrompe
      $this->errorProgress("Erro na requisição: "
        . $exception->getMessage()
      );
      $this->error("Erro na requisição HTTP: [{code}] "
        . "{message}",
        [ 'code' => $exception->getCode(),
          'message' => $exception->getMessage() ]
      );

      // Responde com uma mensagem de erro
      $response = [
        'success' => false,
        'error' => $exception->getCode(),
        'msg' => $exception->getMessage()
      ];
    }
    catch(JSONException $exception){
      // Ocorreu um erro na resposta JSON, então registra o erro,
      // atualiza o progresso, informando o erro, e interrompe
      $this->errorProgress("Erro na requisição: "
        . $exception->getMessage()
      );
      $this->error("Erro na resposta JSON: [{code}] {message}",
        [ 'code' => $exception->getCode(),
          'message' => $exception->getMessage() ]
      );

      // Responde com uma mensagem de erro
      $response = [
        'success' => false,
        'error' => $exception->getCode(),
        'msg' => $exception->getMessage()
      ];
    }

    return $response;
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
  abstract protected function process($response, int $filterCount,
    array $filter): void;

  /**
   * O método responsável por executar as requisições usando a API do
   * serviço, solicitando os dados.
   */
  public function synchronize(): void
  {
    // Iniciamos nossas variáveis de controle da percentagem concluída.
    // A percentagem concluída possui uma parte inteira e uma parte
    // fracionária. A parte inteira corresponde ao progresso dos itens
    // de filtragem e a parte fracionária corresponde aos progresso dos
    // itens dentro de um filtro (Ex: quando se recupera os dados de
    // cidades por UF, então a parte inteira corresponde a iteração nas
    // UF's, de 1 a 26. Para cada UF se recupera a quantidade de cidades
    // e, a medida que o processamento é executado, a parte fracionária
    // irá indicar o quanto destas cidades já foi processada).
    $this->done = 0.0;
    $this->startedProgress = false;
    $this->calcProgressAsFractionalPortion = false;

    // Garantimos que nosso filtro esteja no início
    $this->filter->rewind();

    $this->debug("Iniciando sincronização", [ ]);

    // Analisamos se temos parâmetros de filtragem
    $filterParameters = $this->filter->getFilterParameters();
    if (count($filterParameters) > 0) {
      // A cada requisição deveremos incluir estes parâmetros de
      // filtragem na solicitação. Então o progresso será calculado no
      // avanço de nossas requisições nestes dados de filtragem
      $this->debug("Adicionado os parâmetros de filtragem: "
        . "{parameters}",
        [ 'parameters' => implode(', ', $filterParameters) ]
      );

      $this->total = floatval($this->filter->total());
      $this->initProgress('Iniciando...');
      $this->calcProgressAsFractionalPortion = true;

      $this->debug("Teremos {total} iterações para os parâmetros "
        . "de filtragem.",
        [ 'total' => $this->total ]
      );
    } else {
      // Não iremos requisitar dados baseados num parâmetro (filtro),
      // então dentro do processamento será inicializado o progresso
      $this->debug("Não temos parâmetros de filtragem adicionais.");
    }

    // Percorremos todas as posições de filtragem
    foreach ($this->filter as $filterCount => $filter) {
      // Atualizamos os parâmetros de filtragem, se necessário
      foreach ($filterParameters as $name) {
        // Obtemos o valor para o parâmetro de filtragem
        $this->parameters[$name] = $this->filter->parameterValue($name);
        $this->debug("Atualizando valor do parâmetro '{name}' "
          . "para '{value}'",
          [ 'name' => $name,
            'value' => $this->filter->parameterValue($name) ]
        );
      }

      // Verifica se temos uma função de inicialização dos parâmetros
      if (isset($this->onInitParameters)) {
        // Executamos a função que inicializa os parâmetros
        $this->debug("Executando função de inicialização dos "
          . "parâmetros.", [ ]
        );

        // Executa a função que inicializa os parâmetros
        $this->parameters = call_user_func($this->onInitParameters,
          $this->parameters, $filter)
        ;
      }

      // Inicializa o contador de tentativas de requisição em caso de
      // falha
      $tryCount = 0;

      do {
        // Executa este código ao menos uma vez e, repete sua execução
        // sempre que tivermos mais dados a serem obtidos para o valor
        // de filtragem atual, bem como nos casos em que ocorrerem erros
        // na requisição, situação na qual a execução se repetirá no
        // máximo a quantidade de vezes estipulada em maxTryCount.

        // Inicialmente indicamos que não devemos repetir
        $this->repeat = false;

        try {
          // Inicializa os parâmetros de contexto
          $context = [];
          $oldParams = $this->parameters;

          // Verifica se precisamos executar uma função antes da
          // requisição à API do serviço
          if (isset($this->beforeRequest)) {
            // Executamos a função que manipula os parâmetros antes da
            // requisição
            $this->debug("Executando função de preparação dos "
              . "parâmetros antes da requisição.", [ ])
            ;

            // Executa a função que atualizará os parâmetros, bem como
            // irá definir quais os parâmetros serão enviados à
            // solicitação
            list($this->parameters, $context) =
              call_user_func($this->beforeRequest, $this->parameters,
                $filter)
            ;
          } else {
            $context = $this->parameters;
          }

          // Verifica se os parâmetros mudaram desde à ultima requisição
          if ($this->parameters !== $oldParams) {
            // Zera o contador de tentativas
            $tryCount = 0;
          }

          // Aqui realizamos a requisição à API do serviço. Utilizamos
          // os dados de contexto (e não os parâmetros armazenados) pois
          // se a API for requisitar os dados por GET, então não podemos
          // adicionar os parâmetros normais (estes serão colocados
          // através da requisição à buildPath) pois estes seriam
          // adicionados à URL de requisição. Os dados de contexto são
          // separados na chamada à 'beforeRequest'. Também atualizamos
          // o progresso, se necessário.
          if ($this->startedProgress) {
            $this->updateProgress('Requisitando...');
          }
          $path = $this->buildPath($this->parameters);
          $this->debug("Requisitando dados em '{path}'.",
            [ 'path' => $path ]
          );
          $response = $this->api->sendRequest($path, $context);
          $this->debug("Obtivemos uma resposta de nossa "
            . "requisição.", [ ]
          );

          // Validamos nossa resposta para determinar qual a ação a ser
          // tomada
          list($action, $message) = $this->handleResponse($response);
          switch ($action) {
            case 'Process':
              // A resposta é válida e temos dados para processar
              $this->debug($message, [ ]);

              // Processa os dados retornados
              $this->process($response, $filterCount, $filter);

              // Verifica se precisamos executar uma função após o
              // processamento dos dados da API do serviço
              if (isset($this->afterProcess)) {
                // Executa a função para pós-processamento
                $this->debug("Executando a função após o "
                  . "processamento dos dados desta requisição.", [ ])
                ;
                call_user_func($this->afterProcess, $this->parameters,
                  $filter)
                ;
              }

              break;
            case 'Abort':
              // Devemos interromper todo o processo
              $this->debug("Abortando nosso processamento. "
                . "{error}",
                [ 'error' => $message ]
              );
              $this->errorProgress("Erro: " . $message);

              return;

              break;
            case 'TryAgain':
              // Devemos tentar novamente. Analisamos a quantidade
              // de tentativas já realizadas
              if ($tryCount < $this->maxTryCount) {
                // Incrementamos o contador de tentativas
                $tryCount++;
                $this->debug($message);
                $this->debug("Realizando {count}ª nova "
                  . "tentativa para obter os dados.",
                  [ 'count' => $tryCount ]
                );

                // Repetimos a mesma requisição após um tempo
                $this->repeat = true;
              }

              break;
            default:
              // GoNext:  Apenas avança normalmente para o próximo
              // valor do filtro e requisita novos dados
              if (count($filterParameters) > 0) {
                // A percentagem concluída é igual à nossa iteração no
                // filtro
                $this->done = $filterCount + 1;
              }
              $this->debug("A requisição não obteve sucesso. "
                . "Avançando para a próxima requisição.",
                [ ]
              );
              $this->updateProgress($message);

              break;
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
          $this->error("Erro na requisição usando o cURL: "
            . "[{code}] {message}",
            [ 'code' => $exception->getCode(),
              'message' => $exception->getMessage() ]
          );

          switch ($exception->getCode()) {
            case 28:
              // Tempo da requisição esgotado, então devemos tentar
              // novamente. Analisamos a quantidade de tentativas já
              // realizadas
              if ($tryCount < $this->maxTryCount) {
                // Incrementamos o contador de tentativas
                $tryCount++;
                $this->debug("Realizando {count}ª nova tentativa "
                  . "para obter os dados",
                  [ 'count' => $tryCount ]
                );

                // Repetimos a mesma requisição após um tempo
                $this->repeat = true;
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
          $this->error("Erro na requisição HTTP: [{code}] "
            . "{message}",
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

        // Se estiver no último item e não precisar repetir, então
        // ignora o tempo entre requisições
        if ( (($filterCount + 1) === $this->filter->total()) &&
             (!$this->repeat) ) {
          continue 1;
        }

        // Aguarda um tempo entre requisições, se necessário
        $this->waitingTimeBetweenRequisitions();
      } while ($this->repeat);
    }

    // Finaliza o progresso
    $this->done = $this->total;
    $this->endProgress('Concluído');
    $this->debug("Sincronização concluída", [ ]);
  }


  // ==========================[ Métodos para lidar com Callbacks ]=====

  /**
   * O método que nos permite adicionar uma função a ser executada na
   * inicialização dos parâmetros a cada nova iteração do filtro.
   *
   * @param callable $callback
   *   A função a ser executada
   */
  public function setOnInitParameters(callable $callback): void
  {
    $this->onInitParameters = $callback;
  }

  /**
   * O método que nos permite adicionar uma função a ser executada antes
   * da requisição dos dados.
   *
   * @param callable $callback
   *   A função a ser executada
   */
  public function setBeforeRequest(callable $callback): void
  {
    $this->beforeRequest = $callback;
  }

  /**
   * O método que nos permite adicionar uma função a ser executada após
   * o processamento dos dados.
   *
   * @param callable $callback
   *   A função a ser executada
   */
  public function setAfterProcess(callable $callback): void
  {
    $this->afterProcess = $callback;
  }

  /**
   * O método que nos permite adicionar uma função a ser executada no
   * processamento dos dados recebidos.
   *
   * @param callable $callback
   *   A função a ser executada
   */
  public function setOnDataProcessing(callable $callback): void
  {
    $this->onDataProcessing = $callback;
  }
}
