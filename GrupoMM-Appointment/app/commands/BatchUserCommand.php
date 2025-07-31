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
 * O controlador do comando de criação de usuário em linha de comando.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

 /**
  * NOTA: Para obter os usuários, execute a query:
  * WITH customers AS (
  *   SELECT V.plate,
  *          V.customerID,
  *          C.name,
  *          S.nationalregister
  *     FROM erp.equipments AS E
  *    INNER JOIN lastPositions AS L ON (E.serialNumber = L.terminalID)
  *    INNER JOIN erp.vehicles AS V ON (E.vehicleID = V.vehicleID)
  *    INNER JOIN erp.entities AS C ON V.customerID = C.entityID
  *    INNER JOIN erp.subsidiaries AS S ON (V.subsidiaryID = S.subsidiaryID)
  *    WHERE E.equipmentModelID IN (60, 61)
  *      AND E.contractorID = 1
  *      AND E.storageLocation = 'Installed')
  * SELECT customers.customerID AS id,
  *        customers.name,
  *        regexp_replace(customers.nationalregister, '[^\d]', '', 'g'),
  *        U.userID,
  *        U.username AS login
  *   FROM customers
  *   LEFT JOIN erp.users AS U ON U.entityid = customers.customerID
  *  WHERE U.name IS NULL;
  */

declare(strict_types = 1);

namespace App\Commands;

use App\Models\Entity AS Contractor;
use Carbon\Carbon;
use Core\Authorization\Authorization;
use Core\Console\Command;
use Core\Controllers\QueryTrait;
use Core\CSV\ParseCSV;
use Core\Hashing\HasherInterface;
use Core\Hashing\Sha384Hasher;
use Core\Helpers\FormatterTrait;
use Core\Helpers\Path;
use RuntimeException;

final class BatchUserCommand
  extends Command
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Os métodos para formatar valores.
   */
  use FormatterTrait;

  /**
   * A instância do gerador do número de verificação (Hash).
   *
   * @var HasherInterface
   */
  protected $hasher;

  /**
   * Exibe uma mensagem com a explicação do funcionamento do comando.
   * 
   * @return void
   */
  private function helpCommand()
  {
    $this->console->out("<white>O comando de importação de usuários de "
      . "arquivo CSV nos permite criar usuários manualmente. O "
      . "formato do comando é:"
    );
    $this->console->out();
    $this->console->out("<lightWhite>php ", $this->application,
      " <lightYellow>csvfile"
    );
    $this->console->out();
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
    $this->debug(
      "Executando o comando de importação de usuários de arquivo CSV "
      . "contendo informações de faturas em modo console.",
      [ ]
    );

    // Cria um manipulador de hashing para proteção da senha do usuário
    $this->hasher = new Sha384Hasher();

    try {
      // Recuperamos a informação do contratante
      $contractor = $this->getContractor($args);

      //if (count($args) > 0) {
      //  // Verifica se foi informado o arquivo
      //  $filenameParm = array_filter($args, function ($arg) {
      //    return (strpos($arg, "file") === 0);
      //  });
      //
      //  if (count($filenameParm) > 0) {
      //    $filename = substr(array_slice($filenameParm, 0, 1)[0], 5);
      //  }
      //}

      //if (!isset($filename)) {
      //  throw new RuntimeException("O nome do arquivo não foi informado.");
      //}

      // Verifica se o arquivo existe
      //$csvFile = new Path($filename);
      //if (!$csvFile->exists() || !$csvFile->isFile()) {
      //  throw new RuntimeException("O arquivo {$filename} não existe");
      //}

      // Importamos o arquivo
      //$csv = new ParseCSV($filename);

      //$this->console->out("<white>Processando arquivo "
      //  . "<lightWhite>{$filename}:"
      //);

      // Obtemos se o usuário já foi criado
      $sql = ""
        . "WITH customers AS ("
        . "  SELECT V.plate,"
        . "         V.customerID,"
        . "         C.name,"
        . "         S.nationalregister"
        . "    FROM erp.equipments AS E"
        . "   INNER JOIN erp.vehicles AS V ON (E.vehicleID = V.vehicleID)"
        . "   INNER JOIN erp.entities AS C ON V.customerID = C.entityID"
        . "   INNER JOIN erp.subsidiaries AS S ON (V.subsidiaryID = S.subsidiaryID)"
        . "   WHERE E.equipmentModelID IN (55, 56)"
        . "     AND E.contractorID = 1"
        . "     AND E.storageLocation = 'Installed')"
        . "SELECT customers.customerID AS id,"
        . "       customers.name,"
        . "       regexp_replace(customers.nationalregister, '[^\d]', '', 'g') AS login,"
        . "       (SELECT phonenumber FROM erp.phones WHERE entityid = customers.customerID LIMIT 1) AS phone,"
        . "       (SELECT email FROM erp.mailings WHERE entityid = customers.customerID LIMIT 1) AS email"
        . "  FROM customers"
        . "  GROUP BY customers.customerID,"
        . "           customers.name,"
        . "           customers.nationalregister"
        . "  ORDER BY customers.name;"
      ;
      $customers = $this->DB->select($sql);

      foreach ($customers as $customer) {
        echo sprintf('Analisando cliente [%d] "%s"' . PHP_EOL, $customer->id, $customer->name);

        // Obtemos se o usuário já foi criado
        $sql = ""
          . "SELECT userID AS id,"
          . "       name"
          . "  FROM erp.users"
          . " WHERE username ILIKE '%{$customer->login}%';"
        ;
        $user = $this->DB->select($sql);

        if ($user) {
          echo sprintf('Usuário já cadastrado: [%d] %s' . PHP_EOL, $user[0]->id, $user[0]->name);
        } else {
          echo sprintf('Criando usuário [%s]' . PHP_EOL, $customer->login);

          // Encripta a senha fornecida
          $password = $this->getHashedPassword("#rastro23");
          $sql = ""
            . "INSERT INTO erp.users (groupid, name, role, username, "
            . "password, phonenumber, contractorid, entityid, email) "
            . "VALUES (6, '{$customer->name}', 'Usuário', '{$customer->login}', "
            . "'{$password}', '{$customer->phone}', 1, {$customer->id}, '{$customer->email}');"
          ;
          $this->DB->select($sql);
        }
      }
    }
    catch (RuntimeException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o evento
      $this->error("Não foi possível criar usuários em lote. Erro"
        . $exception->getMessage()
      );

      // Como não foi fornecido o nome de um serviço deste provedor do
      // sistema de integração que se deseja sincronizar, então exibe
      // uma mensagem de ajuda
      $this->console->out("<lightYellow><bgRed><bold>Atenção!");
      $this->console->out();
      $this->console->out("Não foi possível executar a criação de "
        . "usuários em lote. " . $exception->getMessage()
      );
      $this->console->out();

      return;
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
    $contractor = Contractor::where("contractor", "true")
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

  /**
   * Cria uma senha encriptada.
   * 
   * @param string $plainPassword
   *   A senha em texto puro
   * 
   * @return string
   *   A senha encriptada
   */
  public function getHashedPassword(string $plainPassword): string
  {
    if (isset($plainPassword)) {
      $password = $this->hasher->hash($plainPassword);
    }

    return $password;
  }
}