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
 * O controlador do comando de importação das informações de sistemas
 * legados.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

declare(strict_types = 1);

namespace App\Commands;

use App\Models\Entity AS Contractor;
use Carbon\Carbon;
use Core\Console\Command;
use Core\Controllers\QueryTrait;
use Core\CSV\ParseCSV;
use Core\Helpers\FormatterTrait;
use Core\Helpers\Path;
use RuntimeException;

final class ImportCommand
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
   * Exibe uma mensagem com a explicação do funcionamento do comando.
   * 
   * @return void
   */
  private function helpCommand()
  {
    $this->console->out("<white>O comando de importação de arquivo CSV "
      . "nos permite incorporar informações de sistemas legados. O "
      . "formato do comando é:"
    );
    $this->console->out();
    $this->console->out("<lightWhite>php ", $this->application,
      " <lightYellow>csvfile"
    );
    $this->console->out();
  }

  /**
   * Formata um valor monetário do arquivo CSV no formato do SQL.
   *
   * @param string $text
   *   O valor a ser convertido
   *                             
   * @return string
   *   O valor corretamente formatado
   */
  private function moneyFormat(string $text): string
  {
    $text = trim($text, 'R$');
    $text = trim($text);
    $value = str_replace(',', '.', str_replace('.', '', $text));

    return $value;
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
      "Executando o comando de importação de arquivo CSV contendo "
      . "informações de faturas em modo console.",
      [ ]
    );

    try {
      // Recuperamos a informação do contratante
      $contractor = $this->getContractor($args);

      if (count($args) > 0) {
        // Verifica se foi informado o arquivo
        $filenameParm = array_filter($args, function ($arg) {
          return (strpos($arg, "file") === 0);
        });

        if (count($filenameParm) > 0) {
          $filename = substr(array_slice($filenameParm, 0, 1)[0], 5);
        }
      }

      // Verifica se o arquivo existe
      $csvFile = new Path($filename);
      if (!$csvFile->exists() || !$csvFile->isFile()) {
        throw new RuntimeException("O arquivo {$filename} não existe");
      }

      // Importamos o arquivo
      $csv = new ParseCSV($filename);

      $this->console->out("<white>Processando arquivo "
        . "<lightWhite>{$filename}:"
      );

      //$results = fopen("results.csv", "w");
      //fwrite($results, "subsidiaryID, customerName\n");
      // Identificamos os IDs de cada cliente, para simplificar. Uma vez
      // localizado os ID's, eu vou inserir manualmente o ID de cada
      // cliente no respectivo arquivo CSV
      //foreach ($csv as $line) {
      //  // Obtemos o ID através do nome do cliente
      //  $sql = ""
      //    . "SELECT entityID AS id,"
      //    . "       name"
      //    . "  FROM erp.entities"
      //    . " WHERE public.unaccented(name) ILIKE public.unaccented('%{$line['customerName']}%')"
      //    . "   AND customer = true;"
      //  ;
      //  $customer = $this->DB->select($sql);
      //  if ($customer) {
      //    fwrite($results,
      //      sprintf('%d, "%s"' . PHP_EOL, $customer[0]->id, $customer[0]->name)
      //    );
      //    echo "{$customer[0]->id}, {$line['customerName']}\n";
      //  } else {
      //    fwrite($results,
      //      sprintf('%d, "%s"' . PHP_EOL, 0, $line['customerName'])
      //    );
      //    echo "[erro] {$line['customerName']}\n";
      //  }
      //}
      //fclose($results);
      //exit();
      // Identificamos os IDs de cada filial do cliente, para
      // simplificar. Uma vez localizado os ID's, eu vou inserir
      // manualmente o ID de cada cliente no respectivo arquivo CSV
      //$cache = [];
      //foreach ($csv as $line) {
      //  echo $line['customerID'] . "\n";
      //  if (intval($line['customerID']) > 0) {
      //    if (isset($cache[$line['customerID']])) {
      //      // Obtemos do cache
      //      fwrite($results,
      //        sprintf('%d, "%s"' . PHP_EOL, $cache[$line['customerID']], $line['customerName'])
      //      );
      //    } else {
      //      // Obtemos o ID da unidade/filial através do ID do cliente
      //      $sql = ""
      //        . "SELECT subsidiaryID AS id"
      //        . "  FROM erp.subsidiaries"
      //        . " WHERE entityid = {$line['customerID']}"
      //        . "   AND affiliated = false"
      //        . " ORDER BY subsidiaryID"
      //        . " FETCH FIRST ROW ONLY;"
      //      ;
      //      $subsidiary = $this->DB->select($sql);
      //      if ($subsidiary) {
      //        fwrite($results,
      //          sprintf('%d, "%s"' . PHP_EOL, $subsidiary[0]->id, $line['customerName'])
      //        );
      //        echo "{$subsidiary[0]->id}, {$line['customerName']}\n";
      //      } else {
      //        fwrite($results,
      //          sprintf('%d, "%s"' . PHP_EOL, 0, $line['customerName'])
      //        );
      //        echo "[erro] {$line['customerName']}\n";
      //      }
      //
      //      // Armazenamos no cache
      //      $cache[$line['customerID']] = $subsidiary[0]->id;
      //    }
      //  } else {
      //    fwrite($results,
      //      sprintf('%d, "%s"' . PHP_EOL, 0, $line['customerName'])
      //    );
      //  }
      //}
      //fclose($results);
      //exit();

      // Importamos as faturas antigas
      $queries = fopen("queries.sql", "w");
      $results = fopen("results.csv", "w");
      $contract = [];
      foreach ($csv as $line) {
        // Estamos lidando com as faturas antigas. Elas serão apenas
        // incluídas para efeito de documentação. Exibimos qual a fatura
        // sendo importada
        echo "--------------------------------------------------\n";
        echo " * {$line['invoiceNumber']} - [{$line['customerID']}] {$line['customerName']}\n";

        if (intval($line['customerID']) > 0) {
          if ($line['Imported'] === 'true') {
            echo " *** Ignorando registro já importado\n";
            fwrite($results, sprintf('%d, "true", "Já importando"' . PHP_EOL, $line['invoiceID']));
          } else {
            // Lidamos com o tipo de cobrança de maneira diferenciada
            if (strtolower($line['formato_boleto']) == 'boleto') {
              // Estamos lidando com um boleto
              echo " - Lidando com boleto\n";
              
              // Precisamos descobrir o número do contrato deste cliente
              if (isset($contract[$line['customerID']])) {
                $contractID = $contract[$line['customerID']];
              } else {
                $sql = ""
                  . "SELECT contractID AS id"
                  . "  FROM erp.contracts"
                  . " WHERE customerID = {$line['customerID']}"
                  . "   AND subsidiaryID = {$line['subsidiaryID']}"
                  . " FETCH FIRST ROW ONLY;"
                ;
                $contract = $this->DB->select($sql);
                if ($contract) {
                  $contractID = intval($contract[0]->id);
                } else {
                  $contractID = 0;
                }

                // Armazenamos no cache para acelerar
                $contract[$line['customerID']] = $contractID;
              }

              if ($contractID > 0) {
                // Criamos uma fatura com os valores informados
                fwrite($queries, "-- {$line['invoiceNumber']} - [{$line['customerID']}] {$line['customerName']}\n");
                fwrite($queries, "BEGIN TRANSACTION;\n");
                fwrite($queries, sprintf(""
                    . "INSERT INTO erp.invoices (invoiceID, contractorID, "
                    . "customerID, subsidiaryID, invoiceDate, dueDate, "
                    . "paymentMethodID, definedMethodID, invoiceValue, "
                    . "referenceMonthYear) "
                    . "VALUES (%d, 1, %d, %d, '%s'::Date, '%s'::Date, %d, "
                    . "%d, %s, '%s');\n",
                    $line['invoiceID'],
                    $line['customerID'],
                    $line['subsidiaryID'],
                    $line['invoiceDate'],
                    $line['dueDate'],
                    5,
                    1,
                    $line['valueToPay'],
                    $line['reference']
                  )
                );

                // Criamos uma cobrança única com o total do valor informado
                fwrite($queries, sprintf(""
                    . "INSERT INTO erp.billings (contractorID, contractID, "
                    . "installationID, billingDate, name, value, invoiceID, "
                    . "invoiced, isMonthlyPayment, "
                    . "createdByUserID, updatedByUserID) "
                    . "VALUES (1, %d, NULL, '%s'::Date, 'Fatura %s', %s, "
                    . "%d, true, true, 1, 1);\n",
                    $contractID,
                    $line['invoiceDate'],
                    $line['reference'],
                    $line['valueToPay'],
                    $line['invoiceID']
                  )
                );

                // Criamos um lançamento único com o total do valor
                // informado
                switch (strtolower($line['situacao'])) {
                  case 'aberto':
                    echo " - Boleto aberto\n";
                    $sentToDunningBureau = $line['MAB'] === 'SIM'
                      ? 'TRUE'
                      : 'FALSE'
                    ;
                    fwrite($queries, sprintf(""
                        . "INSERT INTO erp.bankingBilletPayments (contractorID, "
                        . "invoiceID, invoiceNumber, dueDate, valueToPay, "
                        . "paymentMethodID, paymentSituationID, definedMethodID, "
                        . "bankCode, agencyNumber, accountNumber, wallet, "
                        . "billingCounter, ourNumber, droppedTypeID, "
                        . "sentToDunningBureau) "
                        . "VALUES (1, %d, %s, '%s'::Date, %s, %d, %d, "
                        . "%d, '%s', '%s', '%s', '%s', %d, '%s', %d, %s);\n",
                        $line['invoiceID'],
                        ($line['invoiceID']==$line['invoiceNumber']?'NULL':"'{$line['invoiceNumber']}'"),
                        $line['dueDate'],
                        $line['valueToPay'],
                        5, // Boleto
                        1, // A receber
                        1, // DefinedMethod
                        $line['bankID'],
                        $line['agencyNumber'] . ($line['DACofAgencyNumber']=='0'?'':'-' . $line['DACofAgencyNumber']),
                        $line['AccountNumber'] . '-' . $line['DACAccountNUmber'],
                        '9',
                        $line['invoiceID'],
                        $line['invoiceID'],
                        2, // Em aberto
                        $sentToDunningBureau
                      )
                    );
                    fwrite($queries, "COMMIT;\n");
                    fwrite($results, sprintf('%d, "true", "Importado"' . PHP_EOL, $line['invoiceID']));

                    break;
                  case 'baixado':
                    echo " - Boleto baixado\n";
                    fwrite($queries, sprintf(""
                        . "INSERT INTO erp.bankingBilletPayments (contractorID, "
                        . "invoiceID, invoiceNumber, dueDate, valueToPay, "
                        . "paymentMethodID, paymentSituationID, paidDate, "
                        . "paidValue, creditDate, definedMethodID, "
                        . "bankCode, agencyNumber, accountNumber, wallet, "
                        . "billingCounter, ourNumber, droppedTypeID) "
                        . "VALUES (1, %d, %s, '%s'::Date, %s, %d, %d, "
                        . "'%s'::Date, %s, "
                        . "erp.getNextWorkday(('%s'::Date + interval '1 day')::Date, 4971), "
                        . " %d, '%s', '%s', '%s', '%s', %d, '%s', %d);\n",
                        $line['invoiceID'],
                        ($line['invoiceID']==$line['invoiceNumber']?'NULL':"'{$line['invoiceNumber']}'"),
                        $line['dueDate'],
                        $line['valueToPay'],
                        5, // Boleto
                        2, // Pago
                        ($line['paidDate']==''?$line['dueDate']:$line['paidDate']),
                        ($line['paidValue']==''?$line['valueToPay']:$line['paidValue']),
                        ($line['paidDate']==''?$line['dueDate']:$line['paidDate']),
                        1, // DefinedMethod
                        $line['bankID'],
                        $line['agencyNumber'] . ($line['DACofAgencyNumber']=='0'?'':'-' . $line['DACofAgencyNumber']),
                        $line['AccountNumber'] . '-' . $line['DACAccountNUmber'],
                        '9',
                        $line['invoiceID'],
                        $line['invoiceID'],
                        3  // Compensado
                      )
                    );
                    fwrite($queries, "COMMIT;\n");
                    fwrite($results, sprintf('%d, "true", "Importado"' . PHP_EOL, $line['invoiceID']));

                    break;
                  case 'negociado':
                    echo " - Boleto negociado\n";
                    $sentToDunningBureau = $line['MAB'] === 'SIM'
                      ? 'TRUE'
                      : 'FALSE'
                    ;
                    fwrite($queries, sprintf(""
                        . "INSERT INTO erp.bankingBilletPayments (contractorID, "
                        . "invoiceID, invoiceNumber, dueDate, valueToPay, "
                        . "paymentMethodID, paymentSituationID, definedMethodID, "
                        . "bankCode, agencyNumber, accountNumber, wallet, "
                        . "billingCounter, ourNumber, droppedTypeID, "
                        . "sentToDunningBureau) "
                        . "VALUES (1, %d, %s, '%s'::Date, %s, %d, %d, "
                        . "%d, '%s', '%s', '%s', '%s', %d, '%s', %d, %s);\n",
                        $line['invoiceID'],
                        ($line['invoiceID']==$line['invoiceNumber']?'NULL':"'{$line['invoiceNumber']}'"),
                        $line['dueDate'],
                        $line['valueToPay'],
                        5, // Boleto
                        4, // Negociado
                        1, // DefinedMethod
                        $line['bankID'],
                        $line['agencyNumber'] . ($line['DACofAgencyNumber']=='0'?'':'-' . $line['DACofAgencyNumber']),
                        $line['AccountNumber'] . '-' . $line['DACAccountNUmber'],
                        '9',
                        $line['invoiceID'],
                        $line['invoiceID'],
                        5, // Baixa manual
                        $sentToDunningBureau
                      )
                    );
                    fwrite($queries, "COMMIT;\n");
                    fwrite($results, sprintf('%d, "true", "Importado"' . PHP_EOL, $line['invoiceID']));

                    break;
                  case 'cancelado':
                    echo " - Boleto cancelado\n";
                    fwrite($queries, sprintf(""
                        . "INSERT INTO erp.bankingBilletPayments (contractorID, "
                        . "invoiceID, invoiceNumber, dueDate, valueToPay, "
                        . "paymentMethodID, paymentSituationID, definedMethodID, "
                        . "bankCode, agencyNumber, accountNumber, wallet, "
                        . "billingCounter, ourNumber, droppedTypeID) "
                        . "VALUES (1, %d, %s, '%s'::Date, %s, %d, %d, "
                        . "%d, '%s', '%s', '%s', '%s', %d, '%s', %d);\n",
                        $line['invoiceID'],
                        ($line['invoiceID']==$line['invoiceNumber']?'NULL':"'{$line['invoiceNumber']}'"),
                        $line['dueDate'],
                        $line['valueToPay'],
                        5, // Boleto
                        3, // Cancelado
                        1, // DefinedMethod
                        $line['bankID'],
                        $line['agencyNumber'] . ($line['DACofAgencyNumber']=='0'?'':'-' . $line['DACofAgencyNumber']),
                        $line['AccountNumber'] . '-' . $line['DACAccountNUmber'],
                        '9',
                        $line['invoiceID'],
                        $line['invoiceID'],
                        5 // Baixa manual
                      )
                    );
                    fwrite($queries, "COMMIT;\n");
                    fwrite($results, sprintf('%d, "true", "Importado"' . PHP_EOL, $line['invoiceID']));

                    break;
                  default:
                    echo " *** Situação do boleto inválida\n";
                    fwrite($results, sprintf('%d, "false", "Situação inválida"' . PHP_EOL, $line['invoiceID']));
                    fwrite($queries, "ROLLBACK;\n");
                }
              } else {
                // Não foi possível identificar o contrato
                echo " *** Não foi possível identificar o contrato\n";
                fwrite($results, sprintf('%d, "false", "Não foi possível identificar o contrato"' . PHP_EOL, $line['invoiceID']));
              }
            } else {
              echo " - {$line['formato_boleto']}\n";
              echo " *** Ignorando\n";
              fwrite($results, sprintf('%d, "false", "É um %s"' . PHP_EOL, $line['invoiceID'], $line['formato_boleto']));
            }
          }
        } else {
          echo " *** Cliente não identificado\n";
          fwrite($results, sprintf('%d, "false", "Cliente não identificado"' . PHP_EOL, $line['invoiceID']));
        }
      }
      fclose($queries);
      fclose($results);

    }
    catch (RuntimeException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o evento
      $this->error("Não foi possível recuperar os dados do emissor");

      // Como não foi fornecido o nome de um serviço deste provedor do
      // sistema de integração que se deseja sincronizar, então exibe
      // uma mensagem de ajuda
      $this->console->out("<lightYellow><bgRed><bold>Atenção!");
      $this->console->out();
      $this->console->out("Não foi possível recuperar os dados do "
        . "emissor. " . $exception->getMessage()
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
}