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
 * O controlador da página inicial do aplicativo de ERP de controle de
 * rastreadores.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

namespace App\Controllers\ERP;

use App\Models\Entity as Customer;
use App\Models\Indicator;
use App\Models\Month;
use App\Models\Vehicle;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Core\Controllers\Controller;
use Slim\Http\Request;
use Slim\Http\Response;

class MainController
  extends Controller
{
  /**
   * Obtém o nome e o sobrenome à partir de um nome completo.
   *
   * @param string $name
   *   O nome completo
   *
   * @return string
   */
  private function getNameAndSurname(string $name): string
  {
    // As palavras que pertencem às exceções à regra de separação. Como
    // sabemos, alguns conectivos e preposições da língua portuguesa e de
    // outras línguas jamais são utilizadas como sobrenome. Essa lista de
    // exceções pode ser adaptada, expandida ou mesmo reduzida conforme as
    // necessidades de cada caso.
    $WORD_EXCEPTIONS = [
      'de', 'di', 'do', 'da', 'dos', 'das', 'dello', 'della', 'dalla',
      'dal', 'del', 'e', 'em', 'na', 'no', 'nas', 'nos', 'van', 'von',
      'y', 'por', 'para'
    ];

    // Primeiramente dividimos o nome em partes, de forma a permitir
    // trabalhar com cada palavra separadamente
    $nameParts = mb_split("\s", $name);
    $result = '';
    if (count($nameParts) > 1) {
      $add = 0;
      for($i = 0; $i < count($nameParts); ++$i) {
        // Copiamos a parte do nome atual
        $result .= $nameParts[$i] . ' ';

        // Verificamos cada parte do nome contra a lista de exceções. Caso
        // haja correspondência, a parte do nome em questão é acrescentada
        // mas não considerada
        if (!in_array(mb_strtolower($nameParts[$i]), $WORD_EXCEPTIONS)) {
          // Não faz parte dos conectores que são exceção, então
          // incrementa o contador de nomes adicionados
          $add++;
        }

        // Interrompemos se conseguirmos duas partes (nome e sobrenome)
        if ($add === 2) {
          break;
        }
      }
    } else {
      $result = $name;
    }

    return trim($result);
  }

  /**
   * Exibe a página inicial do erp.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function home(Request $request, Response $response)
  {
    // Recupera a informação do contratante
    $contractor = $this->authorization->getContractor();

    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );

    // Registra o acesso
    $this->debug("Acesso à página inicial.");

    $days = [
      [ 'number' => 1, 'name' => 'Seg' ],
      [ 'number' => 2, 'name' => 'Ter' ],
      [ 'number' => 3, 'name' => 'Qua' ],
      [ 'number' => 4, 'name' => 'Qui' ],
      [ 'number' => 5, 'name' => 'Sex' ],
      [ 'number' => 6, 'name' => 'Sáb' ],
      [ 'number' => 0, 'name' => 'Dom' ],
    ];
    $today = Carbon::today()->locale('pt_BR');
    $dayNumber = $today->format('N');

    $fullname = $this->authorization->getUser()->name;
    $name = $this->getNameAndSurname($fullname);

    // ============================================[ Estatísticas ]=====
    
    // ------------------------------------------------[ Clientes ]-----
    $activeCustomers = Customer::join("entitiestypes", "entities.entitytypeid",
          '=', "entitiestypes.entitytypeid"
        )
      ->join('contracts', 'entities.entityid', '=',
          'contracts.customerid'
        )
      ->where('entities.contractorid', $contractor->id)
      ->where('entitiestypes.cooperative', 'false')
      ->where('entities.customer', 'true')
      ->whereNull('contracts.enddate')
      ->count()
    ;
    $inactiveCustomers = Customer::join("entitiestypes", "entities.entitytypeid",
          '=', "entitiestypes.entitytypeid"
        )
      ->join('contracts', 'entities.entityid', '=',
          'contracts.customerid'
        )
      ->where('entities.contractorid', $contractor->id)
      ->where('entitiestypes.cooperative', 'false')
      ->where('entities.customer', 'true')
      ->whereRaw('('
          . 'SELECT count(*)'
          . '  FROM erp.contracts AS entityContract'
          . ' WHERE entityContract.customerID = entities.entityID'
          . '   AND entityContract.endDate IS NULL'
          . ') = 0')
      ->count()
    ;

    // ---------------------------------------------[ Associações ]-----
    $activeCooperatives = Customer::join("entitiestypes", "entities.entitytypeid",
          '=', "entitiestypes.entitytypeid"
        )
      ->where('entities.contractorid', $contractor->id)
      ->where('entitiestypes.cooperative', 'true')
      ->where('entities.customer', 'true')
      ->where('entities.blocked', 'false')
      ->count()
    ;

    $sql = "WITH associations AS (
                 SELECT association.entityID AS id,
                        association.tradingname as name
                   FROM erp.entities AS association
                  INNER JOIN erp.entitiesTypes AS associationType USING (entityTypeID)
                  WHERE association.contractorid = {$contractor->id}
                    AND association.customer = true
                    AND associationType.cooperative = TRUE
                    AND association.deleted = false
                    AND association.blocked = false
                  ORDER BY association.tradingname
               )
           SELECT association.name,
                  count(*) AS count
             FROM associations AS association
            INNER JOIN erp.affiliations ON (association.id = affiliations.associationID)
            WHERE affiliations.unjoinedAt IS NULL
            GROUP BY association.name
            ORDER BY association.name;"
    ;
    $activeAffiliateds = $this->DB->select($sql);
    if ( count($activeAffiliateds) === 0 ) {
      $activeAffiliateds = [];
    }

    // ------------------------------------------------[ Veículos ]-----
    $sql = "SELECT count(*) AS value
              FROM erp.equipments AS equipment
             INNER JOIN erp.entities AS customer ON (equipment.customerPayerID = customer.entityID)
             INNER JOIN erp.entitiesTypes AS customerType USING (entityTypeID)
             WHERE equipment.contractorID = {$contractor->id}
               AND customerType.cooperative = FALSE;"
    ;
    $activeVehicles = $this->DB->select($sql);
    if ( count($activeVehicles) === 0 ) {
      $activeVehicles = [];
    } else {
      $activeVehicles = $activeVehicles[0]->value;
    }

    $sql = "SELECT payer.tradingname as name,
                   count(*) AS count
              FROM erp.equipments AS equipment
             INNER JOIN erp.entities AS payer ON (equipment.customerPayerID = payer.entityID)
             INNER JOIN erp.entitiesTypes AS payerType USING (entityTypeID)
             WHERE equipment.contractorid = {$contractor->id}
               AND payer.customer = true
               AND payerType.cooperative = true
             GROUP BY payer.tradingname
             ORDER BY payer.tradingname;"
    ;
    $activeAffiliatedVehicles = $this->DB->select($sql);
    if ( count($activeAffiliatedVehicles) === 0 ) {
      $activeAffiliatedVehicles = [];
    }

    $inactiveVehicles = Vehicle::join("entities", "vehicles.customerid",
          '=', "entities.entityid"
        )
      ->join("entitiestypes", "entities.entitytypeid",
          '=', "entitiestypes.entitytypeid"
        )
      ->where('vehicles.contractorid', $contractor->id)
      ->where('entitiestypes.cooperative', 'false')
      ->where('entities.customer', 'true')
      ->where('entities.blocked', 'false')
      ->where('vehicles.blocked', 'true')
      ->count()
    ;

    // ---------------------------------[ Indicadores Financeiros ]-----
    
    $indicators = Indicator::join('accumulatedvalues',
          'indicators.indicatorid', '=', 'accumulatedvalues.indicatorid'
        )
      ->whereRaw("TO_DATE(year || LPAD(month::text, 2, '0') || '01', 'YYYYMMDD') >= (date_trunc('month', current_date) - INTERVAL '12 months')")
      ->orderByRaw('name, year, month')
      ->get([
          'indicators.name',
          'accumulatedvalues.month',
          'accumulatedvalues.year',
          'accumulatedvalues.value'
        ])
    ;

    // Separamos os indicadores financeiros
    $values = [];
    foreach ($indicators AS $indicator) {
      $values[$indicator->name][] = $indicator->value;
    }

    // Obtém os nomes dos últimos 12 meses
    $start = $today->copy();
    // Ajustamos o dia para primeiro
    $start->setDate($start->format('Y'), $start->format('n'), 1);
    // Ajustamos a hora para meia-noite
    $start->setTime(0, 0, 0);
    $start->sub(new CarbonInterval('P12M'));
    $interval = new CarbonInterval('P1M');
    $recurrences = 12;

    // Recupera as informações de meses do ano
    $months = Month::get();
    $labels = [];

    foreach (new CarbonPeriod($start, $interval, $recurrences) as $date) {
      $labels[] = $months[ intval($date->format('m')) - 1 ]['short']
        . '/' . $date->format('y')
      ;
    }

    // Renderiza a página
    return $this->render($request, $response,
        'erp/home.twig',
        [ 'name' => $name,
          'days' => $days,
          'today' => $dayNumber,
          'indicators' => [
            'labels' => $labels,
            'dataset' => $values
          ],
          'summary' => [
            'activeCustomers' => $activeCustomers,
            'inactiveCustomers' => $inactiveCustomers,
            'activeCooperatives' => $activeCooperatives,
            'activeAffiliateds' => $activeAffiliateds,
            'activeVehicles' => $activeVehicles,
            'inactiveVehicles' => $inactiveVehicles,
            'activeAffiliatedVehicles' => $activeAffiliatedVehicles,
          ]
        ]
      )
    ;
  }
  
  /**
   * Exibe a página de apresentação do sistema de ERP.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function about(Request $request, Response $response)
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Sobre',
      $this->path('ERP\About')
    );

    // Registra o acesso
    $this->debug("Acesso à página sobre.");
    
    // Renderiza a página
    return $this->render($request, $response, 'erp/about.twig');
  }
  
  /**
   * Exibe a página de controle de privacidade.
   * 
   * @param Request $request
   *   A requisição HTTP
   * @param Response $response
   *   A resposta HTTP
   *
   * @return Response $response
   */
  public function privacity(Request $request, Response $response)
  {
    // Adiciona as informações da trilha de navegação
    $this->breadcrumb->push('Início',
      $this->path('ERP\Home')
    );
    $this->breadcrumb->push('Política de privacidade',
      $this->path('ERP\Privacity')
    );

    // Registra o acesso
    $this->debug("Acesso à página de controle de privacidade.");
    
    // Renderiza a página
    return $this->render($request, $response, 'erp/privacity.twig');
  }
}
