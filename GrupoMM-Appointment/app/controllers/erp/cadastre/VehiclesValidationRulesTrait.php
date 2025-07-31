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
 * Uma característica (trait) que declara as regras de validação dos
 * campos do cadastro de veículos.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Cadastre;

use Respect\Validation\Validator as V;

trait VehiclesValidationRulesTrait
{
  /**
   * Recupera as regras de validação.
   *
   * @param bool $addition
   *   Se a validação é para um formulário de adição
   *
   * @return array
   */
  protected function getValidationRules(
    bool $addition = false
  ): array
  {
    $validationRules = [
      'vehicleid' => V::notBlank()
        ->intVal()
        ->setName('ID do veículo'),
      'customername' => V::notBlank()
        ->length(2, 100)
        ->setName('Nome'),
      'entitytypeid' => V::intVal()
        ->setName('ID do tipo de cliente'),
      'customerid' => V::notBlank()
        ->intVal()
        ->setName('ID do cliente'),
      'subsidiaryname' => V::notBlank()
        ->length(2, 50)
        ->setName('Unidade/Filial'),
      'subsidiaryid' => V::notBlank()
        ->intVal()
        ->setName('ID da unidade/filial'),
      'equipments' => [
        'equipmentid' => V::intVal()
          ->setName('ID do equipamento'),
        'serialnumber' => V::notEmpty()
          ->setName('Número de série'),
        'equipmentbrandname' => V::notBlank()
          ->length(2, 50)
          ->setName('Marca do equipamento'),
        'equipmentmodelname' => V::notBlank()
          ->length(2, 50)
          ->setName('Modelo de equipamento'),
        'amountOfPayerContracts' => V::intVal()
          ->setName('Quantidade de contratos do pagante'),
        'amountOfItensInContract' => V::intVal()
          ->setName('Quantidade de itens no contrato do pagante'),
        'loyaltyperiod' => V::intVal()
          ->setName('Quantidade de meses do período de fidelidade'),
        'endloyaltyperiod' => V::optional(V::date('Y-m-d'))
          ->setName('Data de término do período de fidelidade'),
        'disablechargeloyaltybreak' => V::boolVal()
          ->setName('Desabilitar cobrança de multa por quebra de fidelidade'),
        'installedat' => V::date('d/m/Y')
          ->setName('Data da instalação'),
        'customerpayername' => V::notBlank()
          ->length(2, 100)
          ->setName('Nome do cliente responsável pelo pagamento'),
        'customerpayerid' => V::notBlank()
          ->intVal()
          ->setName('ID do cliente pagante'),
        'originalcustomerpayerid' => V::notBlank()
          ->intVal()
          ->setName('ID do cliente pagante original'),
        'payerentitytypeid' => V::intVal()
          ->setName('ID do tipo de cliente pagante'),
        'subsidiarypayername' => V::notBlank()
          ->length(2, 100)
          ->setName('Unidade/filial/titular responsável pelo pagamento'),
        'subsidiarypayerid' => V::notBlank()
          ->intVal()
          ->setName('ID da unidade/filial/titular responsável pelo pagamento'),
        'originalsubsidiarypayerid' => V::notBlank()
          ->intVal()
          ->setName('ID da unidade/filial/titular responsável pelo pagamento original'),
        'contractid' => V::notBlank()
          ->intVal()
          ->setName('Nº do contrato'),
        'actualcontractid' => V::notBlank()
          ->intVal()
          ->setName('Nº do contrato atual'),
        'installationid' => V::notBlank()
          ->intVal()
          ->setName('Nº do item de contrato'),
        'actualinstallationid' => V::notBlank()
          ->intVal()
          ->setName('Nº do item de contrato atual'),
        'contractsuspended' => V::boolVal()
          ->setName('Contrato suspenso'),
        'terminate' => V::boolVal()
          ->setName('Encerrar contrato'),
        'notclose' => V::boolVal()
          ->setName('Manter item de contrato ativo'),
        'notchargeloyaltybreak' => V::boolVal()
          ->setName('Não cobrar multa por quebra de fidelidade'),
      ],
      'plate' => V::notEmpty()
        ->vehiclePlate()
        ->setName('Placa'),
      'vehiclemodelname' => V::notEmpty()
        ->length(2, 50)
        ->setName('Modelo do veículo'),
      'vehiclemodelid' => V::intVal()
        ->setName('ID do modelo do veículo'),
      'yearfabr' => V::year()
        ->min(1950)
        ->setName('Ano fabricação'),
      'yearmodel' => V::year()
        ->min(1950)
        ->setName('Ano modelo'),
      'vehicletypeid' => V::intVal()
        ->setName('Tipo de veículo'),
      'vehiclesubtypeid' => V::intVal()
        ->setName('Subtipo de veículo'),
      'vehiclebrandid' => V::intVal()
        ->setName('ID da marca do veículo'),
      'vehiclebrandname' => V::notBlank()
        ->length(2, 30)
        ->setName('Marca do veículo'),
      'vehiclecolorid' => V::intVal()
        ->min(1)
        ->setName('Cor predominante'),
      'carnumber' => V::optional(
            V::notBlank()
              ->length(1, 20)
          )
        ->setName('Nº da Frota/Carro'),
      'renavam' => V::optional(
          V::notBlank()
            ->length(9, 11)
          )
        ->setName('RENAVAM'),
      'vin' => V::optional(
            V::notBlank()
              ->length(1, 17)
          )
        ->setName('Número do chassi'),
      'fueltype' => V::notBlank()
        ->alpha()
        ->setName('Combustível'),
      'customeristheowner' => V::boolVal()
        ->setName('O cliente é o proprietário deste veículo'),
      'ownername' => V::optional(
            V::notBlank()
              ->length(2, 100)
          )
        ->setName('Nome do proprietário'),
      'regionaldocumenttype' => V::notEmpty()
        ->intVal()
        ->setName('Tipo de documento'),
      'regionaldocumentnumber' => V::optional(
            V::notEmpty()
              ->length(1, 20)
          )
        ->setName('Número do documento'),
      'regionaldocumentstate' => V::oneOf(
            V::not(V::notEmpty()),
            V::notEmpty()
              ->oneState()
          )
        ->setName('UF'),
      'nationalregister' => V::oneOf(
            V::not(V::notEmpty()),
            V::notEmpty()
              ->cpf(),
            V::notEmpty()
              ->cnpj()
          )
        ->setName('CPF/CNPJ'),
      'address' => V::notEmpty()
        ->length(2, 100)
        ->setName('Endereço'),
      'streetnumber' => V::optional(
            V::notEmpty()
              ->length(1, 10)
          )
        ->setName('Nº'),
      'complement' => V::optional(
            V::notEmpty()
              ->length(2, 30)
          )
        ->setName('Complemento'),
      'district' => V::optional(V::notEmpty()
          ->length(2, 50))
        ->setName('Bairro'),
      'postalcode' => V::notEmpty()
        ->postalCode('BR')
        ->setName('CEP'),
      'cityname' => V::notEmpty()
        ->length(2, 50)
        ->setName('Cidade'),
      'cityid' => V::notEmpty()
        ->intVal()
        ->setName('ID da cidade'),
      'state' => V::notBlank()
        ->oneState()
        ->setName('UF'),
      'ownerPhones' => [
        'ownerphoneid' => V::intVal()
          ->setName('ID do telefone'),
        'phonenumber' => V::optional(
              V::notBlank()
                ->length(14, 20)
            )
          ->setName('Telefone'),
        'phonetypeid' => V::notBlank()
          ->intval()
          ->setName('Tipo de telefone')
      ],
      'placeOfStay' => V::notBlank()
        ->in([ 'atsamecustomeraddress', 'atsameowneraddress', 'atanotheraddress' ])
        ->setName('Local de permanência do veículo'),
      'anothername' => V::optional(
            V::notEmpty()
              ->length(2, 100)
          )
        ->setName('Endereço'),
      'anotheraddress' => V::optional(
            V::notEmpty()
              ->length(2, 100)
          )
        ->setName('Endereço'),
      'anotherstreetnumber' => V::optional(
            V::notEmpty()
              ->length(1, 10)
          )
        ->setName('Nº'),
      'anothercomplement' => V::optional(
            V::notEmpty()
              ->length(2, 30)
          )
        ->setName('Complemento'),
      'anotherdistrict' => V::optional(
            V::notEmpty()
              ->length(2, 50)
          )
        ->setName('Bairro'),
      'anotherpostalcode' => V::optional(
            V::notEmpty()
              ->postalCode('BR')
          )
        ->setName('CEP'),
      'anothercityname' => V::optional(
            V::notEmpty()
              ->length(2, 50)
          )
        ->setName('Cidade'),
      'anothercityid' => V::optional(
            V::intVal()
          )
        ->setName('ID da cidade'),
      'anotherstate' => V::optional(
            V::notBlank()
              ->oneState()
          )
        ->setName('UF'),
      'anotherPhones' => [
        'anotherphoneid' => V::intVal()
          ->setName('ID do telefone'),
        'phonenumber' => V::optional(
              V::notBlank()
                ->length(14, 20)
            )
          ->setName('Telefone'),
        'phonetypeid' => V::notBlank()
          ->intval()
          ->setName('Tipo de telefone')
      ],
      'note' => V::optional(
            V::notBlank()
          )
        ->setName('Observação')
    ];

    if ($addition) {
      // Ajusta as regras para adição
      unset($validationRules['vehicleid']);
      unset($validationRules['equipments']);
    } else {
      // Ajusta as regras para edição
      $validationRules['blocked'] = V::boolVal()
        ->setName('Inativar este veículo')
      ;
      $validationRules['originalcustomerid'] = V::intval()
        ->setName('ID do cliente original')
      ;
      $validationRules['originalsubsidiaryid'] = V::intval()
        ->setName('ID do cliente original')
      ;
      $validationRules['transferat'] = V::optional(
            V::date('d/m/Y')
        )->setName('Data da transferência')
      ;
      $validationRules['blocknotices'] = V::boolVal()
        ->setName('Bloquear o envio de avisos para este veículo')
      ;
      $validationRules['blockeddays'] = V::optional(
            V::intval()
          )
        ->setName('Duração do bloqueio')
      ;
      $validationRules['blockedstartat'] = V::optional(
            V::date('d/m/Y')
          )
        ->setName('Período de início do bloqueio')
      ;
    }

    return $validationRules;
  }

  /**
   * Recupera as regras de validação para um vínculo entre veículo e
   * rastreador.
   *
   * @param bool $attached
   *   Se a validação deve incluir campos para edição do vínculo
   * @param bool $replace
   *   Se a validação deve incluir campos para substituição de
   *   equipamento
   * @param bool $transfer
   *   Se a validação deve incluir campos para transferência de
   *   titularidade
   *
   * @return array
   */
  protected function getValidationRulesForAttach(
    bool $attached,
    bool $replace,
    bool $transfer
  ): array
  {
    $validationRules = [
      'vehicleid' => V::notBlank()
        ->intVal()
        ->setName('ID do veículo'),
      'attached' => V::boolVal()
        ->setName('Indicativo de SIM Card anexado'),
      'main' => V::boolVal()
        ->setName('Indicativo se rastreador principal ou contingência'),
      'keepAsContingency' => V::boolVal()
        ->setName('Indicativo se devemos forçar a ser de contingência'),
      'hiddenfromcustomer' => V::boolVal()
        ->setName('Indicativo se o rastreador deve ser ocultado do cliente'),
      'blocktype' => V::boolVal()
        ->setName('Indicativo se a seleção do tipo de instalação é bloqueada'),
      'plate' => V::notEmpty()
        ->setName('Placa'),
      'customerid' => V::notBlank()
        ->intVal()
        ->setName('ID do cliente'),
      'customername' => V::notEmpty()
        ->setName('Nome do cliente'),
      'subsidiaryid' => V::notBlank()
        ->intVal()
        ->setName('ID da unidade/filial'),
      'subsidiaryname' => V::notEmpty()
        ->setName('Unidade/filial'),
      'vehiclebrandid' => V::optional(
            V::intVal()
          )
        ->setName('ID da marca do veículo'),
      'vehiclebrandname' => V::optional(
            V::notBlank()
              ->length(1, 30)
          )
        ->setName('Marca do veículo'),
      'vehiclemodelname' => V::optional(
            V::notEmpty()
              ->length(1, 50)
          )
        ->setName('Modelo do veículo'),
      'vehiclemodelid' => V::optional(
            V::intVal()
          )
        ->setName('ID do modelo do veículo'),
      'vehicletypeid' => V::optional(
            V::intVal()
          )
        ->setName('ID do tipo de veículo'),
      'vehicletypename' => V::optional(
            V::notEmpty()
              ->length(1, 30)
          )
        ->setName('Tipo de veículo'),
      'vehiclecolorid' => V::optional(
            V::intVal()
              ->min(1)
          )
        ->setName('Cor predominante'),
      'vehiclecolorname' => V::optional(
            V::notEmpty()
              ->length(1, 30)
          )
        ->setName('Cor predominante'),
      'carnumber' => V::optional(
            V::notBlank()
              ->length(1, 20)
          )
        ->setName('Nº da Frota/Carro'),
      'renavam' => V::optional(
            V::notEmpty()
              ->length(1, 11)
          )
        ->setName('RENAVAM'),
      'vin' => V::optional(
            V::notEmpty()
              ->length(1, 17)
          )
        ->setName('Número do chassi'),
      'equipmentid' => V::intVal()
        ->setName('ID do equipamento'),
      'serialnumber' => V::notEmpty()
        ->setName('Número de série'),
      'equipmentbrandname' => V::notBlank()
        ->length(2, 50)
        ->setName('Marca do equipamento'),
      'equipmentbrandid' => V::intVal()
        ->setName('ID da marca de equipamento'),
      'equipmentmodelname' => V::notBlank()
        ->length(2, 50)
        ->setName('Modelo de equipamento'),
      'equipmentmodelid' => V::intVal()
        ->setName('ID do modelo de equipamento'),
      'storedlocationname' => V::notBlank()
        ->length(2, 50)
        ->setName('Situação atual'),
      'customerpayername' => V::notBlank()
        ->length(2, 100)
        ->setName('Nome do cliente responsável pelo pagamento'),
      'customerpayerid' => V::notBlank()
        ->intVal()
        ->setName('ID do cliente pagante'),
      'payerentitytypeid' => V::notBlank()
        ->intVal()
        ->setName('ID do tipo do cliente pagante'),
      'subsidiarypayername' => V::notBlank()
        ->length(2, 100)
        ->setName('Unidade/filial/titular responsável pelo pagamento'),
      'subsidiarypayerid' => V::notBlank()
        ->intVal()
        ->setName('ID da unidade/filial/titular'),
      'contractid' => V::notBlank()
        ->intVal()
        ->setName('Nº do contrato'),
      'installationid' => V::intVal()
        ->setName('Nº do item de contrato'),
      'installationsite' => V::notBlank()
        ->length(2, 100)
        ->setName('Local de instalação do rastreador'),
      'hasblocking' => V::boolVal()
        ->setName('Indicativo de que existe um bloqueador'),
      'blockingsite' => V::notBlank()
        ->length(2, 100)
        ->setName('Local onde ocorre o bloqueio'),
      'hasibutton' => V::boolVal()
        ->setName('Indicativo de que existe uma leitora de iButton'),
      'ibuttonsite' => V::notBlank()
        ->length(2, 100)
        ->setName('Local onde a leitora de iButton está instalada'),
      'hassiren' => V::boolVal()
        ->setName('Indicativo de que existe uma sirene'),
      'sirensite' => V::notBlank()
        ->length(2, 100)
        ->setName('Local de instalação da sirene'),
      'panicbuttonsite' => V::notBlank()
        ->length(2, 100)
        ->setName('Local de instalação do botão de pânico'),
      'installedat' => V::optional(V::date('d/m/Y'))
        ->setName('Data da instalação')
    ];

    if ($attached) {
      // Ajusta as regras para edição
      $validationRules['replace'] = V::boolVal()
        ->setName('Substituir')
      ;
      $validationRules['transfer'] = V::boolVal()
        ->setName('Transferir')
      ;
      $validationRules['amountOfPayerContracts'] = V::intVal()
        ->setName('Quantidade de contratos do pagante')
      ;
      $validationRules['amountOfItensInContract'] = V::intVal()
        ->setName('Quantidade de itens no contrato do pagante')
      ;
      $validationRules['terminate'] = V::boolVal()
        ->setName('Encerrar contrato')
      ;
      $validationRules['notclose'] = V::boolVal()
        ->setName('Manter item de contrato ativo')
      ;
      $validationRules['loyaltyperiod'] = V::intVal()
        ->setName('Quantidade de meses do período de fidelidade')
      ;
      $validationRules['endloyaltyperiod'] = V::optional(V::date('Y-m-d'))
        ->setName('Data de término do período de fidelidade')
      ;
      $validationRules['disablechargeloyaltybreak'] = V::boolVal()
        ->setName('Desabilitar cobrança de multa por quebra de fidelidade')
      ;
      $validationRules['notchargeloyaltybreak'] = V::boolVal()
        ->setName('Não cobrar multa por quebra de fidelidade')
      ;

      if ($replace) {
        $validationRules['newequipmentid'] = V::intVal()
          ->setName('ID do equipamento')
        ;
        $validationRules['newserialnumber'] = V::notEmpty()
          ->setName('Número de série')
        ;
        $validationRules['newequipmentbrandname'] = V::notBlank()
          ->length(2, 50)
          ->setName('Marca do equipamento')
        ;
        $validationRules['newequipmentbrandid'] = V::intVal()
          ->setName('ID da marca de equipamento')
        ;
        $validationRules['newequipmentmodelname'] = V::notBlank()
          ->length(2, 50)
          ->setName('Modelo de equipamento')
        ;
        $validationRules['newequipmentmodelid'] = V::intVal()
          ->setName('ID do modelo de equipamento')
        ;
        $validationRules['newstoredlocationname'] = V::notBlank()
          ->length(2, 50)
          ->setName('Situação atual')
        ;
        $validationRules['replacedat'] = V::date('d/m/Y')
          ->setName('Data da substituição')
        ;
      }

      if ($transfer) {
        $validationRules['newcustomerpayername'] = V::notBlank()
          ->length(2, 100)
          ->setName('Nome do novo cliente responsável pelo pagamento')
        ;
        $validationRules['newcustomerpayerid'] = V::notBlank()
          ->intVal()
          ->setName('ID do novo cliente pagante')
        ;
        $validationRules['newpayerentitytypeid'] = V::notBlank()
          ->intVal()
          ->setName('ID do novo tipo do cliente pagante')
        ;
        $validationRules['newsubsidiarypayername'] = V::notBlank()
          ->length(2, 100)
          ->setName('Nova unidade/filial/titular responsável pelo pagamento')
        ;
        $validationRules['newsubsidiarypayerid'] = V::notBlank()
          ->intVal()
          ->setName('ID da nova unidade/filial/titular')
        ;
        $validationRules['newcontractid'] = V::notBlank()
          ->intVal()
          ->setName('Nº do novo contrato')
        ;
        $validationRules['newinstallationid'] = V::intVal()
          ->setName('Nº do novo item de contrato')
        ;
        $validationRules['transferat'] = V::date('d/m/Y')
          ->setName('Data da transferência')
        ;
      }
    }

    return $validationRules;
  }

  /**
   * Verifica se um número de chassi informado é válido.
   *
   * @param string $madeAt
   *   O ano em que o veículo foi fabricado
   * @param string $vin
   *   O número do chassi
   *
   * @return bool
   */
  protected function validVIN(
    string $madeAt,
    string $vin
  ): bool
  {
    // Obtemos o valor do ano de fabricação informado
    $madeAt = trim($madeAt !== "")
      ? intval($madeAt)
      : null
    ;

    if ($madeAt) {
      // Foi informado o ano de fabricação
      if ($madeAt >= 1988) {
        // A padronização do número do chassi ocorreu à partir do ano de
        // 1986. Desta forma precisamos obter a informação do número do
        // chassi informado e verificar se o mesmo é válido caso exista
        // algum valor informado. Todavia, encontramos casos ainda de
        // caminhões em 1987, então alteramos para 1988
        if (strlen($vin) > 0) {
          // Temos algum valor digitado. Então analisamos

          // Verificamos se o código possui 'REM' no final
          if (substr($vin, -3) === 'REM') {
            // O código possui 'REM' no final, então removemos
            $vin = substr($vin, 0, -3);
          }

          if (strlen($vin) === 17) {
            // O código possui a quantidade de dígitos correta
            
            // TODO: Verificar se o código é válido pelo dígito
            //       verificador
          } else {
            // O código informado é inválido
            return false;
          }
        }
      }
    }

    // Em todas as outras circunstâncias retorna como válido
    return true;
  }
}