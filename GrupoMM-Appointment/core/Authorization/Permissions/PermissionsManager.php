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
 * Um manipulador de permissões por grupo de usuários.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace Core\Authorization\Permissions;

use Carbon\Carbon;
use Core\Authorization\Users\UserInterface;
use Core\Logger\LoggerTrait;
use Core\Traits\ContainerTrait;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerInterface;

class PermissionsManager
  implements PermissionsInterface
{
  /**
   * Os métodos para manipulação do container
   */
  use ContainerTrait;

  /**
   * Os métodos para registro de eventos em Logs
   */
  use LoggerTrait;

  /**
   * As informações de permissões para o usuário autenticado.
   * 
   * @var array
   */
  protected $permissions;

  // ============================================[ Criação da classe ]==
  
  /**
   * O construtor de nossa classe.
   * 
   * @param ContainerInterface $container
   *   Nossa interface de containers
   */
  public function __construct(ContainerInterface $container)
  {
    $this->container = $container;
    unset($container);
  }
  
  /**
   * Carrega as informações de permissões em cache.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   */
  public function loadPermissions(UserInterface $user): void
  {
    // Libera quaisquer permissões armazenadas
    unset($this->permissions);

    // Recupera todas as permissões do grupo ao qual o usuário pertence
    $group = $user->group;
    $permissions = Permission::leftJoin('permissionspergroups',
          'permissions.permissionid', '=',
          'permissionspergroups.permissionid'
        )
      ->where('permissionspergroups.groupid', $group->groupid)
      ->orderBy('permissions.permissionid')
      ->get([
          'permissions.permissionid AS id',
          'permissions.name AS routename',
          'permissionspergroups.httpmethod',
        ])
    ;

    // Monta a estrutura das permissões e armazena internamente
    $this->permissions = $this->buildPermissionData($permissions);
    unset($permissions);

    // Armazenamos na sessão com uma data de expiração
    $expiration = Carbon::now()->addHours(2);
    $data = [
      'expiration' => $expiration,
      'data' => $this->permissions
    ];
    $this->session->permissions = serialize($data);
    unset($data);

    $this->debug(
      "Recuperada as informações das permissões do grupo '{name}'",
      [
        'name' => $group->name
      ]
    );
  }

  /**
   * Monta as informações de permissões baseado numa coleção.
   *
   * @param Collection $permissions
   *   A coleção com as informações de permissões
   *
   * @return array
   *   A matriz com as informações de permissões
   */
  protected function buildPermissionData($permissions): array
  {
    // Montamos uma tabela com as informações de permissão
    $perRoute = [];
    $perRouteGroup = [];
    foreach ($permissions as $permission) {
      // Verifica se já temos esta permissão adicionada
      if ( isset($perRoute[ $permission->id ]) ) {
        // Adicionamos este método HTTP as informações desta permissão
        $perRoute[ $permission->id ]['methods'][] =
          $permission->httpmethod
        ;
      } else {
        // Adicionamos um registro para esta permissão
        $perRoute[ $permission->id ] = [
          'routeName' => $permission->routename,
          'methods' => [
            $permission->httpmethod
          ]
        ];
      }

      if ($permission->httpmethod === 'GET') {
        // Montamos também as permissões para os grupos de rotas que é
        // usado na liberação de opções dos menus
        $routeParts = explode("\\", $permission->routename);
        if ( count($routeParts) > 1 ) {
          $path = null;
          $parts = array_slice($routeParts, 0, count($routeParts) - 1);
          foreach ($parts as $part) {
            $path .= ((isset($path)) ? '\\' : '' ) . $part;

            if ( isset($perRouteGroup[ $path ]) ) {
              $perRouteGroup[ $path ]++;
            } else {
              $perRouteGroup[ $path ] = 1;
            }
          }
        }
      }
    }

    return [
      // As informações de permissão por rota
      'perRoute' => $perRoute,
      // As informações de permissão por grupo de rotas
      'perRouteGroup' => $perRouteGroup
    ];
  }

  /**
   * Lida com as informações de permissões armazenadas em cache. Caso
   * não tenhamos ainda informações armazenadas, então simplesmente
   * carrega estas informações do banco de dados.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   */
  protected function handleCachedPermissions(UserInterface $user): void
  {
    // Inicialmente indica que não temos permissões
    $permissions = null;

    // Verifica se temos armazenadas na sessão as informações de
    // permissões para o grupo ao qual este usuário pertence. Fazemos
    // isto para melhorar a performance mantendo um cache destas
    // informações na sessão
    $group = $user->group;
    if ( isset($this->session->permissions) ) {
      $this->debug("Consultando as informações de permissões do grupo "
        . "'{name}' armazenadas na sessão",
        [
          'name' => $group->name
        ]
      );
      $sessionData = unserialize($this->session->permissions);

      // Determina se estas permissões ainda são válidas
      $now = Carbon::now();
      $expiration = $sessionData['expiration'];
      if ($now->lessThanOrEqualTo($expiration)) {
        // Recupera as informações de permissões da sessão
        $this->permissions = $sessionData['data'];
        $this->debug("Restaurada as informações das permissões do "
          . "grupo '{name}' pelos valores armazenados na sessão",
          [
            'name' => $group->name
          ]
        );

        return;
      } else {
        $this->debug("As informações das permissões do grupo '{name}' "
          . "armazenadas na sessão são obsoletas e foram descartadas",
          [
            'name' => $group->name
          ]
        );
        unset($this->session->permissions);
        unset($this->permissions);
      }
      unset($sessionData);
    } else {
      $this->debug("Não temos informações das permissões do grupo "
        . "'{name}' armazenadas na sessão",
        [
          'name' => $group->name
        ]
      );
    }

    // Se não temos permissões armazenadas, então carregamos novamente
    // do banco de dados
    $this->loadPermissions($user);
  }


  // ----------------------[ Implementações da PermissionsInterface ]---

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
  ): bool
  {
    if (empty($routeName)) {
      // O nome da rota não foi informado
      $this->debug("Ignorando consulta de permissão para rota vazia");

      return false;
    }

    if (is_null($this->permissions)) {
      // Lidamos com as informações de permissões armazenadas em cache
      $this->handleCachedPermissions($user);
    }

    // Agora determina se o usuário têm permissão para a rota
    foreach ($this->permissions['perRoute'] as $id => $permission) {
      if ($permission['routeName'] === $routeName) {
        return in_array($httpMethod, $permission['methods']);
      }
    }

    $this->debug("Negado acesso à rota '{route}' desconhecida usando "
      . "o método HTTP {method}",
      [
        'route' => $routeName,
        'method' => $httpMethod
      ]
    );

    return false;
  }

  /**
   * Retorna se o grupo ao qual o usuário pertence possuir uma ou mais
   * permissões de acesso a um determinado grupo de rotas. É usado para
   * permitir ocultar um grupo de menus na interface, pois verificamos
   * se o grupo ao qual o usuário pertence possui ao menos uma permissão
   * naquele grupo de rotas que estamos exibindo. Por exemplo, para as
   * rotas:
   *   ERP\Cadastre\Customers
   *   ERP\Cadastre\Vehicles
   *   ERP\Cadastre\Users
   * Temos em comum que todas elas pertencem ao grupo de rotas
   *   ERP\Cadastre
   * Este método verifica se este grupo ao qual o usuário pertence
   * possui permissão de acesso para pelo menos um dos itens que serão
   * exibidos (no caso Customers, Vehicles ou Users). Armazenamos apenas
   * a quantidade de permissões para cada grupo de rotas, sendo que zero
   * determina que o grupo ao qual o usuário pertence não possui
   * qualquer permissão a nenhum dos itens a serem exibidos.
   * 
   * @param UserInterface $user
   *   Os dados do usuário
   * @param string $routeGroupPath
   *   O grupo de rotas (início da rota)
   * 
   * @return bool
   */
  public function hasPermissionOnGroupOfRoutes(UserInterface $user,
    string $routeGroupPath): bool
  {
    if ( empty($routeGroupPath) ) {
      // O nome d rota não foi informado
      $this->debug("Ignorando consulta de permissão para rota vazia");

      return false;
    }

    if ( is_null($this->permissions) ) {
      // Lidamos com as informações de permissões armazenadas em cache
      $this->handleCachedPermissions($user);
    }

    // Agora determina se o usuário têm permissão para o grupo de rotas
    if (isset($this->permissions['perRouteGroup'][$routeGroupPath])) {
      return ($this->permissions['perRouteGroup'][$routeGroupPath] > 0);
    }

    $this->debug("Negado acesso ao grupo de rotas '{route}'",
      [
        'route' => $routeGroupPath
      ]
    );

    return false;
  }
}
