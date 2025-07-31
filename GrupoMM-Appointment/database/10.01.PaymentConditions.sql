-- =====================================================================
-- Financeiro
-- =====================================================================
-- Tabelas utilizada no controle financeiro do sistema
-- =====================================================================

-- ---------------------------------------------------------------------
-- Contas bancárias
-- ---------------------------------------------------------------------
-- Armazena as informações de contas bancárias do contratante.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.accounts (
  accountID     serial,       -- ID da conta
  contractorID  integer       -- ID do contratante
                NOT NULL,
  entityID      integer       -- ID da entidade para a qual a conta foi
                NOT NULL,     -- definida
  bankID        char(3),      -- O número do banco
  agencyNumber  varchar(10)   -- Número da agência com dígito verificador
                NOT NULL,
  accountNumber varchar(15)   -- Número da conta com dígito verificador
                NOT NULL,
  wallet        varchar(10)   -- A carteira de pagamento
                NOT NULL,
  pixKeyTypeID  integer       -- ID do tipo de chave PIX definido
                NOT NULL
                DEFAULT 1,
  pixKey        varchar(72),  -- A chave PIX para transferências
  PRIMARY KEY (accountID),    -- O indice primário
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (entityID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE,
  FOREIGN KEY (bankID)
    REFERENCES erp.banks(bankID)
    ON DELETE RESTRICT
);

INSERT INTO erp.accounts (accountID, contractorID, entityID, bankID, agencyNumber, accountNumber, wallet) VALUES
  (1, 1, 1, '237', '1574-1', '52338-0', '9');

ALTER SEQUENCE erp.accounts_accountid_seq RESTART WITH 2;

-- ---------------------------------------------------------------------
-- Definições dos meios de pagamentos
-- ---------------------------------------------------------------------
-- Permite definir as configurações adicionais para um meio de pagamento
-- e que é necessário para seu processamento. Por exemplo, um boleto
-- deve ter configurado aspectos específicos. Porém, podemos ter mais de
-- uma configuração de boletos válidos no sistema, inclusive por bancos
-- distintos.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.definedMethods (
  definedMethodID serial,         -- ID da definição
  contractorID    integer         -- ID da entidade contratante
                  NOT NULL,
  name            varchar(50)     -- O nome da definição do meio
                  NOT NULL,       -- de pagamento
  paymentMethodID integer         -- ID do meio de pagamento
                  NOT NULL,
  accountID       integer         -- O ID da conta bancária a ser
                  NOT NULL,       -- utilizada
  parameters      text            -- Os parâmetros configurados
                  NOT NULL,       -- em formato JSON
  billingCounter  integer         -- O contador de cobranças realizadas
                  NOT NULL        -- e que é usado para numerar, por
                  DEFAULT 0,      -- exemplo, boletos no banco
  shippingCounter integer         -- O contador de envios de arquivos de
                  NOT NULL        -- remessa, utilizado em boletos
                  DEFAULT 0,
  dayCounter      integer         -- O contador de envios de arquivos de
                  NOT NULL        -- remessa num mesmo dia
                  DEFAULT 0,
  counterDate     date            -- A data do contador
                  DEFAULT CURRENT_DATE,
  blocked         boolean         -- O indicativo de definição
                  DEFAULT FALSE,  -- bloqueada para uso
  PRIMARY KEY (definedMethodID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (paymentMethodID)
    REFERENCES erp.paymentMethods(paymentMethodID)
    ON DELETE CASCADE,
  FOREIGN KEY (accountID)
    REFERENCES erp.accounts(accountID)
    ON DELETE CASCADE
);

-- ALTER TABLE erp.definedMethods
--   ADD COLUMN shippingCounter integer NOT NULL DEFAULT 0;
-- ALTER TABLE erp.definedMethods
--   ADD COLUMN dayCounter integer NOT NULL DEFAULT 0;
-- ALTER TABLE erp.definedMethods
--   ADD COLUMN counterDate date DEFAULT CURRENT_DATE;

INSERT INTO erp.definedMethods (definedMethodID, contractorID, name,
  paymentMethodID, parameters, blocked, accountID) VALUES
  (1, 1, 'Bradesco', 5,
   '{ "emitterCode": 5167709, "kindOfDocument": "RC", "CIP": "000", "instructionID": 6, "instructionDays": 59 }', FALSE, 1),
  (2, 1, 'Transferência eletrônica', 6,
   '{ "emitterCode": "5167709", "kindOfDocument": "RC", "CIP": "000" }', FALSE, 1);

ALTER SEQUENCE erp.definedmethods_definedmethodid_seq RESTART WITH 2;

-- ---------------------------------------------------------------------
-- Definições das tarifas por meio de pagamento
-- ---------------------------------------------------------------------
-- Permite definir os valores cobrados pela emissão de título através do
-- meio de pagamento.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.definedMethodTariffs (
  definedMethodTariffID serial,         -- ID da tarifa para o método definido
  definedMethodID       integer         -- ID do método para o qual esta
                        NOT NULL,       -- tarifa será cobrada
  basicFare             numeric(12,2)   -- Tarifa básica (cobrada pela
                        NOT NULL        -- emissão)
                        DEFAULT 0.00,
  validFrom             date            -- A data à partir da qual este
                        NOT NULL        -- valor passou a ser cobrado
                        DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (definedMethodTariffID),
  FOREIGN KEY (definedMethodID)
    REFERENCES erp.definedMethods(definedMethodID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Condições de pagamento
-- ---------------------------------------------------------------------
-- Permite estruturar as condições de pagamentos permitidas.
-- 
-- As condições de pagamento são as definições estabelecidas na
-- negociação para a realização da entrega do dinheiro, por exemplo, se
-- é à vista ou parcelado, quantas parcelas, se haverá entrada, se há
-- juros, entre outras questões. 
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.paymentConditions (
  paymentConditionID  serial,         -- ID da condição de pagamento
  contractorID        integer         -- ID da entidade contratante
                      NOT NULL,
  name                varchar(50),    -- O nome da condição
  paymentMethodID     integer         -- ID do meio de pagamento
                      NOT NULL,
  definedMethodID     integer         -- ID da configuração da forma de
                      DEFAULT NULL,   -- pagamento
  paymentFormID       integer         -- ID da forma de pagamento
                      NOT NULL,
  paymentInterval     varchar(50)     -- O intervalo entre pagamentos
                      NOT NULL
                      DEFAULT '0',
  timeUnit  PaymentIntervalTimeUnit   -- A unidade de tempo dos valores
                      NOT NULL        -- descritos no intervalo entre
                      DEFAULT 'DAY',  -- pagamentos
  usePaymentGateway   boolean         -- Flag indicativo de usar
                      DEFAULT FALSE,  -- gateway de pagamento
  formatAsBooklet     boolean         -- Flag indicativo de formatar como
                      DEFAULT FALSE,  -- carnê de pagamentos
  blocked             boolean         -- O indicativo de condição de
                      DEFAULT FALSE,  -- pagamento bloqueada
  PRIMARY KEY (paymentConditionID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (paymentMethodID)
    REFERENCES erp.paymentMethods(paymentMethodID)
    ON DELETE RESTRICT,
  FOREIGN KEY (definedMethodID)
    REFERENCES erp.definedMethods(definedMethodID)
    ON DELETE RESTRICT,
  FOREIGN KEY (paymentFormID)
    REFERENCES erp.paymentForms(paymentFormID)
    ON DELETE RESTRICT
);

INSERT INTO erp.paymentConditions (paymentConditionID, contractorID,
  name, paymentMethodID, definedMethodID, paymentFormID, paymentInterval,
  timeUnit, usePaymentGateway, formatAsBooklet) VALUES
  (1, 1, 'Dinheiro', 1, NULL, 1, '0', 'DAY', FALSE, FALSE),
  (2, 1, 'Boleto à vista', 5, 1, 1, '2', 'DAY', FALSE, FALSE),
  (3, 1, 'Boleto mensal', 5, 1, 2, '1', 'MONTH', FALSE, FALSE),
  (4, 1, 'Carnê 12 vezes', 5, 1, 2, '1/2/3/4/5/6/7/8/9/10/11/12', 'MONTH', FALSE, TRUE);

ALTER SEQUENCE erp.paymentconditions_paymentconditionid_seq RESTART WITH 5;

-- ---------------------------------------------------------------------
-- Tipos de parcelamento
-- ---------------------------------------------------------------------
-- Armazena as informações de tipos de parcelamentos e dá os requisitos
-- de como este parcelamento é realizado.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.installmentTypes (
  installmentTypeID         serial,       -- ID do tipo de parcelamento
  contractorID              integer       -- ID do contratante
                            NOT NULL,
  name                      varchar(30)   -- Nome do tipo de parcelamento
                            NOT NULL,
  minimumInstallmentValue   numeric(9,2)  -- Valor de parcela mínima
                            NOT NULL
                            DEFAULT 0.0,
  maxNumberOfInstallments   smallint      -- Quantidade máxima de parcelas
                            NOT NULL
                            DEFAULT 12
                            CHECK (maxNumberOfInstallments > 0),
  interestRate              numeric(6,3)  -- Taxa de juros
                            NOT NULL
                            DEFAULT 0.0
                            CHECK (interestRate >= 0.00),
  interestFrom              smallint      -- Cobrar juros a partir da
                            NOT NULL      -- parcela (0 não cobra)
                            DEFAULT 0
                            CHECK (interestFrom >= 0 AND interestFrom <= maxNumberOfInstallments),
  calculationFormula        smallint      -- Fórmula de cálculo
                            NOT NULL      --   1: juros simples
                            DEFAULT 1,    --   2: tabela price
  blocked                   boolean       -- O indicativo de tipo de
                            DEFAULT false,-- parcelamento bloqueado
  createdAt                 timestamp     -- A data de criação do tipo
                            NOT NULL      -- de parcelamento
                            DEFAULT CURRENT_TIMESTAMP,
  createdByUserID           integer       -- O ID do usuário responsável
                            NOT NULL,     -- pelo cadastro deste tipo
                                          -- de parcelamento
  updatedAt                 timestamp     -- A data de modificação do
                            NOT NULL      -- tipo de parcelamento
                            DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID           integer       -- O ID do usuário responsável
                            NOT NULL,     -- pela última modificação
                                          -- deste tipo de parcelamento
  PRIMARY KEY (installmentTypeID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

-- Insere os tipos de parcelamentos padrões
INSERT INTO erp.installmentTypes (installmentTypeID, contractorID, name, minimumInstallmentValue, maxNumberOfInstallments, interestRate, interestFrom, createdByUserID, updatedByUserID) VALUES
  ( 1, 1, 'Até 12 vezes sem juros', 50.00, 12, 0.000, 0, 1, 1);

ALTER SEQUENCE erp.installmenttypes_installmenttypeid_seq RESTART WITH 2;

-- ---------------------------------------------------------------------
-- Tipos de cobranças
-- ---------------------------------------------------------------------
-- Armazena as informações de tipos de cobranças e dá os requisitos de
-- como ou quando esta cobrança é realizada. Os tipos de cobrança que
-- podem ser definidos são:
--   - Cobranças de serviços discriminados em ordens de serviço e que
--     foram executadas por técnicos (próprios ou terceirizados);
--   - Valores de taxas e/ou outros valores (tais como acessórios,
--     serviços adicionais, etc) a serem cobrados do cliente mensalmente
--     e/ou em algum outro momento do relacionamento (tal como no início
--     ou término do contrato);
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.billingTypes (
  billingTypeID       serial,         -- ID do tipo de cobrança
  contractorID        integer         -- ID do contratante
                      NOT NULL,
  name                varchar(60)     -- Nome do tipo de cobrança
                      NOT NULL,
  description         text,           -- Uma breve descrição
  ratePerEquipment    boolean         -- Flag indicador de cobrança
                      NOT NULL        -- realizada em função da
                      DEFAULT false,  -- quantidade de instalações
  inAttendance        boolean         -- Flag indicador de cobrança
                      NOT NULL        -- realizada por ordem de serviço
                      DEFAULT false,
  preApproved         boolean         -- Flag indicador de serviço pré
                      NOT NULL        -- aprovado, ou seja, não precisa
                      DEFAULT false,  -- de anuência do cliente
  billingMoments      int[]           -- Os momentos em que este tipo de
                      DEFAULT '{1}',  -- cobrança pode ser realizado
  installmentTypeID   integer         -- Número de identificação do tipo
                      DEFAULT NULL,   -- de parcelamento permitido (nulo
                                      -- não permite parcelamento)
  executionTime       time            -- O tempo de execução previsto
                      NOT NULL        -- para um serviço
                      DEFAULT '00:00'::time,
  createdAt           timestamp       -- A data de criação do tipo de
                      NOT NULL        -- cobrança
                      DEFAULT CURRENT_TIMESTAMP,
  createdByUserID     integer         -- O ID do usuário responsável pelo
                      NOT NULL,       -- cadastro deste tipo de cobrança
  updatedAt           timestamp       -- A data de modificação do tipo de
                      NOT NULL        -- cobrança
                      DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID     integer         -- O ID do usuário responsável pela
                      NOT NULL,       -- última modificação deste tipo de
                                      -- cobrança
  PRIMARY KEY (billingTypeID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

ALTER TABLE erp.billingTypes
  ADD CONSTRAINT billingtypes_service_check
  CHECK ((inAttendance = FALSE AND preApproved = FALSE) OR (inAttendance = TRUE));

-- Insere os tipos de cobranças padrões
INSERT INTO erp.billingTypes (billingTypeID, contractorID, name,
  description,
  ratePerEquipment, inAttendance, preApproved, billingMoments, installmentTypeID, executionTime, createdByUserID, updatedByUserID) VALUES
  ( 1, 1, 'Adesão',
    'A taxa de adesão é um valor cobrado para cobrir os custos relacionados ao início da relação com o cliente.',
    true, false, false,     '{2}',    1, '00:00'::time, 1, 1),
  ( 2, 1, 'Serviço de instalação',
    'Serviço em que o equipamento de rastreamento é instalado, bem como acessórios e outros dispositivos acoplados, tais como botão de pânico, sirene, etc. Está incluso a fiação para conexão do equipamento.',
    true,  true,  true,   '{1,2}',    1, '01:00'::time, 1, 1),
  ( 3, 1, 'Serviço de reinstalação',
    'Serviço em que o equipamento de rastreamento é reinstalado, incluíndo acessórios nele acoplados, garantindo seu pleno funcionamento.',
    true,  true, false,     '{1}',    1, '01:00'::time, 1, 1),
  ( 4, 1, 'Serviço de manutenção',
    'Serviço que realiza a manutenção do equipamento e acessórios nele acoplados, garantindo seu pleno funcionamento.',
    true,  true, false,     '{1}', null, '01:00'::time, 1, 1),
  ( 5, 1, 'Transferência de equipamento',
    'Serviço que realiza a transferência do equipamento e acessórios de um veículo para outro.',
    true,  true, false,     '{1}',    1, '01:00'::time, 1, 1),
  ( 6, 1, 'Retirada de equipamento',
    'Serviço de retirada do equipamento (e acessórios, se necessário).',
    true,  true,  true, '{1,3,4}', null, '00:30'::time, 1, 1),
  ( 7, 1, 'Acessório',
    'Cobrança de acessório acoplado ao equipamento de rastreamento.',
    true, false, false,     '{5}', null, '00:00'::time, 1, 1),
  ( 8, 1, 'Técnico fixo',
    'Técnico especializado disponibilizado pela contratada para permanecer nas dependências do cliente para executar quaisquer serviços técnicos necessários nos equipamentos de rastreamento da contratada, tais como instalação, manutenção, transferência de equipamento, dentre outras.',
    false, false, false,     '{5}', null, '00:00'::time, 1, 1);

ALTER SEQUENCE erp.billingtypes_billingtypeid_seq RESTART WITH 9;

-- ---------------------------------------------------------------------
-- Dias de vencimento
-- ---------------------------------------------------------------------
-- Contém os dias de vencimento permitidos para os contratos dos
-- clientes.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.dueDays (
  dueDayID      serial,    -- ID do dia de vencimento
  contractorID  integer    -- ID do contratante
                NOT NULL,
  day           smallint   -- O dia de vencimento
                NOT NULL,
  CHECK (day BETWEEN 1 AND 31),
  UNIQUE (contractorID, day),
  PRIMARY KEY (dueDayID)
);

INSERT INTO erp.dueDays (dueDayID, contractorID, day) VALUES
  ( 1, 1,  5),
  ( 2, 1, 10),
  ( 3, 1, 15),
  ( 4, 1, 20),
  ( 5, 1, 25),
  ( 6, 1, 30);

ALTER SEQUENCE erp.duedays_duedayid_seq RESTART WITH 7;
