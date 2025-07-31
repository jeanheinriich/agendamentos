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
 * Um manipulador de contratantes.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Contractors;

use Core\Exceptions\CredentialException;

class ContractorsManager
  implements ContractorsManagerInterface
{
  // ============================================[ Criação da classe ]==
  
  /**
   * O construtor do manipulador de contratantes.
   */
  public function __construct() { }

  
  // ---------------[ Implementações da ContractorsManagerInterface ]---

  /**
   * Localiza um contratante pela ID fornecida.
   * 
   * @param int $contractorID
   *   O ID do contratante
   * 
   * @return Contractor|null
   *   Os dados do contratante ou nulo se não encontrar
   * 
   * @throws CredentialException
   *   Em caso de erro nas credenciais
   */
  public function findById(int $contractorID): ?Contractor
  {
    $contractor = Contractor::where("contractor", "true")
      ->where("entityid", "=", $contractorID)
      ->get([
          "entityid AS id",
          "name",
          "entityuuid AS uuid",
          "stckey"
        ])
      ->first()
    ;

    if (empty($contractor)) {
      $this->throwException("Não foi possível localizar o "
        . "contratante através da ID informada."
      );

      return null;
    }

    return $contractor;
  }
  
  /**
   * Localiza um contratante pela ID de uma entidade.
   * 
   * @param int $entityID
   *   O ID da entidade (cliente ou fornecedor)
   * 
   * @return Contractor|null
   *   Os dados do contratante ou nulo se não encontrar
   * 
   * @throws CredentialException
   *   Em caso de erro nas credenciais
   */
  public function findByEntityId(int $entityID): ?Contractor
  {
    // Recupera o ID do contratante pela entidade ao qual pertence
    $entity = Entity::find($entityID);

    if (empty($entity)) {
      $this->throwException('Não foi possível localizar a "
        . "entidade através da ID informada.'
      );

      return null;
    }

    $contractorID = $entity->getContractorId();

    // Agora recupera as informações deste contratante
    return $this->findById($contractorID);
  }
  
  /**
   * Localiza um usuário pelo UUID fornecido.
   * 
   * @param array $credentials
   *   As credenciais com os dados de autenticação
   * 
   * @return Contractor|null
   *   Os dados do contratante ou nulo se não encontrar
   * 
   * @throws CredentialException
   *   Em caso de erro nas credenciais
   */
  public function findByUUID(array $credentials): ?Contractor
  {
    // Extraímos das credenciais o UUID do contratante
    list($uuid, $credentials) = $this->parseCredentials($credentials);

    if ($this->validateUUID($uuid)) {
      // Recuperamos as informações do contratante pela UUID
      $contractor = Contractor::where("contractor", "true")
        ->where("entityuuid", "=", $uuid)
        ->get([
            "entityid AS id",
            "name",
            "entityuuid AS uuid",
            "stckey"
          ])
        ->first()
      ;

      if (empty($contractor)) {
        $this->throwException("Não foi possível localizar o "
          . "contratante através das credenciais informadas."
        );

        return null;
      }

      if ($contractor->blocked) {
        $this->throwException("O contratante {$contractor->name} está "
          . "com sua conta suspensa."
        );
      }

      return $contractor;
    }

    return null;
  }

  
  // ---------------------------------------[ Outras implementações ]---

  /**
   * Analisa as credenciais fornecidas para retornar, separadamente, as
   * informações do campo de identificação do contratante (uuid) e as
   * demais credenciais.
   * 
   * @param array $credentials
   *   As credenciais de autenticação
   * 
   * @return array
   *   Os valores da UUID e demais dados separados
   */
  protected function parseCredentials(array $credentials): array
  {
    // Retira das credenciais a UUID do contratante, caso fornecida
    if (isset($credentials['uuid'])) {
      $uuid = $credentials['uuid'];
      
      unset($credentials['uuid']);
    } else {
      $uuid = null;
    }
    
    // Retorna os dados de UUID e demais credenciais
    return [$uuid, $credentials];
  }
  
  /**
   * Valida as credenciais de um usuário.
   * 
   * @param array $credentials
   *   As credenciais de autenticação
   * 
   * @return bool
   * 
   * @throws CredentialException
   *   Em caso de erro nas credenciais
   */
  public function validateContractor(array $credentials): bool
  {
    // Vamos simplesmente analisar das credenciais o UUID
    list($uuid, $credentials) = $this->parseCredentials($credentials);
    
    // Verifica se foi fornecido uma UUID válida
    return $this->validateUUID($uuid);
  }

  /**
   * Verifica se a UUID é válida.
   * 
   * @param string $uuid
   *   A UUID do contratante à ser validada
   * 
   * @return bool
   * 
   * @throws CredentialException
   *   Em caso de erro nas credenciais
   */
  protected function validateUUID(string $uuid): bool
  {
    if ($uuid === null) {
      $this->throwException('A credencial do contratante não pode ser "
        . "nula'
      );

      return false;
    } else {
      $UUID_V4 = '/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i';
      
      if (!is_string($uuid) || (preg_match($UUID_V4, $uuid) !== 1)) {
        $this->throwException(
          "A credencial '{$uuid}' do contratante é inválida."
        );

        return false;
      }
    }

    return true;
  }

  /**
   * Lança uma exceção nos casos de argumentos inválidos.
   *
   * @param string $message
   *   A mensagem a ser relatada
   * 
   * @throws CredentialException
   *   Em caso de erro nas credenciais
   */
  protected function throwException($message): void
  {
    $exception = new CredentialException($message);

    throw $exception;
  }
}
