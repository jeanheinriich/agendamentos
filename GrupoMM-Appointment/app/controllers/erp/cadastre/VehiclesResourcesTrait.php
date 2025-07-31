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
 * Uma característica (trait) que declara os recursos necessários do
 * controlador de veículos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Cadastre;

use App\Models\Contract;
use App\Models\DocumentType;
use App\Models\FuelType;
use App\Models\PhoneType;
use App\Models\VehicleColor;
use App\Models\VehicleSubtype;
use App\Models\VehicleType;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use RuntimeException;


trait VehiclesResourcesTrait
{
  /**
   * Recupera as informações de tipos de veículos.
   *
   * @return Collection
   *   A matriz com as informações de tipos de veículos
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de veículos
   */
  protected function getVehiclesTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de veículos
      $vehicleTypes = VehicleType::orderBy('vehicletypeid')
        ->get([
            'vehicletypeid AS id',
            'name'
          ])
      ;

      if ( $vehicleTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de veículo "
          . "cadastrado"
        );
      }
    }
    catch (QueryException | Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "veículos. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "veículos"
      );
    }

    return $vehicleTypes;
  }

  /**
   * Recupera as informações de subtipos de veículos.
   *
   * @return array
   *   A matriz com as informações de subtipos de veículos
   *
   * @throws RuntimeException
   *   Em caso de não termos subtipos de veículos
   */
  protected function getVehicleSubtypes(): array
  {
    try {
      // Recupera as informações de subtipos de veículos
      $vehicleSubtypes = VehicleSubtype::orderBy('vehicletypeid')
        ->get([
            'vehiclesubtypeid AS id',
            'vehicletypeid',
            'name'
          ])
      ;

      if ( $vehicleSubtypes->isEmpty() ) {
        throw new Exception("Não temos nenhum subtipo de veículo "
          . "cadastrado"
        );
      }

      $subtypesPerType = [];
      foreach ($vehicleSubtypes as $vehicleSubtype) {
        // Criamos o novo subtipo de veículo
        $newVehicleSubtype = [
          'id' => $vehicleSubtype->id,
          'name' => $vehicleSubtype->name
        ];

        if (isset($subtypesPerType[$vehicleSubtype->vehicletypeid])) {
          $subtypesPerType[$vehicleSubtype->vehicletypeid][] = 
            $newVehicleSubtype
          ;
        } else {
          $subtypesPerType[$vehicleSubtype->vehicletypeid] = [
            $newVehicleSubtype
          ];
        }
      }

      foreach ($subtypesPerType as $typeID => $subtypes) {
        if (count($subtypesPerType[$typeID]) !== 1) {
          // Acrescentamos sempre um subtipo não informado
          array_unshift(
            $subtypesPerType[$typeID],
            [
              'id' => 0,
              'name' => 'Não informado'
            ]
          );
        }
      }

      $vehicleSubtypes = $subtypesPerType;
    }
    catch (QueryException | Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de subtipos "
        . "de veículos. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "veículos"
      );
    }

    return $vehicleSubtypes;
  }

  /**
   * Recupera as informações de cores de veículos.
   *
   * @return Collection
   *   A matriz com as informações de cores de veículos
   *
   * @throws RuntimeException
   *   Em caso de não termos cores de veículos
   */
  protected function getVehiclesColors(): Collection
  {
    try {
      // Recupera as informações de cores de veículos
      $vehicleColors = VehicleColor::orderBy('name')
        ->get([
            'vehiclecolorid AS id',
            'name',
            'color'
          ])
      ;

      if ( $vehicleColors->isEmpty() ) {
        throw new Exception("Não temos nenhuma cor de veículo "
          . "cadastrada"
        );
      }
    }
    catch (QueryException | Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de cores de "
        . "veículos. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException("Não foi possível obter as cores de "
        . "veículos"
      );
    }

    return $vehicleColors;
  }

  /**
   * Recupera as informações de tipos de combustível.
   *
   * @return Collection
   *   A matriz com as informações de tipos de combustível
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de combustível
   */
  protected function getFuelTypes(): Collection
  {
    try {
      // Recupera as informações de combustíveis
      $fuelTypes = FuelType::orderBy('fueltype')
        ->get([
            'fueltype AS id',
            'name'
          ])
      ;

      if ( $fuelTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de combustível "
          . "cadastrado"
        );
      }
    }
    catch (QueryException | Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "combustível. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "combustível"
      );
    }

    return $fuelTypes;
  }

  /**
   * Recupera as informações de tipos de documentos.
   *
   * @return Collection
   *   A matriz com as informações de tipos de documentos
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de documentos
   */
  protected function getDocumentTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de documentos
      $documentTypes = DocumentType::orderBy('documenttypeid')
        ->get([
            'documenttypeid as id',
            'name'
          ])
      ;

      if ( $documentTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de documento "
          . "cadastrado"
        );
      }
    }
    catch (QueryException | Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "documentos. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "documentos"
      );
    }

    return $documentTypes;
  }

  /**
   * Recupera as informações de tipos de telefones.
   *
   * @return Collection
   *   A matriz com as informações de tipos de telefones
   *
   * @throws RuntimeException
   *   Em caso de não termos tipos de telefones
   */
  protected function getPhoneTypes(): Collection
  {
    try {
      // Recupera as informações de tipos de telefones
      $phoneTypes = PhoneType::orderBy('phonetypeid')
        ->get([
            'phonetypeid as id',
            'name'
          ])
      ;

      if ( $phoneTypes->isEmpty() ) {
        throw new Exception("Não temos nenhum tipo de telefone "
          . "cadastrado"
        );
      }
    }
    catch (QueryException | Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de tipos de "
        . "telefones. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException("Não foi possível obter os tipos de "
        . "telefones"
      );
    }

    return $phoneTypes;
  }

  /**
   * Recupera as informações de contratos.
   *
   * @param int $customerID
   *   A ID do cliente cujos contratos desejamos recuperar
   * @param int $subsidiaryID
   *   A ID da unidade/filial do cliente cujos contratos desejamos
   *   recuperar
   * @param bool $all
   *   A flag para indicar se deve recuperar todos os contratos ou
   *   apenas os contratos ativos
   * @param int|null $currentContractID
   *   A ID do contrato cujas informações desejamos recuperar juntamente
   * com as informações dos demais contratos. É utilizado para obter os
   * contratos ativos e o contrato que um equipamento está vinculado
   *
   * @return Collection
   *   A matriz com as informações de contratos
   *
   * @throws RuntimeException
   *   Em caso de não termos contratos disponíveis
   */
  protected function getContracts(
    int $customerID,
    int $subsidiaryID,
    bool $all,
    int $currentContractID = null
  ): Collection
  {
    try {
      if ($all) {
        // Recupera as informações de todos os contratos do cliente
        $contracts = Contract::where('customerid', '=', $customerID)
          ->where('subsidiaryid', '=', $subsidiaryID)
          ->get([
              "contractid AS id",
              $this->DB->raw('getContractNumber(createdat) AS number'),
              $this->DB->raw("trim(to_char(contracts.monthprice, '9999999999D99')) AS monthprice"),
              $this->DB->raw(''
                . "CASE"
                . "  WHEN signaturedate IS NULL THEN 'Não assinado'"
                . "  ELSE 'Assinado em ' || to_char(signaturedate, 'DD/MM/YYYY') "
                . "END AS description"),
              "active",
              $this->DB->raw("(enddate IS NOT NULL) AS closed")
            ])
        ;
      } else {
        // Recupera as informações de contratos ativos do cliente
        $contracts = Contract::where('customerid', '=', $customerID)
          ->where('subsidiaryid', '=', $subsidiaryID)
          ->whereNull('enddate')
          ->where('active', true)
          ->orWhere('contractid', '=', $currentContractID??0)
          ->get([
              "contractid AS id",
              $this->DB->raw('getContractNumber(createdat) AS number'),
              $this->DB->raw("trim(to_char(contracts.monthprice, '9999999999D99')) AS monthprice"),
              $this->DB->raw(''
                . "CASE"
                . "  WHEN signaturedate IS NULL THEN 'Não assinado'"
                . "  ELSE 'Assinado em ' || to_char(signaturedate, 'DD/MM/YYYY') "
                . "END AS description"),
              "active",
              $this->DB->raw("(enddate IS NOT NULL) AS closed")
            ])
        ;
      }

      if ( $contracts->isEmpty() ) {
        // Não temos nenhum contrato deste cliente disponível
        return new Collection([]);
      }
    }
    catch (QueryException | Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de "
        . "contratos. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException("Não foi possível obter os contratos");
    }

    return $contracts;
  }

  /**
   * Recupera as informações de itens de um contrato.
   *
   * @param int $contractID
   *   A ID do contrato cujos itens desejamos recuperar
   * @param bool $all
   *   A flag para indicar se deve recuperar todos os contratos ou
   *   apenas os contratos ativos
   * @param int|null $currentInstallationID
   *   A ID do item de contrato cujas informações desejamos recuperar
   * juntamente com as informações dos demais itens de contratao. É
   * utilizado para obter os itens de  contratos ativos e àquele em que
   * o equipamento está vinculado
   *
   * @return array
   *   A matriz com as informações de itens de um contrato
   *
   * @throws RuntimeException
   *   Em caso de não termos itens do contrato disponíveis
   */
  protected function getInstallations(
    int $contractID,
    bool $all,
    int $currentInstallationID
  ): array
  {
    try {
      $includeSuspended = 'FALSE';
      $includeFinish = 'FALSE';

      if ($all) {
        $includeSuspended = 'TRUE';
        $includeFinish = 'TRUE';
      }

      // Recupera as informações dos itens de um contrato de um cliente,
      // incluindo os itens ativos (que já possuam ao menos um
      // rastreador em operação)
      $sql = "SELECT I.id,
              I.installationNumber,
              I.plate,
              I.startDate,
              I.suspended,
              I.noTracker
        FROM (
          SELECT installationID AS id,
                  installationNumber,
                  plate,
                  startDate,
                  suspended,
                  noTracker
            FROM erp.getInstallationsData($contractID, {$includeSuspended}, {$includeFinish}, {$currentInstallationID})
            ORDER BY noTracker DESC, startDate ASC
          ) AS I;"
      ;
      $installations = $this->DB->select($sql);
      $results = [];
      foreach ($installations AS $installation) {
        $description = 'Sem rastreador';
        if ($installation->startdate) {
          if (! $installation->notracker ) {
            $description = 'Instalado em ' . $installation->startdate;
          }
        } else {
          $description = 'Não instalado';
        }

        $results[] = [
          'id' => $installation->id,
          'contractsuspended' => $installation->suspended,
          'notracker' => $installation->notracker,
          'number' => $installation->installationnumber,
          'plate' => $installation->plate,
          'description' => $description
        ];
      }
    }
    catch (QueryException | Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível obter as informações de itens do "
        . "contrato deste cliente. Erro: {error}.",
        [
          'error' => $exception->getMessage()
        ]
      );

      throw new RuntimeException(
        "Não foi possível obter os itens do contrato"
      );
    }

    return $results;
  }
}