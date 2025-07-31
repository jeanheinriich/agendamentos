<?php
/*
 * Este arquivo é parte do Sistema de ERP do Grupo M&M
 *
 * (c) Grupo M&M
 *
 * Para obter informações completas sobre direitos autorais e licenças,
 * consulte o arquivo LICENSE que foi distribuído com este código-fonte.
 * ---------------------------------------------------------------------
 * Descrição:
 * 
 * O controlador do comando de sincronismo dos dados utilizando um dos
 * provedores.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

declare(strict_types = 1);

namespace App\Commands;

use App\Models\Entity;
use App\Providers\Fipe\FipeDataSynchronizer;
use App\Providers\Fipe\FipeIntegrationService;
use App\Providers\STC\StcDataSynchronizer;
use App\Providers\STC\StcIntegrationService;
use Carbon\Carbon;
use Core\Console\Command;
use Core\HTTP\HTTPService;
use Core\HTTP\Progress\Console AS ConsoleProgress;
use InvalidArgumentException;
use RuntimeException;

final class SyncCommand
  extends Command
{
  /**
   * Os provedores de dados habilitados às funções de sincronismo.
   * 
   * @var array
   */
  private $providers = [
    'fipe', 'stc'
  ];

  private $namespace = [
    'fipe' => 'Fipe',
    'stc'  => 'STC'
  ];

  /**
   * Exibe uma mensagem com a explicação do funcionamento do comando.
   * 
   * @return void
   */
  private function helpCommand()
  {
    $this->console->out("<white>O comando de sincronismo nos permite "
      . "sincronizar dados entre o ERP e um provedor externo. O "
      . "formato do comando é:"
    );
    $this->console->out();
    $this->console->out("<lightWhite>php ", $this->application,
      " <lightYellow>sync <lightCyan><provedor> <serviço>",
      " <lightMagenta>[<argumentos>]"
    );
    $this->console->out();
    $this->console->out("<white>Onde: ");
    $this->console->out("<lightYellow> - provedor: <white>É um dos ",
      "provedores de serviços válidos. Estão disponíveis:"
    );
    $this->console->out(" <lightCyan>            ",
      implode(', ', $this->providers)
    );
    $this->console->out("<lightYellow> - serviço:  <white>É o serviço "
      . "de atualização disponível para o provedor informado"
    );
    $this->console->out("<lightYellow> - argumentos: <white>um ou mais "
      . "argumentos opcionais a serem passados ao serviço deste "
      . "provedor"
    );
  }

  /**
   * Recupera as configurações para um provedor de dados.
   * 
   * @param string $providerName
   *   O nome do provedor de dados
   * 
   * @return array
   *   Uma matriz com as configurações da URL, método e caminho para
   *   armazenamento dos cookies
   *
   * @throws InvalidArgumentException
   */
  private function getConfiguration(string $providerName)
  {
    // Recuperamos as configurações de provedores disponíveis
    $settings = $this->container['settings']['integration'];

    if (array_key_exists($providerName, $settings)) {
      $providerSettings = $settings[$providerName];
    } else {
      throw new InvalidArgumentException("As configurações do provedor "
        . "de dados " . ucfirst($providerName) . " não estão "
        . "disponíveis."
      );
    }

    // Recupera as configurações deste provedor
    $url      = $providerSettings['url'];
    $method   = $providerSettings['method'];
    $path     = $providerSettings['path'];

    return [ $url, $method, $path ];
  }

  /**
   * Obtém o nome do ambiente (NameSpace) para o provedor informado.
   *
   * @param string $providerName
   *   O nome do provedor de dados
   *
   * @return string
   */
  private function getNameSpace(string $providerName)
  {
    return "App\Providers\\"
      . $this->namespace[$providerName] . "\\"
      . $this->namespace[$providerName]
    ;
  }

  /**
   * Instancia o provedor de dados.
   *
   * @param string $providerName
   *   O nome do provedor de dados
   * @param string[] $args
   *   Os argumentos da linha de comando
   *
   * @return object
   *   A instância do provedor de dados
   */
  private function createProviderData(string $providerName,
    array $args)
  {
    // O nome da classe para o sincronismo de dados
    $synchronizerClass = $this->getNameSpace($providerName)
      . 'DataSynchronizer'
    ;
    $providerClass = $this->getNameSpace($providerName)
      . 'IntegrationService'
    ;
    $provider = null;

    if (class_exists($synchronizerClass)) {
      // Recuperamos as configurações para o provedor de dados informado
      list($url, $method, $path) = $this->getConfiguration($providerName);

      // Criamos um serviço para acesso à API deste provedor através do
      // protocolo HTTP
      $httpService = new HTTPService($url, $method, $path);

      // Criamos uma exibição de progresso em modo console
      $consoleProgress = new ConsoleProgress();

      // Criamos nosso sincronizador de dados com este provedor
      $synchronizer = new $synchronizerClass($httpService, $this->logger,
        $consoleProgress)
      ;

      // Recuperamos o acesso ao banco de dados
      $DB = $this->container->get('DB');

      // Recuperamos a informação do contratante
      $contractor = $this->getContractor($args);

      if (class_exists($providerClass)) {
        // Criamos o serviço de integração
        $provider = new $providerClass($synchronizer, $this->logger,
          $contractor, $DB)
        ;
      } else {
        throw new RuntimeException("A classe {$providerClass} não "
          . "existe ou não está disponível."
        );
      }
    } else {
      throw new RuntimeException("A classe {$synchronizerClass} não "
        . "existe ou não está disponível."
      );
    }

    return $provider;
  }

  /**
   * O comando a ser executado
   *
   * @param string[] $args
   *   Os argumentos da linha de comando
   *
   * @return void
   */
  public function command(array $args)
  {
    // Registra a execução
    $this->debug("Executando o comando de sincronismo em modo console.",
      [ ]);

    // Verifica se foram fornecidos os argumentos na linha de comando
    if (count($args) < 1) {
      // Registra o erro
      $this->error("Não foi fornecido o nome do provedor de dados para "
        . "a operação de sincronismo.",
        [ ]);

      // Como não foi fornecido o nome de um provedor para os serviços
      // do sistema de integração que se deseja sincronizar, então exibe
      // uma mensagem de ajuda
      $this->helpCommand();

      return;
    }

    // A primeira opção deve ser o nome do provedor com o qual desejamos
    // trabalhar
    $providerName = strtolower(array_shift($args));

    if (!in_array($providerName, $this->providers)) {
      // Registra o erro
      $this->error("Não foi fornecido um provedor de dados válido "
        . "para executarmos a operação de sincronismo.",
        [ ]);

      $this->helpCommand();

      return;
    }

    // Extraímos a operação de loop, se necessário
    $repeat = false;
    if (($key = array_search("loop", $args)) !== false) {
      unset($args[$key]);
      $repeat = true;
    }

    // Inicializamos o provedor de dados
    $providerData = $this->createProviderData($providerName,
      array_slice($args, 1))
    ;

    // Verifica se foram fornecidos os argumentos na linha de comando
    if (count($args) < 1) {
      // Registra o erro
      $this->error("Não foi fornecido o nome do serviço do provedor de "
        . "dados para a operação de sincronismo.",
        [ ]);

      // Como não foi fornecido o nome de um serviço deste provedor do
      // sistema de integração que se deseja sincronizar, então exibe
      // uma mensagem de ajuda
      $this->console->out("<lightYellow><bgRed><bold>Atenção!");
      $this->console->out();
      $this->console->out("Não foi possível iniciar o sincronismo de ",
        "dados. Você não informou nenhum serviço disponível neste ",
        "provedor."
      );
      $this->console->out();
      $providerData->printServices();

      return;
    }

    // Recuperamos o nome do serviço
    $name = strtolower(array_shift($args));
    $serviceName = 'sync' . ucfirst($name);

    // Precisamos verificar se o serviço existe neste provedor de dados
    if (method_exists($providerData, $serviceName)) {
      $this->console->out("Sincronizando os dados através do provedor ",
        ucfirst($providerName)
      );
      $this->console->out("----------------------------------------------");
      $this->console->out();

      // Executamos o respectivo serviço deste provedor, passando 
      // restante dos argumentos disponíveis
      do {
        // Recuperamos o tempo antes do início do sincronismo
        $startTime = microtime(true);

        // Executamos o serviço de sincronismo
        $providerData->{$serviceName}($args);

        // Recuperamos o tempo após a execução do sincronismo
        $endTime = microtime(true);

        // Calculamos o tempo de execução
        $executionTime = $endTime - $startTime;

        // Exibimos o tempo de execução do sincronismo
        $this->console->out('Tempo total de execução: ',
          $this->getHumanTime((int) round($executionTime)))
        ;
      } while ($repeat);
    } else {
      // Registra o erro
      $this->error("Não foi informado um serviço válido para o "
        . "provedor {$providerName} para o qual deseja-se sincronizar.",
        [ ]
      );

      // Exibimos os serviços disponíveis neste provedor de dados
      $this->console->out("O provedor {$providerName} não possui um ",
        "serviço de sincronismo denominado de '{$name}'."
      );
      $providerData->printServices();
    }
  }


  // ===================================================[ Auxiliares ]==

  /**
   * Recupera o tempo decorrido (em segundos) de uma forma legível ao
   * usuário.
   * 
   * @param int $seconds
   *   O tempo em segundos
   * 
   * @return string
   *   O tempo de maneira legível
   */
  protected function getHumanTime(int $seconds): string
  {
    $humanTime = '0';

    if ($seconds > 0) {
      // Convertemos o tempo (em segundos) em um valor
      // legível ao usuário, ignorando segundos neste cálculo
      $now      = Carbon::now()->locale('pt_BR');
      $interval = Carbon::now()->locale('pt_BR')
        ->addSeconds($seconds)
      ;
      $humanTime = $now
        ->longAbsoluteDiffForHumans($interval, 4)
      ;
    }

    return $humanTime;
  }
  
  /**
   * Recupera os dados do contratante.
   * 
   * @param array $args
   *   Os argumentos de linha de comando
   * 
   * @return mixed
   *   Os dados do contratante
   */
  protected function getContractor(array $args) {
    // Verifica se foram fornecidos os argumentos na linha de comando
    $UUID = null;

    if (count($args) > 0) {
      // Verifica se foi informado o UUID do contratante
      $uuidParm = array_filter($args, function ($arg) {
        return (strpos($arg, "uuid") === 0);
      });
      if (count($uuidParm) > 0) {
        $UUID = strtolower(substr(array_slice($uuidParm, 0, 1)[0], 5));

        if (strlen($UUID) !== 36) {
          // Devemos interromper, pois a UUID não é válida
          throw new RuntimeException("A UUID '{$UUID}' informada é "
            . "inválida")
          ;
        }
      }
    }
    
    if (is_null($UUID)) {
      // Recupera da configuração o UUID do contratante base
      $settings = $this->container['settings']['contractor'];
      $UUID = $settings['uuid'];
    }

    // Recupera a informação do contratante
    $contractor = Entity::where("contractor", "true")
      ->where("entityuuid", "=", $UUID)
      ->get([
          "entityid AS id",
          "name",
          "stckey"
        ])
      ->first();
    ;

    if (!$contractor) {
      // Devemos interromper, pois não encontramos um contratante à
      // partir da UUID informada
      throw new RuntimeException("Não temos um contratante com a UUID '{$UUID}'");
    }

    return $contractor;
  }

  protected function showApplicationData()
  {
    $this->console->out("  _____            _           ___ ___ ___  ");
    $this->console->out(" |_   _| _ __ _ __| |_____ _ _| __| _ \ _ \ ");
    $this->console->out("   | || '_/ _` / _| / / -_) '_| _||   /  _/ ");
    $this->console->out("   |_||_| \__,_\__|_\_\___|_| |___|_|_\_|   ");
    $this->console->out("                                            ");
  }
}