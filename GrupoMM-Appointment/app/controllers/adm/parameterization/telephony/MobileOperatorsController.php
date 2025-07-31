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
 * O controlador do gerenciamento de operadoras de telefonia móvel. Uma
 * operadora de telefonia móvel é uma entidade que fornece SIM Cards.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ADM\Parameterization\Telephony;

use App\Models\AccessPointName;
use App\Models\MobileNetworkCode;
use App\Models\MobileOperator;
use Core\Controllers\Controller;
use Core\Controllers\HandleFileTrait;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Respect\Validation\Validator as V;
use Slim\Http\Request;
use Slim\Http\Response;

class MobileOperatorsController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Os métodos para manipular o recebimento de arquivos.
   */
  use HandleFileTrait;

  /**
   * A logomarca vazia, usada para preencher o espaço.
   *
   * @var string
   */
  protected $emptyLogo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgA'
    . 'AAPoAAABQCAQAAACaYqA+AAAAkUlEQVR42u3RMQEAAAzCsOHf9FzwkEpocporFkA'
    . 'XdEEXdEEXdEEXdEEXdEEXdEEXdEEXdEGHLuiCLuiCLuiCLuiCLuiCLuiCLuiCLui'
    . 'CDl3QBV3QBV3QBV3QBV3QBV3QBV3QBV3QBR26oAu6oAu6oAu6oAu6oAu6oAu6oAu'
    . '6oAs6dEEXdEEXdEEXdEEXdEFXqwdizwBR5Wqp1gAAAABJRU5ErkJggg=='
  ;


  /**
   * Exibe a página inicial do gerenciamento de operadoras de telefonia
   * móvel.
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
    $this->breadcrumb->push('Telefonia', '');
    $this->breadcrumb->push('Operadoras de telefonia móvel',
      $this->path('ADM\Parameterization\Telephony\MobileOperators')
    );
    
    // Registra o acesso
    $this->info("Acesso ao gerenciamento de operadoras de telefonia "
      . "móvel."
    );
    
    // Recupera os dados da sessão
    $mobileOperator = $this->session->get('mobileoperator',
      [ 'name' => '' ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/telephony/mobileoperators/mobileoperators.twig',
      [ 'mobileoperator' => $mobileOperator ])
    ;
  }
  
  /**
   * Recupera a relação dos operadoras de telefonia móvel em formato
   * JSON.
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
    $this->debug("Acesso à relação de operadoras de telefonia móvel "
      . "despachada."
    );
    
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
    $this->session->set('mobileoperator',
      [ 'name' => $name ]
    );

    // Corrige o escape dos campos
    $name = addslashes($name);
    
    try
    {
      // Formata o ordenamento dos campos
      $ORDER = $this->formatOrderBy($orderBy, $orderDir);

      // Inicializa a query
      $MobileOperatorQry = MobileOperator::whereRaw("1=1");
      
      // Acrescenta os filtros
      if (!empty($name)) {
        $MobileOperatorQry
          ->whereRaw("public.unaccented(name) ILIKE "
              . "public.unaccented('%{$name}%')"
            )
        ;
      }

      // Conclui nossa consulta
      $mobileOperators = $MobileOperatorQry
        ->skip($start)
        ->take($length)
        ->orderByRaw($ORDER)
        ->get([
            'mobileoperatorid AS id',
            'name',
            'logo',
            $this->DB->raw('count(*) OVER() AS fullcount')
          ])
      ;
      
      if (count($mobileOperators) > 0) {
        $rowCount = $mobileOperators[0]->fullcount;
        
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'draw' => $draw,
              'recordsTotal' => $rowCount,
              'recordsFiltered' => $rowCount,
              'data' => $mobileOperators
            ])
        ;
      } else {
        if (empty($name)) {
          $error = "Não temos operadoras de telefonia móvel "
            . "cadastradas."
          ;
        } else {
          $error = "Não temos operadoras de telefonia móvel "
            . "cadastradas cujo nome contém <i>{$name}</i>."
          ;
        }
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno no banco de dados: {error}.",
        [ 'module' => 'operadoras de telefonia móvel',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "operadoras de telefonia móvel. Erro interno no banco de "
        . "dados."
      ;
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module}. Erro interno: {error}.",
        [ 'module' => 'operadoras de telefonia móvel',
          'error'  => $exception->getMessage() ]
      );

      $error = "Não foi possível recuperar as informações de "
        . "operadoras de telefonia móvel. Erro interno."
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
   * Exibe um formulário para adição de uma operadora de telefonia
   * móvel, quando solicitado, e confirma os dados enviados.
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
      // Registra o acesso
      $this->debug("Processando à adição de operadora de telefonia "
        . "móvel."
      );

      // Valida os dados
      $this->validator->validate($request, [
        'name' => V::notBlank()
          ->length(2, 20)
          ->setName('Nome da operadora de telefonia móvel'),
        'logo' => V::optional(
            V::oneOf(
              V::extension('png'),
              V::extension('jpg'),
              V::extension('jpeg')
            )
          )->setName('Logomarca da operadora'),
        'networkcodes' => [
          'mobilenetworkcodeid' => V::intVal()
            ->setName('ID do código de rede'),
          'mcc' => V::notEmpty()
            ->length(3, null)
            ->setName('MCC'),
          'mnc' => V::notEmpty()
            ->length(2, 3)
            ->setName('MNC')
        ],
        'accesspoints' => [
          'accesspointnameid' => V::intVal()
            ->setName('ID da APN'),
          'name' => V::notEmpty()
            ->length(2, 30)
            ->setName('Nome'),
          'address' => V::notEmpty()
            ->length(2, 100)
            ->domain(false)
            ->setName('Ponto de Acesso (APN)'),
          'username' => V::optional(
              V::notEmpty()
                ->alnum()
                ->noWhitespace()
            )->setName('Usuário'),
          'password' => V::optional(
              V::notEmpty()
                ->alnum()
                ->noWhitespace()
            )->setName('Senha')
        ]
      ]);

      if ($this->validator->isValid()) {
        // Grava as informações no banco de dados
        try
        {
          // Recupera os dados da operadora de telefonia móvel
          $mobileOperatorData = $this->validator->getValues();
          
          // Primeiro, verifica se não temos uma operadora de telefonia
          // móvel com o mesmo nome
          if (MobileOperator::whereRaw("public.unaccented(name) ILIKE "
                    . "public.unaccented('{$mobileOperatorData['name']}')"
                  )
                ->count() === 0) {
            // Recupera o arquivo da logomarca, se enviado
            $uploadedFiles = $request->getUploadedFiles();

            // Lida com a logomarca da operadora
            $uploadedFile = $uploadedFiles['logo'];
            
            if ($this->fileHasBeenTransferred($uploadedFile)) {
              // Lê o arquivo de imagem, convertendo para Base64 de
              // forma a permitir armazenar dentro da base de dados
              $mobileOperatorData['logo'] =
                $this->getImage($uploadedFile, 250, 80, false)
              ;
            } else {
              // Não foi enviado nenhum arquivo com uma imagem da
              // logomarca, então coloca uma imagem vazia
              $mobileOperatorData['logo'] = $this->emptyLogo;
            }
            
            // Iniciamos a transação
            $this->DB->beginTransaction();

            // Precisa retirar dos parâmetros as informações
            // correspondentes aos códigos de rede
            $networkCodes = $mobileOperatorData['networkcodes'];
            unset($mobileOperatorData['networkcodes']);

            // Precisa retirar dos parâmetros as informações
            // correspondentes aos pontos de acesso
            $accessPoints = $mobileOperatorData['accesspoints'];
            unset($mobileOperatorData['accesspoints']);

            // Grava a nova operadora de telefonia móvel
            $mobileOperator = new MobileOperator();
            $mobileOperator->fill($mobileOperatorData);
            $mobileOperator->save();
            $mobileOperatorID = $mobileOperator->mobileoperatorid;

            // Agora inserimos os novos códigos de rede
            foreach ($networkCodes as $networkCodeData) {
              // Incluímos o novo código de rede desta operadora de
              // telefonia móvel
              unset($networkCodeData['mobilenetworkcodeid']);
              $networkCode = new MobileNetworkCode();
              $networkCode->fill($networkCodeData);
              $networkCode->mobileoperatorid = $mobileOperatorID;
              $networkCode->save();
            }

            // Por último, inserimos os novos pontos de acesso
            foreach ($accessPoints as $accessPointData) {
              // Incluímos o novo ponto de acesso desta operadora de
              // telefonia móvel
              unset($accessPointData['accesspointnameid']);
              $accessPoint = new AccessPointName();
              $accessPoint->fill($accessPointData);
              $accessPoint->mobileoperatorid = $mobileOperatorID;
              $accessPoint->save();
            }

            // Efetiva a transação
            $this->DB->commit();
            
            // Registra o sucesso
            $this->info("Cadastrada a operadora de telefonia móvel "
              . "'{name}' com sucesso.",
              [ 'name'  => $mobileOperatorData['name'] ]
            );
            
            // Alerta o usuário
            $this->flash("success", "A operadora de telefonia móvel "
              . "<i>'{name}'</i> foi cadastrada com sucesso.",
              [ 'name'  => $mobileOperatorData['name'] ]
            );
            
            // Registra o evento
            $this->debug("Redirecionando para {routeName}",
              [ 'routeName' => 'ADM\Parameterization\Telephony\MobileOperators' ]
            );
            
            // Redireciona para a página de gerenciamento de operadoras
            // de telefonia móvel
            return $this->redirect($response,
              'ADM\Parameterization\Telephony\MobileOperators')
            ;
          } else {
            // Registra o erro
            $this->debug("Não foi possível inserir as informações da "
              . "operadora de telefonia móvel '{name}'. Já existe uma "
              . "operadora de telefonia móvel com o mesmo nome.",
              [ 'name'  => $mobileOperatorData['name'] ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Já existe uma operadora de "
              . "telefonia móvel com o nome <i>'{name}'</i>.",
              [ 'name'  => $mobileOperatorData['name'] ]
            );
          }
        }
        catch(QueryException $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();
          
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "operadora de telefonia móvel '{name}'. Erro interno no "
            . "banco de dados: {error}.",
            [ 'name'  => $mobileOperatorData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da operadora de telefonia móvel. Erro "
            . "interno no banco de dados."
          );
        }
        catch(Exception $exception)
        {
          // Reverte (desfaz) a transação
          $this->DB->rollBack();
          
          // Registra o erro
          $this->error("Não foi possível inserir as informações da "
            . "operadora de telefonia móvel '{name}'. Erro interno: "
            . "{error}.",
            [ 'name'  => $mobileOperatorData['name'],
              'error' => $exception->getMessage() ]
          );
          
          // Alerta o usuário
          $this->flashNow("error", "Não foi possível inserir as "
            . "informações da operadora de telefonia móvel. Erro "
            . "interno."
          );
        }
      } else {
        // Adiciona a logomarca vazia novamente
        $this->validator->setValue('logo', $this->emptyLogo);
      }
    } else {
      // Carrega os dados iniciais para simplificar a digitação
      $this->validator->setValues([
        'logo' => $this->emptyLogo
      ]);
    }
    
    // Exibe um formulário para adição de uma operadora de telefonia
    // móvel
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Telefonia', '');
    $this->breadcrumb->push('Operadoras de telefonia móvel',
      $this->path('ADM\Parameterization\Telephony\MobileOperators')
    );
    $this->breadcrumb->push('Adicionar',
      $this->path('ADM\Parameterization\Telephony\MobileOperators\Add')
    );
    
    // Registra o acesso
    $this->info("Acesso à adição de operadora de telefonia móvel.");
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/telephony/mobileoperators/mobileoperator.twig',
      [ 'formMethod' => 'POST' ])
    ;
  }
  
  /**
   * Exibe um formulário para edição de uma operadora de telefonia
   * móvel, quando solicitado, e confirma os dados enviados.
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
      // Recupera as informações da operadora de telefonia móvel
      $mobileOperatorID = $args['mobileOperatorID'];
      $mobileOperator = MobileOperator::findOrFail($mobileOperatorID);

      // Agora recupera as informações dos Códigos de Rede para
      // identificação das Operadoras de Telefonia Móvel (MNC)
      $mobileOperator['networkcodes'] =
        MobileNetworkCode::where('mobileoperatorid', $mobileOperatorID)
          ->get([
              'mobilenetworkcodeid',
              'mcc',
              'mnc'
            ])
          ->toArray()
      ;

      // Por último, recupera as informações das APNs
      $mobileOperator['accesspoints'] =
        AccessPointName::where('mobileoperatorid', $mobileOperatorID)
          ->get([
              'accesspointnameid',
              'name',
              'address',
              'username',
              'password'
            ])
          ->toArray()
      ;
      
      // Verifica se estamos modificando os dados
      if ($request->isPut()) {
        // Os dados estão sendo modificados

        // Registra o acesso
        $this->debug("Processando à edição da operadora de telefonia "
          . "móvel '{name}'.",
          [ 'name' => $mobileOperator['name'] ]
        );
        
        // Valida os dados
        $this->validator->validate($request, [
          'mobileoperatorid' => V::intVal()
            ->setName('ID da operadora de telefonia móvel'),
          'name' => V::notBlank()
            ->length(2, 20)
            ->setName('Nome da operadora de telefonia móvel'),
          'logo' => V::optional(
              V::oneOf(
                V::extension('png'),
                V::extension('jpg'),
                V::extension('jpeg')
              )
            )->setName('Logomarca da operadora'),
          'networkcodes' => [
            'mobilenetworkcodeid' => V::intVal()
              ->setName('ID do código de rede'),
            'mcc' => V::notEmpty()
              ->length(3, null)
              ->setName('MCC'),
            'mnc' => V::notEmpty()
              ->length(2, 3)
              ->setName('MNC')
          ],
          'accesspoints' => [
            'accesspointnameid' => V::intVal()
              ->setName('ID da APN'),
            'name' => V::notEmpty()
              ->length(2, 30)
              ->setName('Nome'),
            'address' => V::notEmpty()
              ->length(2, 100)
              ->domain(false)
              ->setName('Ponto de Acesso (APN)'),
            'username' => V::optional(
                V::notEmpty()
                  ->alnum()
                  ->noWhitespace()
              )->setName('Usuário'),
            'password' => V::optional(
                V::notEmpty()
                  ->alnum()
                  ->noWhitespace()
              )->setName('Senha')
          ]
        ]);

        if ($this->validator->isValid()) {
          // Grava as informações no banco de dados
          try
          {
            // Recupera os dados modificados da operadora de telefonia
            // móvel
            $mobileOperatorData = $this->validator->getValues();

            // Primeiro, verifica se não mudamos o nome da operadora de
            // telefonia móvel
            $save = false;
            if ($mobileOperator->name != $mobileOperatorData['name']) {
              // Modificamos o nome da operadora de telefonia móvel,
              // então verifica se temos uma operadora de telefonia
              // móvel com o mesmo nome antes de prosseguir
              if (MobileOperator::whereRaw("public.unaccented(name) "
                        . "ILIKE public.unaccented('{$mobileOperatorData['name']}')"
                      )
                    ->count() === 0) {
                $save = true;
              } else {
                // Registra o erro
                $this->debug("Não foi possível modificar as "
                  . "informações da operadora de telefonia móvel "
                  . "'{name}'. Já existe uma operadora de telefonia "
                  . "móvel com o mesmo nome.",
                  [ 'name'  => $mobileOperatorData['name'] ]
                );
                
                // Alerta o usuário
                $this->flashNow("error", "Já existe uma operadora de "
                  . "telefonia móvel com o nome <i>'{name}'</i>.",
                  [ 'name'  => $mobileOperatorData['name'] ]
                );
              }
            } else {
              $save = true;
            }
            
            if ($save) {
              // Recupera o arquivo da logomarca, se enviado
              $uploadedFiles = $request->getUploadedFiles();

              // Lida com a logomarca da operadora
              $uploadedFile = $uploadedFiles['logo'];
              
              if ($this->fileHasBeenTransferred($uploadedFile)) {
                // Lê o arquivo de imagem, convertendo para Base64 de
                // forma a permitir armazenar dentro da base de dados
                $mobileOperatorData['logo'] =
                  $this->getImage($uploadedFile, 250, 80, false)
                ;
              } else {
                // Não foi enviado nenhum arquivo com uma imagem da
                // logomarca, então mantém a imagem atual
                $mobileOperatorData['logo'] = $mobileOperator->logo;
              }
              
              // Iniciamos a transação
              $this->DB->beginTransaction();

              // Precisa retirar dos parâmetros as informações
              // correspondentes aos códigos de rede
              $networkCodeData = $mobileOperatorData['networkcodes'];
              unset($mobileOperatorData['networkcodes']);

              // Precisa retirar dos parâmetros as informações
              // correspondentes aos pontos de acesso
              $accessPointData = $mobileOperatorData['accesspoints'];
              unset($mobileOperatorData['accesspoints']);

              // ===============================[ Códigos de Rede ]=====
              // Recupera as informações dos códigos de rede e separa os
              // dados para as operações de inserção, atualização e
              // remoção.
              // =======================================================

              // -----------------------------[ Pré-processamento ]-----

              // Analisa os códigos de rede informados, de forma a
              // separar quais códigos precisam ser adicionados,
              // removidos e atualizados

              // Matrizes que armazenarão os dados dos códigos de rede
              // a serem adicionados, atualizados e removidos
              $newNetworkCodes = [ ];
              $updNetworkCodes = [ ];
              $delNetworkCodes = [ ];

              // Os IDs dos códigos de rede mantidos para permitir
              // determinar os códigos de rede a serem removidos
              $heldNetworkCodes = [ ];

              // Determina quais códigos serão mantidos (e atualizados)
              // e os que precisam ser adicionados (novos)
              foreach ($networkCodeData AS $networkCode) {
                if (empty($networkCode['mobilenetworkcodeid'])) {
                  // Código de rede novo
                  $newNetworkCodes[] = $networkCode;
                } else {
                  // Código de rede existente
                  $heldNetworkCodes[] =
                    $networkCode['mobilenetworkcodeid']
                  ;
                  $updNetworkCodes[]  = $networkCode;
                }
              }

              // Recupera os códigos de rede armazenados atualmente
              $networkCodes =
                MobileNetworkCode::where('mobileoperatorid',
                      $mobileOperatorID
                    )
                  ->get([
                      'mobilenetworkcodeid'
                    ])
                  ->toArray()
              ;
              $oldNetworkCodes = [ ];
              foreach ($networkCodes as $networkCode) {
                $oldNetworkCodes[] =
                  $networkCode['mobilenetworkcodeid']
                ;
              }

              // Verifica quais os códigos de rede estavam na base de
              // dados e precisam ser removidos
              $delNetworkCodes =
                array_diff($oldNetworkCodes, $heldNetworkCodes)
              ;


              // ==============================[ Pontos de Acesso ]=====
              // Recupera as informações dos pontos de acesso e separa
              // os dados para as operações de inserção, atualização e
              // remoção.
              // =======================================================

              // -----------------------------[ Pré-processamento ]-----

              // Analisa os pontos de acesso informados, de forma a
              // separar quais nomes precisam ser adicionados, removidos
              // e atualizados

              // Matrizes que armazenarão os dados dos pontos de acesso
              // a serem adicionados, atualizados e removidos
              $newAccessPoints = [ ];
              $updAccessPoints = [ ];
              $delAccessPoints = [ ];

              // Os IDs dos pontos de acesso mantidos para permitir
              // determinar os pontos de acesso a serem removidos
              $heldAccessPoints = [ ];

              // Determina quais nomes serão mantidos (e atualizados)
              // e os que precisam ser adicionados (novos)
              foreach ($accessPointData AS $accessPoint) {
                if (empty($accessPoint['accesspointnameid'])) {
                  // Código de rede novo
                  $newAccessPoints[] = $accessPoint;
                } else {
                  // Ponto de acesso existente
                  $heldAccessPoints[] =
                    $accessPoint['accesspointnameid']
                  ;
                  $updAccessPoints[]  = $accessPoint;
                }
              }

              // Recupera os pontos de acesso armazenados atualmente
              $accessPoints = AccessPointName::where('mobileoperatorid',
                    $mobileOperatorID
                  )
                ->get([
                    'accesspointnameid'
                  ])
                ->toArray()
              ;
              $oldAccessPoints = [ ];
              foreach ($accessPoints as $accessPoint) {
                $oldAccessPoints[] = $accessPoint['accesspointnameid'];
              }

              // Verifica quais os pontos de acesso estavam na base de
              // dados e precisam ser removidos
              $delAccessPoints =
                array_diff($oldAccessPoints, $heldAccessPoints)
              ;


              // --------------------------------------[ Gravação ]-----
              
              // Grava as informações da operadora de telefonia móvel
              $mobileOperator =
                MobileOperator::findOrFail($mobileOperatorID)
              ;
              $mobileOperator->fill($mobileOperatorData);
              $mobileOperator->save();
              
              // Primeiro apagamos os códigos de rede removidos pelo
              // usuário durante a edição
              foreach ($delNetworkCodes as $networkCodeID) {
                // Apaga cada código de rede marcado para remoção
                $networkCode =
                  MobileNetworkCode::findOrFail($networkCodeID)
                ;
                $networkCode->delete();
              }

              // Agora inserimos os novos códigos de rede
              foreach ($newNetworkCodes as $networkCodeData) {
                // Incluímos o novo código de rede desta operadora de
                // telefonia móvel
                unset($networkCodeData['mobilenetworkcodeid']);
                $networkCode = new MobileNetworkCode();
                $networkCode->fill($networkCodeData);
                $networkCode->mobileoperatorid = $mobileOperatorID;
                $networkCode->save();
              }

              // Por último, modificamos os códigos de rede mantidos
              foreach($updNetworkCodes AS $networkCodeData) {
                // Retira a ID do código de rede
                $networkCodeID =
                  $networkCodeData['mobilenetworkcodeid']
                ;
                unset($networkCodeData['mobilenetworkcodeid']);

                // Grava as informações do código de rede
                $networkCode =
                  MobileNetworkCode::findOrFail($networkCodeID)
                ;
                $networkCode->fill($networkCodeData);
                $networkCode->mobileoperatorid = $mobileOperatorID;
                $networkCode->save();
              }

              // Na sequência apagamos os pontos de acesso removidos
              // pelo usuário durante a edição
              foreach ($delAccessPoints as $accessPointID) {
                // Apaga cada ponto de acesso marcado para remoção
                $accessPoint =
                  AccessPointName::findOrFail($accessPointID)
                ;
                $accessPoint->delete();
              }

              // Agora inserimos os novos pontos de acesso
              foreach ($newAccessPoints as $accessPointData) {
                // Incluímos o novo ponto de acesso desta operadora de
                // telefonia móvel
                unset($accessPointData['accesspointnameid']);
                $accessPoint = new AccessPointName();
                $accessPoint->fill($accessPointData);
                $accessPoint->mobileoperatorid = $mobileOperatorID;
                $accessPoint->save();
              }

              // Por último, modificamos os pontos de acesso mantidos
              foreach($updAccessPoints AS $accessPointData) {
                // Retira a ID do código de rede
                $accessPointID = $accessPointData['accesspointnameid'];
                unset($accessPointData['accesspointnameid']);

                // Grava as informações do ponto de acesso
                $accessPoint =
                  AccessPointName::findOrFail($accessPointID)
                ;
                $accessPoint->fill($accessPointData);
                $accessPoint->mobileoperatorid = $mobileOperatorID;
                $accessPoint->save();
              }

              // Efetiva a transação
              $this->DB->commit();
              
              // Registra o sucesso
              $this->info("Modificada a operadora de telefonia móvel "
                . "'{name}' com sucesso.",
                [ 'name'  => $mobileOperatorData['name'] ]
              );
              
              // Alerta o usuário
              $this->flash("success", "A operadora de telefonia móvel "
                . "<i>'{name}'</i> foi modificada com sucesso.",
                [ 'name'  => $mobileOperatorData['name'] ]
              );
              
              // Registra o evento
              $this->debug("Redirecionando para {routeName}",
                [ 'routeName' => 'ADM\Parameterization\Telephony\MobileOperators' ]
              );
              
              // Redireciona para a página de gerenciamento de
              // operadoras de telefonia móvel
              return $this->redirect($response,
                'ADM\Parameterization\Telephony\MobileOperators')
              ;
            }
          }
          catch(QueryException $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();

            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da operadora de telefonia móvel '{name}'. Erro "
              . "interno no banco de dados: {error}.",
              [ 'name'  => $mobileOperatorData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da operadora de telefonia móvel. Erro "
              . "interno no banco de dados."
            );
          }
          catch(Exception $exception)
          {
            // Reverte (desfaz) a transação
            $this->DB->rollBack();
            
            // Registra o erro
            $this->error("Não foi possível modificar as informações "
              . "da operadora de telefonia móvel '{name}'. Erro "
              . "interno: {error}.",
              [ 'name'  => $mobileOperatorData['name'],
                'error' => $exception->getMessage() ]
            );
            
            // Alerta o usuário
            $this->flashNow("error", "Não foi possível modificar as "
              . "informações da operadora de telefonia móvel. Erro "
              . "interno."
            );
          }
        } else {
          // Adiciona a logomarca original novamente
          $this->validator->setValue('logo', $mobileOperator['logo']);
        }
      } else {
        // Carrega os dados atuais
        $this->validator->setValues($mobileOperator->toArray());
      }
    }
    catch(ModelNotFoundException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível localizar operadora de telefonia "
        . "móvel código {mobileOperatorID}.",
        [ 'mobileOperatorID' => $mobileOperatorID ]
      );
      
      // Alerta o usuário
      $this->flash("error", "Não foi possível localizar esta operadora "
        . "de telefonia móvel."
      );
      
      // Registra o evento
      $this->debug("Redirecionando para {routeName}",
        [ 'routeName' => 'ADM\Parameterization\Telephony\MobileOperators' ]
      );
      
      // Redireciona para a página de gerenciamento de operadoras de
      // telefonia móvel
      return $this->redirect($response,
        'ADM\Parameterization\Telephony\MobileOperators'
      );
    }
    
    // Exibe um formulário para edição de uma operadora de telefonia
    // móvel
    
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ADM\Home')
    );
    $this->breadcrumb->push('Parametrização', '');
    $this->breadcrumb->push('Telefonia', '');
    $this->breadcrumb->push('Operadoras de telefonia móvel',
      $this->path('ADM\Parameterization\Telephony\MobileOperators')
    );
    $this->breadcrumb->push('Editar',
      $this->path('ADM\Parameterization\Telephony\MobileOperators\Edit',
        [ 'mobileOperatorID' => $mobileOperatorID ]
      )
    );
    
    // Registra o acesso
    $this->info("Acesso à edição da operadora de telefonia móvel "
      . "'{name}'.",
      [ 'name' => $mobileOperator['name'] ]
    );
    
    // Renderiza a página
    return $this->render($request, $response,
      'adm/parameterization/telephony/mobileoperators/mobileoperator.twig',
      [ 'formMethod' => 'PUT' ])
    ;
  }
  
  /**
   * Remove a operadora de telefonia móvel.
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
    $this->debug("Processando à remoção da operadora de telefonia "
      . "móvel."
    );
    
    // Recupera o ID
    $mobileOperatorID = $args['mobileOperatorID'];

    try
    {
      // Recupera a operadora de telefonia móvel
      $mobileOperator = MobileOperator::findOrFail($mobileOperatorID);
      
      // Iniciamos a transação
      $this->DB->beginTransaction();
      
      // Agora apaga a operadora de telefonia móvel
      $mobileOperator->deleteCascade();

      // Efetiva a transação
      $this->DB->commit();
      
      // Registra o sucesso
      $this->info("A operadora de telefonia móvel '{name}' foi "
        . "removida com sucesso.",
        [ 'name' => $mobileOperator->name ]
      );
      
      // Informa que a remoção foi realizada com sucesso
      return $response
        ->withHeader('Content-type', 'application/json')
        ->withJson([
            'result' => 'OK',
            'params' => $request->getParams(),
            'message' => "Removida a operadora de telefonia móvel "
              . "{$mobileOperator->name}",
            'data' => "Delete"
          ])
      ;
    }
    catch(ModelNotFoundException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível localizar a operadora de "
        . "telefonia móvel código {mobileOperatorID} para remoção.",
        [ 'mobileOperatorID' => $mobileOperatorID ]
      );
      
      $message = "Não foi possível localizar a operadora de telefonia "
        . "móvel para remoção."
      ;
    }
    catch(QueryException $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações da "
        . "operadora de telefonia móvel ID {id}. Erro interno no "
        . "banco de dados: {error}.",
        [ 'id' => $mobileOperatorID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a operadora de telefonia "
        . "móvel. Erro interno no banco de dados."
      ;
    }
    catch(Exception $exception)
    {
      // Reverte (desfaz) a transação
      $this->DB->rollBack();

      // Registra o erro
      $this->error("Não foi possível remover as informações da "
        . "operadora de telefonia móvel ID {id}. Erro interno: "
        . "{error}.",
        [ 'id' => $mobileOperatorID,
          'error' => $exception->getMessage() ]
      );
      
      $message = "Não foi possível remover a operadora de telefonia "
        . "móvel. Erro interno."
      ;
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
