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
 * O controlador do gerenciamento das entidades do sistema. Uma entidade
 * pode ser qualquer uma pessoa (física ou jurídica ) do sistema, tais
 * como clientes e fornecedores.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP\Cadastre;

use App\Models\Entity;
use App\Models\Mailing;
use App\Models\Phone;
use Core\Controllers\Controller;
use Core\Controllers\QueryTrait;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Stream;

class EntitiesController
  extends Controller
{
  /**
   * Os métodos para manipular queries.
   */
  use QueryTrait;

  /**
   * Recupera a relação das entidades em formato JSON no padrão dos
   * campos de preenchimento automático. As entidades devem ser de um
   * tipo especificado (clientes ou fornecedores).
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function getAutocompletionData(
    Request $request,
    Response $response
  ): Response
  {
    $this->debug("Relação de entidades para preenchimento automático "
      . "despachada."
    );

    // --------------------------[ Recupera os dados requisitados ]-----

    // Recupera os dados da requisição
    $postParams = $request->getParsedBody();

    // Recupera os dados do contratante
    $contractor   = $this->authorization->getContractor();
    $contractorID = $contractor->id;
    
    // Lida com as informações provenientes da solicitação
    
    // O termo de pesquisa (normalmente o nome ou parte do nome da
    // entidade a ser localizada)
    $searchTerm   = $postParams['searchTerm'];

    // O tipo da entidade que estamos tentando localizar
    $type         = $postParams['type'];
    
    // Determina os limites e parâmetros da consulta
    $start  = 0;
    $length = 1;
    if (isset($postParams['limit'])) {
      $length = $postParams['limit'];
    }
    $entityID = 0;
    if (isset($postParams['entityID'])) {
      $entityID = $postParams['entityID'];
    }
    $ORDER  = 'name ASC';

    // Registra o acesso
    $typeNames = [
      'entity'     => 'empresa(s)',
      'customer'   => 'cliente(s)',
      'payer'      => 'cliente(s) pagante(s)',
      'supplier'   => 'fornecedor(es)',
      'subsidiary' => 'unidade(s)/filial(is)',
      'ownerdata'  => 'dados do proprietário',
      'maildata'   => 'emails do cliente',
      'plate'      => 'veículo(s) por placa'
    ];

    switch ($type) {
      case 'associate':
        $this->debug("Acesso aos dados de associados do cliente "
          . "'{name}'",
          [ 'name' => $searchTerm ]
        );

        break;
      case 'maildata':
        $this->debug("Acesso aos dados de preenchimento automático dos "
          . "endereços de e-mail de um cliente"
        );

        break;
      case 'ownerdata':
        $this->debug("Acesso aos dados de preenchimento automático dos "
          . "dados do proprietário de um veículo"
        );

        break;
      default:
        $this->debug("Acesso aos dados de preenchimento automático de "
          . "{type} que contenha(m) '{name}'",
          [ 'type' => $typeNames[$type],
            'name' => $searchTerm ]
        );
        break;
    }
    
    try
    {
      switch ($type) {
        case 'associate':
          // Carregamos todos os associados ativos do cliente informado
          $sql = "SELECT DISTINCT ON (associate.name)
                         associate.entityID AS value,
                         associate.name
                    FROM erp.affiliations AS association
                   INNER JOIN erp.entities AS associate ON (associate.entityID = association.customerid)
                   WHERE associate.contractorID = {$contractorID}
                     AND association.associationid = {$entityID}
                     AND association.unjoinedAt IS NULL
                   ORDER BY associate.name;";
          $associates = $this->DB->select($sql);

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
                'success' => true,
                'results' => $associates
              ])
          ;

          break;

        case 'plate':
          $this->debug("Autocomplete por placa '{placa}'", ['placa' => $searchTerm]);

          $sql = "SELECT DISTINCT ON (v.plate)
                         v.plate AS id,
                         v.plate,
                         vm.name AS vehiclemodel,
                         e.name AS customername,
                         e.entityid AS customerid
                    FROM erp.vehicles v
              INNER JOIN erp.entities e ON e.entityid = v.customerid
              INNER JOIN erp.vehiclemodels vm ON vm.vehiclemodelid = v.vehiclemodelid
                   WHERE v.contractorid = ?
                     AND public.unaccented(v.plate) ILIKE public.unaccented(?)
                     AND (v.trackerprincipalid IS NOT NULL OR v.trackercontingencyid IS NOT NULL)
                ORDER BY v.plate;";

          $vehicles = $this->DB->select($sql, [ $contractorID, "%{$searchTerm}%" ]);

          return $response
            ->withHeader('Content-type', 'application/json')
            ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => "Veículos com placa que contém '{$searchTerm}'",
              'data' => $vehicles
        ]);

        case 'maildata':
          // Localiza os dados dos endereços de e-mail na base de dados.

          // Recupera a ID da entidade e unidade/filial/titular/associado
          // do cliente cujos dados foram enviados na solicitação
          $customerID = isset($postParams['customerID'])
            ? $postParams['customerID']
            : 0
          ;
          $subsidiaryID = isset($postParams['subsidiaryID'])
            ? $postParams['subsidiaryID']
            : 0
          ;

          // Recupera a relação de e-mails com base nas informações
          // enviadas na solicitação
          $entities = Mailing::where('entityid', $customerID)
            ->where('subsidiaryid', $subsidiaryID)
            ->get([
                'entityid',
                'subsidiaryid',
                'mailingid',
                'email'
              ])
          ;

          if ( $entities->isEmpty() ) {
            $entities = [];
          } else {
            // Convertemos para matriz
            $entities = $entities
              ->toArray()
            ;
          }

          break;
        case 'ownerdata':
          // Localiza os dados do proprietário de um veículo na base de
          // dados. Neste caso, aceita clientes que estejam bloqueados,
          // já que esta informação também é usada para renderizar as
          // informações no sistema

          // Recupera a ID da entidade e unidade/filial/titular/associado
          // do proprietário cujos dados foram enviados na solicitação
          $customerID = isset($postParams['customerID'])
            ? $postParams['customerID']
            : 0
          ;
          $subsidiaryID = isset($postParams['subsidiaryID'])
            ? $postParams['subsidiaryID']
            : 0
          ;

          // Recupera a entidade proprietária com base nas informações
          // enviadas na solicitação
          $entities = Entity::join("subsidiaries", "entities.entityid",
                '=', "subsidiaries.entityid"
              )
            ->join("entitiestypes", "entities.entitytypeid",
                '=', "entitiestypes.entitytypeid"
              )
            ->join("documenttypes", "subsidiaries.regionaldocumenttype",
                '=', "documenttypes.documenttypeid"
              )
            ->join("cities", "subsidiaries.cityid", '=',
                "cities.cityid"
              )
            ->where("entities.contractorid", $contractorID)
            ->where("entities.entityid", $customerID)
            ->where("entities.customer", "true")
            ->where("entities.deleted", "false")
            ->where("subsidiaries.subsidiaryid", $subsidiaryID)
            ->where("subsidiaries.deleted", "false")
            ->get([
                'entities.name AS entityname',
                'entities.entitytypeid',
                'entitiestypes.name AS entitytypename',
                'entitiestypes.juridicalperson',
                'entitiestypes.cooperative',
                'subsidiaries.subsidiaryid AS subsidiaryid',
                'subsidiaries.name AS subsidiaryname',
                'subsidiaries.regionaldocumenttype',
                'documenttypes.name AS regionaldocumentname',
                'subsidiaries.regionaldocumentnumber',
                'subsidiaries.regionaldocumentstate',
                'subsidiaries.nationalregister',
                'subsidiaries.address',
                'subsidiaries.streetnumber',
                'subsidiaries.complement',
                'subsidiaries.district',
                'subsidiaries.postalcode',
                'subsidiaries.cityid',
                'cities.name AS cityname',
                'cities.state AS state'
              ])
          ;
          if ( $entities->isEmpty() ) {
            throw new ModelNotFoundException("Não temos nenhum cliente "
              . "do contratante {$contractor->name} com o código "
              . "{$customerID} e unidade/filial/titular/associado "
              . "código {$subsidiaryID} cadastrado"
            );
          }

          // Convertemos para matriz
          $entities = $entities
            ->first()
            ->toArray()
          ;

          // Agora anexamos a informação do número de telefone principal
          $phones = Phone::join('phonetypes',
                'phones.phonetypeid', '=', 'phonetypes.phonetypeid'
              )
            ->where('subsidiaryid', $subsidiaryID)
            ->orderBy('phones.phoneid')
            ->get([
                'phones.phonetypeid',
                'phonetypes.name as phonetypename',
                'phones.phonenumber'
              ])
          ;
          if ( $phones->isEmpty() ) {
            // Criamos os dados de telefone em branco
            $entities['phonetypeid'] = 1;
            $entities['phonetypename'] = 'Fixo';
            $entities['phonenumber'] = '';
          } else {
            $phone = $phones
              ->first()
            ;
            $entities['phonetypeid'] = $phone->phonetypeid;
            $entities['phonetypename'] = $phone->phonetypename;
            $entities['phonenumber'] = $phone->phonenumber;
          }

          break;
        case 'subsidiary':
          // Localiza as unidades/filiais de uma entidade na base de
          // dados. Não permite localizar entidades que estejam
          // bloqueadas

          // Recuperamos o ID da empresa à qual pertencem estas
          // unidades/filiais
          $entityID = isset($postParams['entityID'])
            ? $postParams['entityID']
            : 0
          ;

          // Recuperamos a informação de que devemos incluir os bloqueados
          $includeBlocked = false;
          if (isset($postParams['includeBlocked'])) {
            $includeBlocked = $postParams['includeBlocked']==='true'
              ? true
              : false
            ;
          }

          // Monta a consulta
          $subsidiariesQry = Entity::join("subsidiaries", "entities.entityid",
                '=', "subsidiaries.entityid"
              )
            ->join("entitiestypes", "entities.entitytypeid",
                '=', "entitiestypes.entitytypeid"
              )
            ->join("cities", "subsidiaries.cityid", '=',
                "cities.cityid"
              )
            ->where("entities.contractorid", $contractorID)
            ->where("entities.entityid", $entityID)
            ->where("entities.deleted", "false")
            ->where("subsidiaries.deleted", "false")
            ->whereRaw("public.unaccented(subsidiaries.name) "
                . "ILIKE public.unaccented('%{$searchTerm}%')"
              )
          ;

          if ($includeBlocked == false) {
            $subsidiariesQry
              ->where("entities.blocked", "false")
              ->where("subsidiaries.blocked", "false")
            ;
          }

          $entities = $subsidiariesQry
            ->skip($start)
            ->take($length)
            ->orderByRaw($ORDER)
            ->get([
                'subsidiaries.subsidiaryid AS id',
                'subsidiaries.name',
                'subsidiaries.nationalregister',
                'entitiestypes.name AS entitytypename',
                'entitiestypes.cooperative',
                'entitiestypes.juridicalperson',
                'cities.name AS city',
                'cities.state AS state'
              ])
          ;

          if ( $entities->isEmpty() ) {
            $entities = [];
          } else {
            // Convertemos para matriz
            $entities = $entities
              ->toArray()
            ;
          }

          break;
        default:
          // Localiza as entidades na base de dados (clientes e/ou
          // fornecedores) e a sua primeira unidade/filial. Não permite
          // localizar entidades que estejam bloqueadas e/ou deletadas

          // Conforme o tipo de entidade que foi requisitada, realiza as
          // devidas filtragens
          switch ($type) {
            case 'customer':
            case 'supplier':
            case 'contractor':
              $WHERE = "entities.{$type} = true";

              break;
            case 'payer':
              $WHERE = "entities.customer = true AND (SELECT count(*) FROM erp.contracts WHERE customerID = entities.entityID AND enddate IS NULL) > 0";

              break;
            default:
              $WHERE = "((entities.contractor = true) OR (entities.customer = true) "
                . "OR (entities.supplier = true))"
              ;
              $this->debug("Usou o padrão pois type contém {type}",
                [ 'type' => $type ]
              );
          }

          // Conforme o nível de permissão do usuário, realiza as devidas
          // filtragens
          $level = $this->authorization->getUser()->groupid;
          switch ($level) {
            case 1:
            case 2:
              // Administrador ERP e Administrador Contratante lidam com
              // todas as entidades do respectivo contratante, então não
              // precisa aplicar filtros
              break;
            case 3:
            case 4:
              // Atendente e Operador não podem adicionar itens ao próprio
              // contratante, apenas as clientes e fornecedores.
              $WHERE .= " AND contractor = false";

              break;
            case 5:
              // Técnicos podem pertencer ao contratante e/ou a um
              // prestador de serviços. Quando pertencerem à um prestador
              // de serviços então somente podem lidar com a própria
              // empresa para a qual trabalham
              if ( $this->authorization->getUser()->entityid !==
                   $contractorID) {
                $WHERE .= " AND entities.entityid = "
                  . $this->authorization->getUser()->entityid
                ;
              }
              
              break;
            default:
              // Cliente, então precisa aplicar filtro pela empresa do
              // próprio cliente
              $WHERE .= " AND entities.entityid = "
                . $this->authorization->getUser()->entityid
              ;
              
              break;
          }

          // Recuperamos a informação de que temos de incluir os nomes
          // das unidades/filiais no resultado
          $detailed = (isset($postParams['detailed']))
            ? $postParams['detailed']
            : false
          ;

          // Recuperamos a informação de que devemos incluir um registro
          // que apenas a empresa (a unidade/filial fica zerada)
          $includeAnyRegister = (isset($postParams['notIncludeAnyRegister']))
            ? !($postParams['notIncludeAnyRegister'])
            : true
          ;

          // Recuperamos a informação de que devemos exibir apenas os
          // clientes
          $onlyCustomers = array_key_exists('onlyCustomers', $postParams)
            ? $postParams['onlyCustomers'] === 'true'
            : false
          ;

          if ($detailed) {
            $DISTINCT_CLAUSE = "";
            $FIND_CLAUSE = "("
              . "public.unaccented(entities.name) ILIKE public.unaccented('%{$searchTerm}%')"
              . " OR "
              . "public.unaccented(entities.tradingname) ILIKE public.unaccented('%{$searchTerm}%')"
              . " OR "
              . "public.unaccented(subsidiaries.name) ILIKE public.unaccented('%{$searchTerm}%')"
              . ")"
            ;
          } else {
            $DISTINCT_CLAUSE = "DISTINCT ON (entities.name)";
            $FIND_CLAUSE = "("
              . "public.unaccented(entities.name) ILIKE public.unaccented('%{$searchTerm}%')"
              . " OR "
              . "public.unaccented(entities.tradingname) ILIKE public.unaccented('%{$searchTerm}%')"
              . ")"
            ;
          }

          $ADDTIONAL_FIND_CLAUSE = '';
          if ($onlyCustomers == true) {
            $ADDTIONAL_FIND_CLAUSE = ""
              . "AND (SELECT count(*) "
              .        "FROM erp.contracts "
              .       "WHERE contracts.customerID = entities.entityID) > 0"
            ;
          }

          $contractorFilter = ($type === 'contractor')
            ? "entities.contractor = true"
            : "entities.contractorid = {$contractorID}"
          ;

          // Monta a consulta
          $sql = "SELECT {$DISTINCT_CLAUSE}
                         entities.entityid AS id,
                         entities.name,
                         entities.tradingname,
                         entities.customer,
                         entities.supplier,
                         entities.serviceProvider,
                         CASE
                           WHEN entities.serviceProvider THEN 'Prestador de Serviços'
                           WHEN entities.supplier THEN 'Fornecedor'
                           WHEN entities.customer AND entitiestypes.cooperative THEN entitiestypes.name
                           WHEN entities.customer AND NOT entitiestypes.cooperative THEN 'Cliente'
                           ELSE 'Contratante'
                         END AS type,
                         entities.entitytypeid,
                         entitiestypes.name AS entitytypename,
                         entitiestypes.cooperative,
                         entitiestypes.juridicalperson,
                         (SELECT COUNT(*) FROM erp.subsidiaries AS S WHERE S.entityID = entities.entityID) AS items,
                         subsidiaries.subsidiaryid,
                         subsidiaries.headoffice,
                         subsidiaries.name AS subsidiaryname
                    FROM erp.entities
                   INNER JOIN erp.entitiestypes USING (entitytypeid)
                   INNER JOIN erp.subsidiaries USING (entityid)
                   WHERE {$contractorFilter}
                     AND {$WHERE}
                     AND {$FIND_CLAUSE} $ADDTIONAL_FIND_CLAUSE
                   ORDER BY entities.name, subsidiaries.subsidiaryid ASC;";
          $entities = $this->DB->select($sql);

          if ( count($entities) > 0 ) {
            if ($detailed) {
              // Acrescentamos um campo para todas as unidades/filiais
              // e/ou titulares e/ou associados
              $entitiesData = [];
              $lastEntityID = 0;
              foreach ($entities as $entity) {
                if ($lastEntityID !== $entity->id) {
                  if ($includeAnyRegister AND ($entity->items > 1)) {
                    // Mudamos de entidade, então acrescentamos um novo
                    // registro para todas as unidades desta entidade
                    $entitiesData[] = [
                      'id' => $entity->id,
                      'name' => $entity->name,
                      'tradingname' => $entity->tradingname,
                      'entitytypename' => $entity->entitytypename,
                      'customer' => $entity->customer,
                      'supplier' => $entity->supplier,
                      'serviceprovider' => $entity->serviceprovider,
                      'type' => $entity->type,
                      'entitytypeid' => 0,
                      'cooperative' => $entity->cooperative,
                      'juridicalperson' => $entity->juridicalperson,
                      'items' => $entity->items,
                      'subsidiaryid' => 0,
                      'headoffice' => false,
                      'subsidiaryname' => ($entity->cooperative)
                        ? 'Todos associados e/ou unidades'
                        : (($entity->juridicalperson)
                          ? 'Todas unidades/filiais'
                          : 'Todos os titulares/dependentes')
                    ];
                  }

                  $lastEntityID = $entity->id;
                }

                // Acrescenta a respectiva unidade
                $entitiesData[] = [
                  'id' => $entity->id,
                  'name' => $entity->name,
                  'tradingname' => $entity->tradingname,
                  'entitytypename' => $entity->entitytypename,
                  'customer' => $entity->customer,
                  'supplier' => $entity->supplier,
                  'serviceprovider' => $entity->serviceprovider,
                  'type' => $entity->type,
                  'entitytypeid' => $entity->entitytypeid,
                  'cooperative' => $entity->cooperative,
                  'juridicalperson' => $entity->juridicalperson,
                  'items' => $entity->items,
                  'subsidiaryid' => $entity->subsidiaryid,
                  'headoffice' => $entity->headoffice,
                  'subsidiaryname' => $entity->subsidiaryname
                ];
              }

              $entities = $entitiesData;
            }
          }

          break;
      }
      
      if ($type === 'ownerdata') {
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => ucfirst($typeNames[$type]) . " cuja ID é "
                . "'{$customerID}'",
              'data' => $entities
            ])
        ;
      } else {
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'OK',
              'params' => $request->getQueryParams(),
              'message' => ucfirst($typeNames[$type]) . " cujo nome "
                . "contém '{$searchTerm}'",
              'data' => $entities
            ])
        ;
      }
    }
    catch(QueryException $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno no "
        . "banco de dados: {error}.",
        [ 'module' => $typeNames[$type],
          'error'  => $exception->getMessage() ]
      );
    }
    catch(Exception $exception)
    {
      // Registra o erro
      $this->error("Não foi possível recuperar as informações de "
        . "{module} para preenchimento automático. Erro interno: "
        . "{error}.",
        [ 'module' => $typeNames[$type],
          'error'  => $exception->getMessage() ]
      );
    }
    
    // Retorna o erro
    switch ($type) {
      case 'associate':
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'NOK',
              'params' => $request->getQueryParams(),
              'message' => "Não foi possível localizar os associados "
                . " do cliente '{$searchTerm}'",
              'data' => []
            ])
        ;

        break;
      case 'maildata':
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'NOK',
              'params' => $request->getQueryParams(),
              'message' => "Não foi possível localizar os dados dos "
                . "endereços de e-mail que contém '{$searchTerm}'",
              'data' => []
            ])
        ;

        break;
      case 'ownerdata':
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'NOK',
              'params' => $request->getQueryParams(),
              'message' => "Não foi possível localizar os "
                . $typeNames[$type] . " cujo nome contém '{$searchTerm}'",
              'data' => []
            ])
        ;

        break;
      default:
        return $response
          ->withHeader('Content-type', 'application/json')
          ->withJson([
              'result' => 'NOK',
              'params' => $request->getQueryParams(),
              'message' => "Não foi possível localizar os dados de "
                . "preenchimento automático de " . $typeNames[$type]
                . " cujo nome contém '{$searchTerm}'",
              'data' => []
            ])
        ;

        break;
    }
  }

  /**
   * Recupera a logomarca do contratante.
   *
   * @param string $contractorUUID
   *   A identificação do contratante
   * @param string $type
   *   O tipo da imagem:
   *     N: para normal
   *     I: para invertida
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  protected function getLogo(
    string $contractorUUID,
    string $type,
    Response $response
  ): Response
  {
    // Recupera o local de armazenamento das imagens
    $logoDirectory = $this->container['settings']['storage']['images'];
    $searchText    = $logoDirectory . DIRECTORY_SEPARATOR
      . "Logo_{$contractorUUID}_{$type}.*"
    ;

    $files = glob($searchText);
    if (count($files) > 0) {
      // Determina informações do arquivo
      $imageFilename = $files[0];
      $imageData = getimagesize($imageFilename);
      if ( !empty($imageData[2]) ) {
        // Recupera o tipo mime do arquivo
        $mimeType = image_type_to_mime_type($imageData[2]);
      } else {
        // Atribui um tipo mime inválido
        $mimeType = "application/octet-stream";
      }
    } else {
      // O arquivo não foi localizado, então retorna um arquivo vazio
      // Determina informações do arquivo
      $resourcesDir = $this->app->getPublicDir()
        . DIRECTORY_SEPARATOR . 'resources'
      ;
      $imageFilename = $resourcesDir . DIRECTORY_SEPARATOR
        . "unknown.png"
      ;
      $imageData = getimagesize($imageFilename);
      if ( !empty($imageData[2]) ) {
        // Recupera o tipo mime do arquivo
        $mimeType = image_type_to_mime_type($imageData[2]);
      } else {
        // Atribui um tipo mime inválido
        $mimeType = "application/octet-stream";
      }
    }

    // Retorna o conteúdo do arquivo
    $size = filesize($imageFilename);
    $fileHandle = fopen($imageFilename, 'rb');
    $stream = new Stream($fileHandle);

    // Determina o tempo de cache para 7 dias
    $maxAge = 60*60*24*7;

    // Retorna a imagem gerada
    return $response
      ->withBody($stream)
      ->withHeader('Content-Type', $mimeType)
      ->withHeader('Content-Length', $size)
      ->withHeader('Content-Disposition', "name='{$imageFilename}'")
      ->withHeader('Cache-Control', "max-age={$maxAge}")
      ->withHeader('Expires', gmdate(DATE_RFC1123, time() + $maxAge))
      ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s',
          filemtime($imageFilename)) . 'GMT'
        );
  }

  /**
   * Obtém a logomarca do contratante para locais onde o fundo é normal,
   * ou seja, fundo claro.
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
  public function getNormalLogo(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Recupera da configuração o UUID do contratante. Verificamos
    // primeiramente se temos uma UUID fornecida com os argumentos
    $contractorUUID = $args['UUID'];
    
    return $this->getLogo($contractorUUID, 'N', $response);
  }

  /**
   * Obtém a logomarca do contratante para locais onde o fundo é
   * invertido, ou seja, fundo escuro.
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
  public function getInvertedLogo(
    Request $request,
    Response $response,
    array $args
  ): Response
  {
    // Recupera da configuração o UUID do contratante. Verificamos
    // primeiramente se temos uma UUID fornecida com os argumentos
    $contractorUUID = $args['UUID'];
    
    return $this->getLogo($contractorUUID, 'I', $response);
  }
}
