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
 * As configurações dos controladores existentes.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

return [
  // ======================================================[ Site ]=====
  // Os controladores do site principal
  'home.controller' => App\Controllers\MainController::class,

  // ===========================================[ Área do cliente ]=====
  // Os controladores da área do cliente
  
  // ------------------------------------------------------[ Base ]-----
  'usr.home.controller' => App\Controllers\USR\MainController::class,
  'usr.auth.controller' => App\Controllers\USR\AuthController::class,
  'usr.billet.controller' => App\Controllers\USR\BankingBilletController::class,
  
  // =======================================================[ ADM ]=====
  // Os controladores da administração do sistema de ERP
  
  // ------------------------------------------------------[ Base ]-----
  'adm.home.controller' => App\Controllers\ADM\MainController::class,
  'adm.auth.controller' => App\Controllers\ADM\AuthController::class,
  
  // --------------------------------------------[ Parametrização ]-----
  'adm.parameterization.cadastral.cities.controller' => App\Controllers\ADM\Parameterization\Cadastral\CitiesController::class,
  'adm.parameterization.cadastral.documenttypes.controller' => App\Controllers\ADM\Parameterization\Cadastral\DocumentTypesController::class,
  'adm.parameterization.cadastral.maritalstatus.controller' => App\Controllers\ADM\Parameterization\Cadastral\MaritalStatusController::class,
  'adm.parameterization.cadastral.genders.controller' => App\Controllers\ADM\Parameterization\Cadastral\GendersController::class,
  'adm.parameterization.cadastral.measuretypes.controller' => App\Controllers\ADM\Parameterization\Cadastral\MeasureTypesController::class,
  'adm.parameterization.cadastral.fieldtypes.controller' => App\Controllers\ADM\Parameterization\Cadastral\FieldTypesController::class,
  'adm.parameterization.vehicles.types.controller' => App\Controllers\ADM\Parameterization\Vehicles\VehicleTypesController::class,
  'adm.parameterization.vehicles.subtypes.controller' => App\Controllers\ADM\Parameterization\Vehicles\VehicleSubtypesController::class,
  'adm.parameterization.vehicles.fuels.controller' => App\Controllers\ADM\Parameterization\Vehicles\FuelTypesController::class,
  'adm.parameterization.vehicles.colors.controller' => App\Controllers\ADM\Parameterization\Vehicles\VehicleColorsController::class,
  'adm.parameterization.devices.accessories.types.controller' => App\Controllers\ADM\Parameterization\Devices\AccessoryTypesController::class,
  'adm.parameterization.devices.features.controller' => App\Controllers\ADM\Parameterization\Devices\FeaturesController::class,
  'adm.parameterization.devices.brands.controller' => App\Controllers\ADM\Parameterization\Devices\EquipmentBrandsController::class,
  'adm.parameterization.devices.models.controller' => App\Controllers\ADM\Parameterization\Devices\EquipmentModelsController::class,
  'adm.parameterization.telephony.phonetypes.controller' => App\Controllers\ADM\Parameterization\Telephony\PhoneTypesController::class,
  'adm.parameterization.telephony.mobileoperators.controller' => App\Controllers\ADM\Parameterization\Telephony\MobileOperatorsController::class,
  'adm.parameterization.telephony.simcardtypes.controller' => App\Controllers\ADM\Parameterization\Telephony\SimCardTypesController::class,
  'adm.parameterization.ownershiptypes.controller' => App\Controllers\ADM\Parameterization\OwnershipTypesController::class,
  'adm.parameterization.holidays.controller' => App\Controllers\ADM\Parameterization\HolidaysController::class,
  'adm.parameterization.permissions.controller' => App\Controllers\ADM\Parameterization\PermissionsController::class,
  'adm.parameterization.systemactions.controller' => App\Controllers\ADM\Parameterization\SystemActionsController::class,
  
  // ------------------------------------------------[ Financeiro ]-----
  'adm.financial.banks.controller' => App\Controllers\ADM\Financial\BanksController::class,
  'adm.financial.indicators.controller' => App\Controllers\ADM\Financial\IndicatorsController::class,
  'adm.financial.indicators.accumulatedvalues.controller' => App\Controllers\ADM\Financial\AccumulatedValuesController::class,
  
  // --------------------------------------------------[ Cadastro ]-----
  'adm.cadastre.contractors.controller' => App\Controllers\ADM\Cadastre\ContractorsController::class,
  'adm.cadastre.monitors.controller' => App\Controllers\ADM\Cadastre\MonitorsController::class,
  'adm.cadastre.users.controller' => App\Controllers\ADM\Cadastre\UsersController::class,

  // =======================================================[ ERP ]=====
  // Os controladores do sistema de ERP
  
  // ------------------------------------------------------[ Base ]-----
  'erp.home.controller' => App\Controllers\ERP\MainController::class,
  'erp.auth.controller' => App\Controllers\ERP\AuthController::class,
  
  // --------------------------------------------[ Parametrização ]-----
  'erp.parameterization.cities.controller' => App\Controllers\ERP\Parameterization\CitiesController::class,
  'erp.parameterization.mobileoperators.controller' => App\Controllers\ERP\Parameterization\MobileOperatorsController::class,
  'erp.parameterization.holidays.controller' => App\Controllers\ERP\Parameterization\HolidaysController::class,
  'erp.parameterization.financial.installmenttypes.controller' => App\Controllers\ERP\Parameterization\Financial\InstallmentTypesController::class,
  'erp.parameterization.financial.billingtypes.controller' => App\Controllers\ERP\Parameterization\Financial\BillingTypesController::class,
  'erp.parameterization.financial.duedays.controller' => App\Controllers\ERP\Parameterization\Financial\DueDaysController::class,
  'erp.parameterization.financial.contracttypes.controller' => App\Controllers\ERP\Parameterization\Financial\ContractTypesController::class,
  'erp.parameterization.financial.definedmethods.controller' => App\Controllers\ERP\Parameterization\Financial\DefinedMethodsController::class,
  'erp.parameterization.financial.paymentconditions.controller' => App\Controllers\ERP\Parameterization\Financial\PaymentConditionsController::class,
  'erp.parameterization.vehicles.brands.controller' => App\Controllers\ERP\Parameterization\Vehicles\VehicleBrandsController::class,
  'erp.parameterization.vehicles.models.controller' => App\Controllers\ERP\Parameterization\Vehicles\VehicleModelsController::class,
  'erp.parameterization.equipments.brands.controller' => App\Controllers\ERP\Parameterization\Equipments\EquipmentBrandsController::class,
  'erp.parameterization.equipments.models.controller' => App\Controllers\ERP\Parameterization\Equipments\EquipmentModelsController::class,
  'erp.parameterization.deposits.controller' => App\Controllers\ERP\Parameterization\DepositsController::class,
  'erp.parameterization.mailingprofiles.controller' => App\Controllers\ERP\Parameterization\MailingProfilesController::class,
  
  // --------------------------------------------------[ Cadastro ]-----
  'erp.cadastre.entities.controller' => App\Controllers\ERP\Cadastre\EntitiesController::class,
  'erp.cadastre.customers.controller' => App\Controllers\ERP\Cadastre\CustomersController::class,
  'erp.cadastre.suppliers.controller' => App\Controllers\ERP\Cadastre\SuppliersController::class,
  'erp.cadastre.serviceproviders.controller' => App\Controllers\ERP\Cadastre\ServiceProvidersController::class,
  'erp.cadastre.technicians.controller' => App\Controllers\ERP\Cadastre\TechniciansController::class,
  'erp.cadastre.sellers.controller' => App\Controllers\ERP\Cadastre\SellersController::class,
  'erp.cadastre.rapidresponses.controller' => App\Controllers\ERP\Cadastre\RapidResponsesController::class,
  'erp.cadastre.vehicles.controller' => App\Controllers\ERP\Cadastre\VehiclesController::class,
  'erp.cadastre.users.controller' => App\Controllers\ERP\Cadastre\UsersController::class,
  
  // ----------------------------------------------[ Dispositivos ]-----
  'erp.devices.simcards.controller' => App\Controllers\ERP\Devices\SimCardsController::class,
  'erp.devices.equipments.controller' => App\Controllers\ERP\Devices\EquipmentsController::class,
  'erp.devices.movimentations.controller' => App\Controllers\ERP\Devices\MovimentationsController::class,

  // ------------------------------------------------[ Financeiro ]-----
  'erp.financial.plans.controller' => App\Controllers\ERP\Financial\PlansController::class,
  'erp.financial.contracts.controller' => App\Controllers\ERP\Financial\ContractsController::class,
  'erp.financial.installations.controller' => App\Controllers\ERP\Financial\InstallationsController::class,
  'erp.financial.billings.controller' => App\Controllers\ERP\Financial\BillingsController::class,
  'erp.financial.monthlycalculations.controller' => App\Controllers\ERP\Financial\MonthlyCalculationsController::class,
  'erp.financial.payments.controller' => App\Controllers\ERP\Financial\PaymentsController::class,
  'erp.financial.cnab.controller' => App\Controllers\ERP\Financial\CnabController::class,

  // ------------------------------------------------[ Agendamentos ]-----
  'erp.appointments.controller' => App\Controllers\Erp\Appointments\AppointmentsController::class,


  // =======================================================[ STC ]=====
  // Os controladores do sistema de integração STC
  
  // ------------------------------------------------------[ Base ]-----
  'stc.home.controller' => App\Controllers\STC\MainController::class,
  'stc.auth.controller' => App\Controllers\STC\AuthController::class,

  // --------------------------------------------[ Parametrização ]-----
  'stc.parameterization.cadastral.cities.controller' => App\Controllers\STC\Parameterization\Cadastral\CitiesController::class,
  'stc.parameterization.cadastral.journeys.controller' => App\Controllers\STC\Parameterization\Cadastral\JourneysController::class,
  'stc.parameterization.vehicles.types.controller' => App\Controllers\STC\Parameterization\Vehicles\VehicleTypesController::class,
  'stc.parameterization.vehicles.brands.controller' => App\Controllers\STC\Parameterization\Vehicles\VehicleBrandsController::class,
  'stc.parameterization.vehicles.models.controller' => App\Controllers\STC\Parameterization\Vehicles\VehicleModelsController::class,
  'stc.parameterization.equipments.manufactures.controller' => App\Controllers\STC\Parameterization\Equipments\ManufacturesController::class,
  
  // --------------------------------------------------[ Cadastro ]-----
  'stc.cadastre.entities.controller' => App\Controllers\STC\Cadastre\EntitiesController::class,
  'stc.cadastre.customers.controller' => App\Controllers\STC\Cadastre\CustomersController::class,
  'stc.cadastre.vehicles.controller' => App\Controllers\STC\Cadastre\VehiclesController::class,
  'stc.cadastre.drivers.controller' => App\Controllers\STC\Cadastre\DriversController::class,
  'stc.cadastre.equipments.controller' => App\Controllers\STC\Cadastre\EquipmentsController::class,

  // --------------------------------------------------[ Cadastro ]-----
  'stc.data.positions.controller' => App\Controllers\STC\Data\PositionsController::class,

  // -------------------------------------------------[ Relatório ]-----
  'stc.report.roadtrips.controller' => App\Controllers\STC\Report\RoadTripsController::class,
  'stc.report.workdays.controller' => App\Controllers\STC\Report\WorkdaysController::class
];
