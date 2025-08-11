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
 * A carga das rotas.
 */

/**
 * @author Emerson Cavalcanti <emersoncavalcanti@gmail.com>
 */

$app->redirect('favicon.ico', 'assets/favicon/favicon.ico', 301);

// ========================================================[ Site ]=====
// As rotas para o site inicial de apresentação, sem restrição de uso
$app->group('/', function () use ($container) {
  $this->get('', 'home.controller:home')
       ->add($container['cache'])
       ->setName('Home');
  $this->get('about', 'home.controller:about')
       ->add($container['cache'])
       ->setName('About');
  $this->get('fuelconsumption', 'home.controller:fuelconsumption')
       ->add($container['cache'])
       ->setName('FuelConsumption');
  $this->get('tracking', 'home.controller:tracking')
       ->add($container['cache'])
       ->setName('Tracking');
  $this->get('monitoring', 'home.controller:monitoring')
       ->add($container['cache'])
       ->setName('Monitoring');
  $this->get('camerasurveillance', 'home.controller:camerasurveillance')
       ->add($container['cache'])
       ->setName('CameraSurveillance');
  $this->get('alarm', 'home.controller:alarm')
       ->add($container['cache'])
       ->setName('Alarm');
  $this->map(['GET', 'POST'], 'contactus', 'home.controller:contactus')
       ->setName('ContactUs');
  $this->map(['GET', 'POST'], 'quotation', 'home.controller:quotation')
       ->setName('RequestQuotation');
  $this->get('privacity', 'home.controller:privacity')
       ->add($container['cache'])
       ->setName('PrivacityControl');
  $this->get('links', 'home.controller:links')
       ->setName('Links');
})->add($container['public.middleware']);

// =============================================[ Área do cliente ]=====
// As rotas para a administração do sistema

// ------------------------------------------------[ Autenticação ]-----
// Rotas sem necessidade de autenticação
$app->group('/usr', function () use ($container) {
  $this->map(['GET', 'POST'], '/login', 'usr.auth.controller:login')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('USR\Login');
  $this->map(['GET', 'POST'], '/forgot', 'usr.auth.controller:forgot')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('USR\Forgot');
  $this->get('/sentinstructions/{ UUID }/{ Email }', 'usr.auth.controller:sentInstructions')
       ->setName('USR\SentInstructions');
  $this->get('/reactivate/{ Token }', 'usr.auth.controller:reactivate')
       ->setName('USR\Reactivate');
  $this->map(['GET', 'PUT'], '/reset/{ UUID }/{ Token }', 'usr.auth.controller:resetPassword')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('USR\Reset');
  $this->get('/billet/get/pdf/{ ciphertext }', 'usr.billet.controller:getPDF')
       ->setName('USR\Billet\Get\PDF');
  $this->get('/billet/invalidlink', 'usr.billet.controller:invalidLink')
       ->setName('USR\Billet\InvalidLink');
})->add($container['guest.middleware']);

// Rotas com necessidade de autenticação
$app->group('/usr', function () use ($container) {
  // ------------------------------------------------------[ Base ]-----
  $this->get('', 'usr.home.controller:home')
       ->setName('USR\Home');
  $this->get('/about', 'usr.home.controller:about')
       ->add($container['cache'])
       ->setName('USR\About');
  $this->get('/privacity', 'usr.home.controller:privacity')
       ->add($container['cache'])
       ->setName('USR\Privacity');
  $this->map(['GET', 'PUT'], '/account', 'usr.auth.controller:account')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('USR\Account');
  $this->map(['GET', 'PUT'], '/password', 'usr.auth.controller:password')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('USR\Password');
  $this->get('/logout', 'usr.auth.controller:logout')
       ->setName('USR\Logout');
  $this->get('/pdf[/{ paymentID }]','usr.home.controller:getPDF')
       ->setName('USR\Payments\Get\PDF');
})->add($container['auth.middleware']);

// =========================================================[ ADM ]=====
// As rotas para a administração do sistema

// ------------------------------------------------[ Autenticação ]-----
// Rotas sem necessidade de autenticação
$app->group('/adm', function () use ($container) {
  $this->map(['GET', 'POST'], '/login', 'adm.auth.controller:login')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('ADM\Login');
  $this->map(['GET', 'POST'], '/register', 'adm.auth.controller:register')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('ADM\Register');
})->add($container['guest.middleware']);

// Rotas com necessidade de autenticação
$app->group('/adm', function () use ($container) {
  // ------------------------------------------------------[ Base ]-----
  $this->get('', 'adm.home.controller:home')
       ->setName('ADM\Home');
  $this->get('/about', 'adm.home.controller:about')
       ->add($container['cache'])
       ->setName('ADM\About');
  $this->get('/privacity', 'adm.home.controller:privacity')
       ->add($container['cache'])
       ->setName('ADM\Privacity');
  $this->map(['GET', 'PUT'], '/account', 'adm.auth.controller:account')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('ADM\Account');
  $this->map(['GET', 'PUT'], '/password', 'adm.auth.controller:password')
       ->add($container['csrf'])->add($container['trimmer'])
       ->setName('ADM\Password');
  $this->get('/logout', 'adm.auth.controller:logout')
       ->setName('ADM\Logout');

  // --------------------------------------------[ Parametrização ]-----
  $this->group('/parameterization', function () use ($container) {
    $this->group('/cadastral', function () use ($container) {
      $this->group('/cities', function () use ($container) {
        $this->get('', 'adm.parameterization.cadastral.cities.controller:show')
             ->setName('ADM\Parameterization\Cadastral\Cities');
        $this->patch('/get', 'adm.parameterization.cadastral.cities.controller:get')
             ->setName('ADM\Parameterization\Cadastral\Cities\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.cadastral.cities.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Cadastral\Cities\Add');
        $this->map(['GET', 'PUT'], '/edit/{ cityID }','adm.parameterization.cadastral.cities.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Cadastral\Cities\Edit');
        $this->delete('/delete/{ cityID }','adm.parameterization.cadastral.cities.controller:delete')
             ->setName('ADM\Parameterization\Cadastral\Cities\Delete');
        $this->patch('/autocompletion/get', 'adm.parameterization.cadastral.cities.controller:getAutocompletionData')
             ->setName('ADM\Parameterization\Cadastral\Cities\Autocompletion\Get');
        $this->patch('/postalcode', 'adm.parameterization.cadastral.cities.controller:getPostalCodeData')
             ->setName('ADM\Parameterization\Cadastral\Cities\PostalCode\Get');
      });
      $this->group('/documenttypes', function () use ($container) {
        $this->get('', 'adm.parameterization.cadastral.documenttypes.controller:show')
             ->setName('ADM\Parameterization\Cadastral\DocumentTypes');
        $this->patch('/get', 'adm.parameterization.cadastral.documenttypes.controller:get')
             ->setName('ADM\Parameterization\Cadastral\DocumentTypes\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.cadastral.documenttypes.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Cadastral\DocumentTypes\Add');
        $this->map(['GET', 'PUT'], '/edit/{ documentTypeID }','adm.parameterization.cadastral.documenttypes.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Cadastral\DocumentTypes\Edit');
        $this->delete('/delete/{ documentTypeID }','adm.parameterization.cadastral.documenttypes.controller:delete')
             ->setName('ADM\Parameterization\Cadastral\DocumentTypes\Delete');
      });
      $this->group('/maritalstatus', function () use ($container) {
        $this->get('', 'adm.parameterization.cadastral.maritalstatus.controller:show')
             ->setName('ADM\Parameterization\Cadastral\MaritalStatus');
        $this->patch('/get', 'adm.parameterization.cadastral.maritalstatus.controller:get')
             ->setName('ADM\Parameterization\Cadastral\MaritalStatus\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.cadastral.maritalstatus.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Cadastral\MaritalStatus\Add');
        $this->map(['GET', 'PUT'], '/edit/{ maritalStatusID }','adm.parameterization.cadastral.maritalstatus.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Cadastral\MaritalStatus\Edit');
        $this->delete('/delete/{ maritalStatusID }','adm.parameterization.cadastral.maritalstatus.controller:delete')
             ->setName('ADM\Parameterization\Cadastral\MaritalStatus\Delete');
      });
      $this->group('/genders', function () use ($container) {
        $this->get('', 'adm.parameterization.cadastral.genders.controller:show')
             ->setName('ADM\Parameterization\Cadastral\Genders');
        $this->patch('/get', 'adm.parameterization.cadastral.genders.controller:get')
             ->setName('ADM\Parameterization\Cadastral\Genders\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.cadastral.genders.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Cadastral\Genders\Add');
        $this->map(['GET', 'PUT'], '/edit/{ genderID }','adm.parameterization.cadastral.genders.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Cadastral\Genders\Edit');
        $this->delete('/delete/{ genderID }','adm.parameterization.cadastral.genders.controller:delete')
             ->setName('ADM\Parameterization\Cadastral\Genders\Delete');
      });
      $this->group('/measuretypes', function () use ($container) {
        $this->get('', 'adm.parameterization.cadastral.measuretypes.controller:show')
             ->setName('ADM\Parameterization\Cadastral\MeasureTypes');
        $this->patch('/get', 'adm.parameterization.cadastral.measuretypes.controller:get')
             ->setName('ADM\Parameterization\Cadastral\MeasureTypes\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.cadastral.measuretypes.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Cadastral\MeasureTypes\Add');
        $this->map(['GET', 'PUT'], '/edit/{ measureTypeID }','adm.parameterization.cadastral.measuretypes.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Cadastral\MeasureTypes\Edit');
        $this->delete('/delete/{ measureTypeID }','adm.parameterization.cadastral.measuretypes.controller:delete')
             ->setName('ADM\Parameterization\Cadastral\MeasureTypes\Delete');
      });
      $this->group('/fieldtypes', function () use ($container) {
        $this->get('', 'adm.parameterization.cadastral.fieldtypes.controller:show')
             ->setName('ADM\Parameterization\Cadastral\FieldTypes');
        $this->patch('/get', 'adm.parameterization.cadastral.fieldtypes.controller:get')
             ->setName('ADM\Parameterization\Cadastral\FieldTypes\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.cadastral.fieldtypes.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Cadastral\FieldTypes\Add');
        $this->map(['GET', 'PUT'], '/edit/{ fieldTypeID }','adm.parameterization.cadastral.fieldtypes.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Cadastral\FieldTypes\Edit');
        $this->delete('/delete/{ fieldTypeID }','adm.parameterization.cadastral.fieldtypes.controller:delete')
             ->setName('ADM\Parameterization\Cadastral\FieldTypes\Delete');
      });
    });
    $this->group('/vehicles', function () use ($container) {
      $this->group('/types', function () use ($container) {
        $this->get('', 'adm.parameterization.vehicles.types.controller:show')
             ->setName('ADM\Parameterization\Vehicles\Types');
        $this->patch('/get', 'adm.parameterization.vehicles.types.controller:get')
             ->setName('ADM\Parameterization\Vehicles\Types\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.vehicles.types.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Vehicles\Types\Add');
        $this->map(['GET', 'PUT'], '/edit/{ vehicleTypeID }','adm.parameterization.vehicles.types.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Vehicles\Types\Edit');
        $this->delete('/delete/{ vehicleTypeID }','adm.parameterization.vehicles.types.controller:delete')
             ->setName('ADM\Parameterization\Vehicles\Types\Delete');
      });
      $this->group('/subtypes', function () use ($container) {
        $this->get('', 'adm.parameterization.vehicles.subtypes.controller:show')
             ->setName('ADM\Parameterization\Vehicles\Subtypes');
        $this->patch('/get', 'adm.parameterization.vehicles.subtypes.controller:get')
             ->setName('ADM\Parameterization\Vehicles\Subtypes\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.vehicles.subtypes.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Vehicles\Subtypes\Add');
        $this->map(['GET', 'PUT'], '/edit/{ vehicleSubtypeID }','adm.parameterization.vehicles.subtypes.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Vehicles\Subtypes\Edit');
        $this->delete('/delete/{ vehicleSubtypeID }','adm.parameterization.vehicles.subtypes.controller:delete')
             ->setName('ADM\Parameterization\Vehicles\Subtypes\Delete');
      });
      $this->group('/fuels', function () use ($container) {
        $this->get('', 'adm.parameterization.vehicles.fuels.controller:show')
             ->setName('ADM\Parameterization\Vehicles\Fuels');
        $this->patch('/get', 'adm.parameterization.vehicles.fuels.controller:get')
             ->setName('ADM\Parameterization\Vehicles\Fuels\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.vehicles.fuels.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Vehicles\Fuels\Add');
        $this->map(['GET', 'PUT'], '/edit/{ fuelType }','adm.parameterization.vehicles.fuels.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Vehicles\Fuels\Edit');
        $this->delete('/delete/{ fuelType }','adm.parameterization.vehicles.fuels.controller:delete')
             ->setName('ADM\Parameterization\Vehicles\Fuels\Delete');
      });
      $this->group('/colors', function () use ($container) {
        $this->get('', 'adm.parameterization.vehicles.colors.controller:show')
             ->setName('ADM\Parameterization\Vehicles\Colors');
        $this->patch('/get', 'adm.parameterization.vehicles.colors.controller:get')
             ->setName('ADM\Parameterization\Vehicles\Colors\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.vehicles.colors.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Vehicles\Colors\Add');
        $this->map(['GET', 'PUT'], '/edit/{ vehicleColorID }','adm.parameterization.vehicles.colors.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Vehicles\Colors\Edit');
        $this->delete('/delete/{ vehicleColorID }','adm.parameterization.vehicles.colors.controller:delete')
             ->setName('ADM\Parameterization\Vehicles\Colors\Delete');
      });
    });
    $this->group('/devices', function () use ($container) {
      $this->group('/accessories', function () use ($container) {
        $this->group('/types', function () use ($container) {
          $this->get('', 'adm.parameterization.devices.accessories.types.controller:show')
               ->setName('ADM\Parameterization\Devices\Accessories\Types');
          $this->patch('/get', 'adm.parameterization.devices.accessories.types.controller:get')
               ->setName('ADM\Parameterization\Devices\Accessories\Types\Get');
          $this->map(['GET', 'POST'], '/add','adm.parameterization.devices.accessories.types.controller:add')
               ->add($container['csrf'])
               ->add($container['trimmer'])
               ->setName('ADM\Parameterization\Devices\Accessories\Types\Add');
          $this->map(['GET', 'PUT'], '/edit/{ accessoryTypeID }','adm.parameterization.devices.accessories.types.controller:edit')
               ->add($container['csrf'])
               ->add($container['trimmer'])
               ->setName('ADM\Parameterization\Devices\Accessories\Types\Edit');
          $this->delete('/delete/{ accessoryTypeID }','adm.parameterization.devices.accessories.types.controller:delete')
               ->setName('ADM\Parameterization\Devices\Accessories\Types\Delete');
        });
      });
      $this->group('/features', function () use ($container) {
        $this->get('', 'adm.parameterization.devices.features.controller:show')
             ->setName('ADM\Parameterization\Devices\Features');
        $this->patch('/get', 'adm.parameterization.devices.features.controller:get')
             ->setName('ADM\Parameterization\Devices\Features\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.devices.features.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Devices\Features\Add');
        $this->map(['GET', 'PUT'], '/edit/{ featureID }','adm.parameterization.devices.features.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Devices\Features\Edit');
        $this->delete('/delete/{ featureID }','adm.parameterization.devices.features.controller:delete')
             ->setName('ADM\Parameterization\Devices\Features\Delete');
      });
      $this->group('/brands', function () use ($container) {
        $this->get('', 'adm.parameterization.devices.brands.controller:show')
             ->setName('ADM\Parameterization\Devices\Brands');
        $this->patch('/get', 'adm.parameterization.devices.brands.controller:get')
             ->setName('ADM\Parameterization\Devices\Brands\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.devices.brands.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Devices\Brands\Add');
        $this->map(['GET', 'PUT'], '/edit/{ equipmentBrandID }','adm.parameterization.devices.brands.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Devices\Brands\Edit');
        $this->delete('/delete/{ equipmentBrandID }','adm.parameterization.devices.brands.controller:delete')
             ->setName('ADM\Parameterization\Devices\Brands\Delete');
        $this->patch('/autocompletion/get', 'adm.parameterization.devices.brands.controller:getAutocompletionData')
             ->setName('ADM\Parameterization\Devices\Brands\Autocompletion\Get');
      });
      $this->group('/models', function () use ($container) {
        $this->get('', 'adm.parameterization.devices.models.controller:show')
             ->setName('ADM\Parameterization\Devices\Models');
        $this->patch('/get', 'adm.parameterization.devices.models.controller:get')
             ->setName('ADM\Parameterization\Devices\Models\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.devices.models.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Devices\Models\Add');
        $this->map(['GET', 'PUT'], '/edit/{ equipmentModelID }','adm.parameterization.devices.models.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Devices\Models\Edit');
        $this->delete('/delete/{ equipmentModelID }','adm.parameterization.devices.models.controller:delete')
             ->setName('ADM\Parameterization\Devices\Models\Delete');
        $this->patch('/autocompletion/get', 'adm.parameterization.devices.models.controller:getAutocompletionData')
             ->setName('ADM\Parameterization\Devices\Models\Autocompletion\Get');
      });
    });
    $this->group('/Telephony', function () use ($container) {
      $this->group('/phonetypes', function () use ($container) {
        $this->get('', 'adm.parameterization.telephony.phonetypes.controller:show')
             ->setName('ADM\Parameterization\Telephony\PhoneTypes');
        $this->patch('/get', 'adm.parameterization.telephony.phonetypes.controller:get')
             ->setName('ADM\Parameterization\Telephony\PhoneTypes\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.telephony.phonetypes.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Telephony\PhoneTypes\Add');
        $this->map(['GET', 'PUT'], '/edit/{ phoneTypeID }','adm.parameterization.telephony.phonetypes.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Telephony\PhoneTypes\Edit');
        $this->delete('/delete/{ phoneTypeID }','adm.parameterization.telephony.phonetypes.controller:delete')
             ->setName('ADM\Parameterization\Telephony\PhoneTypes\Delete');
      });
      $this->group('/mobileoperators', function () use ($container) {
        $this->get('', 'adm.parameterization.telephony.mobileoperators.controller:show')
             ->setName('ADM\Parameterization\Telephony\MobileOperators');
        $this->patch('/get', 'adm.parameterization.telephony.mobileoperators.controller:get')
             ->setName('ADM\Parameterization\Telephony\MobileOperators\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.telephony.mobileoperators.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Telephony\MobileOperators\Add');
        $this->map(['GET', 'PUT'], '/edit/{ mobileOperatorID }','adm.parameterization.telephony.mobileoperators.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Telephony\MobileOperators\Edit');
        $this->delete('/delete/{ mobileOperatorID }','adm.parameterization.telephony.mobileoperators.controller:delete')
             ->setName('ADM\Parameterization\Telephony\MobileOperators\Delete');
      });
      $this->group('/simcardtypes', function () use ($container) {
        $this->get('', 'adm.parameterization.telephony.simcardtypes.controller:show')
             ->setName('ADM\Parameterization\Telephony\SimCardTypes');
        $this->patch('/get', 'adm.parameterization.telephony.simcardtypes.controller:get')
             ->setName('ADM\Parameterization\Telephony\SimCardTypes\Get');
        $this->map(['GET', 'POST'], '/add','adm.parameterization.telephony.simcardtypes.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Telephony\SimCardTypes\Add');
        $this->map(['GET', 'PUT'], '/edit/{ simcardTypeID }','adm.parameterization.telephony.simcardtypes.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Parameterization\Telephony\SimCardTypes\Edit');
        $this->delete('/delete/{ simcardTypeID }','adm.parameterization.telephony.simcardtypes.controller:delete')
             ->setName('ADM\Parameterization\Telephony\SimCardTypes\Delete');
      });
    });
    $this->group('/ownershiptypes', function () use ($container) {
      $this->get('', 'adm.parameterization.ownershiptypes.controller:show')
           ->setName('ADM\Parameterization\OwnershipTypes');
      $this->patch('/get', 'adm.parameterization.ownershiptypes.controller:get')
           ->setName('ADM\Parameterization\OwnershipTypes\Get');
      $this->map(['GET', 'POST'], '/add','adm.parameterization.ownershiptypes.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Parameterization\OwnershipTypes\Add');
      $this->map(['GET', 'PUT'], '/edit/{ ownershipTypeID }','adm.parameterization.ownershiptypes.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Parameterization\OwnershipTypes\Edit');
      $this->delete('/delete/{ ownershipTypeID }','adm.parameterization.ownershiptypes.controller:delete')
           ->setName('ADM\Parameterization\OwnershipTypes\Delete');
    });
    $this->group('/holidays', function () use ($container) {
      $this->get('', 'adm.parameterization.holidays.controller:show')
           ->setName('ADM\Parameterization\Holidays');
      $this->patch('/get', 'adm.parameterization.holidays.controller:get')
           ->setName('ADM\Parameterization\Holidays\Get');
      $this->map(['GET', 'POST'], '/add','adm.parameterization.holidays.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Parameterization\Holidays\Add');
      $this->map(['GET', 'PUT'], '/edit/{ holidayID }','adm.parameterization.holidays.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Parameterization\Holidays\Edit');
      $this->delete('/delete/{ holidayID }','adm.parameterization.holidays.controller:delete')
           ->setName('ADM\Parameterization\Holidays\Delete');
      $this->get('/pdf','adm.parameterization.holidays.controller:getPDF')
           ->setName('ADM\Parameterization\Holidays\Get\PDF');
    });
    $this->group('/permissions', function () use ($container) {
      $this->get('', 'adm.parameterization.permissions.controller:show')
           ->setName('ADM\Parameterization\Permissions');
      $this->patch('/get', 'adm.parameterization.permissions.controller:get')
           ->setName('ADM\Parameterization\Permissions\Get');
      $this->map(['GET', 'POST'], '/add','adm.parameterization.permissions.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Parameterization\Permissions\Add');
      $this->map(['GET', 'PUT'], '/edit/{ permissionID }','adm.parameterization.permissions.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Parameterization\Permissions\Edit');
      $this->delete('/delete/{ permissionID }','adm.parameterization.permissions.controller:delete')
           ->setName('ADM\Parameterization\Permissions\Delete');
    });
    $this->group('/systemactions', function () use ($container) {
      $this->get('', 'adm.parameterization.systemactions.controller:show')
           ->setName('ADM\Parameterization\SystemActions');
      $this->patch('/get', 'adm.parameterization.systemactions.controller:get')
           ->setName('ADM\Parameterization\SystemActions\Get');
      $this->map(['GET', 'POST'], '/add','adm.parameterization.systemactions.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Parameterization\SystemActions\Add');
      $this->map(['GET', 'PUT'], '/edit/{ systemActionID }','adm.parameterization.systemactions.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Parameterization\SystemActions\Edit');
      $this->delete('/delete/{ systemActionID }','adm.parameterization.systemactions.controller:delete')
           ->setName('ADM\Parameterization\SystemActions\Delete');
    });
  });

  // ------------------------------------------------[ Financeiro ]-----
  $this->group('/financial', function () use ($container) {
    $this->group('/banks', function () use ($container) {
      $this->get('', 'adm.financial.banks.controller:show')
           ->setName('ADM\Financial\Banks');
      $this->patch('/get', 'adm.financial.banks.controller:get')
           ->setName('ADM\Financial\Banks\Get');
      $this->map(['GET', 'POST'], '/add','adm.financial.banks.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Financial\Banks\Add');
      $this->map(['GET', 'PUT'], '/edit/{ bankID }','adm.financial.banks.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Financial\Banks\Edit');
      $this->delete('/delete/{ bankID }','adm.financial.banks.controller:delete')
           ->setName('ADM\Financial\Banks\Delete');
    });
    $this->group('/indicators', function () use ($container) {
      $this->get('', 'adm.financial.indicators.controller:show')
           ->setName('ADM\Financial\Indicators');
      $this->patch('/get', 'adm.financial.indicators.controller:get')
           ->setName('ADM\Financial\Indicators\Get');
      $this->map(['GET', 'POST'], '/add','adm.financial.indicators.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Financial\Indicators\Add');
      $this->map(['GET', 'PUT'], '/edit/{ indicatorID }','adm.financial.indicators.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Financial\Indicators\Edit');
      $this->delete('/delete/{ indicatorID }','adm.financial.indicators.controller:delete')
           ->setName('ADM\Financial\Indicators\Delete');

      $this->group('/accumulatedvalues', function () use ($container) {
        $this->get('', 'adm.financial.indicators.accumulatedvalues.controller:show')
             ->setName('ADM\Financial\Indicators\AccumulatedValues');
        $this->patch('/get', 'adm.financial.indicators.accumulatedvalues.controller:get')
             ->setName('ADM\Financial\Indicators\AccumulatedValues\Get');
        $this->map(['GET', 'POST'], '/add','adm.financial.indicators.accumulatedvalues.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Financial\Indicators\AccumulatedValues\Add');
        $this->map(['GET', 'PUT'], '/edit/{ accumulatedValueID }','adm.financial.indicators.accumulatedvalues.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ADM\Financial\Indicators\AccumulatedValues\Edit');
        $this->delete('/delete/{ accumulatedValueID }','adm.financial.indicators.accumulatedvalues.controller:delete')
             ->setName('ADM\Financial\Indicators\AccumulatedValues\Delete');
        $this->get('/update', 'adm.financial.indicators.accumulatedvalues.controller:update')
             ->setName('ADM\Financial\Indicators\AccumulatedValues\Update');
      });
    });
  });

  // --------------------------------------------------[ Cadastro ]-----
  $this->group('/cadastre', function () use ($container) {
    $this->group('/contractors', function () use ($container) {
      $this->get('', 'adm.cadastre.contractors.controller:show')
           ->setName('ADM\Cadastre\Contractors');
      $this->patch('/get', 'adm.cadastre.contractors.controller:get')
           ->setName('ADM\Cadastre\Contractors\Get');
      $this->map(['GET', 'POST'], '/add','adm.cadastre.contractors.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Cadastre\Contractors\Add');
      $this->map(['GET', 'PUT'], '/edit/{ contractorID }','adm.cadastre.contractors.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Cadastre\Contractors\Edit');
      $this->any('/delete/{ contractorID }','adm.cadastre.contractors.controller:delete')
           ->setName('ADM\Cadastre\Contractors\Delete');
      $this->put('/toggleblocked/{ contractorID }[/{ subsidiaryID }]','adm.cadastre.contractors.controller:toggleBlocked')
           ->setName('ADM\Cadastre\Contractors\ToggleBlocked');
      $this->get('/pdf/{ contractorID }[/{ subsidiaryID }]','adm.cadastre.contractors.controller:getPDF')
           ->setName('ADM\Cadastre\Contractors\Get\PDF');
      $this->patch('/autocompletion/get', 'adm.cadastre.contractors.controller:getAutocompletionData')
           ->setName('ADM\Cadastre\Contractors\Autocompletion\Get');
    });
    $this->group('/monitors', function () use ($container) {
      $this->get('', 'adm.cadastre.monitors.controller:show')
           ->setName('ADM\Cadastre\Monitors');
      $this->patch('/get', 'adm.cadastre.monitors.controller:get')
           ->setName('ADM\Cadastre\Monitors\Get');
      $this->map(['GET', 'POST'], '/add','adm.cadastre.monitors.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Cadastre\Monitors\Add');
      $this->map(['GET', 'PUT'], '/edit/{ monitorID }','adm.cadastre.monitors.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Cadastre\Monitors\Edit');
      $this->delete('/delete/{ monitorID }','adm.cadastre.monitors.controller:delete')
           ->setName('ADM\Cadastre\Monitors\Delete');
      $this->put('/toggleblocked/{ monitorID }[/{ subsidiaryID }]','adm.cadastre.monitors.controller:toggleBlocked')
           ->setName('ADM\Cadastre\Monitors\ToggleBlocked');
      $this->get('/pdf/{ monitorID }[/{ subsidiaryID }]','adm.cadastre.monitors.controller:getPDF')
           ->setName('ADM\Cadastre\Monitors\Get\PDF');
      $this->get('/hasoneormore', 'adm.cadastre.monitors.controller:hasOneOrMore')
           ->setName('ADM\Cadastre\Monitors\HasOneOrMore');
    });
    $this->group('/users', function () use ($container) {
      $this->get('', 'adm.cadastre.users.controller:show')
           ->setName('ADM\Cadastre\Users');
      $this->patch('/get', 'adm.cadastre.users.controller:get')
           ->setName('ADM\Cadastre\Users\Get');
      $this->map(['GET', 'POST'], '/add','adm.cadastre.users.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Cadastre\Users\Add');
      $this->map(['GET', 'PUT'], '/edit/{ userID }','adm.cadastre.users.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ADM\Cadastre\Users\Edit');
      $this->delete('/delete/{ userID }','adm.cadastre.users.controller:delete')
           ->setName('ADM\Cadastre\Users\Delete');
      $this->put('/toggleforcenewpassword/{ userID }','adm.cadastre.users.controller:toggleForceNewPassword')
           ->setName('ADM\Cadastre\Users\ToggleForceNewPassword');
      $this->put('/toggleblocked/{ userID }','adm.cadastre.users.controller:toggleBlocked')
           ->setName('ADM\Cadastre\Users\ToggleBlocked');
      $this->put('/togglesuspended/{ userID }','adm.cadastre.users.controller:toggleSuspended')
           ->setName('ADM\Cadastre\Users\ToggleSuspended');
    });
  });
})->add($container['auth.middleware']);

// =========================================================[ ERP ]=====
// As rotas para o sistema ERP

// ------------------------------------------------[ Autenticação ]-----
// Rotas sem necessidade de autenticação
$app->group('/erp', function () use ($container) {
  $this->map(['GET', 'POST'], '/login[/{ UUID }]', 'erp.auth.controller:login')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('ERP\Login');
  $this->map(['GET', 'POST'], '/forgot[/{ UUID }]', 'erp.auth.controller:forgot')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('ERP\Forgot');
  $this->get('/sentinstructions/{ UUID }/{ Email }', 'erp.auth.controller:sentInstructions')
       ->setName('ERP\SentInstructions');
  $this->get('/reactivate/{ UUID }/{ Token }', 'erp.auth.controller:reactivate')
       ->setName('ERP\Reactivate');
  $this->map(['GET', 'PUT'], '/reset/{ UUID }/{ Token }', 'erp.auth.controller:resetPassword')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('ERP\Reset');
  $this->map(['GET', 'POST'], '/register', 'erp.auth.controller:register')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('ERP\Register');
})->add($container['guest.middleware']);

// ----------------------------------------------------[ Recursos ]-----
// Rotas públicas
$app->group('/erp', function () use ($container) {
  $this->group('/resources', function () use ($container) {
    $this->group('/logo', function () use ($container) {
      $this->get('/normal/{ UUID }', 'erp.cadastre.entities.controller:getNormalLogo')
           ->setName('ERP\Resources\Logo\Normal');
      $this->get('/inverted/{ UUID }', 'erp.cadastre.entities.controller:getInvertedLogo')
           ->setName('ERP\Resources\Logo\Inverted');
    });
  });
})->add($container['public.middleware']);

// Rotas com necessidade de autenticação
$app->group('/erp', function () use ($container) {
  // ------------------------------------------------------[ Base ]-----
  $this->get('', 'erp.home.controller:home')
       ->setName('ERP\Home');
  $this->get('/about', 'erp.home.controller:about')
       ->add($container['cache'])
       ->setName('ERP\About');
  $this->get('/privacity', 'erp.home.controller:privacity')
       ->add($container['cache'])
       ->setName('ERP\Privacity');
  $this->map(['GET', 'PUT'], '/account', 'erp.auth.controller:account')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('ERP\Account');
  $this->map(['GET', 'PUT'], '/password', 'erp.auth.controller:password')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('ERP\Password');
  $this->get('/logout', 'erp.auth.controller:logout')
       ->setName('ERP\Logout');

  // ------------------------------------------------[ Agendamentos ]-----

  $this->group('/appointments', function () use ($container) {
    $this->get('', 'erp.appointments.controller:show')
         ->setName('ERP\Appointments\Calendar');
    $this->patch('/get', 'erp.appointments.controller:get')
         ->setName('ERP\Appointments\Get');
    $this->map(['GET', 'POST'], '/add', 'erp.appointments.controller:add')
         ->add($container['csrf'])
         ->add($container['trimmer'])
         ->setName('ERP\Appointments\Add');
    $this->map(['GET', 'PUT'], '/edit/{appointmentID}', 'erp.appointments.controller:edit')
         ->add($container['csrf'])
         ->add($container['trimmer'])
         ->setName('ERP\Appointments\Edit');
    $this->delete('/delete/{appointmentID}', 'erp.appointments.controller:delete')
         ->setName('ERP\Appointments\Delete');
    $this->get('/list', 'erp.appointments.controller:index')
         ->setName('ERP\Appointments\List');
    $this->put('/togglestatus/{appointmentID}', 'erp.appointments.controller:toggleStatus')
         ->setName('ERP\Appointments\ToggleStatus');
    $this->get('/pdf/{appointmentID}', 'erp.appointments.controller:getPDF')
         ->setName('ERP\Appointments\Get\PDF');
    $this->post('/appointments/vehicle-addresses', 'AppointmentsController:getVehicleAddresses')
         ->setName('ERP\Appointments\GetVehicleAddresses');
  });

  // --------------------------------------------[ Parametrização ]-----
  $this->group('/parameterization', function () use ($container) {
    $this->group('/cities', function () use ($container) {
      $this->patch('/autocompletion/get', 'erp.parameterization.cities.controller:getAutocompletionData')
           ->setName('ERP\Parameterization\Cities\Autocompletion\Get');
      $this->patch('/postalcode', 'erp.parameterization.cities.controller:getPostalCodeData')
           ->setName('ERP\Parameterization\Cities\PostalCode\Get');
    });
    $this->group('/mobileoperators', function () use ($container) {
      $this->patch('/imsi', 'erp.parameterization.mobileoperators.controller:getMobileOperatorFromIMSI')
           ->setName('ERP\Parameterization\MobileOperators\IMSI\Get');
      $this->patch('/apn', 'erp.parameterization.mobileoperators.controller:getAPN')
           ->setName('ERP\Parameterization\MobileOperators\APN\Get');
    });
    $this->group('/financial', function () use ($container) {
      $this->group('/installmenttypes', function () use ($container) {
        $this->get('', 'erp.parameterization.financial.installmenttypes.controller:show')
             ->setName('ERP\Parameterization\Financial\InstallmentTypes');
        $this->patch('/get', 'erp.parameterization.financial.installmenttypes.controller:get')
             ->setName('ERP\Parameterization\Financial\InstallmentTypes\Get');
        $this->map(['GET', 'POST'], '/add','erp.parameterization.financial.installmenttypes.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Financial\InstallmentTypes\Add');
        $this->map(['GET', 'PUT'], '/edit/{ installmentTypeID }','erp.parameterization.financial.installmenttypes.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Financial\InstallmentTypes\Edit');
        $this->delete('/delete/{ installmentTypeID }','erp.parameterization.financial.installmenttypes.controller:delete')
             ->setName('ERP\Parameterization\Financial\InstallmentTypes\Delete');
        $this->put('/toggleblocked/{ installmentTypeID }', 'erp.parameterization.financial.installmenttypes.controller:toggleBlocked')
             ->setName('ERP\Parameterization\Financial\InstallmentTypes\ToggleBlocked');
        $this->patch('/installmentplan', 'erp.parameterization.financial.installmenttypes.controller:getInstallmentPlan')
             ->setName('ERP\Parameterization\Financial\InstallmentTypes\InstallmentPlan\Get');
      });
      $this->group('/billingtypes', function () use ($container) {
        $this->get('', 'erp.parameterization.financial.billingtypes.controller:show')
             ->setName('ERP\Parameterization\Financial\BillingTypes');
        $this->patch('/get', 'erp.parameterization.financial.billingtypes.controller:get')
             ->setName('ERP\Parameterization\Financial\BillingTypes\Get');
        $this->map(['GET', 'POST'], '/add','erp.parameterization.financial.billingtypes.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Financial\BillingTypes\Add');
        $this->map(['GET', 'PUT'], '/edit/{ billingTypeID }','erp.parameterization.financial.billingtypes.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Financial\BillingTypes\Edit');
        $this->delete('/delete/{ billingTypeID }','erp.parameterization.financial.billingtypes.controller:delete')
             ->setName('ERP\Parameterization\Financial\BillingTypes\Delete');
      });
      $this->group('/duedays', function () use ($container) {
        $this->get('', 'erp.parameterization.financial.duedays.controller:show')
             ->setName('ERP\Parameterization\Financial\DueDays');
        $this->patch('/get', 'erp.parameterization.financial.duedays.controller:get')
             ->setName('ERP\Parameterization\Financial\DueDays\Get');
        $this->map(['GET', 'POST'], '/add','erp.parameterization.financial.duedays.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Financial\DueDays\Add');
        $this->map(['GET', 'PUT'], '/edit/{ dueDayID }','erp.parameterization.financial.duedays.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Financial\DueDays\Edit');
        $this->delete('/delete/{ dueDayID }','erp.parameterization.financial.duedays.controller:delete')
             ->setName('ERP\Parameterization\Financial\DueDays\Delete');
      });
      $this->group('/contracttypes', function () use ($container) {
        $this->get('', 'erp.parameterization.financial.contracttypes.controller:show')
             ->setName('ERP\Parameterization\Financial\ContractTypes');
        $this->patch('/get', 'erp.parameterization.financial.contracttypes.controller:get')
             ->setName('ERP\Parameterization\Financial\ContractTypes\Get');
        $this->map(['GET', 'POST'], '/add','erp.parameterization.financial.contracttypes.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Financial\ContractTypes\Add');
        $this->map(['GET', 'PUT'], '/edit/{ contractTypeID }','erp.parameterization.financial.contracttypes.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Financial\ContractTypes\Edit');
        $this->delete('/delete/{ contractTypeID }','erp.parameterization.financial.contracttypes.controller:delete')
             ->setName('ERP\Parameterization\Financial\ContractTypes\Delete');
        $this->put('/toggleactive/{ contractTypeID }', 'erp.parameterization.financial.contracttypes.controller:toggleActive')
             ->setName('ERP\Parameterization\Financial\ContractTypes\ToggleActive');
      });
      $this->group('/definedmethods', function () use ($container) {
        $this->get('', 'erp.parameterization.financial.definedmethods.controller:show')
             ->setName('ERP\Parameterization\Financial\DefinedMethods');
        $this->patch('/get', 'erp.parameterization.financial.definedmethods.controller:get')
             ->setName('ERP\Parameterization\Financial\DefinedMethods\Get');
        $this->map(['GET', 'POST'], '/add','erp.parameterization.financial.definedmethods.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Financial\DefinedMethods\Add');
        $this->map(['GET', 'PUT'], '/edit/{ definedMethodID }','erp.parameterization.financial.definedmethods.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Financial\DefinedMethods\Edit');
        $this->delete('/delete/{ definedMethodID }','erp.parameterization.financial.definedmethods.controller:delete')
             ->setName('ERP\Parameterization\Financial\DefinedMethods\Delete');
        $this->put('/toggleblocked/{ definedMethodID }', 'erp.parameterization.financial.definedmethods.controller:toggleBlocked')
             ->setName('ERP\Parameterization\Financial\DefinedMethods\ToggleBlocked');
      });
      $this->group('/paymentconditions', function () use ($container) {
        $this->get('', 'erp.parameterization.financial.paymentconditions.controller:show')
             ->setName('ERP\Parameterization\Financial\PaymentConditions');
        $this->patch('/get', 'erp.parameterization.financial.paymentconditions.controller:get')
             ->setName('ERP\Parameterization\Financial\PaymentConditions\Get');
        $this->map(['GET', 'POST'], '/add','erp.parameterization.financial.paymentconditions.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Financial\PaymentConditions\Add');
        $this->map(['GET', 'PUT'], '/edit/{ paymentConditionID }','erp.parameterization.financial.paymentconditions.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Financial\PaymentConditions\Edit');
        $this->delete('/delete/{ paymentConditionID }','erp.parameterization.financial.paymentconditions.controller:delete')
             ->setName('ERP\Parameterization\Financial\PaymentConditions\Delete');
        $this->put('/toggleblocked/{ paymentConditionID }', 'erp.parameterization.financial.paymentconditions.controller:toggleBlocked')
             ->setName('ERP\Parameterization\Financial\PaymentConditions\ToggleBlocked');
      });
    });
    $this->group('/vehicles', function () use ($container) {
      $this->group('/brands', function () use ($container) {
        $this->get('', 'erp.parameterization.vehicles.brands.controller:show')
             ->setName('ERP\Parameterization\Vehicles\Brands');
        $this->patch('/get', 'erp.parameterization.vehicles.brands.controller:get')
             ->setName('ERP\Parameterization\Vehicles\Brands\Get');
        $this->map(['GET', 'POST'], '/add','erp.parameterization.vehicles.brands.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Vehicles\Brands\Add');
        $this->map(['GET', 'PUT'], '/edit/{ vehicleBrandID }','erp.parameterization.vehicles.brands.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Vehicles\Brands\Edit');
        $this->delete('/delete/{ vehicleBrandID }','erp.parameterization.vehicles.brands.controller:delete')
             ->setName('ERP\Parameterization\Vehicles\Brands\Delete');
        $this->get('/synchronize', 'erp.parameterization.vehicles.brands.controller:synchronize')
             ->setName('ERP\Parameterization\Vehicles\Brands\Synchronize');
        $this->patch('/autocompletion/get', 'erp.parameterization.vehicles.brands.controller:getAutocompletionData')
             ->setName('ERP\Parameterization\Vehicles\Brands\Autocompletion\Get');
      });
      $this->group('/vehiclemodels', function () use ($container) {
        $this->get('', 'erp.parameterization.vehicles.models.controller:show')
             ->setName('ERP\Parameterization\Vehicles\Models');
        $this->patch('/get', 'erp.parameterization.vehicles.models.controller:get')
             ->setName('ERP\Parameterization\Vehicles\Models\Get');
        $this->map(['GET', 'POST'], '/add','erp.parameterization.vehicles.models.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Vehicles\Models\Add');
        $this->map(['GET', 'PUT'], '/edit/{ vehicleModelID }','erp.parameterization.vehicles.models.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Parameterization\Vehicles\Models\Edit');
        $this->delete('/delete/{ vehicleModelID }','erp.parameterization.vehicles.models.controller:delete')
             ->setName('ERP\Parameterization\Vehicles\Models\Delete');
        $this->get('/synchronize', 'erp.parameterization.vehicles.models.controller:synchronize')
             ->setName('ERP\Parameterization\Vehicles\Models\Synchronize');
        $this->patch('/autocompletion/get', 'erp.parameterization.vehicles.models.controller:getAutocompletionData')
             ->setName('ERP\Parameterization\Vehicles\Models\Autocompletion\Get');
      });
    });
    $this->group('/equipments', function () use ($container) {
      $this->group('/models', function () use ($container) {
        $this->patch('/autocompletion/get', 'erp.parameterization.equipments.models.controller:getAutocompletionData')
             ->setName('ERP\Parameterization\Equipments\Models\Autocompletion\Get');
      });
    });
    $this->group('/deposits', function () use ($container) {
      $this->get('', 'erp.parameterization.deposits.controller:show')
           ->setName('ERP\Parameterization\Deposits');
      $this->patch('/get', 'erp.parameterization.deposits.controller:get')
           ->setName('ERP\Parameterization\Deposits\Get');
      $this->map(['GET', 'POST'], '/add','erp.parameterization.deposits.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Parameterization\Deposits\Add');
      $this->map(['GET', 'PUT'], '/edit/{ depositID }','erp.parameterization.deposits.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Parameterization\Deposits\Edit');
      $this->delete('/delete/{ depositID }','erp.parameterization.deposits.controller:delete')
           ->setName('ERP\Parameterization\Deposits\Delete');
    });
    $this->group('/mailingprofiles', function () use ($container) {
      $this->get('', 'erp.parameterization.mailingprofiles.controller:show')
           ->setName('ERP\Parameterization\MailingProfiles');
      $this->patch('/get', 'erp.parameterization.mailingprofiles.controller:get')
           ->setName('ERP\Parameterization\MailingProfiles\Get');
      $this->map(['GET', 'POST'], '/add','erp.parameterization.mailingprofiles.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Parameterization\MailingProfiles\Add');
      $this->map(['GET', 'PUT'], '/edit/{ mailingProfileID }','erp.parameterization.mailingprofiles.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Parameterization\MailingProfiles\Edit');
      $this->delete('/delete/{ mailingProfileID }','erp.parameterization.mailingprofiles.controller:delete')
           ->setName('ERP\Parameterization\MailingProfiles\Delete');
    });
  });

  // --------------------------------------------------[ Cadastro ]-----
  $this->group('/cadastre', function () use ($container) {
    $this->group('/entities', function () use ($container) {
      $this->patch('/autocompletion/get', 'erp.cadastre.entities.controller:getAutocompletionData')
           ->setName('ERP\Cadastre\Entities\Autocompletion\Get');
    });
    $this->group('/customers', function () use ($container) {
      $this->get('', 'erp.cadastre.customers.controller:show')
           ->setName('ERP\Cadastre\Customers');
      $this->patch('/get', 'erp.cadastre.customers.controller:get')
           ->setName('ERP\Cadastre\Customers\Get');
      $this->map(['GET', 'POST'], '/add','erp.cadastre.customers.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\Customers\Add');
      $this->map(['GET', 'PUT'], '/edit/{ customerID }','erp.cadastre.customers.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\Customers\Edit');
      $this->map(['GET', 'PUT'], '/contract/{ contractID }','erp.cadastre.customers.controller:editContract')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\Customers\Contract');
      $this->delete('/delete/{ customerID }','erp.cadastre.customers.controller:delete')
           ->setName('ERP\Cadastre\Customers\Delete');
      $this->put('/toggleblocked/{ customerID }[/{ subsidiaryID }]','erp.cadastre.customers.controller:toggleBlocked')
           ->setName('ERP\Cadastre\Customers\ToggleBlocked');
      $this->get('/pdf/{ customerID }[/{ subsidiaryID }]','erp.cadastre.customers.controller:getPDF')
           ->setName('ERP\Cadastre\Customers\Get\PDF');
      $this->get('/hasoneormore', 'erp.cadastre.customers.controller:hasOneOrMore')
           ->setName('ERP\Cadastre\Customers\HasOneOrMore');
      $this->group('/affiliated', function () use ($container) {
        $this->map(['GET', 'POST'], '/add/{ customerID }','erp.cadastre.customers.controller:addAffiliated')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Cadastre\Customers\Affiliated\Add');
        $this->map(['GET', 'PUT'], '/edit/{ customerID }/{ affiliatedID }','erp.cadastre.customers.controller:editAffiliated')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Cadastre\Customers\Affiliated\Edit');
      });
    });
    $this->group('/suppliers', function () use ($container) {
      $this->get('', 'erp.cadastre.suppliers.controller:show')
           ->setName('ERP\Cadastre\Suppliers');
      $this->patch('/get', 'erp.cadastre.suppliers.controller:get')
           ->setName('ERP\Cadastre\Suppliers\Get');
      $this->map(['GET', 'POST'], '/add','erp.cadastre.suppliers.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\Suppliers\Add');
      $this->map(['GET', 'PUT'], '/edit/{ supplierID }','erp.cadastre.suppliers.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\Suppliers\Edit');
      $this->delete('/delete/{ supplierID }','erp.cadastre.suppliers.controller:delete')
           ->setName('ERP\Cadastre\Suppliers\Delete');
      $this->put('/toggleblocked/{ supplierID }[/{ subsidiaryID }]','erp.cadastre.suppliers.controller:toggleBlocked')
           ->setName('ERP\Cadastre\Suppliers\ToggleBlocked');
      $this->get('/pdf/{ supplierID }[/{ subsidiaryID }]','erp.cadastre.suppliers.controller:getPDF')
           ->setName('ERP\Cadastre\Suppliers\Get\PDF');
      $this->get('/hasoneormore', 'erp.cadastre.suppliers.controller:hasOneOrMore')
           ->setName('ERP\Cadastre\Suppliers\HasOneOrMore');
    });
    $this->group('/serviceproviders', function () use ($container) {
      $this->get('', 'erp.cadastre.serviceproviders.controller:show')
           ->setName('ERP\Cadastre\ServiceProviders');
      $this->patch('/get', 'erp.cadastre.serviceproviders.controller:get')
           ->setName('ERP\Cadastre\ServiceProviders\Get');
      $this->map(['GET', 'POST'], '/add','erp.cadastre.serviceproviders.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\ServiceProviders\Add');
      $this->map(['GET', 'PUT'], '/edit/{ serviceProviderID }','erp.cadastre.serviceproviders.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\ServiceProviders\Edit');
      $this->delete('/delete/{ serviceProviderID }','erp.cadastre.serviceproviders.controller:delete')
           ->setName('ERP\Cadastre\ServiceProviders\Delete');
      $this->put('/toggleblocked/{ serviceProviderID }[/{ subsidiaryID }]','erp.cadastre.serviceproviders.controller:toggleBlocked')
           ->setName('ERP\Cadastre\ServiceProviders\ToggleBlocked');
      $this->get('/pdf/{ serviceProviderID }[/{ subsidiaryID }]','erp.cadastre.serviceproviders.controller:getPDF')
           ->setName('ERP\Cadastre\ServiceProviders\Get\PDF');
      $this->get('/hasoneormore', 'erp.cadastre.serviceproviders.controller:hasOneOrMore')
           ->setName('ERP\Cadastre\ServiceProviders\HasOneOrMore');

      $this->group('/technicians', function () use ($container) {
        $this->map(['GET', 'POST'], '/add/{ serviceProviderID }','erp.cadastre.technicians.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Cadastre\ServiceProviders\Technicians\Add');
        $this->map(['GET', 'PUT'], '/edit/{ serviceProviderID }/{ technicianID }','erp.cadastre.technicians.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Cadastre\ServiceProviders\Technicians\Edit');
        $this->delete('/delete/{ serviceProviderID }/{ technicianID }','erp.cadastre.technicians.controller:delete')
             ->setName('ERP\Cadastre\ServiceProviders\Technicians\Delete');
        $this->put('/toggleblocked/{ serviceProviderID }/{ technicianID }','erp.cadastre.technicians.controller:toggleBlocked')
             ->setName('ERP\Cadastre\ServiceProviders\Technicians\ToggleBlocked');
      });
    });
    $this->group('/sellers', function () use ($container) {
      $this->get('', 'erp.cadastre.sellers.controller:show')
           ->setName('ERP\Cadastre\Sellers');
      $this->patch('/get', 'erp.cadastre.sellers.controller:get')
           ->setName('ERP\Cadastre\Sellers\Get');
      $this->map(['GET', 'POST'], '/add','erp.cadastre.sellers.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\Sellers\Add');
      $this->map(['GET', 'PUT'], '/edit/{ sellerID }','erp.cadastre.sellers.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\Sellers\Edit');
      $this->delete('/delete/{ sellerID }','erp.cadastre.sellers.controller:delete')
           ->setName('ERP\Cadastre\Sellers\Delete');
      $this->put('/toggleblocked/{ sellerID }[/{ subsidiaryID }]','erp.cadastre.sellers.controller:toggleBlocked')
           ->setName('ERP\Cadastre\Sellers\ToggleBlocked');
      $this->get('/pdf/{ sellerID }[/{ subsidiaryID }]','erp.cadastre.sellers.controller:getPDF')
           ->setName('ERP\Cadastre\Sellers\Get\PDF');
      $this->get('/hasoneormore', 'erp.cadastre.sellers.controller:hasOneOrMore')
           ->setName('ERP\Cadastre\Sellers\HasOneOrMore');
    });
    $this->group('/rapidresponses', function () use ($container) {
      $this->get('', 'erp.cadastre.rapidresponses.controller:show')
           ->setName('ERP\Cadastre\RapidResponses');
      $this->patch('/get', 'erp.cadastre.rapidresponses.controller:get')
           ->setName('ERP\Cadastre\RapidResponses\Get');
      $this->map(['GET', 'POST'], '/add','erp.cadastre.rapidresponses.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\RapidResponses\Add');
      $this->map(['GET', 'PUT'], '/edit/{ rapidResponseID }','erp.cadastre.rapidresponses.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\RapidResponses\Edit');
      $this->delete('/delete/{ rapidResponseID }','erp.cadastre.rapidresponses.controller:delete')
           ->setName('ERP\Cadastre\RapidResponses\Delete');
      $this->put('/toggleblocked/{ rapidResponseID }[/{ subsidiaryID }]','erp.cadastre.rapidresponses.controller:toggleBlocked')
           ->setName('ERP\Cadastre\RapidResponses\ToggleBlocked');
      $this->get('/pdf/{ rapidResponseID }[/{ subsidiaryID }]','erp.cadastre.rapidresponses.controller:getPDF')
           ->setName('ERP\Cadastre\RapidResponses\Get\PDF');
      $this->get('/hasoneormore', 'erp.cadastre.rapidresponses.controller:hasOneOrMore')
           ->setName('ERP\Cadastre\RapidResponses\HasOneOrMore');
    });
    $this->group('/vehicles', function () use ($container) {
      $this->get('', 'erp.cadastre.vehicles.controller:show')
           ->setName('ERP\Cadastre\Vehicles');
      $this->patch('/get', 'erp.cadastre.vehicles.controller:get')
           ->setName('ERP\Cadastre\Vehicles\Get');
      $this->map(['GET', 'POST'], '/add','erp.cadastre.vehicles.controller:add')
           ->setName('ERP\Cadastre\Vehicles\Add');
      $this->map(['GET', 'PUT'], '/edit/{ vehicleID }','erp.cadastre.vehicles.controller:edit')
           ->setName('ERP\Cadastre\Vehicles\Edit');
      $this->delete('/delete/{ vehicleID }','erp.cadastre.vehicles.controller:delete')
           ->setName('ERP\Cadastre\Vehicles\Delete');
      $this->patch('maildata', 'erp.cadastre.vehicles.controller:getMailData')
           ->setName('ERP\Cadastre\Vehicles\Get\MailData');
      $this->map(['GET', 'PUT'], '/attach/{ vehicleID }[/{ equipmentID }]','erp.cadastre.vehicles.controller:attach')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\Vehicles\Attach');
      $this->delete('/detach/{ equipmentID }','erp.cadastre.vehicles.controller:detach')
           ->setName('ERP\Cadastre\Vehicles\Detach');
      $this->put('/toggleblocked/{ vehicleID }[/{ subsidiaryID }]','erp.cadastre.vehicles.controller:toggleBlocked')
           ->setName('ERP\Cadastre\Vehicles\ToggleBlocked');
      $this->put('/togglemonitored/{ vehicleID }[/{ customerID }/{ subsidiaryID }/{ associationID }/{ associationUnityID }]','erp.cadastre.vehicles.controller:toggleMonitored')
           ->setName('ERP\Cadastre\Vehicles\ToggleMonitored');
      $this->get('/pdf/{ customerID }/{ subsidiaryID }[/{ vehicleID }]','erp.cadastre.vehicles.controller:getPDF')
           ->setName('ERP\Cadastre\Vehicles\Get\PDF');
      $this->patch('/autocompletion/get', 'erp.cadastre.vehicles.controller:getAutocompletionData')
           ->setName('ERP\Cadastre\Vehicles\Autocompletion\Get');
      $this->get('/attachments/get/{ operation }/{ attachmentID }', 'erp.cadastre.vehicles.controller:getAttachment')
           ->setName('ERP\Cadastre\Vehicles\Attachments\Get');
      $this->delete('/attachments/delete/{ attachmentID }','erp.cadastre.vehicles.controller:deleteAttachment')
           ->setName('ERP\Cadastre\Vehicles\Attachments\Delete');
      $this->get('/attachments/pdf/{ attachmentID }','erp.cadastre.vehicles.controller:getAttachmentPDF')
           ->setName('ERP\Cadastre\Vehicles\Attachments\Get\PDF');
      $this->get('/attachments/thumbnail/{ filename }','erp.cadastre.vehicles.controller:thumbnailAttachment')
           ->setName('ERP\Cadastre\Vehicles\Attachments\Thumbnail');
    });
    $this->group('/users', function () use ($container) {
      $this->get('', 'erp.cadastre.users.controller:show')
           ->setName('ERP\Cadastre\Users');
      $this->patch('/get', 'erp.cadastre.users.controller:get')
           ->setName('ERP\Cadastre\Users\Get');
      $this->map(['GET', 'POST'], '/add','erp.cadastre.users.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\Users\Add');
      $this->map(['GET', 'PUT'], '/edit/{ userID }','erp.cadastre.users.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Cadastre\Users\Edit');
      $this->delete('/delete/{ userID }','erp.cadastre.users.controller:delete')
           ->setName('ERP\Cadastre\Users\Delete');
      $this->put('/toggleforcenewpassword/{ userID }','erp.cadastre.users.controller:toggleForceNewPassword')
           ->setName('ERP\Cadastre\Users\ToggleForceNewPassword');
      $this->put('/toggleblocked/{ userID }','erp.cadastre.users.controller:toggleBlocked')
           ->setName('ERP\Cadastre\Users\ToggleBlocked');
      $this->put('/togglesuspended/{ userID }','erp.cadastre.users.controller:toggleSuspended')
           ->setName('ERP\Cadastre\Users\ToggleSuspended');
    });
  });

  // ----------------------------------------------[ Dispositivos ]-----
  $this->group('/devices', function () use ($container) {
    $this->group('/simcards', function () use ($container) {
      $this->get('', 'erp.devices.simcards.controller:show')
           ->setName('ERP\Devices\SimCards');
      $this->patch('/get', 'erp.devices.simcards.controller:get')
           ->setName('ERP\Devices\SimCards\Get');
      $this->map(['GET', 'POST'], '/add','erp.devices.simcards.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Devices\SimCards\Add');
      $this->map(['GET', 'PUT'], '/edit/{ simcardID }','erp.devices.simcards.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Devices\SimCards\Edit');
      $this->delete('/delete/{ simcardID }','erp.devices.simcards.controller:delete')
           ->setName('ERP\Devices\SimCards\Delete');
      $this->put('/toggleblocked/{ simcardID }','erp.devices.simcards.controller:toggleBlocked')
           ->setName('ERP\Devices\SimCards\ToggleBlocked');
      $this->group('/history', function () use ($container) {
        $this->get('/{ simcardID }', 'erp.devices.simcards.controller:showHistory')
             ->setName('ERP\Devices\SimCards\History');
        $this->patch('/{ simcardID }/get','erp.devices.simcards.controller:getHistory')
             ->setName('ERP\Devices\SimCards\History\Get');
      });
      $this->get('/pdf/{ supplierID }/{ subsidiaryID }[/{ simcardID }]','erp.devices.simcards.controller:getPDF')
           ->setName('ERP\Devices\SimCards\Get\PDF');
      $this->patch('/autocompletion/get', 'erp.devices.simcards.controller:getAutocompletionData')
           ->setName('ERP\Devices\SimCards\Autocompletion\Get');
      $this->get('/hasoneormore', 'erp.devices.simcards.controller:hasOneOrMore')
           ->setName('ERP\Devices\SimCards\HasOneOrMore');
    });
    $this->group('/equipments', function () use ($container) {
      $this->get('', 'erp.devices.equipments.controller:show')
           ->setName('ERP\Devices\Equipments');
      $this->patch('/get', 'erp.devices.equipments.controller:get')
           ->setName('ERP\Devices\Equipments\Get');
      $this->map(['GET', 'POST'], '/add','erp.devices.equipments.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Devices\Equipments\Add');
      $this->map(['GET', 'PUT'], '/edit/{ equipmentID }','erp.devices.equipments.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Devices\Equipments\Edit');
      $this->delete('/delete/{ equipmentID }','erp.devices.equipments.controller:delete')
           ->setName('ERP\Devices\Equipments\Delete');
      $this->map(['GET', 'POST'], '/attach/{ equipmentID }/slot/{ slotNumber }','erp.devices.equipments.controller:slotattach')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Devices\Equipments\Slot\Attach');
      $this->delete('/detach/{ equipmentID }/slot/{ slotNumber }','erp.devices.equipments.controller:slotDetach')
           ->setName('ERP\Devices\Equipments\Slot\Detach');
      $this->put('/toggleblocked/{ equipmentID }','erp.devices.equipments.controller:toggleBlocked')
           ->setName('ERP\Devices\Equipments\ToggleBlocked');
      $this->group('/history', function () use ($container) {
        $this->get('/{ equipmentID }', 'erp.devices.equipments.controller:showHistory')
             ->setName('ERP\Devices\Equipments\History');
        $this->patch('/{ equipmentID }/get','erp.devices.equipments.controller:getHistory')
             ->setName('ERP\Devices\Equipments\History\Get');
      });
      $this->get('/pdf/{ supplierID }/{ subsidiaryID }[/{ equipmentID }]','erp.devices.equipments.controller:getPDF')
           ->setName('ERP\Devices\Equipments\Get\PDF');
      $this->patch('/autocompletion/get', 'erp.devices.equipments.controller:getAutocompletionData')
           ->setName('ERP\Devices\Equipments\Autocompletion\Get');
      $this->get('/hasoneormore', 'erp.devices.equipments.controller:hasOneOrMore')
           ->setName('ERP\Devices\Equipments\HasOneOrMore');
    });
    $this->group('/movimentations', function () use ($container) {
      $this->map(['GET', 'PUT'], '/transfer','erp.devices.movimentations.controller:transfer')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Devices\Movimentations\Transfer');
      $this->patch('/get', 'erp.devices.movimentations.controller:getDevices')
           ->setName('ERP\Devices\Movimentations\Get');
      $this->map(['GET', 'PUT'], '/return','erp.devices.movimentations.controller:return')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Devices\Movimentations\Return');
    });
  });

  // ------------------------------------------------[ Financeiro ]-----
  $this->group('/financial', function () use ($container) {
    $this->group('/plans', function () use ($container) {
      $this->get('', 'erp.financial.plans.controller:show')
           ->setName('ERP\Financial\Plans');
      $this->patch('/get', 'erp.financial.plans.controller:get')
           ->setName('ERP\Financial\Plans\Get');
      $this->map(['GET', 'POST'], '/add','erp.financial.plans.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Financial\Plans\Add');
      $this->map(['GET', 'PUT'], '/edit/{ planID }','erp.financial.plans.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Financial\Plans\Edit');
      $this->delete('/delete/{ planID }','erp.financial.plans.controller:delete')
           ->setName('ERP\Financial\Plans\Delete');
      $this->put('/toggleactive/{ planID }', 'erp.financial.plans.controller:toggleActive')
           ->setName('ERP\Financial\Plans\ToggleActive');
    });
    $this->group('/contracts', function () use ($container) {
      $this->get('', 'erp.financial.contracts.controller:show')
           ->setName('ERP\Financial\Contracts');
      $this->patch('/get', 'erp.financial.contracts.controller:get')
           ->setName('ERP\Financial\Contracts\Get');
      $this->map(['GET', 'POST'], '/add[/{ customerID }]','erp.financial.contracts.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Financial\Contracts\Add');
      $this->map(['GET', 'PUT'], '/edit/{ contractID }','erp.financial.contracts.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Financial\Contracts\Edit');
      $this->delete('/delete/{ contractID }','erp.financial.contracts.controller:delete')
           ->setName('ERP\Financial\Contracts\Delete');
      $this->put('/toggleactive/{ contractID }', 'erp.financial.contracts.controller:toggleActive')
           ->setName('ERP\Financial\Contracts\ToggleActive');
      $this->patch('/autocompletion/get', 'erp.financial.contracts.controller:getAutocompletionData')
         ->setName('ERP\Financial\Contracts\Autocompletion\Get');

      $this->group('/installations', function () use ($container) {
        $this->patch('/get', 'erp.financial.installations.controller:get')
             ->setName('ERP\Financial\Contracts\Installations\Get');
        $this->post('/add/{ contractID }','erp.financial.installations.controller:add')
             ->setName('ERP\Financial\Contracts\Installations\Add');
        $this->map(['GET', 'PUT'], '/edit/{ installationID }','erp.financial.installations.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('ERP\Financial\Contracts\Installations\Edit');
        $this->delete('/delete/{ installationID }','erp.financial.installations.controller:delete')
             ->setName('ERP\Financial\Contracts\Installations\Delete');
      });
    });
    $this->group('/billings', function () use ($container) {
      $this->get('', 'erp.financial.billings.controller:show')
           ->setName('ERP\Financial\Billings');
      $this->patch('/get', 'erp.financial.billings.controller:get')
           ->setName('ERP\Financial\Billings\Get');
      $this->map(['GET', 'POST'], '/add','erp.financial.billings.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Financial\Billings\Add');
      $this->map(['GET', 'PUT'], '/edit/{ billingID }','erp.financial.billings.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Financial\Billings\Edit');
      $this->delete('/delete/{ billingID }','erp.financial.billings.controller:delete')
           ->setName('ERP\Financial\Billings\Delete');
      $this->put('/grante/{ billingID }', 'erp.financial.billings.controller:grante')
           ->setName('ERP\Financial\Billings\Grante');
      $this->map(['GET', 'POST'], '/renegotiate/{ billingID }','erp.financial.billings.controller:renegotiate')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Financial\Billings\Renegotiate');
    });
    $this->group('/monthlycalculations', function () use ($container) {
      $this->get('', 'erp.financial.monthlycalculations.controller:show')
           ->setName('ERP\Financial\MonthlyCalculations');
      $this->patch('/get', 'erp.financial.monthlycalculations.controller:get')
           ->setName('ERP\Financial\MonthlyCalculations\Get');
      $this->put('/start', 'erp.financial.monthlycalculations.controller:start')
           ->setName('ERP\Financial\MonthlyCalculations\Start');
      $this->delete('/discard','erp.financial.monthlycalculations.controller:discard')
           ->setName('ERP\Financial\MonthlyCalculations\Discard');
      $this->get('/detail', 'erp.financial.monthlycalculations.controller:detail')
           ->setName('ERP\Financial\MonthlyCalculations\Detail');
      $this->put('/recalculate', 'erp.financial.monthlycalculations.controller:recalculate')
           ->setName('ERP\Financial\MonthlyCalculations\Recalculate');
      $this->put('/finish', 'erp.financial.monthlycalculations.controller:finish')
           ->setName('ERP\Financial\MonthlyCalculations\Finish');
      $this->get('/pdf','erp.financial.monthlycalculations.controller:getPDF')
           ->setName('ERP\Financial\MonthlyCalculations\Get\PDF');
      $this->map(['GET', 'POST'], '/add','erp.financial.monthlycalculations.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Financial\MonthlyCalculations\Add');
      $this->map(['GET', 'PUT'], '/edit/{ billingID }','erp.financial.monthlycalculations.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Financial\MonthlyCalculations\Edit');
      $this->delete('/delete/{ billingID }','erp.financial.monthlycalculations.controller:delete')
           ->setName('ERP\Financial\MonthlyCalculations\Delete');
      $this->put('/grante/{ billingID }', 'erp.financial.monthlycalculations.controller:grante')
           ->setName('ERP\Financial\MonthlyCalculations\Grante');
    });
    $this->group('/payments', function () use ($container) {
      $this->get('', 'erp.financial.payments.controller:show')
           ->setName('ERP\Financial\Payments');
      $this->map(['GET', 'POST'], '/add','erp.financial.payments.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Financial\Payments\Add');
      $this->map(['GET', 'PUT'], '/edit/{ paymentID }','erp.financial.payments.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Financial\Payments\Edit');
      $this->delete('/delete/{ paymentID }','erp.financial.payments.controller:delete')
           ->setName('ERP\Financial\Payments\Delete');
      $this->put('/drop/{ paymentID }', 'erp.financial.payments.controller:drop')
           ->setName('ERP\Financial\Payments\Drop');
      $this->map(['GET', 'POST'], '/renegotiate/{ paymentID }','erp.financial.payments.controller:renegotiate')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('ERP\Financial\Payments\Renegotiate');
      $this->group('/get', function () use ($container) {
        $this->patch('', 'erp.financial.payments.controller:get')
             ->setName('ERP\Financial\Payments\Get');
        $this->get('billingphonelist','erp.financial.payments.controller:getBillingPhoneList')
             ->setName('ERP\Financial\Payments\Get\BillingPhoneList');
        $this->get('pdf[/{ paymentID }]','erp.financial.payments.controller:getPDF')
             ->setName('ERP\Financial\Payments\Get\PDF');
        $this->patch('digitableline', 'erp.financial.payments.controller:getDigitableLine')
             ->setName('ERP\Financial\Payments\Get\DigitableLine');
        $this->patch('downloadablelink', 'erp.financial.payments.controller:getDownloadableLink')
             ->setName('ERP\Financial\Payments\Get\DownloadableLink');
        $this->patch('maildata', 'erp.financial.payments.controller:getMailData')
             ->setName('ERP\Financial\Payments\Get\MailData');
        $this->patch('tariffdata', 'erp.financial.payments.controller:getTariffData')
             ->setName('ERP\Financial\Payments\Get\TariffData');
        $this->patch('historydata', 'erp.financial.payments.controller:getHistoryData')
             ->setName('ERP\Financial\Payments\Get\HistoryData');
      });
      $this->group('/send', function () use ($container) {
        $this->put('/mail', 'erp.financial.payments.controller:sendByMail')
             ->setName('ERP\Financial\Payments\Send\Mail');
        $this->put('/sms', 'erp.financial.payments.controller:sendBySMS')
             ->setName('ERP\Financial\Payments\Send\SMS');
      });
      $this->group('/cnab', function () use ($container) {
        $this->group('/shippingfile', function () use ($container) {
          $this->get('', 'erp.financial.cnab.controller:shippingFile')
               ->setName('ERP\Financial\Payments\CNAB\ShippingFile');
          $this->patch('/get', 'erp.financial.cnab.controller:getShippingFiles')
               ->setName('ERP\Financial\Payments\CNAB\ShippingFile\Get');
          $this->get('/download[/{fileID}]', 'erp.financial.cnab.controller:getShippingFile')
               ->setName('ERP\Financial\Payments\CNAB\ShippingFile\Download');
        });
        $this->group('/returnfile', function () use ($container) {
          $this->get('', 'erp.financial.cnab.controller:returnFile')
               ->setName('ERP\Financial\Payments\CNAB\ReturnFile');
          $this->patch('/get', 'erp.financial.cnab.controller:getReturnFiles')
               ->setName('ERP\Financial\Payments\CNAB\ReturnFile\Get');
          $this->post('/process','erp.financial.cnab.controller:processReturnFile')
               ->setName('ERP\Financial\Payments\CNAB\ReturnFile\Process');
        });
      });
    });
  });
})->add($container['auth.middleware']);


// =========================================================[ ERP ]=====
// As rotas para o sistema ERP

// ------------------------------------------------[ Autenticação ]-----
// Rotas sem necessidade de autenticação
$app->group('/stc', function () use ($container) {
  $this->map(['GET', 'POST'], '/login[/{ UUID }]', 'stc.auth.controller:login')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('STC\Login');
})->add($container['guest.middleware']);

// ----------------------------------------------------[ Recursos ]-----
// Rotas públicas
$app->group('/stc', function () use ($container) {
  $this->group('/resources', function () use ($container) {
    $this->group('/logo', function () use ($container) {
      $this->get('/normal/{ UUID }', 'stc.cadastre.entities.controller:getNormalLogo')
           ->setName('STC\Resources\Logo\Normal');
      $this->get('/inverted/{ UUID }', 'stc.cadastre.entities.controller:getInvertedLogo')
           ->setName('STC\Resources\Logo\Inverted');
    });
  });
})->add($container['public.middleware']);

// Rotas com necessidade de autenticação
$app->group('/stc', function () use ($container) {
  // ------------------------------------------------------[ Base ]-----
  $this->get('', 'stc.home.controller:home')
       ->setName('STC\Home');
  $this->get('/about', 'stc.home.controller:about')
       ->add($container['cache'])
       ->setName('STC\About');
  $this->get('/privacity', 'stc.home.controller:privacity')
       ->add($container['cache'])
       ->setName('STC\Privacity');
  $this->map(['GET', 'PUT'], '/account', 'stc.auth.controller:account')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('STC\Account');
  $this->map(['GET', 'PUT'], '/password', 'stc.auth.controller:password')
       ->add($container['csrf'])
       ->add($container['trimmer'])
       ->setName('STC\Password');
  $this->get('/logout', 'stc.auth.controller:logout')
       ->setName('STC\Logout');

  // --------------------------------------------[ Parametrização ]-----
  $this->group('/parameterization', function () use ($container) {
    $this->group('/cadastral', function () use ($container) {
      $this->group('/cities', function () use ($container) {
        $this->get('', 'stc.parameterization.cadastral.cities.controller:show')
             ->setName('STC\Parameterization\Cadastral\Cities');
        $this->patch('/get', 'stc.parameterization.cadastral.cities.controller:get')
             ->setName('STC\Parameterization\Cadastral\Cities\Get');
        $this->get('/synchronize', 'stc.parameterization.cadastral.cities.controller:synchronize')
             ->setName('STC\Parameterization\Cadastral\Cities\Synchronize');
        $this->patch('/autocompletion/get', 'stc.parameterization.cadastral.cities.controller:getAutocompletionData')
             ->setName('STC\Parameterization\Cadastral\Cities\Autocompletion\Get');
      });
      $this->group('/journeys', function () use ($container) {
        $this->get('', 'stc.parameterization.cadastral.journeys.controller:show')
             ->setName('STC\Parameterization\Cadastral\Journeys');
        $this->patch('/get', 'stc.parameterization.cadastral.journeys.controller:get')
             ->setName('STC\Parameterization\Cadastral\Journeys\Get');
        $this->map(['GET', 'POST'], '/add','stc.parameterization.cadastral.journeys.controller:add')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('STC\Parameterization\Cadastral\Journeys\Add');
        $this->map(['GET', 'PUT'], '/edit/{ journeyID }','stc.parameterization.cadastral.journeys.controller:edit')
             ->add($container['csrf'])
             ->add($container['trimmer'])
             ->setName('STC\Parameterization\Cadastral\Journeys\Edit');
        $this->delete('/delete/{ journeyID }','stc.parameterization.cadastral.journeys.controller:delete')
             ->setName('STC\Parameterization\Cadastral\Journeys\Delete');
        $this->put('/toggleblocked/{ journeyID }','stc.parameterization.cadastral.journeys.controller:toggleDefault')
             ->setName('STC\Parameterization\Cadastral\Journeys\ToggleDefault');
      });
    });
    $this->group('/vehicles', function () use ($container) {
      $this->group('/types', function () use ($container) {
        $this->get('', 'stc.parameterization.vehicles.types.controller:show')
             ->setName('STC\Parameterization\Vehicles\Types');
        $this->patch('/get', 'stc.parameterization.vehicles.types.controller:get')
             ->setName('STC\Parameterization\Vehicles\Types\Get');
        $this->get('/synchronize', 'stc.parameterization.vehicles.types.controller:synchronize')
             ->setName('STC\Parameterization\Vehicles\Types\Synchronize');
      });
      $this->group('/brands', function () use ($container) {
        $this->get('', 'stc.parameterization.vehicles.brands.controller:show')
             ->setName('STC\Parameterization\Vehicles\Brands');
        $this->patch('/get', 'stc.parameterization.vehicles.brands.controller:get')
             ->setName('STC\Parameterization\Vehicles\Brands\Get');
        $this->get('/synchronize', 'stc.parameterization.vehicles.brands.controller:synchronize')
             ->setName('STC\Parameterization\Vehicles\Brands\Synchronize');
        $this->patch('/autocompletion/get', 'stc.parameterization.vehicles.brands.controller:getAutocompletionData')
             ->setName('STC\Parameterization\Vehicles\Brands\Autocompletion\Get');
      });
      $this->group('/vehiclemodels', function () use ($container) {
        $this->get('', 'stc.parameterization.vehicles.models.controller:show')
             ->setName('STC\Parameterization\Vehicles\Models');
        $this->patch('/get', 'stc.parameterization.vehicles.models.controller:get')
             ->setName('STC\Parameterization\Vehicles\Models\Get');
        $this->get('/synchronize', 'stc.parameterization.vehicles.models.controller:synchronize')
             ->setName('STC\Parameterization\Vehicles\Models\Synchronize');
      });
    });
    $this->group('/equipments', function () use ($container) {
      $this->group('/manufactures', function () use ($container) {
        $this->get('', 'stc.parameterization.equipments.manufactures.controller:show')
             ->setName('STC\Parameterization\Equipments\Manufactures');
        $this->patch('/get', 'stc.parameterization.equipments.manufactures.controller:get')
             ->setName('STC\Parameterization\Equipments\Manufactures\Get');
        $this->get('/synchronize', 'stc.parameterization.equipments.manufactures.controller:synchronize')
             ->setName('STC\Parameterization\Equipments\Manufactures\Synchronize');
        $this->patch('/autocompletion/get', 'stc.parameterization.equipments.manufactures.controller:getAutocompletionData')
             ->setName('STC\Parameterization\Equipments\Manufactures\Autocompletion\Get');
      });
    });
  });
  
  // --------------------------------------------------[ Cadastro ]-----
  $this->group('/cadastre', function () use ($container) {
    $this->group('/customers', function () use ($container) {
      $this->get('', 'stc.cadastre.customers.controller:show')
           ->setName('STC\Cadastre\Customers');
      $this->patch('/get', 'stc.cadastre.customers.controller:get')
           ->setName('STC\Cadastre\Customers\Get');
      $this->get('/view/{ clientID }','stc.cadastre.customers.controller:view')
           ->setName('STC\Cadastre\Customers\View');
      $this->get('/synchronize', 'stc.cadastre.customers.controller:synchronize')
           ->setName('STC\Cadastre\Customers\Synchronize');
      $this->patch('/autocompletion/get', 'stc.cadastre.customers.controller:getAutocompletionData')
           ->setName('STC\Cadastre\Customers\Autocompletion\Get');
      $this->put('/togglegetpositions/{ clientID }[/{ subsidiaryID }]','stc.cadastre.customers.controller:toggleGetPositions')
           ->setName('STC\Cadastre\Customers\ToggleGetPositions');
    });
    $this->group('/drivers', function () use ($container) {
      $this->get('', 'stc.cadastre.drivers.controller:show')
           ->setName('STC\Cadastre\Drivers');
      $this->patch('/get', 'stc.cadastre.drivers.controller:get')
           ->setName('STC\Cadastre\Drivers\Get');
      $this->map(['GET', 'POST'], '/add','stc.cadastre.drivers.controller:add')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('STC\Cadastre\Drivers\Add');
      $this->map(['GET', 'PUT'], '/edit/{ clientID }/{ driverID }','stc.cadastre.drivers.controller:edit')
           ->add($container['csrf'])
           ->add($container['trimmer'])
           ->setName('STC\Cadastre\Drivers\Edit');
      $this->delete('/delete/{ clientID }/{ driverID }','stc.cadastre.drivers.controller:delete')
           ->setName('STC\Cadastre\Drivers\Delete');
      $this->patch('/autocompletion/get', 'stc.cadastre.drivers.controller:getAutocompletionData')
           ->setName('STC\Cadastre\Drivers\Autocompletion\Get');
      $this->get('/send', 'stc.cadastre.drivers.controller:sendDriver')
           ->setName('STC\Cadastre\Drivers\Send');
    });
    $this->group('/vehicles', function () use ($container) {
      $this->get('', 'stc.cadastre.vehicles.controller:show')
           ->setName('STC\Cadastre\Vehicles');
      $this->patch('/get', 'stc.cadastre.vehicles.controller:get')
           ->setName('STC\Cadastre\Vehicles\Get');
      $this->get('/view/{ vehicleID }','stc.cadastre.vehicles.controller:view')
           ->setName('STC\Cadastre\Vehicles\View');
      $this->get('/synchronize', 'stc.cadastre.vehicles.controller:synchronize')
           ->setName('STC\Cadastre\Vehicles\Synchronize');
      $this->patch('/autocompletion/get', 'stc.cadastre.vehicles.controller:getAutocompletionData')
           ->setName('STC\Cadastre\Vehicles\Autocompletion\Get');
    });
    $this->group('/equipments', function () use ($container) {
      $this->get('', 'stc.cadastre.equipments.controller:show')
           ->setName('STC\Cadastre\Equipments');
      $this->patch('/get', 'stc.cadastre.equipments.controller:get')
           ->setName('STC\Cadastre\Equipments\Get');
      $this->get('/synchronize', 'stc.cadastre.equipments.controller:synchronize')
           ->setName('STC\Cadastre\Equipments\Synchronize');
      $this->get('/{ equipmentID }/drivers', 'stc.cadastre.equipments.controller:driversshow')
           ->setName('STC\Cadastre\Equipments\Drivers');
      $this->get('/{ equipmentID }/drivers/synchronize', 'stc.cadastre.equipments.controller:driverssynchronize')
           ->setName('STC\Cadastre\Equipments\Drivers\Synchronize');
    });
  });
  
  // -----------------------------------------------------[ Dados ]-----
  $this->group('/data', function () use ($container) {
    $this->group('/positions', function () use ($container) {
      $this->get('', 'stc.data.positions.controller:show')
           ->setName('STC\Data\Positions');
      $this->patch('/get', 'stc.data.positions.controller:get')
           ->setName('STC\Data\Positions\Get');
      $this->get('/view/{ customerID }','stc.data.positions.controller:view')
           ->setName('STC\Data\Positions\View');
      $this->get('/synchronize', 'stc.data.positions.controller:synchronize')
           ->setName('STC\Data\Positions\Synchronize');
    });
  });

  // ------------------------------------------------[ Relatórios ]-----
  $this->group('/reports', function () use ($container) {
    $this->group('/roadtrips', function () use ($container) {
      $this->get('', 'stc.report.roadtrips.controller:show')
           ->setName('STC\Report\RoadTrips');
      $this->patch('/get', 'stc.report.roadtrips.controller:get')
           ->setName('STC\Report\RoadTrips\Get');
      $this->get('/pdf','stc.report.roadtrips.controller:getPDF')
           ->setName('STC\Report\RoadTrips\Get\PDF');
    });
    $this->group('/workdays', function () use ($container) {
      $this->get('', 'stc.report.workdays.controller:show')
           ->setName('STC\Report\Workdays');
      $this->patch('/get', 'stc.report.workdays.controller:get')
           ->setName('STC\Report\Workdays\Get');
      $this->get('/pdf','stc.report.workdays.controller:getPDF')
           ->setName('STC\Report\Workdays\Get\PDF');
    });
  });

})->add($container['auth.middleware']);


$app->get('/debug', function ($request, $response) {
     return $response->write("Rota funcionando.");
 });


 
