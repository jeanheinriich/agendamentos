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
 * A interface para um manipulador de contratantes.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */


namespace Core\Authorization\Contractors;

interface ContractorsManagerInterface
{
  /**
   * Localiza um contratante pela ID fornecida.
   * 
   * @param int $contractorID
   *   O ID do contratante
   * 
   * @return Contractor
   *   Os dados do contratante
   * 
   * @throws CredentialException
   *   Em caso de erro nas credenciais
   */
  public function findById(int $contractorID);
  
  /**
   * Localiza um contratante pela ID de uma entidade.
   * 
   * @param int $entityID
   *   O ID da entidade (cliente ou fornecedor)
   * 
   * @return Contractor
   *   Os dados do contratante
   * 
   * @throws CredentialException
   *   Em caso de erro nas credenciais
   */
  public function findByEntityId(int $entityID);
  
  /**
   * Localiza um usuário pelo UUID fornecido.
   * 
   * @param array $credentials
   *   As credenciais com os dados de autenticação
   * 
   * @return Contractor
   *   Um objeto com os dados do contratante
   * 
   * @throws CredentialException
   *   Em caso de erro nas credenciais
   */
  public function findByUUID(array $credentials);
}
