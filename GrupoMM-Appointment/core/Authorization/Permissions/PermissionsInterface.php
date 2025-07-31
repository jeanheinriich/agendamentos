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
 * Uma interface para as permissões de um usuário.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Permissions;

use Core\Authorization\Users\UserInterface;

interface PermissionsInterface
{
  /**
   * Carrega as informações de permissões em cache.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   */
  public function loadPermissions(UserInterface $user): void;
  
  /**
   * Retorna se o usuário possui permissão de acesso para a rota
   * informada.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * @param string $routeName
   *   O nome da rota a ser analisada
   * @param string $httpMethod
   *   O método HTTP usado
   * 
   * @return bool
   */
  public function hasAccess(
    UserInterface $user,
    string $routeName,
    string $httpMethod
  ): bool;

  /**
   * Retorna se o grupo ao qual o usuário pertence possuir uma ou mais
   * permissões de acesso ao grupo de rotas informada. É usado para
   * permitir ocultar um grupo de menus na interface.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * @param string $routeGroupName
   *   O grupo de rotas (início da rota)
   * 
   * @return bool
   */
  public function hasPermissionOnGroupOfRoutes(
    UserInterface $user,
    string $routeGroupName
  ): bool;
}
