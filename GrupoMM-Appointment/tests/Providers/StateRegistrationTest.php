<?php
/* StateRegistrationTest
 * ---------------------------------------------------------------------
 * Descrição:
 *
 * Classe responsável pelo teste do validador de inscrição estadual.
 * ---------------------------------------------------------------------
 * Execução:
 *
 * phpunit --verbose --testdox tests/Providers/StateRegistrationTest.php
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

use App\Providers\StateRegistration;
use PHPUnit\Framework\TestCase;

class StateRegistrationTest extends TestCase
{
  /**
   * @dataProvider stateRegistrationProvider
   */
  public function testCanBeValidStateRegistration($stateOfRegistration, $numbers): void
  {
    foreach ($numbers as $number) {
      $this->assertTrue(StateRegistration::isValid($number, $stateOfRegistration));
    }
  }

  public function testCanBeInvalidStateRegistration(): void
  {
    // Exception expeted
    $this->expectException(\InvalidArgumentException::class);

    // Code that generates the exception
    StateRegistration::isValid('123456789-0', 'XX');
  }

  /**
   * @dataProvider stateRegistrationImmuneProvider
   */
  public function testCanBeImmuneStateRegistration($stateOfRegistration, $numbers): void
  {
    foreach ($numbers as $number) {
      $this->assertTrue(StateRegistration::isValid($number, $stateOfRegistration));
    }
  }

  public function stateRegistrationProvider()
  {
    return [
      'Acre (AC)'  => [ 'AC', [
        '01.140.812/690-10',
        '01.091.956/663-13',
        '01.196.046/936-88',
        '01.118.369/045-77'
      ]],
      'Alagoas (AL)' => [ 'AL', [
        '248958151',
        '248546619',
        '248284150',
        '248762877'
      ]],
      'Amapá (AP)' => ['AP', [
        '036933961',
        '034224262',
        '037606360',
        '035510790'
      ]],
      'Amazonas (AM)' => ['AM', [
        '42.969.930-1',
        '49.638.488-0',
        '73.175.054-3',
        '28.988.906-5'
      ]],
      'Bahia (BA)' => ['BA', [
        '858196-21',
        '687589-10',
        '888276-65',
        '063038-64'
      ]],
      'Ceará (CE)' => ['CE', [
        '32243170-0',
        '48033539-7',
        '69737692-3',
        '28476334-9'
      ]],
      'Distrito Federal (DF)' => ['DF', [
        '07835871001-06',
        '07978113001-26',
        '07213869001-13',
        '07896512001-10'
      ]],
      'Espírito Santo (ES)' => ['ES', [
        '95626307-0',
        '04826935-2',
        '94922441-3',
        '70767408-5'
      ]],
      'Goiás (GO)' => ['GO', [
        '11.633.857-1',
        '11.994.947-4',
        '11.021.157-0',
        '15.502.687-9'
      ]],
      'Maranhão (MA)' => ['MA', [
        '12237142-9',
        '12231313-5',
        '12232188-0',
        '12363815-1'
      ]],
      'Mato Grosso (MT)' => ['MT', [
        '0497737873-2',
        '8849365996-3',
        '6168944205-1',
        '5577637184-0'
      ]],
      'Mato Grosso do Sul (MS)' => ['MS', [
        '28068808-3',
        '28484880-8',
        '28032644-0',
        '28625535-9'
      ]],
      'Minas Gerais (MG)' => ['MG', [
        '249.947.947/3140',
        '572.911.338/1095',
        '062.908.129-0052',
        '062.307.904/0081'
      ]],
      'Pará (PA)' => ['PA', [
        '15-111288-6',
        '15-308854-0',
        '15-490724-3',
        '15-223844-1'
      ]],
      'Paraíba (PB)' => ['PB', [
        '23584033-5',
        '50701606-8',
        '69428087-9',
        '39980479-0'
      ]],
      'Paraná (PR)' => ['PR', [
        '493.20728-88',
        '168.12631-08',
        '038.02934-71',
        '385.54862-10'
      ]],
      'Pernambuco (PE)' => ['PE', [
        '4382449-82',
        '5366276-81',
        '2010871-03',
        '2571272-16',
        '18.1.001.0005584-6'
      ]],
      'Piauí (PI)' => ['PI', [
        '45792160-9',
        '03856917-5',
        '89949241-0',
        '75717363-2'
      ]],
      'Rio de Janeiro (RJ)' => ['RJ', [
        '80.757.80-8',
        '95.934.30-7',
        '76.983.30-5',
        '02.708.85-0'
      ]],
      'Rio Grande do Norte (RN)' => ['RN', [
        '20.450.738-3',
        '20.307.898-5',
        '20.203.831-9',
        '20.199.674-0',
        '20.211.430-9'
      ]],
      'Rio Grande do Sul (RS)' => ['RS', [
        '107/0149486',
        '401/7155080',
        '955/5460375',
        '211/5823081'
      ]],
      'Rondônia (RO)' => ['RO', [
        '3523462479727-7',
        '2174687218049-0',
        '3437865280897-3',
        '4799588050984-8',
        '211.228.524'
      ]],
      'Rorâima (RR)' => ['RR', [
        '24006628-1',
        '24001755-6',
        '24003429-0',
        '24001360-3',
        '24008266-8',
        '24006153-6',
        '24007356-2',
        '24005467-4',
        '24004145-5',
        '24001340-7'
      ]],
      'Santa Catarina (SC)' => ['SC', [
        '623.580.721',
        '671.579.762',
        '096.234.725',
        '635.500.310'
      ]],
      'São Paulo (SP)' => ['SP', [
        '904.044.446.562',
        '366.802.933.110',
        '228.240.188.630',
        '571.512.230.487',
        'P-01100424.3/002'
      ]],
      'Sergipe (SE)' => ['SE', [
        '75459432-7',
        '76600875-4',
        '89577853-0',
        '50516127-3'
      ]],
      'Tocantins (TO)' => ['TO', [
        '2803371827-7',
        '9203569801-6',
        '6003784811-1',
        '0503218530-3'
      ]]
    ];
  }
  
  public function stateRegistrationImmuneProvider()
  {
    return [
      'Isento (SP)'  => [ 'SP', [
        'Isento'
      ]]
    ];
  }
}
