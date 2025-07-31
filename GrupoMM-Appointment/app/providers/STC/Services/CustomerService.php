<?php
/*
 * This file is part of STC Integration Library.
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
 * As requisições ao serviço de obtenção dos dados de clientes através
 * da API do sistema STC.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

/**
 * API STC
 *
 * http://ap1.stc.srv.br/docs/
 *
 * Copyright (c) 2017 - STC Tecnologia <www.stctecnologia.com.br>
 */

namespace App\Providers\STC\Services;

use App\Models\Entity;
use App\Models\STC\Customer;
use Core\HTTP\Service;

class CustomerService
  extends STCService
  implements Service
{
  /**
   * O caminho para nosso serviço.
   *
   * @var string
   */
  protected $path = 'ws/client/list';

  /**
   * O método responsável por executar as requisições ao serviço,
   * sincronizando os dados.
   */
  public function synchronize(): void
  {
    // Ajusta os parâmetros para sincronismo
    $this->synchronizer->setURI($this->path);

    // Primeiramente preparamos os parâmetros de nossa requisição
    $this->synchronizer->prepareParameters();

    // Definimos que a requisição é paginada
    $this->synchronizer->setHandlePages(true);

    // Seta uma função para lidar com os dados recebidos
    $this->synchronizer->setOnDataProcessing([$this, 'onDataProcessing']);

    // Executa o sincronismo dos clientes com o sistema STC
    $this->synchronizer->synchronize();
  }

  /**
   * A função responsável por processar os dados recebidos.
   *
   * @param array $customerData
   *   Os dados obtidos do servidor STC
   */
  public function onDataProcessing(array $customerData): void
  {
    // Localiza se temos o cadastro do cliente no ERP
    $local = Entity::join("subsidiaries", "entities.entityid", '=',
          "subsidiaries.entityid"
        )
      ->where("entities.contractorid", '=', $this->contractor->id)
      ->where("entities.customer", "true")
      ->where("subsidiaries.nationalregister", '=',
          $this->formatNationalRegister($customerData['cpfcnpj'])
        )
      ->get([
          'subsidiaries.entityid AS customerid',
          'subsidiaries.subsidiaryid'
        ])
      ->first()
    ;

    // Primeiro, verifica se este cliente não está cadastrado
    if (Customer::where("contractorid", '=', $this->contractor->id)
          ->where("clientid", '=', intval($customerData['id']))
          ->count() === 0) {
      $customer = new Customer();

      $customer->clientid               = intval($customerData['id']);
      $customer->nationalregister       = $this->formatNationalRegister($customerData['cpfcnpj']);
      $customer->name                   = $this->normalizeString($customerData['name']);
      $customer->email                  = strtolower(trim($customerData['email']));
      $customer->status                 = intval($customerData['status'])===1?true:false;
      $customer->postalcode             = $this->formatPostalCode($customerData['zipcode']);
      $customer->cityid                 = intval($customerData['city']);
      $customer->entitytypeid           = $this->formatUserType($customerData['usertype']);
      $customer->regionaldocumentnumber = trim($customerData['ierg']);
      $customer->address                = $this->normalizeString($customerData['address']);
      $customer->district               = $this->normalizeString($customerData['neighbourhood']);
      $customer->complement             = $this->normalizeString($customerData['complement']);
      $customer->info                   = $this->normalizeString($customerData['info']);
      $customer->login                  = strtolower(trim($customerData['login']));
      $customer->password               = isset($customerData['password'])
        ? strtolower(trim($customerData['password']))
        : ''
      ;
      $customer->createdat              = trim($customerData['created_at']);
      $customer->contractorid           = $this->contractor->id;
      if (!is_null($local)) {
        // Atribuímos as informações do cliente no ERP
        $customer->customerid   = $local->customerid;
        $customer->subsidiaryid = $local->subsidiaryid;
      }

      // Garante que o id da cidade seja válido
      if ($customer->cityid === 0) {
        $customer->cityid = 1;
      }

      $customer->save();
    } else {
      // Precisa atualizar apenas, então recupera o cliente
      $customer = Customer::where("contractorid", '=', $this->contractor->id)
                          ->where("clientid", '=', intval($customerData['id']))
                          ->firstOrFail();

      // Atualiza os dados
      $customer->nationalregister       = $this->formatNationalRegister($customerData['cpfcnpj']);
      $customer->name                   = $this->normalizeString($customerData['name']);
      $customer->email                  = strtolower(trim($customerData['email']));
      $customer->status                 = intval($customerData['status'])===1?true:false;
      $customer->postalcode             = $this->formatPostalCode($customerData['zipcode']);
      $customer->cityid                 = intval($customerData['city']);
      $customer->entitytypeid           = $this->formatUserType($customerData['usertype']);
      $customer->regionaldocumentnumber = trim($customerData['ierg']);
      $customer->address                = $this->normalizeString($customerData['address']);
      $customer->district               = $this->normalizeString($customerData['neighbourhood']);
      $customer->complement             = $this->normalizeString($customerData['complement']);
      $customer->info                   = $this->normalizeString($customerData['info']);
      $customer->login                  = strtolower(trim($customerData['login']));
      $customer->password               = isset($customerData['password'])
        ? strtolower(trim($customerData['password']))
        : ''
      ;
      $customer->createdat              = trim($customerData['created_at']);
      if (!is_null($local)) {
        // Atribuímos as informações do cliente no ERP
        $customer->customerid   = $local->customerid;
        $customer->subsidiaryid = $local->subsidiaryid;
      }

      // Garante que o id da cidade seja válido
      if ($customer->cityid === 0) {
        $customer->cityid = 1;
      }
      
      $customer->save();
    }
  }
}
