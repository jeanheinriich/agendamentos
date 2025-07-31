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
 * O controlador do comando de exportação das informações a serem
 * enviadas para a agência de cobrança.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

declare(strict_types = 1);

namespace App\Commands;

use App\Models\BankingBilletPayment;
use App\Models\Entity AS Contractor;
use App\Models\Entity As Customer;
use App\Models\Mailing;
use App\Models\MailingAddress;
use App\Models\Phone;
use App\Models\Subsidiary;
use Carbon\Carbon;
use Core\Console\Command;
use Core\Controllers\QueryTrait;
use Core\FileHandlers\Excel_XML;
use Core\Helpers\FormatterTrait;
use Core\Payments\PaymentSituation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Exception;
use RuntimeException;

final class GetDunningAgencyDataCommand
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
    $this->console->out("<white>O comando de exportação de planilha "
      . "contendo os dados de clientes marcados para envio à agência "
      . "de cobrança. O formato do comando é:"
    );
    $this->console->out();
    $this->console->out("<lightWhite>php ", $this->application,
      " <lightYellow>getagencydata"
    );
    $this->console->out();
  }

  /**
   * Recupera as informações de números de telefones de uma
   * unidade/filial/titular/associado.
   *
   * @param int $customerID
   *   A ID do cliente para o qual desejamos obter esta informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste cliente para o qual desejamos obter
   *   os dados de telefones disponíveis
   *
   * @return Collection
   *   A matriz com as informações de números de telefones
   *
   * @throws RuntimeException
   *   Em caso de erros
   */
  protected function getPhones(
    int $customerID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de números de telefones
    return Phone::join('phonetypes',
          'phones.phonetypeid', '=', 'phonetypes.phonetypeid'
        )
      ->where('entityid', $customerID)
      ->where('subsidiaryid', $subsidiaryID)
      ->get([
          'phones.entityid',
          'phones.subsidiaryid',
          'phones.phoneid',
          'phones.phonetypeid',
          'phonetypes.name as phonetypename',
          'phones.phonenumber'
        ])
    ;
  }

  /**
   * Recupera as informações de e-mails de uma unidade/filial/titular ou
   * associado.
   *
   * @param int $customerID
   *   A ID do cliente para o qual desejamos obter esta informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste cliente para o qual desejamos obter
   *   os dados de e-mails disponíveis
   *
   * @return Collection
   *   A matriz com as informações de e-mails
   *
   * @throws RuntimeException
   *   Em caso de erros
   */
  protected function getEmails(
    int $customerID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de dados de e-mail
    return Mailing::where('entityid', $customerID)
      ->where('subsidiaryid', $subsidiaryID)
      ->get([
          'entityid',
          'subsidiaryid',
          'mailingid',
          'email'
        ])
    ;
  }

  /**
   * Recupera as informações de contatos adicionais de uma
   * unidade/filial/titular/associado.
   *
   * @param int $contractorID
   *   A ID do contratante deste cliente
   * @param int $customerID
   *   A ID do cliente para o qual desejamos obter esta informação
   * @param int $subsidiaryID
   *   A ID da unidade/filial deste cliente para o qual desejamos obter
   *   os dados de contato disponíveis
   *
   * @return Collection
   *   A matriz com as informações de dados de contatos adicionais
   *
   * @throws RuntimeException
   *   Em caso de erros
   */
  protected function getContacts(
    int $contractorID,
    int $customerID,
    int $subsidiaryID
  ): Collection
  {
    // Recupera as informações de contatos adicionais
    return MailingAddress::join('phonetypes',
          'mailingaddresses.phonetypeid', '=', 'phonetypes.phonetypeid'
        )
      ->join('mailingprofiles',
          function ($join) use ($contractorID) {
            $join
              ->on('mailingaddresses.mailingprofileid', '=',
                  'mailingprofiles.mailingprofileid'
                )
              ->where('mailingprofiles.contractorid', '=',
                  $contractorID
                )
            ;
          }
        )
      ->where('entityid', $customerID)
      ->where('subsidiaryid', $subsidiaryID)
      ->get([
          'mailingaddresses.entityid',
          'mailingaddresses.subsidiaryid',
          'mailingaddresses.mailingaddressid',
          'mailingaddresses.name',
          'mailingaddresses.attribute',
          'mailingaddresses.mailingprofileid',
          'mailingprofiles.name as mailingprofilename',
          'mailingaddresses.email',
          'mailingaddresses.phonetypeid',
          'phonetypes.name as phonetypename',
          'mailingaddresses.phonenumber'
        ])
    ;
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
      "Executando o comando de exportação em planilha dos dados de "
      . "clientes inadimplentes para envio à agência de cobrança em "
      . "modo console.",
      [ ]
    );

    try {
      // Recuperamos a informação do contratante
      $contractor = $this->getContractor($args);

      // Recuperamos os dados de clientes inadimplentes marcados para
      // envio para agência de cobrança
      $sql = ""
        . "WITH lateBillets AS ("
        . "     SELECT DISTINCT ON (I.customerID)"
        . "            I.customerID AS id,"
        . "            DATE_PART('day', CURRENT_DATE::timestamp - P.dueDate::timestamp)::int AS days,"
        . "            S.overduenoticedays::int AS factor"
        . "       FROM erp.bankingBilletPayments AS P"
        . "      INNER JOIN erp.invoices AS I USING (invoiceID)"
        . "      INNER JOIN erp.taskSettings AS S ON (P.contractorID = S.contractorID)"
        . "      WHERE P.dueDate < CURRENT_DATE"
        . "        AND P.paymentSituationID = " . PaymentSituation::RECEIVABLE
        . "        AND P.contractorID = {$contractor->id}"
        . "        AND (P.restrictionid >> 2) & 1 = 1"
        . "      ORDER BY I.customerID, P.dueDate"
        . "   ), customersWithLatePayments AS ("
        . "     SELECT id"
        . "       FROM lateBillets"
        . "      WHERE days > 0"
        . "      GROUP BY id"
        . "   )"
        . " SELECT I.customerID AS id,"
        . "        array_agg(P.paymentID::TEXT) AS recordsOnScope"
        . "   FROM erp.bankingBilletPayments AS P"
        . "  INNER JOIN erp.invoices AS I USING (invoiceID)"
        . "  INNER JOIN customersWithLatePayments AS C ON (I.customerID = C.id)"
        . "  WHERE P.dueDate < CURRENT_DATE"
        . "    AND P.paymentSituationID = " . PaymentSituation::RECEIVABLE
        . "  GROUP BY I.customerID;"
      ;
      $latePayments = $this->DB->select($sql);

      // Criamos uma nova planilha Excel
      $xls = new Excel_XML;

      // Criamos uma nova folha de trabalho
      $workSheet = [];
      $workSheet[] = [
        1 => 'Devedor',
        'CPF/CNPJ',
        'Fone Residencial',
        'Fone Comercial',
        'Fone Celular',
        'E-mail',
        'Empreendimento (produto)',
        'Contrato',
        'Nº Boleto',
        'Banco',
        'Agência',
        'Conta',
        'Nº Cheque',
        'Alínea',
        'Vencimento',
        'Valor',
        'Despesas',
        'Obs',
      ];

      // Para cada cliente, obtemos os dados de cada cliente
      foreach ($latePayments as $latePayment) {
        // Obtemos os dados do cliente
        $customer = Customer::join("entitiestypes", "entities.entitytypeid",
              '=', "entitiestypes.entitytypeid"
            )
          ->where("entities.entityid", $latePayment->id)
          ->where("entities.contractorid", '=', $contractor->id)
          ->get([
              'entitiestypes.name as entitytypename',
              'entitiestypes.juridicalperson',
              'entities.*'
            ])
          ->first()
        ;

        // Agora recupera as informações da unidade/filial
        $subsidiary = Subsidiary::join("cities",
              "subsidiaries.cityid", '=', "cities.cityid"
            )
          ->join("documenttypes", "subsidiaries.regionaldocumenttype",
              '=', "documenttypes.documenttypeid"
            )
          ->where("entityid", $latePayment->id)
          ->orderBy("subsidiaryid")
          ->get([
              'subsidiaries.subsidiaryid AS id',
              'subsidiaries.*',
              'documenttypes.name as regionaldocumenttypename',
              'cities.name as cityname',
              'cities.state as state'
            ])
          ->first()
        ;

        // Telefones
        $phones = $this
          ->getPhones(
              $latePayment->id,
              $subsidiary->id
            )
        ;

        // E-mails
        $emails = $this
          ->getEmails(
              $latePayment->id,
              $subsidiary['subsidiaryid']
            )
        ;

        // Contatos adicionais
        $contacts = $this
          ->getContacts(
              $contractor->id,
              $latePayment->id,
              $subsidiary['subsidiaryid']
            )
        ;

        $residencial = [];
        $comercial = [];
        $celular = [];
        foreach ($phones as $phone) {
          if ($phone->phonetypeid == 2) {
            $celular[] = $phone->phonenumber;
          } else {
            if ($customer->juridicalperson) {
              $comercial[] = $phone->phonenumber;
            } else {
              $residencial[] = $phone->phonenumber;
            }
          }
        }

        $address = [];
        foreach ($emails as $email) {
          $address[] = $email->email;
        }

        foreach ($contacts as $contact) {
          if ($contact->email) {
            $address[] = $contact->email;
          }

          if ($contact->phonenumber) {
            if ($contact->phonetypeid == 2) {
              $celular[] = $contact->phonenumber;
            } else {
              if ($customer->juridicalperson) {
                $comercial[] = $contact->phonenumber;
              } else {
                $residencial[] = $contact->phonenumber;
              }
            }
          }
        }

        // Convertemos em lista
        $residencial = implode(', ', $residencial);
        $comercial = implode(', ', $comercial);
        $celular = implode(', ', $celular);
        $address = implode(', ', $address);

        // Por último, obtemos os dados das cobranças abertas

        $inList = explode(
          ',', trim(trim($latePayment->recordsonscope,'{'),'}')
        );

        $payments = BankingBilletPayment::join('invoices',
              'bankingbilletpayments.invoiceid', '=', 'invoices.invoiceid'
            )
          ->join('paymentmethods', 'bankingbilletpayments.paymentmethodid',
              '=', 'paymentmethods.paymentmethodid'
            )
          ->where('bankingbilletpayments.contractorid', '=', $contractor->id)
          ->whereIn('bankingbilletpayments.paymentid', $inList)
          ->get()
        ;

        $sql = "SELECT getContractNumber(C.createdat) AS number
                  FROM erp.installations AS I
                 INNER JOIN erp.contracts AS C USING (contractID)
                 WHERE I.customerID = {$latePayment->id}
                 ORDER BY I.enddate NULLS FIRST
                 FETCH FIRST ROW ONLY;";
        $contracts = $this->DB->select($sql);
        $contractNumber = $contracts[0]->number;

        foreach ($payments as $payment) {
          // Criamos uma nova linha e acrescentamos os dados
          $row = [
            1 => $customer->name .
            ( ($customer->juridicalperson)
                ? ', contato com ' . $subsidiary->personname
                :''
              ),
            $subsidiary->nationalregister,
            $residencial,
            $comercial,
            $celular,
            $address,
            'Serviço de rastreamento',
            $contractNumber,
            $payment->invoicenumber?$payment->invoicenumber:(string)$payment->invoiceid,
            $payment->bankcode,
            $payment->agencynumber,
            $payment->accountnumber,
            '',
            '',
            $payment->duedate->format('d/m/Y'),
            number_format((float) $payment->valuetopay, 2, ',', ''),
            number_format((float) $payment->tariffvalue, 2, ',', ''),
            ''
          ];

          $workSheet[] = $row;

          print_r($workSheet);
        }
      }

      $xls->addWorksheet('Plan1', $workSheet);
      $workbook = $xls->writeWorkbook('ClientesInadimplentes.xls');
    } catch(ModelNotFoundException $exception) {
      // Registra o erro
      $this->error("Não foi possível localizar o cliente. {error}",
        [ 'error' => $exception->getMessage() ]
      );

      // Como não foi fornecido o nome de um serviço deste provedor do
      // sistema de integração que se deseja sincronizar, então exibe
      // uma mensagem de ajuda
      $this->console->out("<lightYellow><bgRed><bold>Atenção!");
      $this->console->out();
      $this->console->out("Não foi possível recuperar os dados do "
        . "cliente. " . $exception->getMessage()
      );
      $this->console->out();

      return;
    } catch (Exception $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar os dados. {error}",
        [ 'error' => $exception->getMessage() ]
      );

      // Como não foi fornecido o nome de um serviço deste provedor do
      // sistema de integração que se deseja sincronizar, então exibe
      // uma mensagem de ajuda
      $this->console->out("<lightYellow><bgRed><bold>Atenção!");
      $this->console->out();
      $this->console->out("Não foi possível recuperar os dados: "
        . $exception->getMessage()
      );
      $this->console->out($exception->getTraceAsString());
      $this->console->out();

      return;
    }  catch (QueryException $exception) {
      // Registra o erro
      $this->error("Não foi possível recuperar os dados. {error}",
        [ 'error' => $exception->getMessage() ]
      );

      // Como não foi fornecido o nome de um serviço deste provedor do
      // sistema de integração que se deseja sincronizar, então exibe
      // uma mensagem de ajuda
      $this->console->out("<lightYellow><bgRed><bold>Atenção!");
      $this->console->out();
      $this->console->out("Não foi possível recuperar os dados: "
        . $exception->getMessage()
      );
      $this->console->out();

      return;
    } catch (RuntimeException $exception)
    {
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