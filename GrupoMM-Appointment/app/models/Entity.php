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
 * Uma entidade do sistema. Uma entidade pode ser um contratante, um
 * cliente e/ou um fornecedor. Também pode ser uma pessoa física e/ou
 * jurídica.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Subsidiary;

class Entity
  extends Model
{
  /**
   * O nome da conexão a ser utilizada.
   *
   * @var string
   */
  protected $connection = 'erp';
  
  /**
   * O nome da tabela.
   *
   * @var string
   */
  protected $table = 'entities';
  
  /**
   * O nome da chave primária.
   *
   * @var string
   */
  protected $primaryKey = 'entityid';
  
  /**
   * Os campos da tabela.
   *
   * @var array
   */
  protected $fillable = [
    'contractor',
    'contractorid',
    'entityuuid',
    'customer',
    'supplier',
    'serviceprovider',
    'seller',
    'monitor',
    'rapidresponse',
    'name',
    'tradingname',
    'entitytypeid',
    'blocked',
    'stckey',
    'note',
    'enableatmonitoring',
    'monitoringid',
    'noteformonitoring',
    'emergencyinstructions',
    'dispatchrapidresponse',
    'rapidresponseid',
    'securitypassword',
    'verificationpassword',
    'usemainphonesforcall',
    'createdbyuserid',
    'updatedbyuserid'
  ];
  
  /**
   * A informação de que temos campos de registro de modificações.
   *
   * @var boolean
   */
  public $timestamps = true;
  
  /**
   * Os campos de criação e modificação.
   *
   * @const string
   */
  const CREATED_AT = 'createdat';
  const UPDATED_AT = 'updatedat';

  /**
   * Os campos que precisam de conversão em tipos de dados comuns.
   *
   * @var array
   */
  protected $casts = [
    'contractor' => 'boolean',
    'customer' => 'boolean',
    'supplier' => 'boolean',
    'serviceprovider' => 'boolean',
    'seller' => 'boolean',
    'monitor' => 'boolean',
    'rapidresponse' => 'boolean',
    'blocked' => 'boolean',
    'deleted' => 'boolean',
    'enableatmonitoring' => 'boolean',
    'usemainphonesforcall' => 'boolean',
    'dispatchrapidresponse' => 'boolean'
  ];

  /**
   * A classe do model de cobranças.
   *
   * @var string
   */
  protected static $billingClass = 'App\Models\Billing';

  /**
   * A classe do model de tipos de cobranças.
   *
   * @var string
   */
  protected static $billingTypeClass = 'App\Models\BillingType';
  
  /**
   * A classe do model de perfis de envio de notificações.
   *
   * @var string
   */
  protected static $mailingProfilesClass = 'App\Models\MailingProfile';

  /**
   * A classe do model de contatos.
   *
   * @var string
   */
  protected static $mailingAddressesClass = 'App\Models\MailingAddress';
  
  /**
   * A classe do model de contratos.
   *
   * @var string
   */
  protected static $contractsClass = 'App\Models\Contract';
  
  /**
   * A classe do model de tipos de contratos.
   *
   * @var string
   */
  protected static $contractTypeClass = 'App\Models\ContractType';
  
  /**
   * A classe do model de valores cobrados por tipo de contrato.
   *
   * @var string
   */
  protected static $contractTypeChargeClass = 'App\Models\ContractTypeCharge';
  
  /**
   * A classe do model de equipamentos.
   *
   * @var string
   */
  protected static $equipmentClass = 'App\Models\Equipment';
  
  /**
   * A classe do model de marcas de equipamentos.
   *
   * @var string
   */
  protected static $equipmentBrandClass = 'App\Models\EquipmentBrand';
  
  /**
   * A classe do model de modelos de equipamentos.
   *
   * @var string
   */
  protected static $equipmentModelClass = 'App\Models\EquipmentModel';

  /**
   * A classe do model de tipos de parcelamentos.
   *
   * @var string
   */
  protected static $installmentTypeClass = 'App\Models\InstallmentType';
  
  /**
   * A classe do model de SIM Cards.
   *
   * @var string
   */
  protected static $simcardClass = 'App\Models\SimCard';
  
  /**
   * A classe do model de unidades/filiais.
   *
   * @var string
   */
  protected static $subsidiariesClass = 'App\Models\Subsidiary';
  
  /**
   * A classe do model de usuários.
   *
   * @var string
   */
  protected static $usersClass = 'App\Models\User';
  
  /**
   * A classe do model de veículos.
   *
   * @var string
   */
  protected static $vehiclesClass = 'App\Models\Vehicle';
  
  /**
   * A classe do model de marcas de veículos.
   *
   * @var string
   */
  protected static $vehicleBrandsClass = 'App\Models\VehicleBrand';
  
  /**
   * A classe do model de modelos de veículos.
   *
   * @var string
   */
  protected static $vehicleModelsClass = 'App\Models\VehicleModel';
  
  // TODO: Adicionar marcas e modelos de equipamentos ******
  
  /**
   * Força a formatação de Data/Hora para o Eloquent.
   * 
   * @return string
   *   O formato de data/hora
   */
  public function getDateFormat()
  {
    return 'Y-m-d H:i:s.u';
  }

  /**
   * Converte um valor para booleano.
   *
   * @param mixed $value
   *   O valor a ser convertido
   *
   * @return bool
   */
  protected function toBoolean($value): bool
  {
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * A função de preenchimento dos campos na tabela.
   *
   * @param array $attributes
   *   Os campos com seus respectivos valores
   */
  public function fill(array $attributes)
  {
    // Converte nos atributos os valores booleanos
    if (array_key_exists('contractor', $attributes))
      $attributes['contractor'] = $this->toBoolean($attributes['contractor']);
    if (array_key_exists('customer', $attributes))
      $attributes['customer'] = $this->toBoolean($attributes['customer']);
    if (array_key_exists('supplier', $attributes))
      $attributes['supplier'] = $this->toBoolean($attributes['supplier']);
    if (array_key_exists('serviceprovider', $attributes))
      $attributes['serviceprovider'] = $this->toBoolean($attributes['serviceprovider']);
    if (array_key_exists('seller', $attributes))
      $attributes['seller'] = $this->toBoolean($attributes['seller']);
    if (array_key_exists('monitor', $attributes))
      $attributes['monitor'] = $this->toBoolean($attributes['monitor']);
    if (array_key_exists('rapidresponse', $attributes))
      $attributes['rapidresponse'] = $this->toBoolean($attributes['rapidresponse']);
    if (array_key_exists('blocked', $attributes))
      $attributes['blocked'] = $this->toBoolean($attributes['blocked']);
    if (array_key_exists('deleted', $attributes))
      $attributes['deleted'] = $this->toBoolean($attributes['deleted']);
    if (array_key_exists('enableatmonitoring', $attributes))
      $attributes['enableatmonitoring'] = $this->toBoolean($attributes['enableatmonitoring']);
    if (array_key_exists('usemainphonesforcall', $attributes))
      $attributes['usemainphonesforcall'] = $this->toBoolean($attributes['usemainphonesforcall']);
    if (array_key_exists('dispatchrapidresponse', $attributes))
      $attributes['dispatchrapidresponse'] = $this->toBoolean($attributes['dispatchrapidresponse']);
    
    // Prossegue normalmente
    parent::fill($attributes);
  }

  /**
   * Retorna o relacionamento com a tabela de cobranças.
   *
   * @return Collection
   *   As informações de cobranças
   */
  public function billings()
  {
    return $this
      ->hasMany(static::$billingClass, 'contractorid', 'entityid')
    ;
  }

  /**
   * Retorna o relacionamento com a tabela de tipos de cobranças.
   *
   * @return Collection
   *   As informações de tipos de cobranças
   */
  public function billingTypes()
  {
    return $this
      ->hasMany(static::$billingTypeClass, 'contractorid', 'entityid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de perfis de envio de
   * notificações.
   *
   * @return Collection
   *   As informações de perfis de envio de notificações
   */
  public function mailingProfiles()
  {
    return $this
      ->hasMany(static::$mailingProfilesClass, 'contractorid',
          'entityid'
        )
    ;
  }

  /**
   * Retorna o relacionamento com a tabela de contatos.
   *
   * @return Collection
   *   As informações de contatos
   */
  public function mailingAddresses()
  {
    return $this
      ->hasMany(static::$mailingAddressesClass, 'entityid', 'entityid')
    ;
  }

  /**
   * Retorna o relacionamento com a tabela de usuários para permitir
   * obter a relação de usuários de um contratante.
   *
   * @return Collection
   *   As informações de usuários deste contratante
   */
  public function contractorsUsers()
  {
    return $this
      ->hasMany(static::$usersClass, 'contractorid', 'entityid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de contratos.
   *
   * @return Collection
   *   As informações de contratos
   */
  public function contracts()
  {
    return $this
      ->hasMany(static::$contractsClass, 'customerid', 'entityid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de tipos de contratos.
   *
   * @return Collection
   *   As informações de tipos de contratos
   */
  public function contractTypes()
  {
    return $this
      ->hasMany(static::$contractTypeClass, 'contractorid', 'entityid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de valores cobrados por tipo
   * de contrato.
   *
   * @return Collection
   *   As informações de valores cobrados por tipo de contrato
   */
  public function contractTypesCharges()
  {
    return $this
      ->hasMany(static::$contractTypeChargeClass, 'contracttypeid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de equipamentos.
   *
   * @return Collection
   *   As informações de equipamentos
   */
  public function equipments()
  {
    return $this
      ->hasMany(static::$equipmentClass, 'contractorid', 'entityid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de marcas de equipamentos.
   *
   * @return Collection
   *   As informações de marcas de equipamentos
   */
  public function equipmentBrands()
  {
    return $this
      ->hasMany(static::$equipmentBrandClass, 'contractorid', 'entityid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de modelos de equipamentos.
   *
   * @return Collection
   *   As informações de modelos de equipamentos
   */
  public function equipmentModels()
  {
    return $this
      ->hasMany(static::$equipmentModelClass, 'contractorid', 'entityid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de usuários para permitir
   * obter a relação de usuários de um cliente e/ou fornecedor.
   *
   * @return Collection
   *   As informações de usuários de um cliente e/ou fornecedor
   */
  public function entitiesUsers()
  {
    return $this
      ->hasMany(static::$usersClass, 'entityid', 'entityid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de tipos de cobranças.
   *
   * @return Collection
   *   As informações de tipos de cobranças
   */
  public function installmentTypes()
  {
    return $this
      ->hasMany(
          static::$installmentTypeClass, 'contractorid', 'entityid'
        )
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de SIM Cards.
   *
   * @return Collection
   *   As informações de SIM Cards
   */
  public function simcards()
  {
    return $this
      ->hasMany(static::$simcardClass, 'contractorid', 'entityid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de unidades/filiais e/ou
   * associados.
   *
   * @return Collection
   *   As informações de unidades/filiais/associados
   */
  public function subsidiaries()
  {
    return $this
      ->hasMany(static::$subsidiariesClass, "entityid", "entityid")
      ->where("subsidiaries.affiliated", "false")
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de veículos.
   *
   * @return Collection
   *   As informações de veículos
   */
  public function vehicles()
  {
    return $this
      ->hasMany(static::$vehiclesClass, 'customerid', 'entityid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de marcas de veículos.
   *
   * @return Collection
   *   As informações de marcas de veículos
   */
  public function vehicleBrands()
  {
    return $this
      ->hasMany(static::$vehicleBrandsClass, 'contractorid', 'entityid')
    ;
  }
  
  /**
   * Retorna o relacionamento com a tabela de modelos de veículos.
   *
   * @return Collection
   *   As informações de modelos de veículos
   */
  public function vehicleModels()
  {
    return $this
      ->hasMany(static::$vehicleModelsClass, 'contractorid', 'entityid')
    ;
  }

  /**
   * Deleta em cascata todos os registros de uma entidade.
   *
   * @param String $path
   *   O caminho para a pasta de armazenamento das imagens
   *
   * @return bool
   */
  public function deleteCascade(String $path)
  {
    // Identifica se estamos removendo um contratante
    if ($this->contractor) {
      // Quando estamos apagando um contratante, precisamos remover
      // também todas as informações à ele relacionadas
      
      // ----------------------------------------------[ Clientes ]-----
      
      // Localiza todos os clientes à ele vinculado
      $customers = self::where("contractorid", '=', $this->entityid)
                       ->where("customer", "true")
                       ->get();

      // Para cada cliente, remove todos os dados relacionados
      foreach ($customers as $count => $customer) {
        // Passamos à rotina de exclusão a informação do path onde estão
        // localizados os arquivos anexos, já que ela é chamada
        // recursivamente
        $customer->delete($path);
      }

      // Localiza todas as cobranças
      //$billings = $this->billings();

      // Para cada cobrança, remove todos os dados relacionados
      //foreach ($billings as $count => $billing) {
      //  $billing->delete();
      //}

      // Localiza todos os tipos de contratos
      $contractTypes = $this->contractTypes()->get();

      // Para cada tipo de contrato, remove todos os dados relacionados
      foreach ($contractTypes as $count => $contractType) {
        $contractType->deleteCascade();
      }

      // Remove todos os tipos de cobranças
      $billingTypes = $this->billingTypes()->delete();
      
      // Remove todos os tipos de parcelamentos
      $installmentTypes = $this->installmentTypes()->delete();

      // TODO: Precisa remover todos os dados de integração, se existirem
      
      // ----------------------------------------------[ Veículos ]-----
      
      // Apaga as informações de modelos de veículos
      $vehicleModels = $this->vehicleModels()->delete();

      // Localiza todos as marcas de veículos
      $vehicleBrands = $this->vehicleBrands()->get();

      // Apaga as informações de marcas de veículos e os dados
      // relacionados
      foreach ($vehicleBrands as $count => $vehicleBrand) {
        $vehicleBrand->deleteCascade();
      }

      // ------------------------------------------[ Fornecedores ]-----
      
      // Localiza todos os fornecedores à ele vinculado
      $suppliers = self::where("contractorid", '=', $this->entityid)
                       ->where("supplier", "true")
                       ->get();

      // Para cada fornecedor, remove todos os dados relacionados
      foreach ($suppliers as $count => $supplier) {
        // Passamos à rotina de exclusão a informação do path onde estão
        // localizados os arquivos anexos, já que ela é chamada
        // recursivamente
        $supplier->delete($path);
      }
      
      // ------------------------------------------[ Dispositivos ]-----
      
      // Apaga todos os modelos de equipamentos
      $equipmentModels = $this->equipmentModels()->delete();
      
      // Localiza todas as marcas de equipamentos
      $equipmentBrands = $this->equipmentBrands();

      // Para cada marca de equipamento, remove todos os dados relacionados
      foreach ($equipmentBrands as $count => $equipmentBrand) {
        $equipmentBrand->deleteCascade();
      }

      // ----------------------------------------------[ Usuários ]-----

      // Apaga as informações de usuários deste contratante
      $contractorsUsers = $this->contractorsUsers();
      foreach ($contractorsUsers as $count => $contractorsUser) {
        $contractorsUser->delete();
      }

      // -----------------------------------------------[ Imagens ]-----

      // Remove as logomarcas do contratante
      
      // Recupera o local de armazenamento das imagens
      $searchText = $path . DIRECTORY_SEPARATOR
        . 'Logo_' . $this->entityuuid . "_*.*"
      ;
      $files = glob($searchText);
      if (count($files) > 0) {
        foreach ($files as $count => $filename) {
          // Remove fisicamente o arquivo de imagem
          unlink($filename);
        }
      }
    } else if ($this->customer) {
      // Quando estamos apagando um cliente, precisamos remover
      // também todas as informações à ele relacionadas
      
      // Localiza todos os veículos à ele vinculado
      $vehicles = Vehicle::where('contractorid', '=', $this->contractorid)
                         ->where('customerid', '=', $this->entityid)
                         ->get();

      // TODO: Precisamos analisar a questão de vínculos dos veículos

      // Removemos cada veículo deste cliente e todos os dados à ele
      // relacionados
      foreach ($vehicles as $count => $vehicle) {
        // Passamos à rotina de exclusão a informação do path onde estão
        // localizados os arquivos anexos para remover o veículo e seus
        // anexos
        $vehicle->deleteCascade($path);
      }
      
      // Localiza todos os contratos deste cliente
      $contracts = $this->contracts()->get();

      // Para cada contrato, remove todos os dados relacionados
      foreach ($contracts as $count => $contract) {
        $contract->deleteCascade();
      }
      
      // Apaga as informações de usuários deste cliente
      $this->entitiesUsers()->delete();
    } else {
      // Quando estamos apagando um fornecedor, precisamos remover
      // também todas as informações à ele relacionadas
      
      // Localiza todos os equipamentos deste fornecedor
      $equipments = $this->equipments()->get();

      // Para cada equipamento, remove todos os dados relacionados
      foreach ($equipments as $count => $equipment) {
        $equipment->deleteCascade();
      }
      
      // Remove todos os SIM Cards deste fornecedor
      $simcards = $this->simcards()->delete();

      // Localiza todos os usuários deste fornecedor
      $users = $this->entitiesUsers();

      // Para cada usuário, remove todos os dados relacionados
      foreach ($users as $count => $user) {
        $user->delete();
      }
    }
    
    // Independente do tipo de entidade, apaga todas as informações com
    // ela relacionadas

    // Localiza todas as unidades/filiais
    $subsidiaries = $this->subsidiaries()->get();

    // Para cada unidade/filial, remove todos os dados relacionados
    foreach ($subsidiaries as $count => $subsidiary) {
      // Remove a unidade/filial e seus contatos
      $subsidiary->deleteCascade();
    }

    // Remove as informações de perfis de envio de notificação

    // Localiza todos os perfis
    $mailingProfiles = $this->mailingProfiles()->get();

    // Para cada perfil, remove todos os dados relacionados
    foreach ($mailingProfiles as $count => $mailingProfile) {
      // Remove o perfil e todos os dados relacionados
      $mailingProfile->deleteCascade();
    }

    // Apaga a entidade
    return parent::delete();
  }
}
