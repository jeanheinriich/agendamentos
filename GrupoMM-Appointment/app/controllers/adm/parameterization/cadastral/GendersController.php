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
 * O controlador do gerenciamento de gêneros (sexos).
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Cadastral;

use App\Models\Gender;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class GendersController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Exibe a página inicial do gerenciamento de gêneros.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function show(Request $request, Response $response)
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Gêneros (Sexos)',
      $this->path('ADM\Parameterization\Cadastral\Genders')
    );

    // Registra o acesso
    $this->info("Acesso ao gerenciamento de gêneros.");

    // Recupera os dados da sessão
    $gender = $this->session->get('gender',
      [ 'name' => '' ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/genders/genders.twig',
      [ 'gender' => $gender ])
    ;
  }

  /**
   * Recupera a relação das gêneros em formato JSON.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function get(Request $request, Response $response)
  {
    $this->debug("Acesso à relação de gêneros.");

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Lida com as informações provenientes do Datatables
    
    // O número da requisição sequencial
    $draw = $postParams['draw'];

    // As definições das colunas
    $columns = $postParams['columns'];

    // O ordenamento, onde:
    //   column: id da coluna
    //      dir: direção
    $order = $postParams['order'][0];
    $orderBy  = $columns[$order['column']]['name'];
    $orderDir = strtoupper($order['dir']);

    // A posição inicial (0 = início) da paginação
    $start = $postParams['start'];

    // O comprimento de cada página
    $length = $postParams['length'];

    // Lida com as informações adicionais de filtragem
    
    // O campo de pesquisa selecionado
    $name = $postParams['searchValue'];

    // Seta os valores da última pesquisa na sessão
    $this->session->set('gender',
      [ 'name' => $name ]
    );
    
    // Corrige o escape dos campos
    $name = addslashes($name);

    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $GenderQry = Gender::whereRaw("1=1");

      // Acrescenta os filtros
      if (!empty($name)) {
        $GenderQry
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $genders = $GenderQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'genderid AS id',
            'genders.name',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;

      if (count($genders) > 0) {
        $rowCount = $genders[0]->fullcount;

        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $genders
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos gêneros cadastrados.";
        } else {
          $error = "Não temos gêneros cadastrados cujo nome contém "
            . "<i>{$name}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'gêneros',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de gêneros. "
        . "Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'gêneros',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de gêneros. "
        . "Erro interno."
      ;
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'draw' => $draw,
          'recordsTotal' => 0,
          'recordsFiltered' => 0,
          'data' => [ ],
          'error' => $error
        ])
    ;
  }

  /**
   * Exibe um formulário para adição de um gênero, quando solicitado,
   * e confirma os dados enviados.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function add(Request $request, Response $response)
  {
    // Verifica se estamos postando os dados
    if ($request->isPost()) {
      // Os dados estão sendo postados

      // Registra o acesso
      $this->debug("Processando à adição de gênero.");

      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 50)
          ->setName('Nome do gênero')
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados do gênero
          $genderData = $this->validator->getValues();

          // Primeiro, verifica se não temos um gênero com o mesmo nome
          if (Gender::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$genderData['name']}')"
                  )
                ->count() === 0) {
            // Grava a novo gênero
            $gender = new Gender();
            $gender->fill($genderData);
            $gender->save();

            // Registra o sucesso
            $this->info("Cadastrado o gênero '{name}' com sucesso.",
              [ 'name'  => $genderData['name'] ]
            );

            // Alerta o usuário
            $this->flash("success", "O gênero <i>'{name}'</i> foi "
              . "cadastrado com sucesso.",
              [ 'name'  => $genderData['name'] ]
            );

            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Cadastral\Genders' ]
            );

            // Redireciona para a página de gerenciamento de gêneros
            return $this->redirect($response,
              'ADM\Parameterization\Cadastral\Genders')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações do "
              . "gênero '{name}'. Já existe um gênero com o mesmo "
              . "nome.",
              [ 'name'  => $genderData['name'] ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Já existe um gênero com o nome "
              . "<i>'{name}'</i>.",
              [ 'name'  => $genderData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "gênero '{name}'. Erro interno no banco de dados: "
            . "{error}.",
            [ 'name'  => $genderData['name'],
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do gênero. Erro interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Registra o erro
          $this->error("Não foi possível inserir as informações do "
            . "gênero '{name}'. Erro interno: {error}.",
            [ 'name'  => $genderData['name'],
              'error' => $exception->getMessage() ]
          );

          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações do gênero. Erro interno."
          );
        }
      }
    }

    // Exibe um formulário para adição de um gênero

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Gêneros',
      $this->path('ADM\Parameterization\Cadastral\Genders')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Cadastral\Genders\Add')
    );

    // Registra o acesso
    $this->info("Acesso à adição de gênero.");

    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/genders/gender.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }

  /**
   * Exibe um formulário para edição de um gênero, quando solicitado,
   * e confirma os dados enviados
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function edit(Request $request, Response $response,
    array $args)
  {
    try
    {
      // Recupera as informações do gênero
      $genderID = $args['genderID'];
      $gender = Gender::findOrFail($genderID);

      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição do gênero '{name}'.",
          [ 'name' => $gender['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'name' => V::notBlank()
            ->length(2, 50)
            ->setName('Nome do gênero')
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados do gênero
            $genderData = $this->validator->getValues();

            // Primeiro, verifica se não mudamos o nome
            $save = false;
            if ($gender->name != $genderData['name']) {
              // Modificamos o nome do gênero, então verifica se temos
              // um gênero com o mesmo nome antes de prosseguir
              if (Gender::whereRaw("public.unaccented(name) ILIKE "
                        . "public.unaccented('{$genderData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações do gênero '{name}'. Já existe um "
                  . "gênero com o mesmo nome.",
                  [ 'name'  => $genderData['name'] ]
                );

                // Alerta o usuário
                $this->flashNow("error", "Já existe um gênero com o "
                  . "nome <i>'{name}'</i>.",
                  [ 'name'  => $genderData['name'] ]
                );
              }
            } else {
              $save = true;
            }

            if ($save) {
              // Grava as informações do gênero
              $gender->fill($genderData);
              $gender->save();

              // Registra o sucesso
              $this->info("Modificada o gênero '{name}' com sucesso.",
                [ 'name'  => $genderData['name'] ]
              );

              // Alerta o usuário
              $this->flash("success", "O gênero <i>'{name}'</i> foi "
                . "modificado com sucesso.",
                [ 'name'  => $genderData['name'] ]
              );

              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Cadastral\Genders' ]
              );

              // Redireciona para a página de gerenciamento de gêneros
              return $this->redirect($response,
                'ADM\Parameterization\Cadastral\Genders'
              );
            }
          }
          catch(QueryException $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do gênero '{name}'. Erro interno no banco de dados: "
              . "{error}",
              [ 'name'  => $genderData['name'],
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do gênero. Erro interno no banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "do gênero '{name}'. Erro interno: {error}",
              [ 'name'  => $genderData['name'],
                'error' => $exception->getMessage() ]
            );

            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações do gênero. Erro interno."
            );
          }
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($gender->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o gênero código "
        . "{genderID}.",
        [ 'genderID' => $genderID ]
      );

      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar este gênero.");

      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Cadastral\Genders' ]
      );

      // Redireciona para a página de gerenciamento de gêneros
      return $this->redirect($response,
        'ADM\Parameterization\Cadastral\Genders'
      );
    }

    // Exibe um formulário para edição de um gênero

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Dados Cadastrais', '');
    $this->breadcrumb->push('Gêneros',
      $this->path('ADM\Parameterization\Cadastral\Genders')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Cadastral\Genders\Edit', [
        'genderID' => $genderID
      ])
    );

    // Registra o acesso
    $this->info("Acesso à edição do gênero '{name}'.",
      [ 'name' => $gender['name'] ]
    );

    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/cadastral/genders/gender.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }

  /**
   * Remove o gênero.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   * @param array $args
   *   Os argumentos da requisição
   *
   * @return Response $response
   */
  public function delete(Request $request, Response $response,
    array $args)
  {
    // Registra o acesso
    $this->debug("Processando à remoção de gênero.");

    // Recupera o ID
    $genderID = $args['genderID'];

    try
    {
      // Recupera as informações do gênero
      $gender = Gender::findOrFail($genderID);

      // Agora apaga o gênero
      $gender->delete();

      // Registra o sucesso
      $this->info("O gênero '{name}' foi removido com sucesso.",
        [ 'name' => $gender->name ]
      );

      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removido o gênero {$gender->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar o gênero código "
        . "{genderID} para remoção.",
        [ 'genderID' => $genderID ]
      );

      $message = "Não foi possível localizar o gênero para remoção.";
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do gênero "
        . "ID {id}. Erro interno no banco de dados: {error}.",
        [ 'id'  => $genderID,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível remover o gênero. Erro interno no "
        . "banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível remover as informações do gênero "
        . "ID {id}. Erro interno: {error}.",
        [ 'id'  => $genderID,
          'error' => $exception->getMessage() ]
      );

      $message = "Não foi possível remover o gênero. Erro interno.";
    }

    return $response
      ->withHeader('Content-type', 'application/json')
      ->withJson([
          'result' => 'NOK',
          'params' => $request->getParams(),
          'message' => $message,
          'data' => null
        ])
    ;
  }
}
