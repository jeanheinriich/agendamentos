-- =====================================================================
-- SEGURANÇA
-- =====================================================================
-- Tabelas utilizada no controle do acesso ao sistema, bem como das
-- permissões.
-- ---------------------------------------------------------------------
-- O controle de acesso se dará por usuário. Cada usuário está vinculado
-- a uma empresa e a um grupo. Um grupo define um conjunto de permissões
-- de acesso ao sistema.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Módulos
-- ---------------------------------------------------------------------
-- Contém os módulos para os quais um usuário possui permissão.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.modules (
  moduleID      serial,         -- O ID do módulo
  name          varchar(50)     -- O nome do módulo
                NOT NULL,
  PRIMARY KEY (moduleID)
);

-- Insere a relação de grupos de usuários disponíveis
INSERT INTO erp.modules (moduleID, name) VALUES
  (1, 'ERP'),
  (2, 'MRFleet Web'),
  (3, 'MRFleet App'),
  (4, 'MRFleet Integração');

ALTER SEQUENCE erp.modules_moduleid_seq RESTART WITH 4;

-- ---------------------------------------------------------------------
-- Grupos de usuários
-- ---------------------------------------------------------------------
-- Contém os grupos de usuários que determinam um nível de acesso a cada
-- usuário à ele pertencente.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.groups (
  groupID       serial,         -- O ID do grupo
  name          varchar(50)     -- O nome do grupo
                NOT NULL,
  administrator boolean         -- A flag indicativa de administrador
                DEFAULT false,
  multientity   boolean         -- A flag indicativa de acesso a mais de
                DEFAULT false,  -- uma entidade
  PRIMARY KEY (groupID)
);

-- Insere a relação de grupos de usuários disponíveis
INSERT INTO erp.groups (groupID, name, administrator, multientity) VALUES
  (1, 'Administrador ERP', true, false),
  (2, 'Administrador Contratante', false, false),
  (3, 'Atendente', false, false),
  (4, 'Operador', false, false),
  (5, 'Técnico', false, false),
  (6, 'Cliente', false, false),
  (7, 'Multicliente', false, true),
  (8, 'Empresa de monitoramento', false, true);

ALTER SEQUENCE erp.groups_groupid_seq RESTART WITH 9;


-- ---------------------------------------------------------------------
-- Permissões
-- ---------------------------------------------------------------------
-- As permissões existentes (módulos do sistema).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.permissions (
  permissionID  serial,       -- O ID da permissão
  name          varchar(100)  -- O nome da permissão
                NOT NULL,
  description   varchar(100)  -- A descrição da permissão
                NOT NULL,
  PRIMARY KEY (permissionID)
);

-- Insere a relação de permissões disponíveis para a administração
INSERT INTO erp.permissions (permissionID, name, description) VALUES
  (   1, 'ADM\Home',
    'Página inicial administração'),
  (   2, 'ADM\About',
    'Página sobre o sistema de ERP'),
  (   3, 'ADM\Privacity',
    'Página sobre a política de privacidade do sistema de ERP'),
  (   4, 'ADM\Account',
    'Página sobre os dados cadastrais do usuário autenticado'),
  (   5, 'ADM\Password',
    'Modificação da senha do usuário'),
  (   6, 'ADM\Logout',
    'Desautenticação do sistema'),
  (   7, 'ADM\Parameterization\Cadastral\Cities',
    'Gerenciamento de cidade'),
  (   8, 'ADM\Parameterization\Cadastral\Cities\Get',
    'Recupera as informações de cidades'),
  (   9, 'ADM\Parameterization\Cadastral\Cities\Add',
    'Adicionar cidade'),
  (  10, 'ADM\Parameterization\Cadastral\Cities\Edit',
    'Editar cidade'),
  (  11, 'ADM\Parameterization\Cadastral\Cities\Delete',
    'Remover cidade'),
  (  12, 'ADM\Parameterization\Cadastral\Cities\Autocompletion\Get',
    'Recupera as informações de uma cidade para um campo de autopreenchimento'),
  (  13, 'ADM\Parameterization\Cadastral\Cities\PostalCode\Get',
    'Recupera as informações de um endereço através do CEP'),
  (  14, 'ADM\Parameterization\Cadastral\DocumentTypes',
    'Gerenciamento de tipos de documentos'),
  (  15, 'ADM\Parameterization\Cadastral\DocumentTypes\Get',
    'Recupera as informações de tipos de documentos'),
  (  16, 'ADM\Parameterization\Cadastral\DocumentTypes\Add',
    'Adicionar tipo de documento'),
  (  17, 'ADM\Parameterization\Cadastral\DocumentTypes\Edit',
    'Editar tipo de documento'),
  (  18, 'ADM\Parameterization\Cadastral\DocumentTypes\Delete',
    'Remover tipo de documento'),
  (  19, 'ADM\Parameterization\Cadastral\MaritalStatus',
    'Gerenciamento de estados civis'),
  (  20, 'ADM\Parameterization\Cadastral\MaritalStatus\Get',
    'Recupera as informações de estados civis'),
  (  21, 'ADM\Parameterization\Cadastral\MaritalStatus\Add',
    'Adicionar estado civil'),
  (  22, 'ADM\Parameterization\Cadastral\MaritalStatus\Edit',
    'Editar estado civil'),
  (  23, 'ADM\Parameterization\Cadastral\MaritalStatus\Delete',
    'Remover estado civil'),
  (  24, 'ADM\Parameterization\Cadastral\Genders',
    'Gerenciamento de gêneros (sexos)'),
  (  25, 'ADM\Parameterization\Cadastral\Genders\Get',
    'Recupera as informações de gêneros (sexos)'),
  (  26, 'ADM\Parameterization\Cadastral\Genders\Add',
    'Adicionar gênero (sexo)'),
  (  27, 'ADM\Parameterization\Cadastral\Genders\Edit',
    'Editar gênero (sexo)'),
  (  28, 'ADM\Parameterization\Cadastral\Genders\Delete',
    'Remover gênero (sexo)'),
  (  29, 'ADM\Parameterization\Cadastral\MeasureTypes',
    'Gerenciamento de tipos de medidas de um valor'),
  (  30, 'ADM\Parameterization\Cadastral\MeasureTypes\Get',
    'Recupera as informações de tipos de medidas de um valor'),
  (  31, 'ADM\Parameterization\Cadastral\MeasureTypes\Add',
    'Adicionar tipo de medida de um valor'),
  (  32, 'ADM\Parameterization\Cadastral\MeasureTypes\Edit',
    'Editar tipo de medida de um valor'),
  (  33, 'ADM\Parameterization\Cadastral\MeasureTypes\Delete',
    'Remover tipo de medida de um valor'),
  (  34, 'ADM\Parameterization\Cadastral\FieldTypes',
    'Gerenciamento de tipos de campos de um formulário'),
  (  35, 'ADM\Parameterization\Cadastral\FieldTypes\Get',
    'Recupera as informações de tipos de campos de um formulário'),
  (  36, 'ADM\Parameterization\Cadastral\FieldTypes\Add',
    'Adicionar tipo de campo de um formulário'),
  (  37, 'ADM\Parameterization\Cadastral\FieldTypes\Edit',
    'Editar tipo de campo de um formulário'),
  (  38, 'ADM\Parameterization\Cadastral\FieldTypes\Delete',
    'Remover tipo de campo de um formulário'),
  (  39, 'ADM\Parameterization\Vehicles\Types',
    'Gerenciamento de tipos de veículos'),
  (  40, 'ADM\Parameterization\Vehicles\Types\Get',
    'Recupera as informações de tipos de veículos'),
  (  41, 'ADM\Parameterization\Vehicles\Types\Add',
    'Adicionar tipo de veículo'),
  (  42, 'ADM\Parameterization\Vehicles\Types\Edit',
    'Editar tipo de veículo'),
  (  43, 'ADM\Parameterization\Vehicles\Types\Delete',
    'Remover tipo de veículo'),
  (  44, 'ADM\Parameterization\Vehicles\Subtypes',
    'Gerenciamento de subtipos de veículos'),
  (  45, 'ADM\Parameterization\Vehicles\Subtypes\Get',
    'Recupera as informações de subtipos de veículos'),
  (  46, 'ADM\Parameterization\Vehicles\Subtypes\Add',
    'Adicionar subtipo de veículo'),
  (  47, 'ADM\Parameterization\Vehicles\Subtypes\Edit',
    'Editar subtipo de veículo'),
  (  48, 'ADM\Parameterization\Vehicles\Subtypes\Delete',
    'Remover subtipo de veículo'),
  (  49, 'ADM\Parameterization\Vehicles\Fuels',
    'Gerenciamento de tipos de combustível'),
  (  50, 'ADM\Parameterization\Vehicles\Fuels\Get',
    'Recupera as informações de tipos de combustível'),
  (  51, 'ADM\Parameterization\Vehicles\Fuels\Add',
    'Adicionar tipo de combustível'),
  (  52, 'ADM\Parameterization\Vehicles\Fuels\Edit',
    'Editar tipo de combustível'),
  (  53, 'ADM\Parameterization\Vehicles\Fuels\Delete',
    'Remover tipo de combustível'),
  (  54, 'ADM\Parameterization\Vehicles\Colors',
    'Gerenciamento de cores de veículos'),
  (  55, 'ADM\Parameterization\Vehicles\Colors\Get',
    'Recupera as informações de cores de veículos'),
  (  56, 'ADM\Parameterization\Vehicles\Colors\Add',
    'Adicionar cor de veículo'),
  (  57, 'ADM\Parameterization\Vehicles\Colors\Edit',
    'Editar cor de veículo'),
  (  58, 'ADM\Parameterization\Vehicles\Colors\Delete',
    'Remover cor de veículo'),
  (  59, 'ADM\Parameterization\Devices\Accessories\Types',
    'Gerenciamento de tipos de acessórios'),
  (  60, 'ADM\Parameterization\Devices\Accessories\Types\Get',
    'Recupera as informações de tipos de acessórios'),
  (  61, 'ADM\Parameterization\Devices\Accessories\Types\Add',
    'Adicionar tipo de acessório'),
  (  62, 'ADM\Parameterization\Devices\Accessories\Types\Edit',
    'Editar tipo de acessório'),
  (  63, 'ADM\Parameterization\Devices\Accessories\Types\Delete',
    'Remover tipo de acessório'),
  (  64, 'ADM\Parameterization\Devices\Features',
    'Gerenciamento de características técnicas'),
  (  65, 'ADM\Parameterization\Devices\Features\Get',
    'Recupera as informações de características técnicas'),
  (  66, 'ADM\Parameterization\Devices\Features\Add',
    'Adicionar característica técnica'),
  (  67, 'ADM\Parameterization\Devices\Features\Edit',
    'Editar característica técnica'),
  (  68, 'ADM\Parameterization\Devices\Features\Delete',
    'Remover característica técnica'),
  (  69, 'ADM\Parameterization\Devices\Brands',
    'Gerenciamento de marcas de equipamentos'),
  (  70, 'ADM\Parameterization\Devices\Brands\Get',
    'Recupera as informações de marcas de equipamentos'),
  (  71, 'ADM\Parameterization\Devices\Brands\Add',
    'Adicionar marca de equipamento'),
  (  72, 'ADM\Parameterization\Devices\Brands\Edit',
    'Editar marca de equipamento'),
  (  73, 'ADM\Parameterization\Devices\Brands\Delete',
    'Remover marca de equipamento'),
  (  74, 'ADM\Parameterization\Devices\Brands\Autocompletion\Get',
    'Recupera as informações de uma marca de equipamento para um campo de autopreenchimento'),
  (  75, 'ADM\Parameterization\Devices\Models',
    'Gerenciamento de modelos de equipamentos'),
  (  76, 'ADM\Parameterization\Devices\Models\Get',
    'Recupera as informações de modelos de equipamentos'),
  (  77, 'ADM\Parameterization\Devices\Models\Add',
    'Adicionar modelo de equipamento'),
  (  78, 'ADM\Parameterization\Devices\Models\Edit',
    'Editar modelo de equipamento'),
  (  79, 'ADM\Parameterization\Devices\Models\Delete',
    'Remover modelo de equipamento'),
  (  80, 'ADM\Parameterization\Devices\Models\Autocompletion\Get',
    'Recupera as informações de um modelo de equipamento para um campo de autopreenchimento'),
  (  81, 'ADM\Parameterization\Telephony\PhoneTypes',
    'Gerenciamento de tipos de telefones'),
  (  82, 'ADM\Parameterization\Telephony\PhoneTypes\Get',
    'Recupera as informações de tipos de telefones'),
  (  83, 'ADM\Parameterization\Telephony\PhoneTypes\Add',
    'Adicionar tipo de telefones'),
  (  84, 'ADM\Parameterization\Telephony\PhoneTypes\Edit',
    'Editar tipo de telefones'),
  (  85, 'ADM\Parameterization\Telephony\PhoneTypes\Delete',
    'Remover tipo de telefones'),
  (  86, 'ADM\Parameterization\Telephony\MobileOperators',
    'Gerenciamento de operadoras de telefonia móvel'),
  (  87, 'ADM\Parameterization\Telephony\MobileOperators\Get',
    'Recupera as informações de operadoras de telefonia móvel'),
  (  88, 'ADM\Parameterization\Telephony\MobileOperators\Add',
    'Adicionar operadora de telefonia móvel'),
  (  89, 'ADM\Parameterization\Telephony\MobileOperators\Edit',
    'Editar operadora de telefonia móvel'),
  (  90, 'ADM\Parameterization\Telephony\MobileOperators\Delete',
    'Remover operadora de telefonia móvel'),
  (  91, 'ADM\Parameterization\Telephony\SimCardTypes',
    'Gerenciamento de modelos de Sim/Card'),
  (  92, 'ADM\Parameterization\Telephony\SimCardTypes\Get',
    'Recupera as informações de modelos de Sim/Card'),
  (  93, 'ADM\Parameterization\Telephony\SimCardTypes\Add',
    'Adicionar modelo de Sim/Card'),
  (  94, 'ADM\Parameterization\Telephony\SimCardTypes\Edit',
    'Editar modelo de Sim/Card'),
  (  95, 'ADM\Parameterization\Telephony\SimCardTypes\Delete',
    'Remover modelo de Sim/Card'),
  (  96, 'ADM\Parameterization\OwnershipTypes',
    'Gerenciamento de tipos da propriedades (posse) de um equipamento ou Sim/Card'),
  (  97, 'ADM\Parameterization\OwnershipTypes\Get',
    'Recupera as informações de tipos da propriedades (posse) de um equipamento ou Sim/Card'),
  (  98, 'ADM\Parameterization\OwnershipTypes\Add',
    'Adicionar tipo da propriedade (posse) de um equipamento ou Sim/Card'),
  (  99, 'ADM\Parameterization\OwnershipTypes\Edit',
    'Editar tipo da propriedade (posse) de um equipamento ou Sim/Card'),
  ( 100, 'ADM\Parameterization\OwnershipTypes\Delete',
    'Remover tipo da propriedade (posse) de um equipamento ou Sim/Card'),
  ( 101, 'ADM\Parameterization\Holidays',
    'Gerenciamento de feriados'),
  ( 102, 'ADM\Parameterization\Holidays\Get',
    'Recupera as informações de feriados'),
  ( 103, 'ADM\Parameterization\Holidays\Add',
    'Adicionar feriado'),
  ( 104, 'ADM\Parameterization\Holidays\Edit',
    'Editar feriado'),
  ( 105, 'ADM\Parameterization\Holidays\Delete',
    'Remover feriado'),
  ( 106, 'ADM\Parameterization\Holidays\Get\PDF',
    'Gera um PDF com a relação de feriados de uma cidade em um ano'),
  ( 107, 'ADM\Parameterization\Permissions',
    'Gerenciamento de permissões'),
  ( 108, 'ADM\Parameterization\Permissions\Get',
    'Recupera as informações de permissões'),
  ( 109, 'ADM\Parameterization\Permissions\Add',
    'Adicionar permissão'),
  ( 110, 'ADM\Parameterization\Permissions\Edit',
    'Editar permissão'),
  ( 111, 'ADM\Parameterization\Permissions\Delete',
    'Remover permissão'),
  ( 112, 'ADM\Parameterization\SystemActions',
    'Gerenciamento de ações do sistema'),
  ( 113, 'ADM\Parameterization\SystemActions\Get',
    'Recupera as informações de ações do sistema'),
  ( 114, 'ADM\Parameterization\SystemActions\Add',
    'Adicionar uma ação do sistema'),
  ( 115, 'ADM\Parameterization\SystemActions\Edit',
    'Editar uma ação do sistema'),
  ( 116, 'ADM\Parameterization\SystemActions\Delete',
    'Remover uma ação do sistema'),
  ( 117, 'ADM\Financial\Banks',
    'Gerenciamento de instituições financeiras'),
  ( 118, 'ADM\Financial\Banks\Get',
    'Recupera as informações de instituições financeiras'),
  ( 119, 'ADM\Financial\Banks\Add',
    'Adicionar instituição financeira'),
  ( 120, 'ADM\Financial\Banks\Edit',
    'Editar instituição financeira'),
  ( 121, 'ADM\Financial\Banks\Delete',
    'Remover instituição financeira'),
  ( 122, 'ADM\Financial\Indicators',
    'Gerenciamento de indicadores financeiros'),
  ( 123, 'ADM\Financial\Indicators\Get',
    'Recupera as informações de indicadores financeiros'),
  ( 124, 'ADM\Financial\Indicators\Add',
    'Adicionar indicador financeiro'),
  ( 125, 'ADM\Financial\Indicators\Edit',
    'Editar indicador financeiro'),
  ( 126, 'ADM\Financial\Indicators\Delete',
    'Remover indicador financeiro'),
  ( 127, 'ADM\Financial\Indicators\AccumulatedValues',
    'Gerenciamento dos valores acumulados nos últimos 12 meses para cada indicador financeiro'),
  ( 128, 'ADM\Financial\Indicators\AccumulatedValues\Get',
    'Recupera as informações dos valores acumulados nos últimos 12 meses para os indicadores financeiros'),
  ( 129, 'ADM\Financial\Indicators\AccumulatedValues\Add',
    'Adicionar um valor acumulado para um indicador financeiro'),
  ( 130, 'ADM\Financial\Indicators\AccumulatedValues\Edit',
    'Editar um valor acumulado para um indicador financeiro'),
  ( 131, 'ADM\Financial\Indicators\AccumulatedValues\Delete',
    'Remover um valor acumulado para um indicador financeiro'),
  ( 132, 'ADM\Financial\Indicators\AccumulatedValues\Update',
    'Atualiza os indices para cada indicador financeiro'),
  ( 133, 'ADM\Cadastre\Contractors',
    'Gerenciamento de contratantes'),
  ( 134, 'ADM\Cadastre\Contractors\Get',
    'Recupera as informações de contratantes'),
  ( 135, 'ADM\Cadastre\Contractors\Add',
    'Adicionar contratante'),
  ( 136, 'ADM\Cadastre\Contractors\Edit',
    'Editar contratante'),
  ( 137, 'ADM\Cadastre\Contractors\Delete',
    'Remover contratante'),
  ( 138, 'ADM\Cadastre\Contractors\ToggleBlocked',
    'Alterna o bloqueio de um contratante e/ou de uma unidade/filial do contratante'),
  ( 139, 'ADM\Cadastre\Contractors\Get\PDF',
    'Gera um PDF com as informações cadastrais de um contratante'),
  ( 140, 'ADM\Cadastre\Contractors\Autocompletion\Get',
    'Recupera as informações de uma entidade para um campo de autopreenchimento'),
  ( 141, 'ADM\Cadastre\Users',
    'Gerenciamento de usuários'),
  ( 142, 'ADM\Cadastre\Users\Get',
    'Recupera as informações de usuários'),
  ( 143, 'ADM\Cadastre\Users\Add',
    'Adicionar usuário'),
  ( 144, 'ADM\Cadastre\Users\Edit',
    'Editar usuário'),
  ( 145, 'ADM\Cadastre\Users\Delete',
    'Remover usuário'),
  ( 146, 'ADM\Cadastre\Users\ToggleForceNewPassword',
    'Alterna o estado de forçar nova senha de um usuário'),
  ( 147, 'ADM\Cadastre\Users\ToggleBlocked',
    'Alterna o bloqueio de um usuário'),
  ( 148, 'ADM\Cadastre\Users\ToggleSuspended',
    'Alterna a suspensão de um usuário'),
  ( 149, 'ADM\Cadastre\Monitors',
    'Gerenciamento de empresas de monitoramento'),
  ( 150, 'ADM\Cadastre\Monitors\Get',
    'Recupera as informações de empresas de monitoramento'),
  ( 151, 'ADM\Cadastre\Monitors\Add',
    'Adicionar empresa de monitoramento'),
  ( 152, 'ADM\Cadastre\Monitors\Edit',
    'Editar empresa de monitoramento'),
  ( 153, 'ADM\Cadastre\Monitors\Delete',
    'Remover empresa de monitoramento'),
  ( 154, 'ADM\Cadastre\Monitors\ToggleBlocked',
    'Alterna o bloqueio de um empresa de monitoramento e/ou de uma unidade/filial'),
  ( 155, 'ADM\Cadastre\Monitors\Get\PDF',
    'Gera um PDF com as informações cadastrais de uma empresa de monitoramento');

-- Insere a relação de permissões disponíveis para o sistema de ERP
INSERT INTO erp.permissions (permissionID, name, description) VALUES
  ( 200, 'ERP\Home',
    'Página inicial administração'),
  ( 201, 'ERP\About',
    'Página sobre o sistema de ERP'),
  ( 202, 'ERP\Privacity',
    'Página sobre a política de privacidade do sistema de ERP'),
  ( 203, 'ERP\Account',
    'Página sobre os dados cadastrais do usuário autenticado'),
  ( 204, 'ERP\Password',
    'Modificação da senha do usuário'),
  ( 205, 'ERP\Logout',
    'Desautenticação do sistema'),
  ( 206, 'ERP\Parameterization\Cities\Autocompletion\Get',
    'Recupera as informações de uma cidade para um campo de autopreenchimento'),
  ( 207, 'ERP\Parameterization\Cities\PostalCode\Get',
    'Recupera as informações de um endereço através do CEP'),
  ( 208, 'ERP\Parameterization\MobileOperators\IMSI\Get',
    'Recupera as informações de uma operadora de telefonia móvel através do IMSI'),
  ( 209, 'ERP\Parameterization\MobileOperators\APN\Get',
    'Recupera as informações de APN de uma operadora de telefonia móvel'),
  ( 210, 'ERP\Parameterization\Financial\InstallmentTypes',
    'Gerenciamento de tipos de parcelamentos'),
  ( 211, 'ERP\Parameterization\Financial\InstallmentTypes\Get',
    'Recupera as informações de tipos de parcelamentos'),
  ( 212, 'ERP\Parameterization\Financial\InstallmentTypes\Add',
    'Adicionar tipo de parcelamento'),
  ( 213, 'ERP\Parameterization\Financial\InstallmentTypes\Edit',
    'Editar tipo de parcelamento'),
  ( 214, 'ERP\Parameterization\Financial\InstallmentTypes\Delete',
    'Remover tipo de parcelamento'),
  ( 215, 'ERP\Parameterization\Financial\InstallmentTypes\ToggleBlocked',
    'Alterna o bloqueio de um tipo de parcelamento'),
  ( 216, 'ERP\Parameterization\Financial\InstallmentTypes\InstallmentPlan\Get',
    'Recupera as informações de um parcelamento com base num valor'),
  ( 217, 'ERP\Parameterization\Financial\BillingTypes',
    'Gerenciamento de tipos de cobranças'),
  ( 218, 'ERP\Parameterization\Financial\BillingTypes\Get',
    'Recupera as informações de tipos de cobranças'),
  ( 219, 'ERP\Parameterization\Financial\BillingTypes\Add',
    'Adicionar tipo de cobrança'),
  ( 220, 'ERP\Parameterization\Financial\BillingTypes\Edit',
    'Editar tipo de cobrança'),
  ( 221, 'ERP\Parameterization\Financial\BillingTypes\Delete',
    'Remover tipo de cobrança'),
  ( 222, 'ERP\Parameterization\Financial\DueDays',
    'Gerenciamento de dias de vencimento'),
  ( 223, 'ERP\Parameterization\Financial\DueDays\Get',
    'Recupera as informações de dias de vencimento'),
  ( 224, 'ERP\Parameterization\Financial\DueDays\Add',
    'Adicionar dia de vencimento'),
  ( 225, 'ERP\Parameterization\Financial\DueDays\Edit',
    'Editar dia de vencimento'),
  ( 226, 'ERP\Parameterization\Financial\DueDays\Delete',
    'Remover dia de vencimento'),
  ( 227, 'ERP\Parameterization\Financial\ContractTypes',
    'Gerenciamento de tipos de contratos'),
  ( 228, 'ERP\Parameterization\Financial\ContractTypes\Get',
    'Recupera as informações de tipos de contratos'),
  ( 229, 'ERP\Parameterization\Financial\ContractTypes\Add',
    'Adicionar tipo de contrato'),
  ( 230, 'ERP\Parameterization\Financial\ContractTypes\Edit',
    'Editar tipo de contrato'),
  ( 231, 'ERP\Parameterization\Financial\ContractTypes\Delete',
    'Remover tipo de contrato'),
  ( 232, 'ERP\Parameterization\Financial\ContractTypes\ToggleActive',
    'Alterna a ativação de um tipo de contrato'),
  ( 233, 'ERP\Parameterization\Financial\DefinedMethods',
    'Gerenciamento de meios de pagamento configurados'),
  ( 234, 'ERP\Parameterization\Financial\DefinedMethods\Get',
    'Recupera as informações de meios de pagamento configurados'),
  ( 235, 'ERP\Parameterization\Financial\DefinedMethods\Add',
    'Adicionar configuração de meio de pagamento'),
  ( 236, 'ERP\Parameterization\Financial\DefinedMethods\Edit',
    'Editar configuração de meio de pagamento'),
  ( 237, 'ERP\Parameterization\Financial\DefinedMethods\Delete',
    'Remover configuração de meio de pagamento'),
  ( 238, 'ERP\Parameterization\Financial\DefinedMethods\ToggleBlocked',
    'Alterna o bloqueio de uma configuração de meio de pagamento'),
  ( 239, 'ERP\Parameterization\Financial\PaymentConditions',
    'Gerenciamento de condições de pagamento'),
  ( 240, 'ERP\Parameterization\Financial\PaymentConditions\Get',
    'Recupera as informações de condições de pagamento'),
  ( 241, 'ERP\Parameterization\Financial\PaymentConditions\Add',
    'Adicionar condição de pagamento'),
  ( 242, 'ERP\Parameterization\Financial\PaymentConditions\Edit',
    'Editar condição de pagamento'),
  ( 243, 'ERP\Parameterization\Financial\PaymentConditions\Delete',
    'Remover condição de pagamento'),
  ( 244, 'ERP\Parameterization\Financial\PaymentConditions\ToggleBlocked',
    'Alterna o bloqueio de uma condição de pagamento'),
  ( 245, 'ERP\Parameterization\Vehicles\Brands',
    'Gerenciamento de marcas de veículos'),
  ( 246, 'ERP\Parameterization\Vehicles\Brands\Get',
    'Recupera as informações de marcas de veículos'),
  ( 247, 'ERP\Parameterization\Vehicles\Brands\Add',
    'Adicionar marca de veículo'),
  ( 248, 'ERP\Parameterization\Vehicles\Brands\Edit',
    'Editar marca de veículo'),
  ( 249, 'ERP\Parameterization\Vehicles\Brands\Delete',
    'Remover marca de veículo'),
  ( 250, 'ERP\Parameterization\Vehicles\Brands\Synchronize',
    'Sincroniza a relação de marcas de veículos com o site da Fipe'),
  ( 251, 'ERP\Parameterization\Vehicles\Brands\Autocompletion\Get',
    'Recupera as informações de uma marca de veículo para um campo de autopreenchimento'),
  ( 252, 'ERP\Parameterization\Vehicles\Models',
    'Gerenciamento de modelos de veículos'),
  ( 253, 'ERP\Parameterization\Vehicles\Models\Get',
    'Recupera as informações de modelos de veículos'),
  ( 254, 'ERP\Parameterization\Vehicles\Models\Add',
    'Adicionar modelo de veículo'),
  ( 255, 'ERP\Parameterization\Vehicles\Models\Edit',
    'Editar modelo de veículo'),
  ( 256, 'ERP\Parameterization\Vehicles\Models\Delete',
    'Remover modelo de veículo'),
  ( 257, 'ERP\Parameterization\Vehicles\Models\Synchronize',
    'Sincroniza a relação de modelos de veículos com o site da Fipe'),
  ( 258, 'ERP\Parameterization\Vehicles\Models\Autocompletion\Get',
    'Recupera as informações de um modelo de veículo para um campo de autopreenchimento'),
  ( 259, 'ERP\Parameterization\Equipments\Brands',
    'Gerenciamento de marcas de equipamentos'),
  ( 260, 'ERP\Parameterization\Equipments\Brands\Get',
    'Recupera as informações de marcas de equipamentos'),
  ( 261, 'ERP\Parameterization\Equipments\Brands\Add',
    'Adicionar marca de equipamento'),
  ( 262, 'ERP\Parameterization\Equipments\Brands\Edit',
    'Editar marca de equipamento'),
  ( 263, 'ERP\Parameterization\Equipments\Brands\Delete',
    'Remover marca de equipamento'),
  ( 264, 'ERP\Parameterization\Equipments\Brands\Autocompletion\Get',
    'Recupera as informações de uma marca de equipamento para um campo de autopreenchimento'),
  ( 265, 'ERP\Parameterization\Equipments\Models',
    'Gerenciamento de modelos de equipamentos'),
  ( 266, 'ERP\Parameterization\Equipments\Models\Get',
    'Recupera as informações de modelos de equipamentos'),
  ( 267, 'ERP\Parameterization\Equipments\Models\Add',
    'Adicionar modelo de equipamento'),
  ( 268, 'ERP\Parameterization\Equipments\Models\Edit',
    'Editar modelo de equipamento'),
  ( 269, 'ERP\Parameterization\Equipments\Models\Delete',
    'Remover modelo de equipamento'),
  ( 270, 'ERP\Parameterization\Equipments\Models\Autocompletion\Get',
    'Recupera as informações de um modelo de equipamento para um campo de autopreenchimento'),
  ( 271, 'ERP\Parameterization\Deposits',
    'Gerenciamento de depósitos'),
  ( 272, 'ERP\Parameterization\Deposits\Get',
    'Recupera as informações de depósitos'),
  ( 273, 'ERP\Parameterization\Deposits\Add',
    'Adicionar depósito'),
  ( 274, 'ERP\Parameterization\Deposits\Edit',
    'Editar depósito'),
  ( 275, 'ERP\Parameterization\Deposits\Delete',
    'Remover depósito'),
  ( 276, 'ERP\Parameterization\MailingProfiles',
    'Gerenciamento de perfis de envio de notificações'),
  ( 277, 'ERP\Parameterization\MailingProfiles\Get',
    'Recupera as informações de perfis de envio de notificações'),
  ( 278, 'ERP\Parameterization\MailingProfiles\Add',
    'Adicionar perfil de envio de notificação'),
  ( 279, 'ERP\Parameterization\MailingProfiles\Edit',
    'Editar perfil de envio de notificação'),
  ( 280, 'ERP\Parameterization\MailingProfiles\Delete',
    'Remover perfil de envio de notificação'),
  ( 281, 'ERP\Cadastre\Entities\Autocompletion\Get',
    'Recupera as informações de uma entidade para um campo de autopreenchimento'),
  ( 282, 'ERP\Cadastre\Customers',
    'Gerenciamento de clientes'),
  ( 283, 'ERP\Cadastre\Customers\Get',
    'Recupera as informações de clientes'),
  ( 284, 'ERP\Cadastre\Customers\Add',
    'Adicionar cliente'),
  ( 285, 'ERP\Cadastre\Customers\Edit',
    'Editar cliente'),
  ( 286, 'ERP\Cadastre\Customers\Contract',
    'Editar contrato do cliente'),
  ( 287, 'ERP\Cadastre\Customers\Delete',
    'Remover cliente'),
  ( 288, 'ERP\Cadastre\Customers\ToggleBlocked',
    'Alterna o bloqueio de um cliente e/ou de uma unidade/filial do cliente'),
  ( 289, 'ERP\Cadastre\Customers\Get\PDF',
    'Gera um PDF com as informações cadastrais de um cliente'),
  ( 290, 'ERP\Cadastre\Customers\HasOneOrMore',
    'Determina se temos um ou mais clientes válidos cadastrados'),
  ( 291, 'ERP\Cadastre\Customers\Affiliated\Add',
    'Adicionar cliente'),
  ( 292, 'ERP\Cadastre\Customers\Affiliated\Edit',
    'Editar cliente'),
  ( 293, 'ERP\Cadastre\Suppliers',
    'Gerenciamento de fornecedores'),
  ( 294, 'ERP\Cadastre\Suppliers\Get',
    'Recupera as informações de fornecedores'),
  ( 295, 'ERP\Cadastre\Suppliers\Add',
    'Adicionar fornecedor'),
  ( 296, 'ERP\Cadastre\Suppliers\Edit',
    'Editar fornecedor'),
  ( 297, 'ERP\Cadastre\Suppliers\Delete',
    'Remover fornecedor'),
  ( 298, 'ERP\Cadastre\Suppliers\ToggleBlocked',
    'Alterna o bloqueio de um fornecedor e/ou de uma unidade/filial do fornecedor'),
  ( 299, 'ERP\Cadastre\Suppliers\Get\PDF',
    'Gera um PDF com as informações cadastrais de um fornecedor'),
  ( 300, 'ERP\Cadastre\Suppliers\HasOneOrMore',
    'Determina se temos um ou mais fornecedores válidos cadastrados'),
  ( 301, 'ERP\Cadastre\ServiceProviders',
    'Gerenciamento de prestadores de serviços'),
  ( 302, 'ERP\Cadastre\ServiceProviders\Get',
    'Recupera as informações de prestadores de serviços'),
  ( 303, 'ERP\Cadastre\ServiceProviders\Add',
    'Adicionar prestador de serviços'),
  ( 304, 'ERP\Cadastre\ServiceProviders\Edit',
    'Editar prestador de serviços'),
  ( 305, 'ERP\Cadastre\ServiceProviders\Delete',
    'Remover prestador de serviços'),
  ( 306, 'ERP\Cadastre\ServiceProviders\ToggleBlocked',
    'Alterna o bloqueio de um prestador de serviços e/ou de uma unidade/filial do prestador de serviços'),
  ( 307, 'ERP\Cadastre\ServiceProviders\Get\PDF',
    'Gera um PDF com as informações cadastrais de um prestador de serviços'),
  ( 308, 'ERP\Cadastre\ServiceProviders\HasOneOrMore',
    'Determina se temos um ou mais prestadores de serviços válidos cadastrados'),
  ( 309, 'ERP\Cadastre\ServiceProviders\Technicians\Get',
    'Recupera as informações de técnicos'),
  ( 310, 'ERP\Cadastre\ServiceProviders\Technicians\Add',
    'Adicionar técnico'),
  ( 311, 'ERP\Cadastre\ServiceProviders\Technicians\Edit',
    'Editar técnico'),
  ( 312, 'ERP\Cadastre\ServiceProviders\Technicians\Delete',
    'Remover técnico'),
  ( 313, 'ERP\Cadastre\ServiceProviders\Technicians\ToggleBlocked',
    'Alterna o bloqueio de um técnico'),
  ( 314, 'ERP\Cadastre\Sellers',
    'Gerenciamento de vendedores'),
  ( 315, 'ERP\Cadastre\Sellers\Get',
    'Recupera as informações de vendedores'),
  ( 316, 'ERP\Cadastre\Sellers\Add',
    'Adicionar vendedor'),
  ( 317, 'ERP\Cadastre\Sellers\Edit',
    'Editar vendedor'),
  ( 318, 'ERP\Cadastre\Sellers\Delete',
    'Remover vendedor'),
  ( 319, 'ERP\Cadastre\Sellers\ToggleBlocked',
    'Alterna o bloqueio de um vendedor e/ou de uma unidade/filial do vendedor'),
  ( 320, 'ERP\Cadastre\Sellers\Get\PDF',
    'Gera um PDF com as informações cadastrais de um vendedor'),
  ( 321, 'ERP\Cadastre\Sellers\HasOneOrMore',
    'Determina se temos um ou mais vendedores válidos cadastrados'),
  ( 322, 'ERP\Cadastre\Vehicles',
    'Gerenciamento de veículos'),
  ( 323, 'ERP\Cadastre\Vehicles\Get',
    'Recupera as informações de veículos'),
  ( 324, 'ERP\Cadastre\Vehicles\Add',
    'Adicionar veículo'),
  ( 325, 'ERP\Cadastre\Vehicles\Edit',
    'Editar veículo'),
  ( 326, 'ERP\Cadastre\Vehicles\Delete',
    'Remover veículo'),
  ( 327, 'ERP\Cadastre\Vehicles\Attach',
    'Associar veículo a um equipamento'),
  ( 328, 'ERP\Cadastre\Vehicles\Detach',
    'Desassociar veículo de um equipamento'),
  ( 329, 'ERP\Cadastre\Vehicles\ToggleBlocked',
    'Alterna o bloqueio de um veículo'),
  ( 439, 'ERP\Cadastre\Vehicles\ToggleMonitored',
    'Alterna o monitoramento de um veículo'),
  ( 330, 'ERP\Cadastre\Vehicles\Get\MailData',
    'Recupera as informações de e-mails enviados de um veículo'),
  ( 331, 'ERP\Cadastre\Vehicles\Get\PDF',
    'Gera um PDF com as informações cadastrais de um veículo'),
  ( 332, 'ERP\Cadastre\Vehicles\Autocompletion\Get',
    'Recupera as informações de um veículo para um campo de autopreenchimento'),
  ( 333, 'ERP\Cadastre\Vehicles\HasOneOrMore',
    'Determina se temos um ou mais veículos válidos cadastrados'),
  ( 334, 'ERP\Cadastre\Vehicles\Attachments\Get',
    'Recupera um documento pertencente à um veículo para visualização'),
  ( 335, 'ERP\Cadastre\Vehicles\Attachments\Delete',
    'Apaga um documento pertencente à um veículo'),
  ( 336, 'ERP\Cadastre\Vehicles\Attachments\Get\PDF',
    'Gera um PDF com a imagem de um documento pertencente à um veículo'),
  ( 337, 'ERP\Cadastre\Vehicles\Attachments\Thumbnail',
    'Recupera a miniatura de um documento pertencente à um veículo'),
  ( 338, 'ERP\Cadastre\Users',
    'Gerenciamento de usuários'),
  ( 339, 'ERP\Cadastre\Users\Get',
    'Recupera as informações de usuários'),
  ( 340, 'ERP\Cadastre\Users\Add',
    'Adicionar usuário'),
  ( 341, 'ERP\Cadastre\Users\Edit',
    'Editar usuário'),
  ( 342, 'ERP\Cadastre\Users\Delete',
    'Remover usuário'),
  ( 343, 'ERP\Cadastre\Users\ToggleForceNewPassword',
    'Alterna o estado de forçar nova senha de um usuário'),
  ( 344, 'ERP\Cadastre\Users\ToggleBlocked',
    'Alterna o bloqueio de um usuário'),
  ( 345, 'ERP\Cadastre\Users\ToggleSuspended',
    'Alterna a suspensão de um usuário'),
  ( 346, 'ERP\Devices\SimCards',
    'Gerenciamento de SIM Cards'),
  ( 347, 'ERP\Devices\SimCards\Get',
    'Recupera as informações de SIM Cards'),
  ( 348, 'ERP\Devices\SimCards\Add',
    'Adicionar SIM Card'),
  ( 349, 'ERP\Devices\SimCards\Edit',
    'Editar SIM Card'),
  ( 350, 'ERP\Devices\SimCards\Delete',
    'Remover SIM Card'),
  ( 351, 'ERP\Devices\SimCards\ToggleBlocked',
    'Alterna o bloqueio de um SIM Card'),
  ( 352, 'ERP\Devices\SimCards\History',
    'Exibe o visualizador das informações do histórico de movimentações de um SIM Card'),
  ( 353, 'ERP\Devices\SimCards\History\Get',
    'Recupera as informações de histórico de movimentações de um SIM Card'),
  ( 354, 'ERP\Devices\SimCards\Get\PDF',
    'Gera um PDF com as informações cadastrais de um SIM Card'),
  ( 355, 'ERP\Devices\SimCards\Autocompletion\Get',
    'Recupera as informações de um SIM Card para um campo de autopreenchimento'),
  ( 356, 'ERP\Devices\SimCards\HasOneOrMore',
    'Determina se temos um ou mais SIM Cards válidos cadastrados'),
  ( 357, 'ERP\Devices\Equipments',
    'Gerenciamento de equipamentos'),
  ( 358, 'ERP\Devices\Equipments\Get',
    'Recupera as informações de equipamentos'),
  ( 359, 'ERP\Devices\Equipments\Add',
    'Adicionar equipamento'),
  ( 360, 'ERP\Devices\Equipments\Edit',
    'Editar equipamento'),
  ( 361, 'ERP\Devices\Equipments\Delete',
    'Remover equipamento'),
  ( 362, 'ERP\Devices\Equipments\Slot\Attach',
    'Associar slot do equipamento com SIM Card'),
  ( 363, 'ERP\Devices\Equipments\Slot\Detach',
    'Desassociar slot do equipamento com SIM Card'),
  ( 364, 'ERP\Devices\Equipments\ToggleBlocked',
    'Alterna o bloqueio de um equipamento'),
  ( 365, 'ERP\Devices\Equipments\History',
    'Exibe o visualizador das informações do histórico de movimentações de um equipamento'),
  ( 366, 'ERP\Devices\Equipments\History\Get',
    'Recupera as informações de histórico de movimentações de um equipamento'),
  ( 367, 'ERP\Devices\Equipments\Get\PDF',
    'Gera um PDF com as informações cadastrais de um equipamento'),
  ( 368, 'ERP\Devices\Equipments\Autocompletion\Get',
    'Recupera as informações de um equipamento para um campo de autopreenchimento'),
  ( 369, 'ERP\Devices\Equipments\HasOneOrMore',
    'Determina se temos um ou mais equipamentos válidos cadastrados'),
  ( 370, 'ERP\Devices\Movimentations\Transfer',
    'Permite registrar a transferência de dispositivos'),
  ( 371, 'ERP\Devices\Movimentations\Get',
    'Recupera as informações de dispositivos'),
  ( 372, 'ERP\Devices\Movimentations\Return',
    'Permite registrar a devolução de dispositivos ao fornecedor'),
  ( 373, 'ERP\Financial\Plans',
    'Gerenciamento de planos de serviços'),
  ( 374, 'ERP\Financial\Plans\Get',
    'Recupera as informações de planos de serviços'),
  ( 375, 'ERP\Financial\Plans\Add',
    'Adicionar plano de serviço'),
  ( 376, 'ERP\Financial\Plans\Edit',
    'Editar plano de serviço'),
  ( 377, 'ERP\Financial\Plans\Delete',
    'Remover plano de serviço'),
  ( 378, 'ERP\Financial\Plans\ToggleActive',
    'Alterna a ativação de um plano de serviço'),
  ( 379, 'ERP\Financial\Contracts',
    'Gerenciamento de contratos'),
  ( 380, 'ERP\Financial\Contracts\Get',
    'Recupera as informações de contratos'),
  ( 381, 'ERP\Financial\Contracts\Add',
    'Adicionar contrato'),
  ( 382, 'ERP\Financial\Contracts\Edit',
    'Editar contrato'),
  ( 383, 'ERP\Financial\Contracts\Delete',
    'Remover contrato'),
  ( 384, 'ERP\Financial\Contracts\ToggleActive',
    'Alterna a ativação de um contrato'),
  ( 385, 'ERP\Financial\Contracts\Autocompletion\Get',
    'Recupera as informações de um contrato para um campo de autopreenchimento'),
  ( 386, 'ERP\Financial\Contracts\Installations\Get',
    'Recupera as informações de instalações'),
  ( 387, 'ERP\Financial\Contracts\Installations\Add',
    'Adicionar intalação'),
  ( 388, 'ERP\Financial\Contracts\Installations\Edit',
    'Editar instalação'),
  ( 389, 'ERP\Financial\Contracts\Installations\Delete',
    'Remover instalação'),
  ( 390, 'ERP\Financial\Billings',
    'Gerenciamento de lançamentos'),
  ( 391, 'ERP\Financial\Billings\Get',
    'Recupera as informações de lançamentos'),
  ( 392, 'ERP\Financial\Billings\Add',
    'Adicionar um lançamento'),
  ( 393, 'ERP\Financial\Billings\Edit',
    'Editar um lançamento'),
  ( 394, 'ERP\Financial\Billings\Delete',
    'Remover um lançamento'),
  ( 395, 'ERP\Financial\Billings\Grante',
    'Abonar um lançamento'),
  ( 396, 'ERP\Financial\Billings\Renegotiate',
    'Renegociar um lançamento'),
  ( 397, 'ERP\Financial\MonthlyCalculations',
    'Fechamento mensal'),
  ( 398, 'ERP\Financial\MonthlyCalculations\Get',
    'Recupera as informações do fechamento atual'),
  ( 399, 'ERP\Financial\MonthlyCalculations\Start',
    'Inicia o processo de fechamento'),
  ( 400, 'ERP\Financial\MonthlyCalculations\Discard',
    'Descarta os valores atuais do fechamento'),
  ( 401, 'ERP\Financial\MonthlyCalculations\Detail',
    'Detalha os valores de fechamento para uma instalação específica'),
  ( 402, 'ERP\Financial\MonthlyCalculations\Recalculate',
    'Recalcula os valores de fechamento para uma instalação específica'),
  ( 403, 'ERP\Financial\MonthlyCalculations\Finish',
    'Finaliza o processo de fechamento'),
  ( 404, 'ERP\Financial\MonthlyCalculations\Get\PDF',
    'Recupera as informações do fechamento atual na forma de um PDF'),
  ( 405, 'ERP\Financial\MonthlyCalculations\Add',
    'Adicionar um lançamento'),
  ( 406, 'ERP\Financial\MonthlyCalculations\Edit',
    'Editar um lançamento'),
  ( 407, 'ERP\Financial\MonthlyCalculations\Delete',
    'Remover um lançamento'),
  ( 408, 'ERP\Financial\MonthlyCalculations\Grante',
    'Abonar um lançamento'),
  ( 409, 'ERP\Financial\Payments',
    'Gerenciamento de cobranças'),
  ( 410, 'ERP\Financial\Payments\Get',
    'Recupera as informações de cobranças'),
  ( 411, 'ERP\Financial\Payments\Add',
    'Adicionar um cobrança avulsa'),
  ( 412, 'ERP\Financial\Payments\Edit',
    'Edita uma cobrança'),
  ( 413, 'ERP\Financial\Payments\Delete',
    'Remover um cobrança'),
  ( 414, 'ERP\Financial\Payments\Drop',
    'Baixa uma cobrança'),
  ( 415, 'ERP\Financial\Payments\Renegotiate',
    'Renegociar um cobrança'),
  ( 416, 'ERP\Financial\Payments\Get\BillingPhoneList',
    'Recupera uma lista de contatos para cobrança'),
  ( 417, 'ERP\Financial\Payments\Get\PDF',
    'Recupera as informações da cobrança em forma de um PDF'),
  ( 418, 'ERP\Financial\Payments\Get\DigitableLine',
    'Recupera a linha digitável de um boleto'),
  ( 419, 'ERP\Financial\Payments\Get\DownloadableLink',
    'Recupera o link para download de um boleto'),
  ( 420, 'ERP\Financial\Payments\Get\MailData',
    'Recupera as informações de e-mails enviados'),
  ( 421, 'ERP\Financial\Payments\Get\TariffData',
    'Recupera as informações de tarifas cobradas'),
  ( 422, 'ERP\Financial\Payments\Get\HistoryData',
    'Recupera as informações de histórico de movimentos'),
  ( 423, 'ERP\Financial\Payments\Send\Mail',
    'Envia a cobrança por e-mail'),
  ( 424, 'ERP\Financial\Payments\Send\SMS',
    'Envia a cobrança por SMS'),
  ( 425, 'ERP\Financial\Payments\CNAB\ShippingFile',
    'Gerenciamento dos arquivos de remessa'),
  ( 426, 'ERP\Financial\Payments\CNAB\ShippingFile\Get',
    'Recupera os dados de arquivos de remessa gerados'),
  ( 427, 'ERP\Financial\Payments\CNAB\ShippingFile\Download',
    'Obtém um arquivo de remessa'),
  ( 428, 'ERP\Financial\Payments\CNAB\ReturnFile',
    'Gerenciamento dos arquivos de retorno'),
  ( 429, 'ERP\Financial\Payments\CNAB\ReturnFile\Get',
    'Recupera os dados de arquivos de retorno processados'),
  ( 430, 'ERP\Financial\Payments\CNAB\ReturnFile\Process',
    'Processa um arquivo de retorno'),
  ( 431, 'ERP\Cadastre\RapidResponses',
    'Gerenciamento de empresas de pronta-resposta'),
  ( 432, 'ERP\Cadastre\RapidResponses\Get',
    'Recupera as informações de empresas de pronta-resposta'),
  ( 433, 'ERP\Cadastre\RapidResponses\Add',
    'Adicionar empresa de pronta-resposta'),
  ( 434, 'ERP\Cadastre\RapidResponses\Edit',
    'Editar empresa de pronta-resposta'),
  ( 435, 'ERP\Cadastre\RapidResponses\Delete',
    'Remover empresa de pronta-resposta'),
  ( 436, 'ERP\Cadastre\RapidResponses\ToggleBlocked',
    'Alterna o bloqueio de uma empresa de pronta-resposta e/ou de uma unidade/filial dela'),
  ( 437, 'ERP\Cadastre\RapidResponses\Get\PDF',
    'Gera um PDF com as informações cadastrais de uma empresa de pronta-resposta'),
  ( 438, 'ERP\Cadastre\RapidResponses\HasOneOrMore',
    'Determina se temos uma ou mais empresas de pronta-resposta válidas cadastradas');

-- Insere a relação de permissões disponíveis para o sistema de
-- integração do ERP com o STC
INSERT INTO erp.permissions (permissionID, name, description) VALUES
  ( 500, 'STC\Home',
    'Página inicial administração'),
  ( 501, 'STC\About',
    'Página sobre o sistema de ERP'),
  ( 502, 'STC\Privacity',
    'Página sobre a política de privacidade do sistema de ERP'),
  ( 503, 'STC\Account',
    'Página sobre os dados cadastrais do usuário autenticado'),
  ( 504, 'STC\Password',
    'Modificação da senha do usuário'),
  ( 505, 'STC\Logout',
    'Desautenticação do sistema'),
  ( 506, 'STC\Parameterization\Cadastral\Cities',
    'Gerenciamento de cidade'),
  ( 507, 'STC\Parameterization\Cadastral\Cities\Get',
    'Recupera as informações de cidades'),
  ( 508, 'STC\Parameterization\Cadastral\Cities\Synchronize',
    'Sincroniza a relação de cidades com o sistema STC'),
  ( 509, 'STC\Parameterization\Cadastral\Cities\Autocompletion\Get',
    'Recupera as informações de uma cidade para um campo de autopreenchimento'),
  ( 510, 'STC\Parameterization\Cadastral\Journeys',
    'Gerenciamento de jornadas de trabalho'),
  ( 511, 'STC\Parameterization\Cadastral\Journeys\Get',
    'Recupera as informações de jornadas de trabalho'),
  ( 512, 'STC\Parameterization\Cadastral\Journeys\Add',
    'Adicionar jornada de trabalho'),
  ( 513, 'STC\Parameterization\Cadastral\Journeys\Edit',
    'Editar jornada de trabalho'),
  ( 514, 'STC\Parameterization\Cadastral\Journeys\Delete',
    'Remover jornada de trabalho'),
  ( 515, 'STC\Parameterization\Cadastral\Journeys\ToggleDefault',
    'Alterna a jornada padrão'),
  ( 516, 'STC\Parameterization\Vehicles\Types',
    'Gerenciamento de tipos de veículos'),
  ( 517, 'STC\Parameterization\Vehicles\Types\Get',
    'Recupera as informações de tipos de veículos'),
  ( 518, 'STC\Parameterization\Vehicles\Types\Synchronize',
    'Sincroniza a relação de tipos de veículos com o sistema STC'),
  ( 519, 'STC\Parameterization\Vehicles\Brands',
    'Gerenciamento de marcas de veículos'),
  ( 520, 'STC\Parameterization\Vehicles\Brands\Get',
    'Recupera as informações de marcas de veículos'),
  ( 521, 'STC\Parameterization\Vehicles\Brands\Synchronize',
    'Sincroniza a relação de marcas de veículos com o sistema STC'),
  ( 522, 'STC\Parameterization\Vehicles\Brands\Autocompletion\Get',
    'Recupera as informações de marcas de veículos para um campo de autopreenchimento'),
  ( 523, 'STC\Parameterization\Vehicles\Models',
    'Gerenciamento de modelos de veículos'),
  ( 524, 'STC\Parameterization\Vehicles\Models\Get',
    'Recupera as informações de modelos de veículos'),
  ( 525, 'STC\Parameterization\Vehicles\Models\Synchronize',
    'Sincroniza a relação de modelos de veículos com o sistema STC'),
  ( 526, 'STC\Parameterization\Equipments\Manufactures',
    'Gerenciamento de fabricantes de equipamentos'),
  ( 527, 'STC\Parameterization\Equipments\Manufactures\Get',
    'Recupera as informações de fabricantes de equipamentos'),
  ( 528, 'STC\Parameterization\Equipments\Manufactures\Synchronize',
    'Sincroniza a relação de fabricantes de equipamentos com o sistema STC'),
  ( 529, 'STC\Parameterization\Equipments\Manufactures\Autocompletion\Get',
    'Recupera as informações de fabricantes de equipamentos para um campo de autopreenchimento'),
  ( 530, 'STC\Cadastre\Customers',
    'Gerenciamento de clientes'),
  ( 531, 'STC\Cadastre\Customers\Get',
    'Recupera as informações de clientes'),
  ( 532, 'STC\Cadastre\Customers\View',
    'Visualiza as informações cadastrais de um cliente'),
  ( 533, 'STC\Cadastre\Customers\Synchronize',
    'Sincroniza a relação de clientes com o sistema STC'),
  ( 534, 'STC\Cadastre\Customers\Autocompletion\Get',
    'Recupera as informações de um cliente para um campo de autopreenchimento'),
  ( 535, 'STC\Cadastre\Customers\ToggleGetPositions',
    'Alterna o estado da obtenção do histórico de posicionamentos dos veículos de um cliente'),
  ( 536, 'STC\Cadastre\Vehicles',
    'Gerenciamento de veículos'),
  ( 537, 'STC\Cadastre\Vehicles\Get',
    'Recupera as informações de veículos'),
  ( 538, 'STC\Cadastre\Vehicles\View',
    'Visualiza as informações de um veículo'),
  ( 539, 'STC\Cadastre\Vehicles\Synchronize',
    'Sincroniza a relação de veículos com o sistema STC'),
  ( 540, 'STC\Cadastre\Vehicles\Autocompletion\Get',
    'Recupera as informações de um veículo para um campo de autopreenchimento'),
  ( 541, 'STC\Cadastre\Drivers',
    'Gerenciamento de motoristas'),
  ( 542, 'STC\Cadastre\Drivers\Get',
    'Recupera as informações de motoristas'),
  ( 543, 'STC\Cadastre\Drivers\Add',
    'Adicionar um motorista'),
  ( 544, 'STC\Cadastre\Drivers\Edit',
    'Edita as informações de um motorista'),
  ( 545, 'STC\Cadastre\Drivers\Delete',
    'Remover um motorista'),
  ( 546, 'STC\Cadastre\Drivers\Send',
    'Envia as informações de um motorista para um teclado'),
  ( 547, 'STC\Cadastre\Drivers\Autocompletion\Get',
    'Recupera as informações de um motorista para um campo de autopreenchimento'),
  ( 548, 'STC\Cadastre\Equipments',
    'Gerenciamento de equipamentos'),
  ( 549, 'STC\Cadastre\Equipments\Get',
    'Recupera as informações de equipamentos'),
  ( 550, 'STC\Cadastre\Equipments\Synchronize',
    'Sincroniza a relação de equipamentos com o sistema STC'),
  ( 551, 'STC\Cadastre\Equipments\Drivers',
    'Exibe a página de sincronismo de motoristas com o equipamento através do sistema STC'),
  ( 552, 'STC\Cadastre\Equipments\Drivers\Synchronize',
    'Sincroniza a relação de motoristas com o equipamento através do sistema STC'),
  ( 553, 'STC\Data\Positions',
    'Histórico de posicionamentos'),
  ( 554, 'STC\Data\Positions\Get',
    'Recupera as informações de histórico de posiciona267tos'),
  ( 555, 'STC\Data\Positions\Synchronize',
    'Sincroniza a relação de histórico de posicionamentos com o sistema STC'),
  ( 556, 'STC\Report\RoadTrips',
    'Relatório de detalhamento das viagens'),
  ( 557, 'STC\Report\RoadTrips\Get',
    'Recupera as informações das viagens executadas'),
  ( 558, 'STC\Report\RoadTrips\Get\PDF',
    'Gera um PDF com as informações de viagens executadas'),
  ( 559, 'STC\Report\Workdays',
    'Relatório de Jornadas de Trabalho'),
  ( 560, 'STC\Report\Workdays\Get',
    'Recupera as informações de jornadas de trabalho'),
  ( 561, 'STC\Report\Workdays\Get\PDF',
    'Gera um PDF com as informações de jornadas de trabalho');

-- Insere a relação de permissões disponíveis para a área do cliente
INSERT INTO erp.permissions (permissionID, name, description) VALUES
  ( 600, 'USR\Home',
    'Página inicial da área do cliente'),
  ( 601, 'USR\About',
    'Página sobre o sistema de ERP'),
  ( 602, 'USR\Privacity',
    'Página sobre a política de privacidade do sistema de ERP'),
  ( 603, 'USR\Account',
    'Página sobre os dados cadastrais do usuário autenticado'),
  ( 604, 'USR\Password',
    'Modificação da senha do usuário'),
  ( 605, 'USR\Logout',
    'Desautenticação do sistema'),
  ( 606, 'USR\Payments\Get\PDF',
    'Recupera as informações da cobrança em forma de um PDF')
;

ALTER SEQUENCE erp.permissions_permissionid_seq RESTART WITH 607;

-- ---------------------------------------------------------------------
-- Métodos HTTP
-- ---------------------------------------------------------------------
-- Os métodos HTTP possíveis: GET (visualizar), POST (adicionar),
-- PUT (modificar), DELETE (apagar), PATCH (ler)
-- ---------------------------------------------------------------------
CREATE TYPE HTTPMethod AS ENUM('GET', 'POST', 'PUT', 'DELETE', 'PATCH');

-- ---------------------------------------------------------------------
-- Permissões por grupo
-- ---------------------------------------------------------------------
-- Armazena as permissões para cada módulo do sistema por grupo. Para
-- um mesmo módulo, os métodos podem ser GET (visualizar), POST
-- (adicionar), PUT (modificar), DELETE (apagar).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.permissionsPerGroups (
  permissionpergroupID  serial,       -- O ID da permissão por grupo
  groupID               integer       -- O ID do grupo
                        NOT NULL,
  permissionID          integer       -- O ID da permissão
                        NOT NULL,
  httpMethod            HTTPMethod,   -- O método HTTP
  PRIMARY KEY (permissionpergroupID),
  UNIQUE (groupID, permissionID, httpMethod),
  FOREIGN KEY (groupID)
    REFERENCES erp.groups(groupID)
    ON DELETE RESTRICT,
  FOREIGN KEY (permissionID)
    REFERENCES erp.permissions(permissionID)
    ON DELETE RESTRICT
);


-- ==============================================[ Administração do ERP]

-- -------------------------------------------------------------[ Base ]

-- Insere a relação de permissões por grupo para as página padrões
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES (  1, 'GET'),
                  (  2, 'GET'),
                  (  3, 'GET'),
                  (  4, 'GET'), (  4, 'PUT'),
                  (  5, 'GET'), (  5, 'PUT'),
                  (  6, 'GET')) y(permissionID, method));

-- ---------------------------------------------------[ Parametrização ]

-- Cadastral

-- Insere a relação de permissões para o gerenciamento de cidades
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES (  7, 'GET'),
                  (  8, 'PATCH'),
                  (  9, 'GET'), (  9, 'POST'),
                  ( 10, 'GET'), ( 10, 'PUT'),
                  ( 11, 'DELETE'),
                  ( 12, 'PATCH'),
                  ( 13, 'PATCH')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de tipos de
-- documentos
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 14, 'GET'),
                  ( 15, 'PATCH'),
                  ( 16, 'GET'), ( 16, 'POST'),
                  ( 17, 'GET'), ( 17, 'PUT'),
                  ( 18, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de estados civis
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 19, 'GET'),
                  ( 20, 'PATCH'),
                  ( 21, 'GET'), ( 21, 'POST'),
                  ( 22, 'GET'), ( 22, 'PUT'),
                  ( 23, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de gêneros
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 24, 'GET'),
                  ( 25, 'PATCH'),
                  ( 26, 'GET'), ( 26, 'POST'),
                  ( 27, 'GET'), ( 27, 'PUT'),
                  ( 28, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de tipos de
-- medidas de um valor
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 29, 'GET'),
                  ( 30, 'PATCH'),
                  ( 31, 'GET'), ( 31, 'POST'),
                  ( 32, 'GET'), ( 32, 'PUT'),
                  ( 33, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de tipos de
-- campos de um formulário
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 34, 'GET'),
                  ( 35, 'PATCH'),
                  ( 36, 'GET'), ( 36, 'POST'),
                  ( 37, 'GET'), ( 37, 'PUT'),
                  ( 38, 'DELETE')) y(permissionID, method));

-- Vehicles

-- Insere a relação de permissões administrativas para o gerenciamento
-- de tipos de veículos
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 39, 'GET'),
                  ( 40, 'PATCH'),
                  ( 41, 'GET'), ( 41, 'POST'),
                  ( 42, 'GET'), ( 42, 'PUT'),
                  ( 43, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões administrativas para o gerenciamento
-- de subtipos de veículos
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 44, 'GET'),
                  ( 45, 'PATCH'),
                  ( 46, 'GET'), ( 46, 'POST'),
                  ( 47, 'GET'), ( 47, 'PUT'),
                  ( 48, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de tipos de
-- combustível
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 49, 'GET'),
                  ( 50, 'PATCH'),
                  ( 51, 'GET'), ( 51, 'POST'),
                  ( 52, 'GET'), ( 52, 'PUT'),
                  ( 53, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de cores de
-- veículos
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 54, 'GET'),
                  ( 55, 'PATCH'),
                  ( 56, 'GET'), ( 56, 'POST'),
                  ( 57, 'GET'), ( 57, 'PUT'),
                  ( 58, 'DELETE')) y(permissionID, method));

-- Equipments

-- Insere a relação de permissões administrativas para o gerenciamento
-- de tipos de acessórios
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 59, 'GET'),
                  ( 60, 'PATCH'),
                  ( 61, 'GET'), ( 61, 'POST'),
                  ( 62, 'GET'), ( 62, 'PUT'),
                  ( 63, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões administrativas para o gerenciamento
-- de características técnicas
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 64, 'GET'),
                  ( 65, 'PATCH'),
                  ( 66, 'GET'), ( 66, 'POST'),
                  ( 67, 'GET'), ( 67, 'PUT'),
                  ( 68, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- marcas de equipamento
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 69, 'GET'),
                  ( 70, 'PATCH'),
                  ( 71, 'GET'), (71, 'POST'),
                  ( 72, 'GET'), (72, 'PUT'),
                  ( 73, 'DELETE'),
                  ( 74, 'PATCH')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- modelos de equipamento
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 75, 'GET'),
                  ( 76, 'PATCH'),
                  ( 77, 'GET'), (77, 'POST'),
                  ( 78, 'GET'), (78, 'PUT'),
                  ( 79, 'DELETE'),
                  ( 80, 'PATCH')) y(permissionID, method));

-- Telephony

-- Insere a relação de permissões para o gerenciamento de tipos de
-- telefones
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 81, 'GET'),
                  ( 82, 'PATCH'),
                  ( 83, 'GET'), ( 83, 'POST'),
                  ( 84, 'GET'), ( 84, 'PUT'),
                  ( 85, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de operadoras de
-- telefonia móvel
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 86, 'GET'),
                  ( 87, 'PATCH'),
                  ( 88, 'GET'), ( 88, 'POST'),
                  ( 89, 'GET'), ( 89, 'PUT'),
                  ( 90, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de modelos de
-- Sim/Card
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 91, 'GET'),
                  ( 92, 'PATCH'),
                  ( 93, 'GET'), ( 93, 'POST'),
                  ( 94, 'GET'), ( 94, 'PUT'),
                  ( 95, 'DELETE')) y(permissionID, method));

-- Demais opções

-- Insere a relação de permissões para o gerenciamento de tipos da
-- propriedades (posse) de um equipamento ou Sim/Card
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES ( 96, 'GET'),
                  ( 97, 'PATCH'),
                  ( 98, 'GET'), ( 98, 'POST'),
                  ( 99, 'GET'), ( 99, 'PUT'),
                  (100, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de feriados
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES (101, 'GET'),
                  (102, 'PATCH'),
                  (103, 'GET'), (103, 'POST'),
                  (104, 'GET'), (104, 'PUT'),
                  (105, 'DELETE'),
                  (106, 'GET')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de permissões
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES (107, 'GET'),
                  (108, 'PATCH'),
                  (109, 'GET'), (109, 'POST'),
                  (110, 'GET'), (110, 'PUT'),
                  (111, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de ações do
-- sistema
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES (112, 'GET'),
                  (113, 'PATCH'),
                  (114, 'GET'), (114, 'POST'),
                  (115, 'GET'), (115, 'PUT'),
                  (116, 'DELETE')) y(permissionID, method));

-- -------------------------------------------------------[ Financeiro ]

-- Insere a relação de permissões para o gerenciamento de bancos
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES (117, 'GET'),
                  (118, 'PATCH'),
                  (119, 'GET'), (119, 'POST'),
                  (120, 'GET'), (120, 'PUT'),
                  (121, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de indicadores
-- financeiros
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES (122, 'GET'),
                  (123, 'PATCH'),
                  (124, 'GET'), (124, 'POST'),
                  (125, 'GET'), (125, 'PUT'),
                  (126, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento dos valores
-- acumulados de cada indicador financeiro
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES (127, 'GET'),
                  (128, 'PATCH'),
                  (129, 'GET'), (129, 'POST'),
                  (130, 'GET'), (130, 'PUT'),
                  (131, 'DELETE'),
                  (132, 'GET')) y(permissionID, method));

-- --------------------------------------------------------[ Cadastros ]

-- Insere a relação de permissões para o gerenciamento de contratantes
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES (133, 'GET'),
                  (134, 'PATCH'),
                  (135, 'GET'), (135, 'POST'),
                  (136, 'GET'), (136, 'PUT'),
                  (137, 'DELETE'),
                  (138, 'PUT'),
                  (139, 'GET'),
                  (140, 'PATCH')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de usuários
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES (141, 'GET'),
                  (142, 'PATCH'),
                  (143, 'GET'), (143, 'POST'),
                  (144, 'GET'), (144, 'PUT'),
                  (145, 'DELETE'),
                  (146, 'PUT'),
                  (147, 'PUT'),
                  (148, 'PUT')) y(permissionID, method));

-- Insere a relação de permissões para o gerenciamento de empresas de monitoramento
-- Administrador do ERP
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1)) x(groupID),
          (VALUES (149, 'GET'),
                  (150, 'PATCH'),
                  (151, 'GET'), (151, 'POST'),
                  (152, 'GET'), (152, 'PUT'),
                  (153, 'DELETE'),
                  (154, 'PUT'),
                  (155, 'GET')) y(permissionID, method));


-- ========================================================[Sistema ERP]

-- -------------------------------------------------------------[ Base ]

-- Insere a relação de permissões por grupo para as página padrões para
-- todos usuários
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,6) x(groupID),
          (VALUES (200, 'GET'),
                  (201, 'GET'),
                  (202, 'GET'),
                  (203, 'GET'), (203, 'PUT'),
                  (204, 'GET'), (204, 'PUT'),
                  (205, 'GET')) y(permissionID, method));

-- ---------------------------------------------------[ Parametrização ]

-- Insere a relação de permissões por grupo para as informações de
-- cidades para todos usuários
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,6) x(groupID),
          (VALUES (206, 'PATCH'),
                  (207, 'PATCH')) y(permissionID, method));

-- Insere a relação de permissões por grupo para as informações de
-- operadoras de telefonia móvel para todos usuários
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,6) x(groupID),
          (VALUES (208, 'PATCH'),
                  (209, 'PATCH')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- tipos de parcelamentos
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (210, 'GET'),
                  (211, 'PATCH'),
                  (212, 'GET'), (212, 'POST'),
                  (213, 'GET'), (213, 'PUT'),
                  (214, 'DELETE'),
                  (215, 'PUT'),
                  (216, 'PATCH')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- tipos de cobrança
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (217, 'GET'),
                  (218, 'PATCH'),
                  (219, 'GET'), (219, 'POST'),
                  (220, 'GET'), (220, 'PUT'),
                  (221, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- dias de vencimento
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (222, 'GET'),
                  (223, 'PATCH'),
                  (224, 'GET'), (224, 'POST'),
                  (225, 'GET'), (225, 'PUT'),
                  (226, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- tipos de contratos
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (227, 'GET'),
                  (228, 'PATCH'),
                  (229, 'GET'), (229, 'POST'),
                  (230, 'GET'), (230, 'PUT'),
                  (231, 'DELETE'),
                  (232, 'PUT')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- meios de pagamento configurados
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (233, 'GET'),
                  (234, 'PATCH'),
                  (235, 'GET'), (235, 'POST'),
                  (236, 'GET'), (236, 'PUT'),
                  (237, 'DELETE'),
                  (238, 'PUT')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- condições de pagamento
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (239, 'GET'),
                  (240, 'PATCH'),
                  (241, 'GET'), (241, 'POST'),
                  (242, 'GET'), (242, 'PUT'),
                  (243, 'DELETE'),
                  (244, 'PUT')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- marcas de veículo
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (245, 'GET'),
                  (246, 'PATCH'),
                  (247, 'GET'), (247, 'POST'),
                  (248, 'GET'), (248, 'PUT'),
                  (249, 'DELETE'),
                  (250, 'GET'),
                  (251, 'PATCH')) y(permissionID, method));

-- Demais usuários acessam apenas o autocompletar
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(3,6) x(groupID),
          (VALUES (251, 'PATCH')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- modelos de veículo
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (252, 'GET'),
                  (253, 'PATCH'),
                  (254, 'GET'), (254, 'POST'),
                  (255, 'GET'), (255, 'PUT'),
                  (256, 'DELETE'),
                  (257, 'GET'),
                  (258, 'PATCH')) y(permissionID, method));

-- Demais usuários acessam apenas o autocompletar
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(3,6) x(groupID),
          (VALUES (258, 'PATCH')) y(permissionID, method));


-- Insere a relação de permissões por grupo para o gerenciamento de
-- marcas de equipamento
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (259, 'GET'),
                  (260, 'PATCH'),
                  (261, 'GET'), (261, 'POST'),
                  (262, 'GET'), (262, 'PUT'),
                  (263, 'DELETE'),
                  (264, 'PATCH')) y(permissionID, method));

-- Demais usuários acessam apenas o autocompletar
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(3,6) x(groupID),
          (VALUES (264, 'PATCH')) y(permissionID, method));


-- Insere a relação de permissões por grupo para o gerenciamento de
-- modelos de equipamento
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (265, 'GET'),
                  (266, 'PATCH'),
                  (267, 'GET'), (267, 'POST'),
                  (268, 'GET'), (268, 'PUT'),
                  (269, 'DELETE'),
                  (270, 'PATCH')) y(permissionID, method));

-- Demais usuários acessam apenas o autocompletar
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(3,6) x(groupID),
          (VALUES (270, 'PATCH')) y(permissionID, method));


-- Insere a relação de permissões por grupo para o gerenciamento de
-- depósitos
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (271, 'GET'),
                  (272, 'PATCH'),
                  (273, 'GET'), (273, 'POST'),
                  (274, 'GET'), (284, 'PUT'),
                  (275, 'DELETE')) y(permissionID, method));


-- Insere a relação de permissões por grupo para o gerenciamento de
-- perfis de envio de notificações
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (276, 'GET'),
                  (277, 'PATCH'),
                  (278, 'GET'), (278, 'POST'),
                  (279, 'GET'), (279, 'PUT'),
                  (280, 'DELETE')) y(permissionID, method));


-- ---------------------------------------------------------[ Cadastro ]

-- Insere a relação de permissões por grupo para o gerenciamento de
-- entidades para todos usuários
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,6) x(groupID),
          (VALUES (281, 'PATCH')) y(permissionID, method));


-- Insere a relação de permissões por grupo para o gerenciamento de
-- clientes para todos os usuários
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,6) x(groupID),
          (VALUES (282, 'GET'),
                  (283, 'PATCH'),
                  (284, 'GET'), (284, 'POST'),
                  (285, 'GET'), (285, 'PUT'),
                  (286, 'GET'), (286, 'PUT'),
                  (287, 'DELETE'),
                  (288, 'PUT'),
                  (289, 'GET'),
                  (290, 'GET'),
                  (291, 'GET'), (291, 'POST'),
                  (292, 'GET'), (292, 'PUT')) y(permissionID, method));

-- Atendentes, operadores não podem apagar, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (3, 4)
   AND permissionID IN (287);

-- Técnicos não podem adicionar, editar, ver e modificar contratos,
-- apagar ou alterar o estado do bloqueio, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (5)
   AND ( (permissionID IN (284, 286, 287, 288, 291)) OR
         (permissionID = 285 AND httpMethod = 'PUT') OR
         (permissionID = 292 AND httpMethod = 'PUT') );

-- Clientes não podem adicionar, editar, modificar contratos,
-- apagar ou alterar o estado do bloqueio, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (6)
   AND ( (permissionID IN (284, 287, 288, 291)) OR
         (permissionID IN (285, 286, 292) AND httpMethod = 'PUT') );


-- Insere a relação de permissões por grupo para o gerenciamento de
-- fornecedores para todos os usuários (exceto clientes)
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,5) x(groupID),
          (VALUES (293, 'GET'),
                  (294, 'PATCH'),
                  (295, 'GET'), (295, 'POST'),
                  (296, 'GET'), (296, 'PUT'),
                  (297, 'DELETE'),
                  (298, 'PUT'),
                  (299, 'GET'),
                  (300, 'GET')) y(permissionID, method));

-- Atendentes não podem apagar, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (3)
   AND permissionID IN (297);

-- Operadores e técnicos não podem adicionar, editar, apagar ou alterar
-- o estado do bloqueio, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (4, 5)
   AND ( (permissionID IN (295, 297, 298)) OR
         (permissionID = 296 AND httpMethod = 'PUT') );

-- Clientes não possuem permissões, então não modifica nada

-- Insere a relação de permissões por grupo para o gerenciamento de
-- prestadores de serviços para todos os usuários (exceto clientes)
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,5) x(groupID),
          (VALUES (301, 'GET'),
                  (302, 'PATCH'),
                  (303, 'GET'), (303, 'POST'),
                  (304, 'GET'), (304, 'PUT'),
                  (305, 'DELETE'),
                  (306, 'PUT'),
                  (307, 'GET'),
                  (308, 'GET'),
                  (309, 'PATCH'),
                  (310, 'GET'), (310, 'POST'),
                  (311, 'GET'), (311, 'PUT'),
                  (312, 'DELETE'),
                  (313, 'PUT')) y(permissionID, method));

-- Atendentes não podem apagar, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (3)
   AND permissionID IN (305, 312);

-- Operadores e técnicos não podem adicionar, editar, apagar ou alterar
-- o estado do bloqueio, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (4, 5)
   AND ( (permissionID IN (303, 305, 306, 310, 312, 313)) OR
         (permissionID = 304 AND httpMethod = 'PUT') OR
         (permissionID = 311 AND httpMethod = 'PUT') );

-- Clientes não possuem permissões, então não modifica nada

-- Insere a relação de permissões por grupo para o gerenciamento de
-- vendedores para todos os usuários (exceto clientes)
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,5) x(groupID),
          (VALUES (314, 'GET'),
                  (315, 'PATCH'),
                  (316, 'GET'), (316, 'POST'),
                  (317, 'GET'), (317, 'PUT'),
                  (318, 'DELETE'),
                  (319, 'PUT'),
                  (320, 'GET'),
                  (321, 'GET')) y(permissionID, method));

-- Atendentes não podem apagar, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (3)
   AND permissionID IN (318);

-- Operadores e técnicos não podem adicionar, editar, apagar ou alterar
-- o estado do bloqueio, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (4, 5)
   AND ( (permissionID IN (316, 318, 319)) OR
         (permissionID = 317 AND httpMethod = 'PUT') );

-- Clientes não possuem permissões, então não modifica nada

-- Insere a relação de permissões por grupo para o gerenciamento de
-- veículos para todos os usuários
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,6) x(groupID),
          (VALUES (322, 'GET'),
                  (323, 'PATCH'),
                  (324, 'GET'), (324, 'POST'),
                  (325, 'GET'), (325, 'PUT'),
                  (326, 'DELETE'),
                  (327, 'GET'), (327, 'PUT'),
                  (328, 'DELETE'),
                  (329, 'PUT'),
                  (439, 'PUT'),
                  (330, 'PATCH'),
                  (331, 'GET'),
                  (332, 'PATCH'),
                  (333, 'GET'),
                  (334, 'GET'),
                  (335, 'DELETE'),
                  (336, 'GET'),
                  (337, 'GET')) y(permissionID, method));

-- Atendentes, operadores e técnicos não podem apagar um veículo ou
-- documento, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (3, 4, 5)
   AND permissionID IN (327, 335);

-- Operadores e clientes não podem apagar um veículo ou documento e não
-- podem vincular veículos com equipamentos, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (4, 6)
   AND permissionID IN (327, 328, 329, 439, 335);


-- Insere a relação de permissões por grupo para o gerenciamento de
-- usuários para administradores e atendentes
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2),(3)) x(groupID),
          (VALUES (338, 'GET'),
                  (339, 'PATCH'),
                  (340, 'GET'), (340, 'POST'),
                  (341, 'GET'), (341, 'PUT'),
                  (342, 'DELETE'),
                  (343, 'PUT'),
                  (344, 'PUT'),
                  (345, 'PUT')) y(permissionID, method));

-- Atendentes não podem apagar usuários, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (3)
   AND permissionID IN (342);


-- -----------------------------------------------------[ Dispositivos ]

-- Insere a relação de permissões por grupo para o gerenciamento de
-- SIM Cards para todos os usuários, exceto os usuários
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,5) x(groupID),
          (VALUES (346, 'GET'),
                  (347, 'PATCH'),
                  (348, 'GET'), (348, 'POST'),
                  (349, 'GET'), (349, 'PUT'),
                  (350, 'DELETE'),
                  (351, 'PUT'),
                  (352, 'GET'),
                  (353, 'PATCH'),
                  (354, 'GET'),
                  (355, 'PATCH'),
                  (356, 'GET')) y(permissionID, method));

-- Operadores não podem apagar
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (4)
   AND permissionID IN (350);

-- Técnicos não podem apagar nem visualizar o histórico, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (5)
   AND permissionID IN (350, 352, 353);

-- Clientes não têm permissões, então não alteramos nada

-- Insere a relação de permissões por grupo para o gerenciamento de
-- Equipamentos para todos os usuários
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,5) x(groupID),
          (VALUES (357, 'GET'),
                  (358, 'PATCH'),
                  (359, 'GET'), (359, 'POST'),
                  (360, 'GET'), (360, 'PUT'),
                  (361, 'DELETE'),
                  (362, 'GET'), (362, 'POST'),
                  (363, 'DELETE'),
                  (364, 'PUT'),
                  (365, 'GET'),
                  (366, 'PATCH'),
                  (367, 'GET'),
                  (368, 'PATCH'),
                  (369, 'GET')) y(permissionID, method));

-- Operadores não podem apagar, vincular ou desvincular e/ou alternar o
-- bloqueio, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (4)
   AND permissionID IN (361, 362, 363, 364);

-- Técnicos não podem apagar nem visualizar o histórico, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (5)
   AND permissionID IN (361, 365, 366);

-- Clientes não podem adicionar, alterar ou apagar, alternar bloqueio,
-- associar slots ou ver o histórico
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (6)
   AND permissionID IN (359, 360, 361, 362, 363, 364, 365, 366);


-- Insere a relação de permissões por grupo para a transferência de
-- dispositivos para os usuários administrativos e atendentes
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,4) x(groupID),
          (VALUES (370, 'GET'), (370, 'PUT'),
                  (371, 'PATCH'),
                  (372, 'GET'), (372, 'PUT')) y(permissionID, method));


-- -------------------------------------------------------[ Financeiro ]

-- Insere a relação de permissões por grupo para o gerenciamento de
-- planos de serviço
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (373, 'GET'),
                  (374, 'PATCH'),
                  (375, 'GET'), (375, 'POST'),
                  (376, 'GET'), (376, 'PUT'),
                  (377, 'DELETE'),
                  (378, 'PUT')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- contratos
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (379, 'GET'),
                  (380, 'PATCH'),
                  (381, 'GET'), (381, 'POST'),
                  (382, 'GET'), (382, 'PUT'),
                  (383, 'DELETE'),
                  (384, 'PUT'),
                  (385, 'PATCH')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- instalações
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (386, 'PATCH'),
                  (387, 'GET'), (387, 'POST'),
                  (388, 'GET'), (388, 'PUT'),
                  (389, 'DELETE')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- lançamentos
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (390, 'GET'),
                  (391, 'PATCH'),
                  (392, 'GET'), (392, 'POST'),
                  (393, 'GET'), (393, 'PUT'),
                  (394, 'DELETE'),
                  (395, 'PUT'),
                  (396, 'GET'), (396, 'POST')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o fechamento mensal
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (397, 'GET'),
                  (398, 'PATCH'),
                  (399, 'PUT'),
                  (400, 'DELETE'),
                  (401, 'GET'),
                  (402, 'PUT'),
                  (403, 'PUT'),
                  (404, 'GET'),
                  (405, 'GET'), (405, 'POST'),
                  (406, 'GET'), (406, 'PUT'),
                  (407, 'DELETE'),
                  (408, 'PUT')) y(permissionID, method));

-- Insere a relação de permissões por grupo para a cobrança
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (409, 'GET'),
                  (410, 'PATCH'),
                  (411, 'GET'), (411, 'POST'),
                  (412, 'GET'), (412, 'PUT'),
                  (413, 'DELETE'),
                  (414, 'PUT'),
                  (415, 'GET'), (415, 'POST'),
                  (416, 'GET'),
                  (417, 'GET'),
                  (418, 'PATCH'),
                  (419, 'PATCH'),
                  (420, 'PATCH'),
                  (421, 'PATCH'),
                  (422, 'PATCH'),
                  (423, 'PUT'),
                  (424, 'PUT'),
                  (425, 'GET'),
                  (426, 'PATCH'),
                  (427, 'GET'),
                  (428, 'GET'),
                  (429, 'PATCH'),
                  (430, 'POST')) y(permissionID, method));
-- Atendente
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (3)) x(groupID),
          (VALUES (409, 'GET'),
                  (410, 'PATCH'),
                  (416, 'GET'),
                  (417, 'GET'),
                  (418, 'PATCH'),
                  (419, 'PATCH'),
                  (420, 'PATCH'),
                  (421, 'PATCH'),
                  (422, 'PUT'),
                  (423, 'PUT')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- empresas de pronta-resposta para todos os usuários (exceto clientes)
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,5) x(groupID),
          (VALUES (431, 'GET'),
                  (432, 'PATCH'),
                  (433, 'GET'), (433, 'POST'),
                  (434, 'GET'), (434, 'PUT'),
                  (435, 'DELETE'),
                  (436, 'PUT'),
                  (437, 'GET'),
                  (438, 'GET')) y(permissionID, method));

-- Atendentes não podem apagar, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (3)
   AND permissionID IN (435);

-- Operadores e técnicos não podem adicionar, editar, apagar ou alterar
-- o estado do bloqueio, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (4, 5)
   AND ( (permissionID IN (433, 435, 436)) OR
         (permissionID = 434 AND httpMethod = 'PUT') );

-- Clientes não possuem permissões, então não modifica nada

-- ========================================================[Sistema STC]

-- -------------------------------------------------------------[ Base ]

-- Insere a relação de permissões por grupo para as página padrões para
-- todos usuários
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,6) x(groupID),
          (VALUES (500, 'GET'),
                  (501, 'GET'),
                  (502, 'GET'),
                  (503, 'GET'), (503, 'PUT'),
                  (504, 'GET'), (504, 'PUT'),
                  (505, 'GET')) y(permissionID, method));


-- ---------------------------------------------------[ Parametrização ]

-- Cadastral

-- Insere a relação de permissões por grupo para o gerenciamento de
-- cidades
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (506, 'GET'),
                  (507, 'PATCH'),
                  (508, 'GET'),
                  (509, 'PATCH')) y(permissionID, method));

-- Demais usuários acessam apenas o autocompletar
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(3,6) x(groupID),
          (VALUES (509, 'PATCH')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- jornadas de trabalho
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2),(6)) x(groupID),
          (VALUES (510, 'GET'),
                  (511, 'PATCH'),
                  (512, 'GET'), (512, 'POST'),
                  (513, 'GET'), (513, 'PUT'),
                  (514, 'DELETE'),
                  (515, 'PUT')) y(permissionID, method));


-- Vehicles

-- Insere a relação de permissões por grupo para o gerenciamento de
-- tipos de veículo
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (516, 'GET'),
                  (517, 'PATCH'),
                  (518, 'GET')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- marcas de veículo
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (519, 'GET'),
                  (520, 'PATCH'),
                  (521, 'GET'),
                  (522, 'PATCH')) y(permissionID, method));

-- Demais usuários acessam apenas o autocompletar
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(3,6) x(groupID),
          (VALUES (522, 'PATCH')) y(permissionID, method));

-- Insere a relação de permissões por grupo para o gerenciamento de
-- modelos de veículo
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (523, 'GET'),
                  (524, 'PATCH'),
                  (525, 'GET')) y(permissionID, method));

-- Equipments

-- Insere a relação de permissões por grupo para o gerenciamento de
-- fabricantes de equipamentos
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (526, 'GET'),
                  (527, 'PATCH'),
                  (528, 'GET'),
                  (529, 'PATCH')) y(permissionID, method));

-- Demais usuários acessam apenas o autocompletar
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(3,6) x(groupID),
          (VALUES (529, 'PATCH')) y(permissionID, method));

-- ---------------------------------------------------------[ Cadastro ]

-- Clientes

-- Insere a relação de permissões por grupo para o gerenciamento de
-- clientes
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (530, 'GET'),
                  (531, 'PATCH'),
                  (532, 'GET'),
                  (533, 'GET'),
                  (534, 'PATCH'),
                  (535, 'PUT')) y(permissionID, method));

-- Clientes podem visualizar os dados cadastrais
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (6)) x(groupID),
          (VALUES (532, 'GET')) y(permissionID, method));

-- Demais usuários acessam apenas o autocompletar
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(3,6) x(groupID),
          (VALUES (534, 'PATCH')) y(permissionID, method));

-- Veículos

-- Insere a relação de permissões por grupo para o gerenciamento de
-- veículos
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (536, 'GET'),
                  (537, 'PATCH'),
                  (538, 'GET'),
                  (539, 'GET'),
                  (540, 'PATCH')) y(permissionID, method));

-- Cliente pode visualizar as informações dos seus veículos
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (6)) x(groupID),
          (VALUES (536, 'GET'),
                  (537, 'PATCH'),
                  (538, 'GET')) y(permissionID, method));


-- Demais usuários acessam apenas o autocompletar
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(3,6) x(groupID),
          (VALUES (540, 'PATCH')) y(permissionID, method));


-- Motoristas

-- Insere a relação de permissões por grupo para o gerenciamento de
-- motoristas
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (541, 'GET'),
                  (542, 'PATCH'),
                  (543, 'GET'), (543, 'POST'),
                  (544, 'GET'), (544, 'PUT'),
                  (545, 'DELETE'),
                  (546, 'GET'),
                  (547, 'PATCH')) y(permissionID, method));

-- Clientes podem visualizar e editar os dados de motoristas
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (6)) x(groupID),
          (VALUES (541, 'GET'),
                  (542, 'PATCH'),
                  (543, 'GET'), (543, 'POST'),
                  (544, 'GET'), (544, 'PUT')) y(permissionID, method));

-- Demais usuários acessam apenas o autocompletar
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(3,6) x(groupID),
          (VALUES (547, 'PATCH')) y(permissionID, method));


-- Equipamentos

-- Insere a relação de permissões por grupo para o gerenciamento de
-- equipamentos
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (548, 'GET'),
                  (549, 'PATCH'),
                  (550, 'GET'),
                  (551, 'GET'),
                  (552, 'GET')) y(permissionID, method));

-- ------------------------------------------------------------[ Dados ]

-- Posicionamentos

-- Insere a relação de permissões por grupo para o histórico de
-- posicionamentos
-- Administrador do ERP e Administrador contratante
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (553, 'GET'),
                  (554, 'PATCH'),
                  (555, 'GET')) y(permissionID, method));

-- Clientes podem visualizar os dados de posicionamentos de seus veículos
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (6)) x(groupID),
          (VALUES (553, 'GET'),
                  (554, 'PATCH')) y(permissionID, method));

-- -------------------------------------------------------[ Relatórios ]

-- Jornadas executadas

-- Insere a relação de permissões por grupo para o relatório de jornadas
-- executadas
-- Administrador do ERP, Administrador contratante, Atendente e Cliente
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2),(3),(6)) x(groupID),
          (VALUES (556, 'GET'),
                  (557, 'PATCH'),
                  (558, 'GET')) y(permissionID, method));

-- Jornadas de trabalho

-- Insere a relação de permissões por grupo para o relatório de jornadas
-- de trabalho
-- Administrador do ERP, Administrador contratante, Atendente e Cliente
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2),(3),(6)) x(groupID),
          (VALUES (559, 'GET'),
                  (560, 'PATCH'),
                  (561, 'GET')) y(permissionID, method));

-- ====================================================[Área do Cliente]

-- -------------------------------------------------------------[ Base ]

-- Insere a relação de permissões por grupo para as página padrões para
-- todos usuários
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,6) x(groupID),
          (VALUES (600, 'GET'),
                  (601, 'GET'),
                  (602, 'GET'),
                  (603, 'GET'), (603, 'PUT'),
                  (604, 'GET'), (604, 'PUT'),
                  (605, 'GET'),
                  (606, 'GET')) y(permissionID, method));

-- =======================================================[ Manutenção ]
-- Usar em casos de ser necessário modificar as permissões existentes e
-- resetar para os valores padrão.

-- TRUNCATE erp.permissionsPerGroups;
-- ALTER SEQUENCE erp.permissionspergroups_permissionpergroupid_seq RESTART WITH 1;
-- TRUNCATE erp.permissions CASCADE;
-- ALTER SEQUENCE erp.permissions_permissionid_seq RESTART WITH 1;

-- ---------------------------------------------------------------------
-- Visualização das permissões por grupo
-- ---------------------------------------------------------------------
-- Permite visualizar as permissões para cada módulo do sistema por
-- grupo. Cada grupo formará uma coluna e cada módulo uma linha. Como
-- resultado teremos uma string com os métodos permitidos, que podem ser
-- GET (visualizar), POST (adicionar), PUT (modificar), DELETE (apagar).
-- ---------------------------------------------------------------------
CREATE TYPE erp.permissionData AS
(
  id           integer,
  name         varchar(100),
  description  varchar(100),
  g1           varchar(30),
  g2           varchar(30),
  g3           varchar(30),
  g4           varchar(30),
  g5           varchar(30),
  g6           varchar(30)
);

CREATE OR REPLACE FUNCTION erp.getPermissionData(searchValue varchar(100))
RETURNS SETOF erp.permissionData AS
$$
DECLARE
  permissionData  erp.permissionData%rowtype;
  nextPermission  record;
BEGIN
  FOR nextPermission IN
  SELECT p.permissionid AS id,
         p.name,
         p.description,
        (SELECT array_to_string(array_agg(distinct g1.httpMethod), ', ')
          FROM erp.permissionsPerGroups AS g1
         WHERE g1.permissionID = p.permissionid
           AND g1.groupid = 1) AS g1,
        (SELECT array_to_string(array_agg(distinct g2.httpMethod), ', ')
          FROM erp.permissionsPerGroups AS g2
         WHERE g2.permissionID = p.permissionid
           AND g2.groupid = 2) AS g2,
        (SELECT array_to_string(array_agg(distinct g3.httpMethod), ', ')
          FROM erp.permissionsPerGroups AS g3
         WHERE g3.permissionID = p.permissionid
           AND g3.groupid = 3) AS g3,
        (SELECT array_to_string(array_agg(distinct g4.httpMethod), ', ')
          FROM erp.permissionsPerGroups AS g4
         WHERE g4.permissionID = p.permissionid
           AND g4.groupid = 4) AS g4,
        (SELECT array_to_string(array_agg(distinct g5.httpMethod), ', ')
          FROM erp.permissionsPerGroups AS g5
         WHERE g5.permissionID = p.permissionid
           AND g5.groupid = 5) AS g5,
        (SELECT array_to_string(array_agg(distinct g6.httpMethod), ', ')
          FROM erp.permissionsPerGroups AS g6
         WHERE g6.permissionID = p.permissionid
           AND g6.groupid = 6) AS g6
  FROM erp.permissions AS p
 WHERE public.unaccented(p.name) ILIKE public.unaccented(searchValue)
  loop
    permissionData.id           = nextPermission.id;
    permissionData.name         = nextPermission.name;
    permissionData.description  = nextPermission.description;
    -- RAISE NOTICE 'ID %', nextPermission.id;
    -- RAISE NOTICE 'G1 %', replace(replace(replace(replace(replace(nextPermission.g1, 'GET', 'Ver'), 'POST', 'Adicionar'), 'PUT', 'Editar'), 'DELETE', 'Apagar'), 'PATCH', 'Recuperar');
    permissionData.g1           = replace(replace(replace(replace(replace(nextPermission.g1, 'GET', 'Ver'), 'POST', 'Adicionar'), 'PUT', 'Editar'), 'DELETE', 'Apagar'), 'PATCH', 'Recuperar');
    -- RAISE NOTICE 'G2 %', replace(replace(replace(replace(replace(nextPermission.g2, 'GET', 'Ver'), 'POST', 'Adicionar'), 'PUT', 'Editar'), 'DELETE', 'Apagar'), 'PATCH', 'Recuperar');
    permissionData.g2           = replace(replace(replace(replace(replace(nextPermission.g2, 'GET', 'Ver'), 'POST', 'Adicionar'), 'PUT', 'Editar'), 'DELETE', 'Apagar'), 'PATCH', 'Recuperar');
    -- RAISE NOTICE 'G3 %', replace(replace(replace(replace(replace(nextPermission.g3, 'GET', 'Ver'), 'POST', 'Adicionar'), 'PUT', 'Editar'), 'DELETE', 'Apagar'), 'PATCH', 'Recuperar');
    permissionData.g3           = replace(replace(replace(replace(replace(nextPermission.g3, 'GET', 'Ver'), 'POST', 'Adicionar'), 'PUT', 'Editar'), 'DELETE', 'Apagar'), 'PATCH', 'Recuperar');
    -- RAISE NOTICE 'G4 %', replace(replace(replace(replace(replace(nextPermission.g4, 'GET', 'Ver'), 'POST', 'Adicionar'), 'PUT', 'Editar'), 'DELETE', 'Apagar'), 'PATCH', 'Recuperar');
    permissionData.g4           = replace(replace(replace(replace(replace(nextPermission.g4, 'GET', 'Ver'), 'POST', 'Adicionar'), 'PUT', 'Editar'), 'DELETE', 'Apagar'), 'PATCH', 'Recuperar');
    -- RAISE NOTICE 'G5 %', replace(replace(replace(replace(replace(nextPermission.g5, 'GET', 'Ver'), 'POST', 'Adicionar'), 'PUT', 'Editar'), 'DELETE', 'Apagar'), 'PATCH', 'Recuperar');
    permissionData.g5           = replace(replace(replace(replace(replace(nextPermission.g5, 'GET', 'Ver'), 'POST', 'Adicionar'), 'PUT', 'Editar'), 'DELETE', 'Apagar'), 'PATCH', 'Recuperar');
    -- RAISE NOTICE 'G6 %', replace(replace(replace(replace(replace(nextPermission.g6, 'GET', 'Ver'), 'POST', 'Adicionar'), 'PUT', 'Editar'), 'DELETE', 'Apagar'), 'PATCH', 'Recuperar');
    permissionData.g6           = replace(replace(replace(replace(replace(nextPermission.g6, 'GET', 'Ver'), 'POST', 'Adicionar'), 'PUT', 'Editar'), 'DELETE', 'Apagar'), 'PATCH', 'Recuperar');

    RETURN NEXT permissionData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- SELECT * FROM erp.getPermissionData('%%');

-- ---------------------------------------------------------------------
-- Determinar se uma rota é permitida para um determinado grupo
-- ---------------------------------------------------------------------
-- Permite verificar se uma determinada rota é permitida para um
-- usuário.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.hasPermission(FgroupID integer,
  FrouteName varchar(100), FhttpMethod HTTPMethod)
RETURNS boolean AS
$$
DECLARE
  amount integer;
BEGIN
  IF (FgroupID IS NULL) THEN
    -- O usuário é inválido
    RAISE
      'Informe o usuário'
      USING ERRCODE = 'restrict_violation';
  END IF;
  IF (FrouteName IS NULL) THEN
    -- A rota é inválida
    RAISE
      'Informe a rota'
      USING ERRCODE = 'restrict_violation';
  ELSE
    IF (FrouteName = '') THEN
      -- A rota é inválida
      RAISE
        'Informe a rota'
        USING ERRCODE = 'restrict_violation';
    END IF;
  END IF;

  -- Monta a consulta
  SELECT count(*) into amount
    FROM erp.permissions AS P
   INNER JOIN erp.permissionspergroups AS PG USING (permissionid)
   WHERE P.name = FrouteName
     AND PG.httpMethod = FhttpMethod
     AND PG.groupid = FgroupID;

  IF (amount > 0) THEN
    RETURN true;
  END IF;

  RETURN false;
END
$$
LANGUAGE 'plpgsql';

-- SELECT erp.hasPermission(1, 'ADM\Parameterization\Cadastral\MeasureTypes\Add', 'GET');

-- ---------------------------------------------------------------------
-- Logins falhos
-- ---------------------------------------------------------------------
-- Armazena as informações dos logins falhos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.failedLogins (
  failedLoginID   serial,         -- O ID da falha
  userID          integer         -- O ID do usuário, se for um usuário
                  DEFAULT NULL,   -- existente
  username        varchar(20)     -- O login do usuário
                  NOT NULL,
  address         inet            -- O IP de origem do login
                  NOT NULL,
  attemptedAt     timestamp       -- A data/hora da última tentativa
                  NOT NULL
                  DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (failedLoginID)
);

-- ---------------------------------------------------------------------
-- Logins bem sucedidos
-- ---------------------------------------------------------------------
-- Armazena as informações dos logins executados com sucesso
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.successfullyLogins (
  successfullyLoginID   serial,         -- O ID do registro
  userID                integer         -- O ID do usuário, se for um usuário
                        DEFAULT NULL,   -- existente
  occurredAt            timestamp       -- A data/hora da ocorrência
                        NOT NULL
                        DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (successfullyLoginID)
);

-- ---------------------------------------------------------------------
-- Usuários
-- ---------------------------------------------------------------------
-- Armazena as informações dos usuários do sistema
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.users (
  userID            serial,         -- O ID do usuário
  groupID           integer         -- O ID do grupo ao qual pertence
                    NOT NULL,       -- este usuário
  name              varchar(100)    -- O nome do usuário
                    NOT NULL,
  role              varchar(50)     -- O cargo do usuário
                    NOT NULL,
  username          varchar(25)     -- O nome do usuário no sistema
                    NOT NULL
                    CHECK (POSITION(' ' IN username) = 0),
  password          char(122)       -- A senha criptografada
                    NOT NULL,
  phoneNumber       varchar(16)     -- O telefone de contato
                    NOT NULL,
  contractorID      integer         -- ID do contratante. Administradores
                    NOT NULL,       -- do ERP tem este valor igual a zero
  entityID          integer         -- O ID da entidade a qual o usuário
                    DEFAULT NULL,   -- está vinculado
  email             varchar(50)     -- O e-mail do usuário
                    NOT NULL
                    CHECK (POSITION(' ' IN email) = 0),
  blocked           boolean         -- Flag indicadora de usuário
                    NOT NULL        -- bloqueado para acesso ao sistema
                    DEFAULT false,
  expires           boolean         -- Flag indicadora de conta com uma
                    DEFAULT false,  -- validade determinada
  expiresAt         date            -- A data de expiração desta conta
                    DEFAULT null,
  timeRestriction   boolean         -- Flag indicadora de conta com
                    DEFAULT false,  -- restrição de horário
  suspended         boolean         -- Flag indicadora de usuário com
                    NOT NULL        -- acesso suspenso ao sistema
                    DEFAULT false,
  subAccount        boolean         -- Flag indicadora de subconta
                    NOT NULL
                    DEFAULT false,
  seeAllVehicles    boolean         -- Flag indicadora de que o usuário
                    NOT NULL        -- vê todos os veículos da conta
                    DEFAULT true,
  createdAt         timestamp       -- A data de criação do usuário
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  updatedAt         timestamp       -- A data de modificação usuário
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  blockedAt         timestamp       -- A data de bloqueio do usuário
                    DEFAULT NULL,
  blockedByUserID   integer         -- O ID do usuário que bloqueou
                    DEFAULT NULL,   -- este usuário
  lastLogin         timestamp       -- A data/hora do último acesso
                    DEFAULT NULL,   -- realizado com sucesso
  failedLogins      integer         -- Contador da quantidade de
                    DEFAULT 0,      -- tentativas frustradas de autenticação
  lastFailedLogin   timestamp       -- A data/hora do último acesso
                    DEFAULT NULL,   -- falho
  forceNewPassword  boolean         -- Flag indicadora para forçar a
                    DEFAULT FALSE,  -- mudança de senha no próximo acesso
  modules           text[]          -- Módulos do sistema que o usuário
                    NOT NULL        -- tem acesso
                    DEFAULT '{}',
  PRIMARY KEY (userID),
  UNIQUE (username, contractorID),
  FOREIGN KEY (groupID)
    REFERENCES erp.groups(groupID)
    ON DELETE RESTRICT
);

-- Insere a relação de usuários padrões
INSERT INTO erp.users (userID, groupID, username, password, name, role,
  contractorID, entityID, phoneNumber, email) VALUES
  ( 1, 1, 'admin', '54dS9prUue21Gkge4hGKkv.gZ8759c4fe60c9ab4ad4a38b579f54d392b1840d1bc55e48808e67e18fddcea36c44105cbb5b871a8a6c747dce366bab5b4',
   'Administrador ERP', 'Administrador do Sistema', 0, 1, '(11) 98450-3639',
   'emersoncavalcanti@gmail.com.br'),
  ( 2, 2, 'emerson', '54dS9prUue21Gkge4hGKkv.gZ8759c4fe60c9ab4ad4a38b579f54d392b1840d1bc55e48808e67e18fddcea36c44105cbb5b871a8a6c747dce366bab5b4',
   'Emerson Cavalcanti', 'Administrador do Sistema', 1, 1, '(11) 98450-3639',
   'emersoncavalcanti@gmail.com.br');

ALTER SEQUENCE erp.users_userid_seq RESTART WITH 3;

CREATE INDEX idx_users_contractorid_entityid ON erp.users(contractorid, entityid);
CREATE INDEX idx_users_groupid_name ON erp.users(groupid, name);
CREATE INDEX idx_users_contractorid_name ON erp.users(contractorid, name);

-- ---------------------------------------------------------------------
-- Gatilho de registro de logins com sucesso
-- ---------------------------------------------------------------------
-- Gatilho para lidar com os registros de logins com sucesso
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.registreUserLoginTransaction()
RETURNS trigger AS $BODY$
BEGIN
  -- Faz a atualização dos valores nos processos em que se cria ou
  -- altera uma entidade. Faz uso da variável especial TG_OP para
  -- para verificar a operação executada e de TG_WHEN para determinar o
  -- instante em que isto ocorre.
  IF (TG_OP = 'UPDATE') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      IF OLD.lastLogin <> NEW.lastLogin THEN
        INSERT INTO erp.successfullyLogins (userID)
          VALUES (NEW.userID);
      END IF;
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$BODY$ LANGUAGE plpgsql;

CREATE TRIGGER userTransactionTriggerBefore
  BEFORE UPDATE ON erp.users
  FOR EACH ROW EXECUTE PROCEDURE erp.registreUserLoginTransaction();


-- ---------------------------------------------------------------------
-- Restrição por horário
-- ---------------------------------------------------------------------
-- Armazena as restrições de horário para os usuários.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.timeRestrictions (
  timeRestrictionID serial,      -- O ID da restrição de tempo
  userID            integer      -- O ID do usuário ao qual pertence esta
                    NOT NULL,    -- restrição
  dayOfWeek         varchar(10)  -- O dia da semana, onde:
                    NOT NULL,    --   '0'..'6': o dia da semana (0 domingo),
                                 --   '*': qualquer um dos dias da semana,
                                 --   'workdays': dias úteis (segunda a sexta)
                                 --   'weekends' fins de semana (sábado e domingo)
  startAt           time         -- O horário de início da restrição
                    NOT NULL,
  endAt             time         -- O horário de término da restrição
                    NOT NULL,
  PRIMARY KEY (timeRestrictionID),
  FOREIGN KEY (userID)
    REFERENCES erp.users(userID)
    ON DELETE CASCADE
);


-- ---------------------------------------------------------------------
-- Tokens de autorização
-- ---------------------------------------------------------------------
-- Armazena as tokens para garantir o acesso do usuário quando o mesmo
-- marca a opção 'Permanecer conectado'.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.authorizationTokens (
  tokenID    serial,    -- O ID do token
  selector   char(32)   -- A chave usada para selecionar o token
             NOT NULL,
  token      char(64)   -- O token propriamente dito
             NOT NULL,
  userID     integer    -- O ID do usuário ao qual pertence este token
             NOT NULL,
  createdAt  timestamp  -- A data de criação do token
             NOT NULL
             DEFAULT CURRENT_TIMESTAMP,
  updatedAt  timestamp  -- A data de modificação do token
             NOT NULL
             DEFAULT CURRENT_TIMESTAMP,
  expires    timestamp  -- A data de expiração do token
             NOT NULL
             DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tokenID),
  FOREIGN KEY (userID)
    REFERENCES erp.users(userID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Tokens de autorização JWT
-- ---------------------------------------------------------------------
-- Armazena as tokens para garantir o acesso do usuário quando o mesmo
-- utiliza a API do sistema.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.authorizationWebTokens (
  tokenID      serial,      -- O ID do token
  contractorID integer      -- ID do contratante
               NOT NULL,
  selector     varchar(36)  -- A chave usada para selecionar o token
               NOT NULL,
  token        text         -- O token propriamente dito
               NOT NULL,
  userID       integer      -- O ID do usuário ao qual pertence este
               NOT NULL,    -- token
  createdAt    timestamp    -- A data de criação do token
               NOT NULL
               DEFAULT CURRENT_TIMESTAMP,
  updatedAt    timestamp    -- A data de modificação do token
               NOT NULL
               DEFAULT CURRENT_TIMESTAMP,
  expiresAt    timestamp    -- A data de expiração do token
               NOT NULL
               DEFAULT CURRENT_TIMESTAMP,
  renewed      boolean      -- Indica se o token foi renovado
               NOT NULL
               DEFAULT FALSE,
  PRIMARY KEY (tokenID),
  FOREIGN KEY (userID)
    REFERENCES erp.users(userID)
    ON DELETE CASCADE
);

CREATE INDEX idx_authorizationWebTokens_selector_token ON erp.authorizationWebTokens(selector, token);
CREATE INDEX idx_authorizationWebTokens_updatedAt ON erp.authorizationWebTokens(updatedAt);

-- ---------------------------------------------------------------------
-- Renovação de token
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que determina se o token informado já está
-- renovado ou não. Se estiver, devolve o token já registrado na
-- renovação, caso contrário insere o novo token informado como sendo o
-- token renovado.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.renew_token(FcurrentToken text,
  FnewToken text, FgeneratedAt timestamp, FexpiresAt timestamp)
RETURNS text AS $$
DECLARE
  oldToken  record;
  newToken  text;
BEGIN
  -- Verifica se o token antigo existe e não foi renovado
  SELECT INTO oldToken
         contractorID,
         selector,
         userID,
         renewed
    FROM erp.authorizationWebTokens
   WHERE token = FcurrentToken
     FOR UPDATE;
  IF NOT FOUND THEN
    RAISE EXCEPTION 'Token não encontrado';
  END IF;

  -- Verifica se o token já foi renovado
  IF oldToken.renewed THEN
    -- Localizamos o token mais recente e retornamos
    SELECT INTO newToken
           token
      FROM erp.authorizationWebTokens
     WHERE userID = oldToken.userID
       AND contractorID = oldToken.contractorID
       AND selector = oldToken.selector
     ORDER BY createdAt DESC
     LIMIT 1;
     IF NOT FOUND THEN
       RAISE EXCEPTION 'Token renovado não encontrado';
     END IF;

     RETURN newToken;
  ELSE
    -- Gera um novo token, mantendo o selector que identifica o
    -- equipamento onde o usuário está conectado
    INSERT INTO erp.authorizationWebTokens (contractorID, selector, token, userID, createdAt, updatedAt, expiresAt)
    VALUES (oldToken.contractorID, oldToken.selector, FnewToken, oldToken.userID, FgeneratedAt, FgeneratedAt, FexpiresAt);

    -- Marca os tokens antigo como renovados
    UPDATE erp.authorizationWebTokens
       SET renewed = TRUE
     WHERE selector = oldToken.selector
       AND userID = oldToken.userID
       AND contractorID = oldToken.contractorID
       AND token != FnewToken;

    RETURN FnewToken;
  END IF;
END;
$$ LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- Ativações
-- ---------------------------------------------------------------------
-- Armazena as informações de ativação de um usuário.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.activations (
  activationID  serial,     -- O ID da ativação
  userID        integer     -- O ID do usuário ao qual pertence esta
                NOT NULL,   -- ativação
  code          char(32),   -- O código de ativação
  completed     boolean     -- A flag indicativa de que o usuário
                NOT NULL    -- completou a ativação de sua conta
                DEFAULT false,
  completedAt   timestamp   -- A data/hora em que ocorreu esta ativação
                DEFAULT NULL,
  PRIMARY KEY (activationID),
  FOREIGN KEY (userID)
    REFERENCES erp.users(userID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Tokens de recuperação de senha
-- ---------------------------------------------------------------------
-- Armazena as tokens para permitir a redefinição da senha do usuário
-- quando o mesmo solicita pela interface. Esta solicitação possui um
-- tempo em que permanece disponível e, após isto, é descartada. O token
-- permite "esconder" o ID real do usuário, garantindo maior segurança
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.reminders (
  reminderID  serial,         -- O ID do token de recuperação
  token       char(64)        -- O token de recuperação de senha a ser
              NOT NULL,       -- enviado ao usuário
  userID      integer         -- O ID do usuário ao qual pertence este
              NOT NULL,       -- token
  createdAt   timestamp       -- A data de criação do token
              NOT NULL
              DEFAULT CURRENT_TIMESTAMP,
  updatedAt   timestamp       -- A data de modificação do token
              NOT NULL
              DEFAULT CURRENT_TIMESTAMP,
  completed   boolean         -- O indicativo de que a modificação de
              DEFAULT FALSE,  -- senha foi realizada com sucesso
  completedAt timestamp       -- A data em que ocorreu a modificação
              DEFAULT NULL,   -- da senha
  expires     timestamp       -- A data de expiração do token
              NOT NULL
              DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (reminderID),
  FOREIGN KEY (userID)
    REFERENCES erp.users(userID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Permissões por módulo
-- ---------------------------------------------------------------------
-- Armazena as permissões de um usuário por módulo. Esta tabela é usada
-- para determinar se um usuário possui ou não acesso a um determinado
-- módulo do sistema.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.modulePermissions (
  modulePermissionID  serial,       -- O ID da permissão do módulo por usuário
  userID              integer       -- O ID do usuário
                      NOT NULL,
  moduleID            integer       -- O ID do módulo
                      NOT NULL,
  PRIMARY KEY (modulePermissionID),
  UNIQUE (userID, moduleID),
  FOREIGN KEY (userID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (moduleID)
    REFERENCES erp.modules(moduleID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Autenticações realizadas
-- ---------------------------------------------------------------------
-- Armazena as autenticações realizadas por um usuário. Esta tabela é
-- usada para efeito de auditoria.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.authenticationLog (
  logID       serial,       -- O ID da permissão do módulo por usuário
  userID      integer       -- O ID do usuário
              NOT NULL,
  userAgent   varchar(255)  -- O agente de usuário
              NOT NULL,
  ipAddress   inet          -- O endereço de IP do usuário
              NOT NULL,
  occurredAt  timestamp     -- A data/hora da autenticação
              NOT NULL
              DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (logID),
  FOREIGN KEY (userID)
    REFERENCES erp.users(userID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Códigos de validação de conta
-- ---------------------------------------------------------------------
-- Armazena os códigos de validação de conta.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.accountValidationCodes (
  accountValidationCodeID serial,       -- O ID do código de validação
  userID                  integer       -- O ID do usuário
                          NOT NULL,
  validationCode          char(4)       -- O código de validação
                          NOT NULL,
  requestedAt             timestamp     -- A data/hora da requisição
                          NOT NULL
                          DEFAULT CURRENT_TIMESTAMP,
  expirationDate          timestamp     -- A data de expiração do código
                          NOT NULL
                          DEFAULT CURRENT_TIMESTAMP + INTERVAL '1 hour',
  PRIMARY KEY (accountValidationCodeID),
  FOREIGN KEY (userID)
    REFERENCES erp.users(userID)
    ON DELETE CASCADE
);
