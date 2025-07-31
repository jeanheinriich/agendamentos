-- =====================================================================
-- INCLUSÃO DO CONTROLE DE PRESTADORES DE SERVIÇOS
-- =====================================================================
-- Esta modificação visa incluir um cadastro separado de prestadores de
-- serviços, bem como definir como um serviço executado por um técnico
-- será cobrado do cliente e, da mesma forma, pago ao prestador de
-- serviços
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- Alterações no cadastro de entidades
-- ---------------------------------------------------------------------

-- Retiramos a limitação de prestador de serviços ser um sub-grupo de
-- fornecedores
ALTER TABLE erp.entities
  DROP CONSTRAINT entities_check;

-- Acrescentamos a informação se devemos emitir ou não NF para o
-- prestador de serviços
ALTER TABLE erp.entities
  ADD COLUMN issueInvoice boolean NOT NULL DEFAULT false;

-- ---------------------------------------------------------------------
-- Inclusão de tipos de chaves PIX
-- ---------------------------------------------------------------------

-- Incluimos uma tabela que permite definir os tipos possíveis de chave
-- PIX.
CREATE TABLE IF NOT EXISTS erp.pixKeyTypes (
  pixKeyTypeID serial,         -- ID da tarifa para o método definido
  name         varchar(10)     -- Nome que descreve o tipo da chave
               NOT NULL,
  PRIMARY KEY (pixKeyTypeID)
);

INSERT INTO erp.pixKeyTypes (pixKeyTypeID, name) VALUES
  (1, 'Nenhuma'),
  (2, 'CPF/CNPJ'),
  (3, 'E-mail'),
  (4, 'Celular'),
  (5, 'Aleatória');

ALTER SEQUENCE erp.pixkeytypes_pixkeytypeid_seq RESTART WITH 6;

-- ---------------------------------------------------------------------
-- Alterações na tabela para armazenamento de informações de contas
-- ---------------------------------------------------------------------
-- Alteramos a tabela de contas para permitir armazenar informações
-- separadas por entidade, permitindo reutilizar esta tabela para
-- armazenar as informações para pagamentos de prestadores de serviços,
-- da mesma forma que permite lidar com as informações de contas do
-- contratante
-- ---------------------------------------------------------------------

-- 1. Incluímos a nova coluna para armazenar o ID da entidade
ALTER TABLE erp.accounts
  ADD COLUMN entityID integer;

-- 2. Preenchemos a nova coluna com informações válidas
UPDATE erp.accounts
   SET entityID = contractorID;

-- 3. Adicionamos o constraint de não nulo e nulo
ALTER TABLE erp.accounts
  ALTER COLUMN entityID SET NOT NULL;
ALTER TABLE erp.accounts
  ALTER COLUMN bankID SET DEFAULT NULL;
ALTER TABLE erp.accounts 
  ALTER COLUMN agencyNumber DROP NOT NULL;
ALTER TABLE erp.accounts 
  ALTER COLUMN agencyNumber SET DEFAULT NULL;
ALTER TABLE erp.accounts 
  ALTER COLUMN accountNumber DROP NOT NULL;
ALTER TABLE erp.accounts 
  ALTER COLUMN accountNumber SET DEFAULT NULL;
ALTER TABLE erp.accounts 
  ALTER COLUMN wallet DROP NOT NULL;
ALTER TABLE erp.accounts 
  ALTER COLUMN wallet SET DEFAULT NULL;
ALTER TABLE erp.accounts 
  ALTER COLUMN pixKey SET DEFAULT NULL;

-- 4. Adicionamos o relacionamento com a tabela de entidades
ALTER TABLE erp.accounts
  ADD CONSTRAINT accounts_entityid_fkey
  FOREIGN KEY (entityID)
  REFERENCES erp.entities(entityID)
  ON DELETE CASCADE;

-- 5. Adicionamos os campos adicionais para informar uma chave PIX
ALTER TABLE erp.accounts
  ADD COLUMN pixKeyTypeID integer NOT NULL DEFAULT 1;
ALTER TABLE erp.accounts
  ADD COLUMN pixKey varchar(72);

-- 6. Adicionamos o relacionamento com a tabela de tipos de chaves PIX
ALTER TABLE erp.accounts
  ADD CONSTRAINT accounts_pixkeytypeid_fkey
  FOREIGN KEY (pixKeyTypeID)
  REFERENCES erp.pixKeyTypes(pixKeyTypeID)
  ON DELETE RESTRICT;

-- 7. Alteramos questões quanto a exclusão, permitindo remoção em
--    cascata de entidades relacionadas
ALTER TABLE erp.subsidiaries
  DROP CONSTRAINT subsidiaries_entityid_fkey;
ALTER TABLE erp.subsidiaries
  ADD CONSTRAINT subsidiaries_entityid_fkey
    FOREIGN KEY (entityID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE;
ALTER TABLE erp.subsidiaries
  DROP CONSTRAINT subsidiaries_regionaldocumenttype_fkey;
ALTER TABLE erp.subsidiaries
  ADD CONSTRAINT subsidiaries_regionaldocumenttype_fkey
    FOREIGN KEY (regionalDocumentType)
    REFERENCES erp.documentTypes(documentTypeID)
    ON DELETE RESTRICT;
ALTER TABLE erp.mailings
  DROP CONSTRAINT mailings_entityid_fkey;
ALTER TABLE erp.mailings
  ADD CONSTRAINT mailings_entityid_fkey
    FOREIGN KEY (entityID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE;
ALTER TABLE erp.mailings
  DROP CONSTRAINT mailings_subsidiaryid_fkey;
ALTER TABLE erp.mailings
  ADD CONSTRAINT mailings_subsidiaryid_fkey
    FOREIGN KEY (subsidiaryID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE CASCADE;
ALTER TABLE erp.phones
  DROP CONSTRAINT phones_entityid_fkey;
ALTER TABLE erp.phones
  ADD CONSTRAINT phones_entityid_fkey
    FOREIGN KEY (entityID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE;
ALTER TABLE erp.phones
  DROP CONSTRAINT phones_subsidiaryid_fkey;
ALTER TABLE erp.phones
  ADD CONSTRAINT phones_subsidiaryid_fkey
    FOREIGN KEY (subsidiaryID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE CASCADE;

-- ---------------------------------------------------------------------
-- Inclusão de coordenadas geográficas de referência
-- ---------------------------------------------------------------------
CREATE TABLE erp.geographicCoordinates (
  geographicCoordinateID  serial,         -- ID da coordenada geográfica
  contractorID            integer         -- ID do contratante
                          NOT NULL,
  entityID                integer         -- ID da entidade na qual ela
                          NOT NULL,       -- é usada
  name                    varchar(100)     -- O nome da coordenada
                          NOT NULL,
  location                point           -- A coordenada geográfica
                          NOT NULL,
  PRIMARY KEY (geographicCoordinateID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

CREATE INDEX ON erp.geographicCoordinates USING GIST(location);

INSERT INTO erp.geographicCoordinates (geographicCoordinateID, contractorID, entityID, name, location) VALUES
  ( 1, 1, 1, 'Sede Grupo M&M', point(-23.3292135,-46.7273893)),
  ( 2, 7, 7, 'Sede E. F. de Morais', point(-23.5170058, -47.5017447));

ALTER SEQUENCE erp.geographiccoordinates_geographiccoordinateid_seq RESTART WITH 3;

-- Acrescentamos a coordenada geográfica no cadastro do contratante
-- para que, salvo se especificado, os valores de deslocamento sejam
-- computados à partir deste local
ALTER TABLE erp.entities
  ADD COLUMN defaultCoordinateID integer;
UPDATE erp.entities SET defaultCoordinateID = 1 WHERE entityID = 1;
UPDATE erp.entities SET defaultCoordinateID = 2 WHERE entityID = 7;

-- Acrescentamos no contrato as informação de valores cobrados:
-- 1. Tempo máximo de espera do técnico no local agendado para que o
--    veículo/cliente esteja disponível para execução do serviço.
ALTER TABLE erp.contracts
  ADD COLUMN maxWaitingTime integer NOT NULL DEFAULT 15;
-- 2. Visita improdutiva (quando o técnico comparece ao local mas não
--    consegue executar o serviço por algum motivo)
ALTER TABLE erp.contracts
  ADD COLUMN unproductiveVisit numeric(12, 2) NOT NULL DEFAULT 100.00;
ALTER TABLE erp.contracts
  ADD COLUMN unproductiveVisitType integer NOT NULL DEFAULT 2;
ALTER TABLE erp.contracts
  ADD CONSTRAINT contracts_unproductivevisittype_fkey
    FOREIGN KEY (unproductiveVisitType)
    REFERENCES erp.measureTypes(measureTypeID)
    ON DELETE RESTRICT;
-- 3. Prazo mínimo (em horas ou dias) de antecedência para que o cliente
--    possa cancelar ou reagendar um serviço sem cobranda de taxa
ALTER TABLE erp.contracts
  ADD COLUMN minimumTime integer NOT NULL DEFAULT 1;
ALTER TABLE erp.contracts
  ADD COLUMN minimumTimeType integer NOT NULL DEFAULT 2;
-- 4. Visita frustada (quando o cliente cancela uma visita num tempo
--    muito curto que não haja tempo hábil para avisar o técnico)
ALTER TABLE erp.contracts
  ADD COLUMN frustratedVisit numeric(12, 2) NOT NULL DEFAULT 100.00;
ALTER TABLE erp.contracts
  ADD COLUMN frustratedVisitType integer NOT NULL DEFAULT 2;
ALTER TABLE erp.contracts
  ADD CONSTRAINT contracts_frustratedvisittype_fkey
    FOREIGN KEY (frustratedVisitType)
    REFERENCES erp.measureTypes(measureTypeID)
    ON DELETE RESTRICT;
-- 5. Coordenada geográfica de referência para que os valores de
--    deslocamento sejam computados à partir deste local
ALTER TABLE erp.contracts
  ADD COLUMN geographicCoordinateID integer;
UPDATE erp.contracts
   SET geographicCoordinateID = 1
 WHERE contractorID = 1;
UPDATE erp.contracts
   SET geographicCoordinateID = 2
 WHERE contractorID = 7;
ALTER TABLE erp.contracts
  ALTER COLUMN geographicCoordinateID SET NOT NULL;
ALTER TABLE erp.contracts
  ADD CONSTRAINT contracts_geographiccoordinateid_fkey
    FOREIGN KEY (geographicCoordinateID)
    REFERENCES erp.geographicCoordinates(geographicCoordinateID)
    ON DELETE CASCADE;

-- ---------------------------------------------------------------------
-- Alterações da maneira como especificamos os valores a serem cobrados
-- ---------------------------------------------------------------------
-- O sistema, inicialmente, tinha sido concebido para que todos os tipos
-- de cobranças estivesse armazenados nas tabelas de planos e, também,
-- replicado na tabela de contratos. Cada valor possível de ser cobrado
-- era referenciado com a tabela de tipos de cobranças (billingTypes).
-- 
-- Com o desenvolvimento do sistema, foi retirado destas tabelas os
-- valores referentes as mensalidades e das multas por quebra do período
-- de fidelidade.
-- 
-- Com o desenvolvimento das ordens de serviço, surgiu a necessidade de
-- referenciar os serviços executados por técnicos (como as instalações,
-- manutenções, etc) com a tabela de prestadores de serviços, de forma
-- que os valores cobrados por cada prestador de serviços possa ser
-- relacionada, bem como que os valores a serem pagos aos prestadores de
-- serviços seja devidamente computada.
-- 
-- Para isto, modificou-se os comporamentos destas tabelas da seguinte
-- forma:
-- 1. A tabela erp.billingTypes para a contar quaisquer valores a serem
-- passíveis de serem cobrados dos clientes, bem como possa discriminar
-- os serviços técnicos executados pelos "prestadores de serviço". Nela
-- acrescentaremos novos campos para prever o tempo médio de execução de
-- um serviço técnico;
-- 
-- 2. As tabelas de valores cobrados por plano, e consequentemente os
-- contratos, deixaram de cadastrar novos tipos de cobranças. Todas elas
-- ficam visíveis na tela e, se ela se aplica ao contrato ou plano, nos
-- associamos esta cobrança e informamos um valor.
-- 
-- 3. Teremos um botão para permitir reajustar o valor por plano e
-- respectivos contratos, discriminando o valor do plano e relacionando
-- apenas àqueles contratos que tenham um valor diferente.
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- Alterações na tabela de tipos de cobranças
-- ---------------------------------------------------------------------

-- Lidamos com a tabela de tipos de cobranças, permitindo que a mesma
-- possa lidar melhor com serviços e demais cobranças realizadas do
-- cliente

-- 1. Removemos a coluna formato de cobrança que se tornou obsoleta em
--    função do aprimoramento
ALTER TABLE erp.billingTypes
  DROP CONSTRAINT billingtypes_billingformatid_fkey;
ALTER TABLE erp.billingTypes
  DROP COLUMN billingFormatID;

-- 2. Adicionamos o campo para indicar se o serviço é considerado
--    pré-aprovado, ou seja, não precisa de autorização do cliente para
--    poder ser marcado e executado
ALTER TABLE erp.billingTypes
  ADD COLUMN preApproved boolean DEFAULT FALSE;

-- 3. Incluímos uma coluna para informar os momentos em que o valor pode
--    ser cobrado, sendo que agora temos a possibilidade de informar
--    mais de um momento em que uma mesma cobrança possa ocorrer.
ALTER TABLE erp.billingTypes
  ADD COLUMN billingMoments int[] DEFAULT '{1}';

-- 4. Incluímos uma checagem para garantir valores válidos para o campo
--    pré-aprovado
ALTER TABLE erp.billingTypes
  ADD CONSTRAINT billingtypes_service_check
  CHECK ((inAttendance = FALSE AND preApproved = FALSE) OR (inAttendance = TRUE));

-- 5. Removemos a coluna momento de cobrança que se tornou obsoleta em
--    função do aprimoramento, já que agora podemos descrever mais de um
--    momento em que uma mesma cobrança possa ocorrer
ALTER TABLE erp.billingTypes
  DROP CONSTRAINT billingtypes_billingmomentid_fkey;
ALTER TABLE erp.billingTypes
  DROP COLUMN billingMomentID;

-- 6. Adicionamos o tempo de execução para um serviço
ALTER TABLE erp.billingTypes
  ADD COLUMN executionTime time NOT NULL DEFAULT '00:00'::time;

-- 7. Adicionamos um campo para uma breve descrição
ALTER TABLE erp.billingTypes
  ADD COLUMN description text;

-- 8. Modificamos as tabelas para refletir as novas modificações
-- Máquina de desenvolvimento
UPDATE erp.billingTypes
   SET name = 'Adesão',
       description = 'A taxa de adesão é um valor cobrado para cobrir os custos relacionados ao início da relação com o cliente.',
       ratePerEquipment = true,
       inAttendance = false,
       preApproved = false,
       billingMoments = '{2}',
       executionTime = '00:00'::time
 WHERE billingTypeID = 1;
UPDATE erp.billingTypes
   SET name = 'Serviço de instalação',
       description = 'Serviço em que o equipamento de rastreamento é instalado, bem como acessórios e outros dispositivos acoplados, tais como botão de pânico, sirene, etc. Está incluso a fiação para conexão do equipamento.',
       ratePerEquipment = true,
       inAttendance = true,
       preApproved = true,
       billingMoments = '{1,2}',
       executionTime = '01:00'::time
 WHERE billingTypeID = 2;
UPDATE erp.billingTypes
   SET name = 'Serviço de reinstalação',
       description = 'Serviço em que o equipamento de rastreamento é reinstalado, incluíndo acessórios nele acoplados, garantindo seu pleno funcionamento.',
       ratePerEquipment = true,
       inAttendance = true,
       preApproved = false,
       billingMoments = '{1}',
       executionTime = '01:00'::time
 WHERE billingTypeID = 3;
UPDATE erp.billingTypes
   SET name = 'Serviço de manutenção',
       description = 'Serviço que realiza a manutenção do equipamento e acessórios nele acoplados, garantindo seu pleno funcionamento.',
       ratePerEquipment = true,
       inAttendance = true,
       preApproved = false,
       billingMoments = '{1}',
       executionTime = '01:00'::time
 WHERE billingTypeID = 4;
UPDATE erp.billingTypes
   SET name = 'Transferência de equipamento',
       description = 'Serviço que realiza a transferência do equipamento e acessórios de um veículo para outro.',
       ratePerEquipment = true,
       inAttendance = true,
       preApproved = false,
       billingMoments = '{1}',
       executionTime = '01:00'::time
 WHERE billingTypeID = 5;
UPDATE erp.billingTypes
   SET name = 'Retirada de equipamento',
       description = 'Serviço de retirada do equipamento (e acessórios, se necessário).',
       ratePerEquipment = true,
       inAttendance = true,
       preApproved = true,
       billingMoments = '{1,3,4}',
       executionTime = '00:30'::time
 WHERE billingTypeID = 6;
INSERT INTO erp.billingTypes (billingTypeID, contractorID, name, description, ratePerEquipment, inAttendance, preApproved, billingMoments, installmentTypeID, executionTime, createdByUserID, updatedByUserID) VALUES
  ( 7, 1, 'Acessório', 'Cobrança de acessório acoplado ao equipamento de rastreamento', true, false, false, '{5}', null, '00:00'::time, 1, 1);
ALTER SEQUENCE erp.billingtypes_billingtypeid_seq RESTART WITH 8;

-- Máquina de produção
UPDATE erp.billingTypes
   SET name = 'Adesão',
       description = 'A taxa de adesão é um valor cobrado para cobrir os custos relacionados ao início da relação com o cliente.',
       ratePerEquipment = true,
       inAttendance = false,
       preApproved = false,
       billingMoments = '{2}',
       executionTime = '00:00'::time
 WHERE billingTypeID = 1;
INSERT INTO erp.billingTypes (billingTypeID, contractorID, name, description, ratePerEquipment, inAttendance, preApproved, billingMoments, installmentTypeID, executionTime, createdByUserID, updatedByUserID) VALUES
  ( 2, 1, 'Serviço de instalação', 'Serviço em que o equipamento de rastreamento é instalado, bem como acessórios e outros dispositivos acoplados, tais como botão de pânico, sirene, etc. Está incluso a fiação para conexão do equipamento.', true, true, true, '{1,2}', 1, '01:00'::time, 1, 1),
  ( 3, 1, 'Serviço de reinstalação', 'Serviço em que o equipamento de rastreamento é reinstalado, incluíndo acessórios nele acoplados, garantindo seu pleno funcionamento.', true, true, false, '{1}', 1, '01:00'::time, 1, 1);
UPDATE erp.planCharges
   SET billingTypeID = 2
 WHERE name = 'Serviço de instalação'
   AND planID IN (SELECT planID FROM erp.plans WHERE contractorID = 1);
UPDATE erp.contractCharges
   SET billingTypeID = 2
 WHERE name = 'Serviço de instalação'
   AND contractID IN (SELECT contractID FROM erp.contracts WHERE contractorID = 1);
UPDATE erp.planCharges
   SET billingTypeID = 3
 WHERE name = 'Serviço de reinstalação'
   AND planID IN (SELECT planID FROM erp.plans WHERE contractorID = 1);
UPDATE erp.contractCharges
   SET billingTypeID = 3
 WHERE name = 'Serviço de reinstalação'
   AND contractID IN (SELECT contractID FROM erp.contracts WHERE contractorID = 1);
UPDATE erp.billingTypes
   SET name = 'Serviço de manutenção',
       description = 'Serviço que realiza a manutenção do equipamento e acessórios nele acoplados, garantindo seu pleno funcionamento.',
       ratePerEquipment = true,
       inAttendance = true,
       preApproved = false,
       billingMoments = '{1}',
       executionTime = '01:00'::time
 WHERE billingTypeID = 4;
UPDATE erp.planCharges
   SET billingTypeID = 4
 WHERE name = 'Serviço de manutenção'
   AND planID IN (SELECT planID FROM erp.plans WHERE contractorID = 1);
UPDATE erp.contractCharges
   SET billingTypeID = 4
 WHERE name = 'Serviço de manutenção'
   AND contractID IN (SELECT contractID FROM erp.contracts WHERE contractorID = 1);
UPDATE erp.billingTypes
   SET name = 'Transferência de equipamento',
       description = 'Serviço que realiza a transferência do equipamento e acessórios de um veículo para outro.',
       ratePerEquipment = true,
       inAttendance = true,
       preApproved = false,
       billingMoments = '{1}',
       executionTime = '01:00'::time
 WHERE billingTypeID = 5;
UPDATE erp.planCharges
   SET billingTypeID = 5
 WHERE name = 'Transferência de equipamento'
   AND planID IN (SELECT planID FROM erp.plans WHERE contractorID = 1);
UPDATE erp.contractCharges
   SET billingTypeID = 5
 WHERE name = 'Transferência de equipamento'
   AND contractID IN (SELECT contractID FROM erp.contracts WHERE contractorID = 1);
UPDATE erp.billingTypes
   SET name = 'Retirada de equipamento',
       description = 'Serviço de retirada do equipamento (e acessórios, se necessário).',
       ratePerEquipment = true,
       inAttendance = true,
       preApproved = true,
       billingMoments = '{1,3,4}',
       executionTime = '00:30'::time
 WHERE billingTypeID = 6;
UPDATE erp.planCharges
   SET billingTypeID = 6
 WHERE name = 'Retirada de equipamento'
   AND planID IN (SELECT planID FROM erp.plans WHERE contractorID = 1);
UPDATE erp.contractCharges
   SET billingTypeID = 6
 WHERE name = 'Retirada de equipamento'
   AND contractID IN (SELECT contractID FROM erp.contracts WHERE contractorID = 1);
INSERT INTO erp.billingTypes (billingTypeID, contractorID, description, name, ratePerEquipment, inAttendance, preApproved, billingMoments, installmentTypeID, executionTime, createdByUserID, updatedByUserID) VALUES
  ( 7, 1, 'Acessório', 'Cobrança de acessório acoplado ao equipamento', true, false, false, '{5}', null, '00:00'::time, 1, 1);
UPDATE erp.planCharges
   SET billingTypeID = 7
 WHERE name = 'Acessório'
   AND planID IN (SELECT planID FROM erp.plans WHERE contractorID = 1);
UPDATE erp.contractCharges
   SET billingTypeID = 7
 WHERE name = 'Acessório'
   AND contractID IN (SELECT contractID FROM erp.contracts WHERE contractorID = 1);
DELETE FROM erp.billingTypes
 WHERE billingTypeID = 15;

-- Remove os tipos de valor cobrados do segundo contratante
DELETE FROM erp.planCharges
 WHERE planID IN (
  SELECT planID
    FROM erp.plans
   WHERE contractorID = 7
  );
DELETE FROM erp.billingTypes
 WHERE contractorID = 7;
ALTER SEQUENCE erp.billingtypes_billingtypeid_seq RESTART WITH 8;

-- Insere os tipos de cobranças padrões para o segundo contratante, bem
-- como os valores padrões do plano
INSERT INTO erp.billingTypes (billingTypeID, contractorID, name, description, ratePerEquipment, inAttendance, preApproved, billingMoments, installmentTypeID, executionTime, createdByUserID, updatedByUserID) VALUES
  ( 8, 7, 'Adesão',                       
    'A taxa de adesão é um valor cobrado para cobrir os custos relacionados ao início da relação com o cliente.',
    true, false, false,     '{2}',    1, '00:00'::time, 6, 6),
  ( 9, 7, 'Serviço de instalação',        
    'Serviço em que o equipamento de rastreamento é instalado, bem como acessórios e outros dispositivos acoplados, tais como botão de pânico, sirene, etc. Está incluso a fiação para conexão do equipamento.',
    true,  true,  true,   '{1,2}',    1, '01:00'::time, 6, 6),
  (10, 7, 'Serviço de reinstalação',      
    'Serviço em que o equipamento de rastreamento é reinstalado, incluíndo acessórios nele acoplados, garantindo seu pleno funcionamento.',
    true,  true, false,     '{1}',    1, '01:00'::time, 6, 6),
  (11, 7, 'Serviço de manutenção',        
    'Serviço que realiza a manutenção do equipamento e acessórios nele acoplados, garantindo seu pleno funcionamento.',
    true,  true, false,     '{1}', null, '01:00'::time, 6, 6),
  (12, 7, 'Transferência de equipamento', 
    'Serviço que realiza a transferência do equipamento e acessórios de um veículo para outro.',
    true,  true, false,     '{1}',    1, '01:00'::time, 6, 6),
  (13, 7, 'Retirada de equipamento',      
    'Serviço de retirada do equipamento (e acessórios, se necessário).',
    true,  true,  true, '{1,3,4}',    1, '00:30'::time, 6, 6),
  (14, 7, 'Acessório',                    
    'Cobrança de acessório acoplado ao equipamento de rastreamento',
    true, false, false,     '{5}', null, '00:00'::time, 6, 6);

-- 9. Incluímos uma função para obter os momentos de cobrança

-- ---------------------------------------------------------------------
-- Obtém os momentos de cobrança em forma de texto
-- ---------------------------------------------------------------------
-- Stored Procedure que converte os possíveis momentos de cobrança para
-- texto.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getBillingMoments(moments integer[])
RETURNS text AS
$$
  SELECT string_agg(name, ' / ')
    FROM unnest(moments) AS id
    INNER JOIN erp.billingMoments ON (billingMoments.billingMomentID = id);
$$ LANGUAGE 'sql' IMMUTABLE;

-- ---------------------------------------------------------------------
-- Alterações na tabela de tipos de cobranças por plano
-- ---------------------------------------------------------------------

-- Excluímos a coluna de nome da cobrança dos valores cobrados por plano
-- pois agora ela será a que está definida no tipo da cobrança
ALTER TABLE erp.planCharges
  DROP COLUMN name;

-- Excluímos o gatilho para lidar com as operações das tarifas cobradas
-- por plano
DROP TRIGGER planChargeTransactionTriggerBefore
  ON erp.planCharges;
DROP TRIGGER planChargeTransactionTriggerAfter
  ON erp.planCharges;
DROP FUNCTION erp.planChargeTransaction();

-- Insere os valores para o plano básico do segundo contratante
INSERT INTO erp.planCharges (planID, billingTypeID, chargeValue, createdByUserID, updatedByUserID) VALUES
  -- 1. Plano Básico
  ( 9, 1, 150.00, 1, 1), -- Adesão
  ( 9, 2, 150.00, 1, 1), -- Instalação
  ( 9, 3, 150.00, 1, 1), -- Reinstalação
  ( 9, 4,  80.00, 1, 1), -- Manutenção
  ( 9, 5, 180.00, 1, 1), -- Transferência
  ( 9, 6, 120.00, 1, 1); -- Retirada

-- ---------------------------------------------------------------------
-- Alterações na tabela de tipos de cobranças por contrato
-- ---------------------------------------------------------------------

-- Excluímos a coluna de nome da cobrança dos valores cobrados por
-- contrato pois agora ela será a que está definida no tipo da cobrança
ALTER TABLE erp.contractCharges
  DROP COLUMN name;
-- Excluímos a coluna de ID da cobrança no plano e do plano. Isto está
-- redundante e pode ser obtido diretamente da tabela de valores
-- cobrados por plano através do ID do plano no respectivo contrato
ALTER TABLE erp.contractCharges
  DROP COLUMN planchargeid;
ALTER TABLE erp.contractCharges
  DROP CONSTRAINT contractcharges_planid_fkey;
ALTER TABLE erp.contractCharges
  DROP COLUMN planid;

-- ---------------------------------------------------------------------
-- Exclusão da tabela formato da cobrança
-- ---------------------------------------------------------------------
-- Excluímos a tabela formato da cobrança que se tornou obsoleta
-- ---------------------------------------------------------------------
DROP TABLE erp.billingFormats;

-- ---------------------------------------------------------------------
-- Inclusão da tabela de valores cobrados de deslocamento por contrato
-- ---------------------------------------------------------------------
-- Criamos uma tabela para armazenar as taxas de deslocamento a serem
-- cobradas do cliente quando for necessário deslocar um técnico para
-- atendê-lo. O comportamento será semelhante aos dos telefones no
-- cadastro de clientes. Esta cobrança é feita por faixa de distâncias
-- em km. O valor é cobrado por km de distância de deslocamento que for
-- necessário.
-- 
-- Inicalmente será exibido algo como:
--   Taxa de deslocamento (por km)
--     Qualquer km  R$ xxx,xx
--
-- Se acrescentado uma faixa de valores, aparecerá algo como:
--   Taxa de deslocamento (por km)
--     até ___ km   R$ xxx,xx
--     acima disto  R$ xxx,xx
--
-- Se acrescentado uma nova faixa de valores, aparecerá algo como:
--   Taxa de deslocamento (por km)
--     até ___ km   R$ xxx,xx
--     até ___ km   R$ xxx,xx
--     acima disto  R$ xxx,xx
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- Taxa de deslocamento
-- ---------------------------------------------------------------------
-- A taxa de deslocamento é o valor a ser cobrado do cliente quando for
-- necessário deslocar um técnico para atendê-lo. Valores de distância
-- nulo são considerados como o valor máximo a ser cobrado do cliente.
-- Neste caso, se especificarmos os valores 5, 10 e NULO, será
-- considerado que de 0 até 5km, será cobrado uma taxa, acima de 5 e até
-- 10km, será cobrado a segunda taxa e, qualquer valor acima disto será
-- cobrada a taxa presente em nulo. Deve existir ao menos uma taxa
-- descrita. Caso não se deseje cobrar, basta colocar o valor 0,00 no 
-- campo 'value'.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.displacementFees (
  displacementFeeID serial,         -- ID da taxa disponível por contrato
  contractID        integer         -- O ID do contrato
                    NOT NULL,
  distance          integer         -- A distância (em km) até a qual
                    DEFAULT NULL,   -- esta faixa está compreendida
  value             numeric(8,2)    -- A taxa a ser cobrada (por padrão
                    NOT NULL        -- não cobra)
                    DEFAULT 0.00,
  CHECK (distance IS NULL OR distance > 0),
  PRIMARY KEY (displacementFeeID),
  FOREIGN KEY (contractID)
    REFERENCES erp.contracts(contractID)
    ON DELETE CASCADE
);

-- Aplicamos um valor zerado a todos os contratos
INSERT INTO erp.displacementFees (contractID, distance, value)
  SELECT contractID, NULL, 0.00 
    FROM erp.contracts
   ORDER BY contractID;

-- ---------------------------------------------------------------------
-- Valores pagos por deslocamento
-- ---------------------------------------------------------------------
-- O valor a ser pago ao prestador de serviços quando um técnico deste
-- executar um atendimento no cliente pelo deslocamento deste. Valores
-- de distância nulo são considerados como o valor máximo a ser pago ao
-- respectivo prestador. Neste caso, se especificarmos os valores 5, 10
-- e NULO, será considerado que de 0 até 5km, será pago um valor, acima
-- de 5 e até 10km, será pago o segundo valor e, qualquer valor acima
-- disto será pago o valor presente em nulo. Deve existir ao menos um
-- valor descrito. Caso não se deseje pagar, basta colocar o valor 0,00
-- no campo 'value'.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.displacementPaids (
  displacementPaidID  serial,         -- ID do valor pago
  serviceProviderID   integer         -- ID do prestador de serviços
                      NOT NULL,
  distance            integer         -- A distância (em km) até a qual
                      DEFAULT NULL,   -- esta faixa está compreendida
  value               numeric(8,2)    -- A taxa a ser cobrada (por padrão
                      NOT NULL        -- não cobra)
                      DEFAULT 0.00,
  CHECK (distance IS NULL OR distance > 0),
  PRIMARY KEY (displacementPaidID),
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE
);

-- =====================================================================
-- ATUALIZAÇÃO DAS INSTITUIÇÕES FINANCEIRAS
-- =====================================================================

-- Atualiza os códigos dos bancos cadastrados
INSERT INTO erp.banks (bankID, shortname, name) VALUES
  ('010', 'CREDICOAMO', 'CREDICOAMO Crédito Rural Cooperativa'),
  ('011', 'Credit Suisse', 'Credit Suisse HEDGING-GRIFFO CV S.A.'),
  ('015', 'UBS Brasil CCTVM', 'UBS Brasil Corretora de Câmbio, Títulos e Valores Mobiliários S.A.'),
  ('029', 'Itaú Consignado', 'Banco Itaú Consignado S.A.'),
  ('060', 'Confidence', 'Confidence Corretora de Câmbio S.A.'),
  ('063', 'Banco Bradescard', 'Banco Bradescard S.A.'),
  ('080', 'B&T CC Ltda', 'B&T Corretora de Câmbio Ltda'),
  ('089', 'CREDISAN CC' , 'CREDISAN Cooperativa de Crédito'),
  ('093', 'Pólocred SCMEPP', 'Pólocred Sociedade de Crédito ao Microempreendedor e à Empresa de Pequeno Porte Ltda'),
  ('097', 'Credisis', 'Credisis - Central de Cooperativas de Crédito Ltda'),
  ('099', 'Uniprime COOPCENTRAL', 'Uniprime Central - Central Nacional de Cooperativa de Crédito Ltda'),
  ('100', 'Planner', 'Planner Corretora de Valores S.A.'),
  ('101', 'Renascença DTVM', 'Renascença Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('102', 'XP Investimentos CCTVM', 'XP Investimentos Corretora de Câmbio, Títulos e Valores Mobiliários S.A.'),
  ('105', 'Lecca CFI', 'Lecca Crédito, Financiamento e Investimento S.A.'),
  ('108', 'Portocred - CFI', 'Portocred S.A - Crédito, Financiamento e Investimento'),
  ('111', 'OLIVEIRA TRUST DTVM', 'Oliveira Trust Distribuidora de Títulos e Valores Mobiliários S.A.'),
  ('113', 'NEON CTVM', 'NEON Corretora de Títulos e Valores Mobiliários S.A.'),
  ('114', 'CECOOP', 'Central Cooperativa de Crédito no Estado do Espírito Santo - CECOOP'),
  ('117', 'Advanced', 'Advanced Corretora de Câmbio Ltda'),
  ('122', 'Bradesco Berj', 'Banco Bradesco Berj S.A.'),
  ('126', 'BR Partners', 'BR Partners Banco de Investimento S.A.'),
  ('127', 'CODEPE CVC', 'Codepe Corretora de Valores e Câmbio S.A.'),
  ('130', 'Caruana SCFI', 'Caruana S.A. - Sociedade de Crédito, Financiamento e Investimento'),
  ('131', 'Tullett Prebon Brasil', 'Tullett Prebon Brasil Corretora de Valores e Câmbio Ltda'),
  ('132', 'ICBC do Brasil', 'ICBC do Brasil Banco Múltiplo S.A.'),
  ('133', 'Cresol Confederação', 'Confederação Nacional das Cooperativas Centrais de Crédito e Economia Familiar e Solidária'),
  ('134', 'BGC Liquidez', 'BGC Liquidez Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('136', 'Unicred do Brasil', 'Confederação Nacional das Cooperativas Centrais Unicred Ltda. - Unicred do Brasil'),
  ('138', 'GET Money', 'GET Money Corretora de Câmbio Ltda'),
  ('139', 'Intesa Sanpaolo', 'Intesa Sanpaolo Brasil S.A. - Banco Múltiplo'),
  ('140', 'NuInvest', 'NuInvest Corretora de Valores S.A.'),
  ('142', 'Broker Brasil CC', 'Broker Brasil Corretora de Câmbio Ltda'),
  ('143', 'Treviso', 'Treviso Corretora de Câmbio S.A.'),
  ('144', 'BEXS', 'BEXS Banco de Cambio S.A.'),
  ('145', 'LEVYCAM', 'LEVYCAM - Corretora de Câmbio e Valores Ltda'),
  ('146', 'GUITTA', 'GUITTA Corretora de Câmbio Ltda'),
  ('149', 'FACTA', 'Facta Financeira S.A. - Crédito Financiamento e Investimento'),
  ('157', 'ICAP', 'ICAP do Brasil Corretora de Títulos e Valores Mobiliários Ltda'),
  ('159', 'Casa Crédito', 'Casa do Crédito S.A. Sociedade de Crédito ao Microempreendedor'),
  ('163', 'Commerzbank Brasil', 'Commerzbank Brasil S.A. - Banco Múltiplo'),
  ('173', 'BRL Trust', 'BRL Trust Distribuidora de Títulos e Valores Mobiliários S.A.'),
  ('174', 'PEFISA', 'PEFISA S.A. - Crédito, Financiamento e Investimento'),
  ('177', 'Guide', 'Guide Investimentos S.A. Corretora de Valores'),
  ('180', 'CM Capital Markets', 'CM Capital Markets Corretora de Câmbio, Títulos e Valores Mobiliários Ltda'),
  ('183', 'SOCRED', 'Socred S.A. - Sociedade de Crédito ao Microempreendedor e à Empresa de Pequeno Porte'),
  ('188', 'Ativa Investimentos', 'Ativa Investimentos S.A. Corretora de Títulos, Câmbio e Valores'),
  ('189', 'HS Financeira', 'HS FINANCEIRA S/A Crédito, Financiamento e Investimentos'),
  ('190', 'Servicoop', 'Servicoop - Coop. de Crédito dos Servidores Públicos Estaduais e Municipais do Rio Grande do Sul'),
  ('191', 'Nova Futura', 'Nova Futura Corretora de Títulos e Valores Mobiliários Ltda'),
  ('194', 'Parmetal', 'Parmetal Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('195', 'VALOR SCD', 'Valor Sociedade de Crédito Direto S.A.'),
  ('196', 'Fair CC', 'Fair Corretora de Câmbio S.A.'),
  ('197', 'Stone', 'Stone Instituição de Pagamentos S.A.'),
  ('253', 'BEXS', 'BEXS Corretora de Câmbio S.A.'),
  ('259', 'MONEYCORP', 'MONEYCORP Banco de Câmbio S.A.'),
  ('260', 'NuBank', 'NU Pagamentos S.A. - Instituição de Pagamentos'),
  ('268', 'Bari CIA Hipotecária', 'BARI Companhia Hipotecária'),
  ('269', 'HSBC', 'Banco HSBC S.A.'),
  ('270', 'Sagitur', 'Sagitur Corretora de Câmbio S.A.'),
  ('271', 'IB CCTVM S.A.', 'IB Corretora de Câmbio, Títulos e Valores Mobiliários S.A.'),
  ('273', 'CCR de São Miguel do Oeste', 'Cooperativa de Crédito Rural de São Miguel do Oeste - Sulcredi/São Miguel'),
  ('274', 'Money Plus', 'Money Plus Sociedade de Crédito ao Microempreendedor e a Empresa de Pequeno Porte'),
  ('276', 'Banco SENFF', 'Banco SENFF S.A.'),
  ('278', 'Genial Investimentos', 'Genial Investimentos Corretora de Valores Mobiliários S.A.'),
  ('279', 'CCR de Primavera do Leste', 'Cooperativa de Crédito Rural de Primavera do Leste'),
  ('280', 'Will Financeira', 'Will Financeira S.A. Crédito, Financiamento e Investimento'),
  ('281', 'CCR Coopavel', 'Cooperativa de Crédito Rural Coopavel'),
  ('283', 'RB Investimentos', 'RB Investimentos Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('285', 'Frente', 'Frente Corretora de Câmbio Ltda'),
  ('286', 'CCR de Ouro', 'Cooperativa de Crédito Rural de Ouro - SULCREDI/OURO'),
  ('288', 'Carol DTVM Ltda', 'Carol Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('289', 'EFX', 'EFX Corretora de Câmbio Ltda'),
  ('290', 'PagBank', 'Pagseguro Internet Instituição de Pagamento S.A.'),
  ('292', 'BS2 DTVM', 'BS2 Distribuidora de Títulos e Valores Mobiliários S.A.'),
  ('293', 'Lastro RDV', 'Lastro RDV Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('296', 'OZ CC', 'OZ Corretora de Câmbio S.A.'),
  ('298', 'VIP''S CC', 'VIP''S Corretora de Câmbio Ltda'),
  ('299', 'Banco SOROCRED', 'Banco SOROCRED S.A. - Banco Múltiplo'),
  ('301', 'BPP IP', 'BPP Instituição de Pagamentos S.A.'),
  ('306', 'PORTOPAR DTVM', 'PORTOPAR Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('307', 'Terra Investimentos', 'Terra Investimentos Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('309', 'CAMBIONET', 'CAMBIONET Corretora de Câmbio Ltda'),
  ('310', 'Vortx', 'Vortx Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('311', 'Dourada Corretora', 'Dourada Corretora de Câmbio Ltda'),
  ('312', 'HSCM', 'HSCM - Sociedade de Crédito ao Microempreendedor e à Empresa de Pequeno Porte Ltda'),
  ('313', 'Amazônia Corretora', 'Amazônia Corretora de Câmbio Ltda'),
  ('321', 'CREFAZ', 'CREFAZ Sociedade de Crédito ao Microempreendedor e à Empresa de Pequeno Porte Ltda'),
  ('322', 'CCR de Abelardo Luz', 'Cooperativa de Crédito Rural de Abelardo Luz - Sulcredi/Crediluz'),
  ('323', 'Mercado Pago', 'Mercado Pago Instituição de Pagamento Ltda'),
  ('324', 'CARTOS SCD', 'Cartos Sociedade de Crédito Direto S.A.'),
  ('325', 'Órama DTVM', 'Órama Distribuidora de Títulos e Valores Mobiliários S.A.'),
  ('326', 'PARATI - CFI', 'PARATI - Crédito, Financiamento e Investimento S.A.'),
  ('328', 'CECM Fabricantes Calçados Sapiranga', 'Cooperativa de Economia e Crédito Mútuo dos Fabricantes de Calçados de Sapiranga'),
  ('329', 'QI SCD', 'QI Sociedade de Crédito Direto S.A.'),
  ('330', 'Banco Bari', 'Banco Bari de Investimentos e Financiamentos S.A.'),
  ('331', 'Fram Capital DTVM', 'Fram Capital Distribuidora de Títulos e Valores Mobiliários S.A.'),
  ('332', 'Acesso Soluções de Pagamento', 'Acesso Soluções de Pagamento S.A.'),
  ('335', 'Banco Digio', 'Banco Digio S.A.'),
  ('336', 'C6 Bank', 'Banco C6 S.A.'),
  ('340', 'Superdigital', 'Superdigital Instituição de Pagamentos S.A.'),
  ('342', 'Creditas SCD', 'Creditas Sociedade de Crédito Direto S.A.'),
  ('343', 'FFA SCMEPP', 'FFA Sociedade de Crédito ao Microempreendedor e à Empresa de Pequeno Porte Ltda'),
  ('348', 'Banco XP', 'Banco XP S.A.'),
  ('349', 'AL5 CFI', 'AL5 S.A. Crédito, Financiamento e Investimento'),
  ('350', 'CREHNOR Laranjeiras', 'Cooperativa de Crédito Rural de Pequenos Agricultores e da Reforma Agrária do Centro Oeste do Paraná'),
  ('352', 'Toro CTVM', 'Toro Corretora de Títulos e Valores Mobiliários S.A.'),
  ('354', 'Necton Investimentos CVM', 'Necton Investimentos S.A. Corretora de Valores Mobiliários e Commodities'),
  ('355', 'Ótimo SCD', 'Ótimo Sociedade de Crédito Direto S.A.'),
  ('358', 'MIDWAY', 'MIDWAY S.A. - Crédito, Financiamento e Investimento'),
  ('359', 'Zema CFI', 'Zema Crédito, Financiamento e Investimento S.A.'),
  ('360', 'Trinus Capital DTVM', 'Trinus Capital Distribuidora de Títulos e Valores Mobiliários S.A.'),
  ('362', 'CIELO', 'CIELO S.A.'),
  ('363', 'Singulare CTVM', 'Singulare Corretora de Títulos e Valores Mobiliários S.A.'),
  ('364', 'Gerencianet', 'Gerencianet S.A.'),
  ('365', 'SIMPAUL', 'SIMPAUL Corretora de Câmbio e Valores Mobiliários S.A.'),
  ('367', 'Vitreo DTVM', 'Vitreo Distribuidora de Títulos e Valores Mobiliários S.A.'),
  ('368', 'Banco CSF', 'Banco CSF S.A.'),
  ('371', 'Warren CVMC', 'Warren Corretora de Valores Mobiliários e Câmbio Ltda'),
  ('373', 'UP.P SEP', 'UP.P Sociedade de Empréstimo entre Pessoas S.A.'),
  ('374', 'Realize CFI', 'Realize Crédito, Financiamento e Investimento S.A.'),
  ('377', 'BMS SCD', 'BMS Sociedade de Crédito Direto S.A.'),
  ('378', 'Banco Brasileiro de Crédito', 'Banco Brasileiro de Crédito S.A.'),
  ('379', 'COOPERFORTE', 'Cooperativa de Economia e Crédito Mútuo de Funcionários de Inst. Financeiras Públicas Federais'),
  ('380', 'PicPay', 'PicPay Instituição de Pagementos S.A.'),
  ('381', 'Banco Mercedes-Benz', 'Banco Mercedes-Benz do Brasil S.A.'),
  ('382', 'Fidúcia SCMEPP', 'Fidúcia Sociedade de Crédito ao Microempreendedor e à Empresa de Pequeno Porte Ltda'),
  ('383', 'EBANX', 'EBANX Instituição de Pagamentos Ltda'),
  ('384', 'Global Finanças', 'Global Finanças Sociedade de Crédito ao Microempreendedor e à Empresa de Pequeno Porte Ltda'),
  ('385', 'CREDESTIVA', 'Cooperativa de Economia e Crédito Mútuo dos Trabalhadores Portuários da Grande Vitória'),
  ('386', 'Nu Financeira', 'Nu Financeira S.A. - Sociedade de Crédito, Financiamento e Investimento'),
  ('387', 'Banco Toyota do Brasil', 'Banco Toyota do Brasil S.A.'),
  ('390', 'Banco GM', 'Banco GM S.A.'),
  ('391', 'CCR de IBIAM', 'Cooperativa de Crédito Rural de Ibiam - SULCREDI/IBIAM'),
  ('393', 'Banco Volkswagen', 'Banco Volkswagen S.A.'),
  ('395', 'F.D''Gold DTVM', 'F.D''Gold - Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('396', 'HUB Pagamentos', 'HUB Pagamentos S.A.'),
  ('397', 'Listo SCD', 'Listo Sociedade de Credito Direto S.A.'),
  ('398', 'Ideal CTVM', 'Ideal Corretora de Títulos e Valores Mobiliários S.A.'),
  ('400', 'CREDITAG', 'Cooperativa de Crédito, Poupança e Serviços Financeiros do Centro Oeste - CREDITAG'),
  ('401', 'IUGU', 'IUGU Instituição de Pagamento S.A.'),
  ('402', 'Cobuccio SCD', 'Cobuccio Sociedade de Crédito Direto S.A.'),
  ('403', 'Cora SCD', 'Cora Sociedade de Crédito Direto S.A.'),
  ('404', 'SUMUP SCD', 'SUMUP Sociedade de Crédito Direto S.A.'),
  ('406', 'ACCREDITO', 'ACCREDITO - Sociedade de Crédito Direto S.A.'),
  ('407', 'Índigo Investimentos DTVM', 'Índigo Investimentos Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('408', 'BonusPago SCD', 'BonusPago Sociedade de Crédito Direto S.A.'),
  ('410', 'Planner SCM', 'Planner Sociedade de Crédito ao Microempreendedor S.A.'),
  ('411', 'Via Certa Financiadora', 'Via Certa Financiadora S.A. - Crédito, Financiamento e Investimentos'),
  ('413', 'Banco BV', 'Banco BV S.A.'),
  ('414', 'Work SCD', 'Work Sociedade de Crédito Direto S.A.'),
  ('416', 'Lamara SCD', 'Lamara Sociedade de Crédito Direto S.A.'),
  ('418', 'Zipdin SCD', 'Zipdin Soluções Digitais Sociedade de Crédito Direto S.A.'),
  ('419', 'NUMBRS SCD', 'NUMBRS Sociedade de Crédito Direto S.A.'),
  ('421', 'Lar Credi', 'Lar Cooperativa de Crédito - Lar Credi'),
  ('423', 'Coluna S.A. DTVM', 'Coluna S.A. Distribuidora de Títulos e Valores Mobiliários'),
  ('425', 'Socinal S.A. - CFI', 'Socinal S.A. - Crédito, Financiamento e Investimento'),
  ('426', 'Biorc Financeira - CFI', 'Biorc Financeira - Crédito, Financiamento e Investimento S.A.'),
  ('427', 'CRED-UFES', 'Cooperativa de Crédito dos Servidores da Universidade Federal do Espírito Santo'),
  ('428', 'CredSystem SCD', 'CredSystem Sociedade de Crédito Direto S.A.'),
  ('429', 'Crediare S.A.', 'Crediare S.A. - Crédito, Financiamento e Investimento'),
  ('430', 'CCR SEARA', 'Cooperativa de Crédito Rural Seara - CREDISEARA'),
  ('433', 'BR-Capital DTVM', 'BR-Capital Distribuidora de Títulos e Valores Mobiliários S.A.'),
  ('435', 'Delcred SCD', 'Delcred Sociedade de Crédito Direto S.A.'),
  ('438', 'Planner Trustee DTVM', 'Planner Trustee Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('439', 'ID Corretora', 'ID Corretora de Títulos e Valores Mobiliários S.A.'),
  ('440', 'CREDIBRF', 'CREDIBRF - Cooperativa de Crédito'),
  ('442', 'Magnetis - DTVM', 'Magnetis - Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('443', 'CrediHome SCD', 'CrediHome Sociedade de Crédito Direto S.A.'),
  ('444', 'Trinus SCD', 'Trinus Sociedade de Crédito Direto S.A.'),
  ('445', 'Plantae CFI', 'Plantae S.A. - Crédito, Financiamento e Investimento'),
  ('447', 'Mirae Asset', 'Mirae Asset Wealth Management (Brazil) Corretora de Câmbio, Títulos e Valores Mobiliários Ltda'),
  ('448', 'Hemera DTVM', 'Hemera Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('449', 'DMCARD SCD', 'DMCARD Sociedade de Crédito Direto S.A.'),
  ('450', 'Fitbank', 'Fitbank Pagamentos Eletrônicos S.A.'),
  ('451', 'J17 - SCD', 'J17 - Sociedade de Crédito Direto S.A.'),
  ('452', 'CREDIFIT SCD', 'CREDIFIT Sociedade de Crédito Direto S.A.'),
  ('454', 'Mérito DTVM', 'Mérito Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('457', 'UY3 SCD', 'UY3 Sociedade de Crédito Direto S.A.'),
  ('458', 'Hedge Investments DTVM', 'Hedge Investments Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('459', 'Credifisco', 'Cooperativa de Crédito Mútuo de Servidores Públicos do Estado de São Paulo - Credifisco'),
  ('460', 'Unavanti SCD', 'Unavanti Sociedade de Crédito Direto S.A.'),
  ('461', 'Asaas Gestão Financeira', 'Asaas Gestão Financeira Instituição de Pagamento S.A.'),
  ('462', 'Stark SCD', 'Stark Sociedade de Crédito Direto S.A.'),
  ('463', 'Azumi DTVM', 'Azumi Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('465', 'Capital Consig SCD', 'Capital Consig Sociedade de Crédito Direto S.A.'),
  ('467', 'Master CCTVM', 'Master S.A. Corretora de Câmbio, Títulos e Valores Mobiliários'),
  ('468', 'PortoSeg CFI', 'PortoSeg S.A. - Crédito, Financiamento e Investimento'),
  ('469', 'Liga Invest DTVM', 'Liga Invest Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('470', 'CDC Sociedade de Crédito', 'CDC Sociedade de Crédito ao Microempreendedor e à Empresa de Pequeno Porte Ltda'),
  ('471', 'Creserv - Pinhão', 'Cooperativa de Economia e Crédito Mútuo dos Servidores Públicos de Pinhão - Creserv - Pinhão'),
  ('478', 'GazinCred SCFI', 'GazinCred S.A. Sociedade de Crédito, Financiamento e Investimento'),
  ('484', 'MAF DTVM', 'MAF Distribuidora de Títulos e Valores Mobiliários S.A.'),
  ('506', 'RJI', 'RJI Corretora de Títulos e Valores Mobiliários Ltda'),
  ('508', 'Avenue Securities DTVM', 'Avenue Securities Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('511', 'Magnum SCD', 'Magnum Sociedade de Crédito Direto S.A.'),
  ('512', 'Captalys DTVM', 'Captalys Distribuidora de Títulos e Valores Mobiliários Ltda'),
  ('545', 'Senso CCVM', 'Senso Corretora de Câmbio e Valores Mobiliários S.A.'),
  ('720', 'Banco RNX', 'Banco RNX S.A.'),
  ('754', 'Banco Sistema', 'Banco Sistema S.A.');

UPDATE erp.banks
   SET shortname = 'State Street Brasil',
       name = 'State Street Brasil S.A. - Banco Comercial'
 WHERE bankID = '014';
UPDATE erp.banks
   SET name = 'Banco Bradesco BBI S.A.'
 WHERE bankID = '036';
UPDATE erp.banks
   SET shortname = 'Banco Morgan Stanley'
 WHERE bankID = '066';
UPDATE erp.banks
   SET shortname = 'Crefisa',
       name = 'Banco Crefisa S.A.'
 WHERE bankID = '069';
UPDATE erp.banks
   SET shortname = 'Safra'
 WHERE bankID = '074';
UPDATE erp.banks
   SET name = 'Banco KDB do Brasil S.A.'
 WHERE bankID = '076';
UPDATE erp.banks
   SET shortname = 'Banco Inter',
       name = 'Banco Inter S.A.'
 WHERE bankID = '077';
UPDATE erp.banks
   SET shortname = 'Haitong',
       name = 'Haitong Banco de Investimento do Brasil S.A.'
 WHERE bankID = '078';
UPDATE erp.banks
   SET shortname = 'PICPAY Bank',
       name = 'PICPAY Bank - Banco Múltiplo S.A.'
 WHERE bankID = '079';
UPDATE erp.banks
   SET shortname = 'BancoSeguro',
       name = 'BancoSeguro S.A.'
 WHERE bankID = '081';
UPDATE erp.banks
   SET shortname = 'UNIPRIME',
       name = 'UNIPRIME do Brasil - Cooperativa de Crédito'
 WHERE bankID = '084';
UPDATE erp.banks
   SET shortname = 'AILOS',
       name = 'Cooperativa Central de Crédito - AILOS'
 WHERE bankID = '085';
UPDATE erp.banks
   SET shortname = 'BRK CFI',
       name = 'BRK S.A. Crédito, Financiamento e Investimento'
 WHERE bankID = '092';
UPDATE erp.banks
   SET shortname = 'Banco Finaxis',
       name = 'Banco Finaxis S.A.'
 WHERE bankID = '094';
UPDATE erp.banks
   SET shortname = 'Travelex Banco de Câmbio',
       name = 'Travelex Banco de Câmbio S.A.'
 WHERE bankID = '095';
UPDATE erp.banks
   SET shortname = 'Banco B3',
       name = 'Banco B3 S.A.'
 WHERE bankID = '096';
UPDATE erp.banks
   SET shortname = 'Banco Bocom BBM',
       name = 'Banco Bocom BBM S.A.'
 WHERE bankID = '107';
UPDATE erp.banks
   SET shortname = 'Agibank',
       name = 'Banco Agibank S.A.'
 WHERE bankID = '121';
UPDATE erp.banks
   SET shortname = 'Banco Genial',
       name = 'Banco Genial S.A.'
 WHERE bankID = '125';
UPDATE erp.banks
   SET shortname = 'BS2',
       name = 'Banco BS2 S.A.'
 WHERE bankID = '218';
UPDATE erp.banks
   SET shortname = 'Crédit Agricole',
       name = 'Banco Crédit Agricole Brasil S.A.'
 WHERE bankID = '222';
UPDATE erp.banks
   SET shortname = 'Banco Master',
       name = 'Banco Master S.A.'
 WHERE bankID = '243';
UPDATE erp.banks
   SET shortname = 'Banco CCB Brasil S.A.',
       name = 'China Construction Bank (Brasil) Banco Múltiplo S.A.'
 WHERE bankID = '320';
UPDATE erp.banks
   SET shortname = 'Kirton Bank',
       name = 'Kirton Bank S.A.'
 WHERE bankID = '399';
UPDATE erp.banks
   SET shortname = 'Social Bank',
       name = 'Social Bank Banco Múltiplo S.A.'
 WHERE bankID = '412';
UPDATE erp.banks
   SET shortname = 'Banco MUFJ Brasil',
       name = 'Banco MUFJ Brasil Brasil S.A.'
 WHERE bankID = '456';
UPDATE erp.banks
   SET shortname = 'Omni Banco',
       name = 'Omni Banco S.A.'
 WHERE bankID = '613';
UPDATE erp.banks
   SET shortname = 'Banco C6 Consignado',
       name = 'Banco C6 Consignado S.A.'
 WHERE bankID = '626';
UPDATE erp.banks
   SET shortname = 'Banco LetsBank',
       name = 'Banco LetsBank S.A.'
 WHERE bankID = '630';
UPDATE erp.banks
   SET shortname = 'Itaú Unibanco Holding',
       name = 'Itaú Unibanco Holding S.A.'
 WHERE bankID = '652';
UPDATE erp.banks
   SET shortname = 'Banco Voiter',
       name = 'Banco Voiter S.A.'
 WHERE bankID = '653';
UPDATE erp.banks
   SET shortname = 'Banco Digimais',
       name = 'Banco Digimais S.A.'
 WHERE bankID = '654';
UPDATE erp.banks
   SET shortname = 'SICOOB',
       name = 'Banco Cooperativo SICOOB S.A. - BANCO SICOOB'
 WHERE bankID = '756';
UPDATE erp.banks
   SET shortname = 'Banco Keb Hana do Brasil',
       name = 'Banco Keb Hana do Brasil S.A.'
 WHERE bankID = '757';

-- Removido 019 - Banco Azteca
DELETE FROM erp.banks
 WHERE bankID = '019';
-- Removido 045 - Banco Opportunity
DELETE FROM erp.banks
 WHERE bankID = '045';
-- Removido 086 - OBOE Crédito Financiamento e Investimento S.A.
DELETE FROM erp.banks
 WHERE bankID = '086';
-- Removido 087 - Unicred Central Santa Catarina
DELETE FROM erp.banks
 WHERE bankID = '087';
-- Removido 091 - Unicred Central do Rio Grande do Sul
DELETE FROM erp.banks
 WHERE bankID = '091';
-- Removido 118 - Standard Chartered Bank
DELETE FROM erp.banks
 WHERE bankID = '118';
-- Removido 229 - Banco Cruzeiro do Sul
DELETE FROM erp.banks
 WHERE bankID = '229';
-- Removido 248 - Banco Boavista Interatlântico S.A.
DELETE FROM erp.banks
 WHERE bankID = '248';
-- Removido 263 - Banco Cacique S.A.
DELETE FROM erp.banks
 WHERE bankID = '263';
-- Removido 641 - Banco Alvorada S.A.
DELETE FROM erp.banks
 WHERE bankID = '641';
-- Removido 719 - Banco Internacional do Funchal (Brasil) S.A.
DELETE FROM erp.banks
 WHERE bankID = '719';
-- Removido 734 - Banco Gerdau S.A.
DELETE FROM erp.banks
 WHERE bankID = '734';
-- Removido 735 - Banco Pottencial S.A.
DELETE FROM erp.banks
 WHERE bankID = '735';
-- Removido 738 - Banco Morada S.A.
DELETE FROM erp.banks
 WHERE bankID = '738';

-- =====================================================================
-- INCLUSÃO DO CADASTRO DE PRESTADORES DE SERVIÇOS
-- =====================================================================

-- ---------------------------------------------------------------------
-- Valores de serviços 
-- ---------------------------------------------------------------------
-- Contém as informações dos serviços que cada prestador está habilitado
-- à prestar e o respectivo valor a ser pago pela sua execução.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.servicePrices (
  servicePriceID    serial,         -- ID do preço por serviço
  serviceProviderID integer         -- ID do prestador de serviços
                    NOT NULL,
  billingTypeID     integer         -- ID do tipo de cobrança
                    NOT NULL,
  priceValue        numeric(12,2)   -- Valor pago
                    NOT NULL
                    DEFAULT 0.00,
  createdAt         timestamp       -- A data de inclusão do preço neste
                    NOT NULL        -- prestador de serviços
                    DEFAULT CURRENT_TIMESTAMP,
  createdByUserID   integer         -- O ID do usuário responsável pelo
                    NOT NULL,       -- cadastro
  updatedAt         timestamp       -- A data de modificação
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID   integer         -- O ID do usuário responsável pela
                    NOT NULL,       -- última modificação
  PRIMARY KEY (servicePriceID),
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE,
  FOREIGN KEY (billingTypeID)
    REFERENCES erp.billingTypes(billingTypeID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Informações adicionais do prestador de serviços
-- ---------------------------------------------------------------------
-- Contém as informações complementares para cadastro do prestador de
-- serviços, além das já armazenadas no cadastro da entidade.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.serviceProviders (
  serviceProviderID       integer           -- ID do prestador de serviços
                          NOT NULL
                          UNIQUE,
  occupationArea          text,             -- Área de atuação
  unproductiveVisit       numeric(12, 2)    -- O valor pago ao técnico
                          NOT NULL          -- em caso de visita
                          DEFAULT 100.0000, -- improdutiva
  unproductiveVisitType   integer           -- Tipo da cobrança
                          NOT NULL          --   1: valor
                          DEFAULT 2,        --   2: porcentagem
  frustratedVisit         numeric(12, 2)    -- O valor pago ao técnico
                          NOT NULL          -- em caso de visita
                          DEFAULT 100.0000, -- frustrada
  frustratedVisitType     integer           -- Tipo da cobrança
                          NOT NULL          --   1: valor
                          DEFAULT 2,        --   2: porcentagem
  unrealizedVisit         numeric(12, 2)    -- O valor cobrado do técnico
                          NOT NULL          -- em caso de visita não
                          DEFAULT 100.0000, -- realiada
  unrealizedVisitType     integer           -- Tipo da cobrança
                          NOT NULL          --   1: valor
                          DEFAULT 2,        --   2: porcentagem
  geographicCoordinateID  integer           -- A coordenada geográfica
                          NOT NULL,         -- de referência para cálculo de deslocamento
  PRIMARY KEY (serviceProviderID),
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE,
  FOREIGN KEY (unproductiveVisitType)
    REFERENCES erp.measureTypes(measureTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (frustratedVisitType)
    REFERENCES erp.measureTypes(measureTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (unrealizedVisitType)
    REFERENCES erp.measureTypes(measureTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (geographicCoordinateID)
    REFERENCES erp.geographicCoordinates(geographicCoordinateID)
    ON DELETE RESTRICT
);

-- Alteração da tabela de veículos para inclusão do relacionamento com a
-- tabela de cores do veículo
ALTER TABLE erp.vehicles
  ADD CONSTRAINT vehicles_vehiclecolorid_fkey
  FOREIGN KEY (vehicleColorID)
  REFERENCES erp.vehicleColors(vehicleColorID)
  ON DELETE RESTRICT;

-- ---------------------------------------------------------------------
-- Técnicos por prestador de serviços
-- ---------------------------------------------------------------------
-- Contém as informações dos serviços que cada prestador está habilitado
-- à prestar e o respectivo valor a ser pago pela sua execução.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.technicians (
  technicianID            serial,         -- ID do técnico
  serviceProviderID       integer         -- ID do prestador de serviços
                          NOT NULL,
  name                    varchar(100)    -- O nome do técnico
                          NOT NULL,
  technicianIsTheProvider boolean         -- O indicativo de que este
                          DEFAULT false,  -- técnico é próprio prestador
  address                 varchar(100)    -- O endereço
                          NOT NULL,
  streetNumber            varchar(10),    -- O número da casa
  complement              varchar(30),    -- O complemento do endereço
  district                varchar(50),    -- O bairro
  cityID                  integer         -- O ID da cidade
                          NOT NULL,
  postalCode              char(9)         -- O CEP
                          NOT NULL,
  regionalDocumentType    integer         -- ID do tipo do documento
                          NOT NULL        -- (Padrão: RG)
                          DEFAULT 1,
  regionalDocumentNumber  varchar(20)     -- Número do documento
                          DEFAULT NULL,
  regionalDocumentState   char(2)         -- O estado (UF) onde foi
                          DEFAULT NULL,   -- emitido o documento
  cpf                     varchar(14)     -- O CPF
                          NOT NULL
                          DEFAULT '000.000.000-00',
  birthday                date,           -- A data de nascimento
  genderID                integer,        -- O ID do gênero
  plate                   varchar(7)      -- Placa do veículo
                          DEFAULT NULL,
  vehicleTypeID           integer         -- ID do tipo do veículo
                          DEFAULT NULL,
  vehicleBrandID          integer         -- ID da marca do veículo
                          DEFAULT NULL,
  vehicleModelID          integer         -- ID do modelo do veículo
                          DEFAULT NULL,
  vehicleColorID          integer         -- O ID da cor predominante do
                          DEFAULT NULL,   -- veículo
  blocked                 boolean         -- O indicativo de técnico
                          NOT NULL        -- bloqueado
                          DEFAULT false,
  createdAt               timestamp       -- A data de inclusão do
                          NOT NULL        -- técnico
                          DEFAULT CURRENT_TIMESTAMP,
  createdByUserID         integer         -- O ID do usuário responsável
                          NOT NULL,       -- pelo cadastro
  updatedAt               timestamp       -- A data de modificação do
                          NOT NULL        -- técnico
                          DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID         integer         -- O ID do usuário responsável
                          NOT NULL,       -- pela última modificação
  PRIMARY KEY (technicianID),
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE,
  FOREIGN KEY (cityID)
    REFERENCES erp.cities(cityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleTypeID)
    REFERENCES erp.vehicleTypes(vehicleTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleBrandID)
    REFERENCES erp.vehicleBrands(vehicleBrandID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleModelID)
    REFERENCES erp.vehicleModels(vehicleModelID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleColorID)
    REFERENCES erp.vehicleColors(vehicleColorID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- E-mails por técnico
-- ---------------------------------------------------------------------
-- Contém as informações dos e-mails por técnico.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.technicianMailings (
  technicianMailingID serial,        -- O ID do e-mail
  serviceProviderID   integer        -- O ID do prestador de serviços ao
                      NOT NULL,      -- qual pertence este e-mail
  technicianID        integer        -- O ID do técnico ao qual pertence
                      NOT NULL,      -- este e-mail
  email               varchar(100)   -- O endereço de e-mail
                      NOT NULL,
  CHECK (POSITION(' ' IN email) = 0),
  PRIMARY KEY (technicianMailingID),
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (technicianID)
    REFERENCES erp.technicians(technicianID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Telefones adicionais por técnico
-- ---------------------------------------------------------------------
-- Contém as informações dos telefones adicionais por técnico.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.technicianPhones (
  technicianPhoneID serial,        -- O ID do telefone
  serviceProviderID integer        -- O ID do prestador de serviços ao
                    NOT NULL,      -- qual pertence este e-mail
  technicianID      integer        -- O ID do técnico ao qual pertence
                    NOT NULL,      -- este e-mail
  phoneTypeID       integer        -- O ID do tipo de telefone
                    NOT NULL,
  phoneNumber       varchar(20)    -- O número do telefone
                    NOT NULL,
  PRIMARY KEY (technicianPhoneID),
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (technicianID)
    REFERENCES erp.technicians(technicianID)
    ON DELETE CASCADE,
  FOREIGN KEY (phoneTypeID)
    REFERENCES erp.phoneTypes(phoneTypeID)
    ON DELETE RESTRICT
);

-- =====================================================================
-- ALTERAÇÕES DAS STORES PROCEDURES QUE TIVERAM MODIFICAÇÃO EM FUNÇÃO
-- DAS MODIFICAÇÕES IMPOSTAS
-- =====================================================================

-- ---------------------------------------------------------------------
-- Calcula os valores referentes ao serviço a ser prestado em um item de
-- contrato levando em consideração um período a ser cobrado.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.toBePerformedService(FinstallationID integer,
  FstartDate date, FnumberOfParcels int)
RETURNS SETOF erp.performedServiceData AS
$$
DECLARE
  serviceData  erp.performedServiceData%rowtype;
  installation  record;

  -- Os parâmetros para cálculo de cada mensalidade e do valor total a
  -- ser cobrado
  parcelNumber  int;
  referenceDate  date;
  startDateOfPeriod  date;
  endDateOfPeriod  date;
  startDateOfBillingPeriod  date;
  monthlyValue  numeric;
  daysToConsider  smallint;

  -- O cálculo do valor de mensalidade por dia
  daysInPeriod  smallint;
  dayPrice  numeric;

  -- A análise de subsídios aplicados
  subsidyRecord  record;
  startOfSubsidy  date;
  endOfSubsidy  date;
  daysInSubsidy  smallint;
  discountValue  numeric;

  -- A análise de outras mensalidades presentes em contrato
  monthlyFeesRecord  record;
BEGIN
  -- Inicializamos as variáveis de processamento
  parcelNumber := 1;
  referenceDate := FstartDate;

  IF (FnumberOfParcels > 0) THEN
    LOOP
      -- Estamos processando cada período da cobrança antecipada, então
      -- precisamos analisar os períodos sendo cobrados e construir o
      -- valor a ser cobrado baseado nos valores computados a cada mês em
      -- cada item de contrato informado
      -- RAISE NOTICE 'Número da parcela: %', parcelNumber;
      -- RAISE NOTICE 'Data de referência: %', TO_CHAR(referenceDate, 'DD/MM/YYYY');

      -- Recupera as informações dos itens de contrato informados
      FOR installation IN
        SELECT I.installationID AS id,
               I.installationNumber AS number,
               C.signatureDate,
               C.startTermAfterInstallation,
               P.prorata,
               I.startDate,
               I.monthprice,
               I.contractID,
               I.lastDayOfBillingPeriod
          FROM erp.installations AS I
         INNER JOIN erp.contracts AS C ON (I.contractID = C.contractID)
         INNER JOIN erp.plans AS P ON (P.planID = I.planID)
         WHERE C.deleted = false
           AND C.active = true
           AND I.endDate IS NULL
           AND I.installationID = FinstallationID
         ORDER BY C.customerPayerid, C.subsidiaryPayerid, C.unifybilling, C.contractID, I.installationid
      LOOP
        -- Para cada parcela sendo calculada, analisamos se devemos ou
        -- não cobrar o período para cada um dos itens de contrato
        -- informados, de forma que conseguimos construir o valor final
        -- corretamente
        -- RAISE NOTICE 'Número do item de contrato: %', installation.number;
        -- RAISE NOTICE 'Data da assinatura do contrato: %', TO_CHAR(installation.signatureDate, 'DD/MM/YYYY');
        -- RAISE NOTICE 'Data do início do item de contrato: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');
        -- RAISE NOTICE 'Iniciar cobrança após instalação: %', CASE WHEN installation.startTermAfterInstallation THEN 'Sim' ELSE 'Não' END;
        -- RAISE NOTICE 'Cobrar proporcional ao serviço prestado: %', CASE WHEN installation.prorata THEN 'Sim' ELSE 'Não' END;

        -- Determinamos o período de cobrança em um mês
        startDateOfPeriod := referenceDate;
        endDateOfPeriod := startDateOfPeriod + interval '1 month' - interval '1 day';
        -- RAISE NOTICE 'Período de % à %', startDateOfPeriod, endDateOfPeriod;

        -- Determinamos à partir de qual data devemos cobrar
        IF (installation.prorata) THEN
          -- Devemos cobrar proporcionalmente, então determinamos quando
          -- isto ocorre
          IF (installation.startTermAfterInstallation) THEN
            IF (installation.startDate IS NULL) THEN
              -- Como a instalação não ocorreu ainda, então consideramos
              -- o início do período mesmo
              -- RAISE NOTICE 'Instalação não ocorreu, considerando o período inteiro';
              startDateOfBillingPeriod := startDateOfPeriod;
            ELSE
              -- Verificamos se o início do item de contrato ocorreu
              -- durante o período que estamos analisado
              IF (installation.startDate >= startDateOfPeriod) THEN
                -- Consideramos a data de instalação
                -- RAISE NOTICE 'Consideramos a data de instalação';
                startDateOfBillingPeriod := installation.startDate;
              ELSE
                -- Como a instalação se deu antes do início do período que
                -- iremos cobrar, então consideramos o início do período
                -- mesmo
                -- RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          ELSE
            IF (installation.signatureDate IS NULL) THEN
              -- Como o contrato não foi assinado ainda, então consideramos
              -- o início do período mesmo
              -- RAISE NOTICE 'Contrato não foi assinado, considerando o período inteiro';
              startDateOfBillingPeriod := baseDate;
            ELSE
              -- Verificamos se a assintatura ocorreu durante o período
              -- sendo analisado
              IF (installation.signatureDate >= startDateOfPeriod) THEN
                -- Consideramos a data de assinatura
                -- RAISE NOTICE 'Consideramos a data de assinatura do contrato';
                startDateOfBillingPeriod := installation.signatureDate;
              ELSE
                -- Como a assinatura do contrato se deu antes do início do
                -- período que iremos cobrar, então consideramos o início
                -- do período mesmo
                -- RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          END IF;
        ELSE
          -- Devemos cobrar integralmente, então o início se dá sempre no
          -- início do período apurado
          -- RAISE NOTICE 'Consideramos o período inteiro';
          startDateOfBillingPeriod := startDateOfPeriod;
        END IF;

        -- Verificamos se já foram realizadas cobranças de períodos
        -- neste item de contrato
        IF (installation.lastDayOfBillingPeriod IS NOT NULL) THEN
          -- Precisamos levar em consideração também o último período já
          -- cobrado se ele for superior ao período que estamos cobrando
          IF ((installation.lastDayOfBillingPeriod + interval '1 day') > startDateOfBillingPeriod) THEN
            startDateOfBillingPeriod := installation.lastDayOfBillingPeriod + interval '1 day';
            -- RAISE NOTICE 'Consideramos o período iniciando em %', startDateOfBillingPeriod;
          END IF;
        END IF;

        -- Calculamos a quantidade de dias no período
        daysInPeriod := DATE_PART('day',
            endDateOfPeriod::timestamp - startDateOfPeriod::timestamp
          ) + 1;
        -- RAISE NOTICE 'Este período possui % dias', daysInPeriod;

        -- Calculamos o valor diário com base na mensalidade
        dayPrice = installation.monthPrice / daysInPeriod;
        -- RAISE NOTICE 'O valor diário é %', ROUND(dayPrice, 2);

        -- Verificamos se precisamos cobrar algum período nesta parcela
        -- para esta instalação
        IF (startDateOfBillingPeriod <= endDateOfPeriod) THEN
          IF (installation.prorata) THEN
            IF (startDateOfBillingPeriod = startDateOfPeriod) THEN
              -- Cobramos o valor integral da mensalidade
              monthlyValue := installation.monthPrice;
              -- RAISE NOTICE 'Cobrando valor integral da mensalidade';
            ELSE
              -- Cobramos o valor proporcional

              -- Calculamos a quantidade de dias a serem cobrados
              daysToConsider := DATE_PART('day',
                  endDateOfPeriod::timestamp - startDateOfBillingPeriod::timestamp
                ) + 1;
              
              -- O serviço será prestado por uma parte do mês
              monthlyValue := ROUND(daysToConsider * dayPrice, 2);
            END IF;
          ELSE
            -- Cobramos sempre o valor integral da mensalidade
            monthlyValue := installation.monthPrice;
            -- RAISE NOTICE 'Cobrando valor integral da mensalidade';
          END IF;

          IF (monthlyValue > 0.00) THEN
            -- Acrescentamos esta valor a ser cobrado
            -- RAISE NOTICE 'O valor da mensalidade calculada é %', ROUND(monthlyValue, 2);
            serviceData.referenceMonthYear := to_char(referenceDate, 'MM/YYYY');
            serviceData.startDateOfPeriod  := startDateOfBillingPeriod;
            serviceData.endDateOfPeriod    := endDateOfPeriod;
            -- serviceData.contractID         := installation.contractid;
            -- serviceData.installationID     := installation.id;
            -- serviceData.installationNumber := installation.number;
            serviceData.name               := format(
              'Mensalidade de %s à %s',
              TO_CHAR(startDateOfBillingPeriod, 'DD/MM/YYYY'),
              TO_CHAR(endDateOfPeriod, 'DD/MM/YYYY')
            );
            serviceData.value              := ROUND(monthlyValue, 2);

            RETURN NEXT serviceData;

            -- Agora analisamos quaisquer subsídios ou bonificações
            -- existentes de forma a concedermos os respectivos descontos,
            -- se pertinente
            FOR subsidyRecord IN
              SELECT performedSubsidies.subsidyID AS ID,
                     performedSubsidies.bonus,
                     performedSubsidies.performedPeriod,
                     performedSubsidies.subsidyPeriod * performedSubsidies.performedPeriod AS subsidedPeriod,
                     performedSubsidies.discountType,
                     performedSubsidies.discountValue
                FROM (
                  SELECT S.subsidyID,
                         S.bonus,
                         public.intervalOfPeriod(startDateOfBillingPeriod, endDateOfPeriod) AS performedPeriod,
                         ('[' || S.periodStartedAt || ',' || COALESCE(S.periodEndedAt || ']', ')'))::public.intervalOfPeriod AS subsidyPeriod,
                         S.discountType,
                         S.discountValue
                    FROM erp.subsidies AS S
                   WHERE S.installationID = FinstallationID
                     AND (
                       (S.periodEndedAt IS NULL AND S.periodStartedAt <= endDateOfPeriod) OR
                       (S.periodEndedAt >= startDateOfBillingPeriod)
                      )
                   ORDER BY S.bonus DESC, S.periodStartedAt
                  ) AS performedSubsidies
            LOOP
              -- Calculamos os valores deste desconto
              startOfSubsidy := lower(subsidyRecord.subsidedPeriod);
              endOfSubsidy   := upper(subsidyRecord.subsidedPeriod);
              IF (startOfSubsidy IS NOT NULL) THEN
                daysInSubsidy  := DATE_PART('day',
                    endOfSubsidy::timestamp - startOfSubsidy::timestamp
                  ) + 1;
                -- RAISE NOTICE 'Período com % dias', daysInSubsidy;
                IF subsidyRecord.bonus THEN
                  -- Aplicamos 100% de desconto no período
                  IF (daysInSubsidy = daysInPeriod) THEN
                    -- O desconto foi concedido pelo mês inteiro
                    discountValue := monthPrice;
                  ELSE
                    -- O desconto foi concedido por uma parte do mês
                    discountValue := ROUND(daysInSubsidy * dayPrice, 2);
                  END IF;
                ELSE
                  -- Precisamos calcular o desconto em função do período
                  IF (subsidyRecord.discountType = 1) THEN
                    -- O desconto é um valor fixo em reais por dia
                    discountValue :=
                      ROUND(daysInSubsidy * subsidyRecord.discountValue, 2)
                    ;
                  ELSE
                    -- O desconto é uma porcentagem do valor do período
                    discountValue :=
                      ROUND(
                        (
                          (daysInSubsidy * dayPrice) *
                          subsidyRecord.discountValue / 100
                        ),
                        2
                      )
                    ;
                  END IF;
                END IF;

                -- RAISE NOTICE 'Adicionando desconto no item de contrato';
                serviceData.name  := format(
                  'Desconto de %s à %s',
                  TO_CHAR(startOfSubsidy, 'DD/MM/YYYY'),
                  TO_CHAR(endOfSubsidy, 'DD/MM/YYYY')
                );
                serviceData.value := ROUND((0 - discountValue), 2);

                RETURN NEXT serviceData;
              END IF;
            END LOOP;

            -- Agora analisamos quaisquer outros valores presentes no
            -- contrato e que precisam ser computados
            FOR monthlyFeesRecord IN
              SELECT B.name,
                     C.chargeValue AS value
                FROM erp.contractCharges AS C
               INNER JOIN erp.billingTypes AS B USING (billingTypeID)
               WHERE C.contractID = installation.contractid
                 AND B.billingMoments @> array[5]
                 AND B.inAttendance = false
                 AND B.ratePerEquipment = true
            LOOP
              -- RAISE NOTICE 'Adicionando a cobrança de % do item de contrato para ser cobrada', monthlyFeesRecord.name;
              serviceData.name  := monthlyFeesRecord.name;
              serviceData.value := monthlyFeesRecord.value;

              RETURN NEXT serviceData;
            END LOOP;
          END IF;
        END IF;
      END LOOP;
      
      -- Incrementamos a quantidade de parcelas que fizemos
      parcelNumber := parcelNumber + 1;

      -- Avançamos para o próximo mês
      referenceDate := referenceDate + interval '1 month';

      -- Repetimos este processo até determinar parcela seja superior a
      -- quantidade de parcelas a serem emitidas
      EXIT WHEN parcelNumber > FnumberOfParcels;
    END LOOP;
  END IF;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Obtém os valores referentes à rescisão contractual
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.contractTerminationValues(
  FinstallationID integer, FstartDateOfPeriod date, FendDateOfPeriod date)
RETURNS SETOF erp.performedServiceData AS
$$
DECLARE
  serviceData  erp.performedServiceData%rowtype;
  installation  record;
  billing  record;

  -- A data de encerramento do contrato
  FendDate  date;
  duration  integer;

  -- Cálculo da multa por quebra da fidelização
  monthsLeft  smallint;
  monthPrice  numeric(12,2);
  totalValue  numeric(12,2);
  fineValue   numeric(12,2);
BEGIN
  -- Recuperamos as informações do item de contrato sendo analisado
  -- RAISE NOTICE 'Recuperando informações do item de contrato';
  SELECT INTO installation
         I.contractorID,
         I.contractID,
         C.notchargeloyaltybreak AS notchargeloyaltybreakinallcontract,
         I.installationNumber AS number,
         P.loyaltyPeriod,
         P.loyaltyFine,
         I.monthPrice,
         I.notChargeLoyaltyBreak,
         I.startDate,
         I.endDate
    FROM erp.installations AS I
   INNER JOIN erp.contracts AS C ON (C.contractID = I.contractID)
   INNER JOIN erp.plans AS P ON (P.planID = I.planID)
   WHERE I.installationID = FinstallationID;

  -- RAISE NOTICE 'Número do item de contrato: %', installation.number;
  -- RAISE NOTICE 'Data do início: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');

  -- Determina a ocorrência de término do contrato e duração
  FendDate := CASE WHEN installation.endDate IS NULL THEN FendDateOfPeriod ELSE installation.endDate END;
  duration := EXTRACT(year FROM age(FendDate, installation.startDate))*12 + EXTRACT(month FROM age(FendDate, installation.startDate));
  -- RAISE NOTICE 'Data de término: %', TO_CHAR(FendDate, 'DD/MM/YYYY');
  -- RAISE NOTICE 'O item de contrato nº % teve duração de % meses',
  --   installation.number,
  --   duration;

  -- Caso o serviço tenha sido contratado com fidelização será
  -- necessário analisar a cobrança de multa
  IF ((installation.loyaltyPeriod > 0) AND (installation.notChargeLoyaltyBreak = FALSE) AND (installation.notChargeLoyaltyBreakInAllContract = FALSE)) THEN
    -- Temos um período de fidelidade, então analisa a questão da multa
    IF ((FendDate >= FstartDateOfPeriod) AND (FendDate <= FendDateOfPeriod)) THEN
      -- O encerramento ocorreu dentro do período apurado, então
      -- analiza se o tempo de duração efetiva do contrato foi
      -- inferior ao período mínimo de fidelização
      IF (duration < installation.loyaltyPeriod) THEN
        -- Precisamos cobrar a multa. A cobrança é proporcional ao
        -- período que falta para completar o período da fidelização,
        -- então determinamos quantos meses ainda faltam
        monthsLeft := installation.loyaltyPeriod - duration;
        -- RAISE NOTICE 'Faltaram % meses para o término do período de fidelidade',
        --  monthsLeft;

        -- Calculamos o valor total que é a quantidade de meses que
        -- faltam vezes o valor da mensalidade vigente
        totalValue := ROUND(monthsLeft * installation.monthPrice, 2);

        -- A multa é uma porcentagem deste valor
        fineValue := ROUND(totalValue * installation.loyaltyFine / 100, 2);

        -- Lançamos esta multa para ser cobrada
        -- RAISE NOTICE 'Inserida uma multa de % no item de contrato nº %',
        --   fineValue,
        --   installation.number;
        serviceData.referenceMonthYear := to_char(FstartDateOfPeriod, 'MM/YYYY');
        serviceData.startDateOfPeriod  := FstartDateOfPeriod;
        serviceData.endDateOfPeriod    := FendDateOfPeriod;
        serviceData.name               := 'Quebra do período fidelidade';
        serviceData.value              := ROUND(fineValue, 2);

        RETURN NEXT serviceData;
      END IF;
    END IF;
  END IF;

  -- Retorna valores a serem cobrados por término do contrato
  FOR billing IN
    SELECT B.name,
           C.chargeValue AS value
      FROM erp.contractCharges AS C
     INNER JOIN erp.billingTypes AS B USING (billingTypeID)
     WHERE C.contractID = installation.contractID
       AND B.billingMoments @> array[3, 4]
       AND B.inAttendance = false
  LOOP
    -- Lançamos esta cobrança
    serviceData.referenceMonthYear := to_char(FstartDateOfPeriod, 'MM/YYYY');
    serviceData.startDateOfPeriod  := FstartDateOfPeriod;
    serviceData.endDateOfPeriod    := FendDateOfPeriod;
    serviceData.name               := billing.name;
    serviceData.value              := billing.value;

    RETURN NEXT serviceData;
  END LOOP;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Inicia o fechamento dos valores de cada item de contrato
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.startMonthlyCalculations(FstartDate date,
  FcontractorID integer, Fcooperative boolean, FuserID integer) RETURNS boolean AS
$$
DECLARE
  -- A data de término do período a ser faturado
  FendDate  date;

  -- Os dados do item de contrato
  installation  record;

  -- Os dados da tarifa a ser cobrada
  contractCharge  record;

  -- O último cliente
  lastCustomerID  integer;
  lastSubsidiaryID  integer;

  -- O último contrato
  lastContractID  integer;

  -- O complemento do próximo mês para determinar o dia de vencimento
  nextMonth  integer;
  nextYear   integer;

  -- O ID da apuração
  FascertainedPeriodID  integer;

  -- Os manipuladores da fatura
  createNewInvoice  boolean;
  newInvoiceID  integer;

  -- A informação da tarifa do meio de pagamento
  lastDefinedMethod  integer;
  basicFare  numeric(12,2);

  -- Cálculo da multa por quebra da fidelização
  monthsLeft  smallint;
  monthPrice  numeric(12,2);
  totalValue  numeric(12,2);
  fineValue   numeric(12,2);

  hasInstallations  boolean;
  invoiceValue  numeric(12,2);

  amountOfRemainingBillings  integer;
  continueWithBillingProcess  boolean;
BEGIN
  -- Inicializamos as variáveis de processamento
  FendDate := FstartDate + interval '1 month' - interval '1 day';
  nextMonth := to_char(FstartDate + interval '1 month', 'MM')::integer;
  nextYear  := to_char(FstartDate + interval '1 month', 'YYYY')::integer;
  lastCustomerID := 0;
  lastSubsidiaryID := 0;
  lastContractID := 0;
  lastDefinedMethod := 0;
  hasInstallations := false;

  -- Recupera as informações de itens de contrato habilitados para
  -- cobrança, e também as informações pertinentes do contrato ao qual
  -- ele pertence
  FOR installation IN
    SELECT I.installationID AS id,
           I.installationNumber AS number,
           I.monthprice,
           C.contractID,
           C.active AS contractActive,
           erp.getContractNumber(C.createdat) AS contractNumber,
           SUB.numberofmonths,
           C.customerID,
           C.customerPayerID,
           C.subsidiaryPayerID,
           C.unifyBilling,
           C.chargeAnyTariffs,
           C.planID,
           CASE
             WHEN P.dueDateOnlyInWorkingdays THEN erp.getNextWorkDay(public.buidDate(nextYear, nextMonth, D.day), S.cityid)
             ELSE public.buidDate(nextYear, nextMonth, D.day)
           END AS dueDate,
           P.loyaltyPeriod,
           P.loyaltyFine,
           I.lastDayOfBillingPeriod,
           I.notChargeLoyaltyBreak,
           I.startDate,
           I.endDate,
           CASE
             WHEN I.endDate IS NOT NULL THEN EXTRACT(year FROM age(I.endDate, I.startDate))*12 + EXTRACT(month FROM age(I.endDate, I.startDate))
             ELSE 0
           END AS duration,
           CASE
             WHEN C.prepaid THEN C2.paymentMethodID
             ELSE C1.paymentMethodID
           END AS paymentMethodID,
           CASE
             WHEN C.prepaid THEN C2.definedMethodID
             ELSE C1.definedMethodID
           END AS definedMethodID,
           CASE
             WHEN C.prepaid THEN C2.name
             ELSE C1.name
           END AS paymentMethodName
      FROM erp.installations AS I
     INNER JOIN erp.contracts AS C ON (I.contractID = C.contractID)
     INNER JOIN erp.subscriptionPlans AS SUB ON (C.subscriptionPlanID = SUB.subscriptionPlanID)
     INNER JOIN erp.entities AS CLI ON (C.customerID = CLI.entityID)
     INNER JOIN erp.entitiesTypes AS ET ON (CLI.entityTypeID = ET.entityTypeID)
     INNER JOIN erp.plans AS P ON (I.planID = P.planID)
     INNER JOIN erp.dueDays AS D ON (C.dueDayID = D.dueDayID)
     INNER JOIN erp.subsidiaries AS S ON (C.subsidiaryID = S.subsidiaryID)
     INNER JOIN erp.paymentConditions AS C1 ON (C.paymentConditionID = C1.paymentConditionID)
     INNER JOIN erp.definedMethods AS D1 ON (C1.definedMethodID = D1.definedMethodID)
     INNER JOIN erp.paymentConditions AS C2 ON (C.additionalPaymentConditionID = C2.paymentConditionID)
     INNER JOIN erp.definedMethods AS D2 ON (C2.definedMethodID = D2.definedMethodID)
     WHERE I.contractorID = FcontractorID
       AND C.deleted = false
       AND C.active = true
       AND I.startDate IS NOT NULL
       AND I.startDate <= FendDate
       -- AND (I.endDate IS NULL OR I.endDate >= FstartDate)
       AND ET.cooperative = Fcooperative
     ORDER BY C.customerPayerid, C.subsidiaryPayerid, C.unifybilling, C.contractID, I.installationid
  LOOP
    -- Indicamos que temos itens de contrato
    hasInstallations := true;
    -- RAISE NOTICE 'Processando o item de contrato nº %', installation.number;

    -- Analisamos se temos períodos ainda a serem apurados para este
    -- item de contrato
    IF ((installation.endDate IS NULL) OR (installation.endDate >= FstartDate)) THEN
      -- Apuramos os valores do período para este item de contrato
      SELECT INTO FascertainedPeriodID
             erp.performedServiceInPeriod(FStartDate, installation.id, FuserID, NULL);
    ELSE
      -- Indica que não temos períodos apurados
      FascertainedPeriodID := 0;
    END IF;

    continueWithBillingProcess := false;
    IF (FascertainedPeriodID = 0) THEN
      -- Verificamos se temos algum valor a ser cobrado
      SELECT count(*) INTO amountOfRemainingBillings
        FROM erp.billings
       WHERE billingDate <= FendDate
         AND billings.installationID = installation.id
         AND billings.contractorID = FcontractorID
         AND invoiced = FALSE
         AND invoiceID IS NULL;
      IF (amountOfRemainingBillings > 0) THEN
        continueWithBillingProcess := true;
      END IF;

      -- RAISE NOTICE 'Não temos mensalidade para o item de contrato nº %', installation.number;
    ELSE
      continueWithBillingProcess := true;
      -- RAISE NOTICE 'Apurado valores da mensalidade para o item de contrato nº % com ID %',
      --   installation.number,
      --   FascertainedPeriodID;
    END IF;

    IF (continueWithBillingProcess) THEN
      -- Analisa a criação de uma nova fatura
      createNewInvoice := false;
      IF (installation.unifyBilling) THEN
        -- Devemos unificar as cobranças em uma única fatura para todos
        -- os itens de contrato de um mesmo cliente, independente do
        -- contrato ao qual pertencem, então analisa se estamos no mesmo
        -- cliente
        -- RAISE NOTICE 'lastCustomerID % <> % installation.contractID',
        --   lastCustomerID,
        --   installation.contractID;
        IF ( (lastCustomerID <> installation.customerPayerID) AND
             (lastSubsidiaryID <> installation.subsidiaryPayerID) ) THEN
          -- Criamos uma nova fatura, pois mudamos de cliente pagador
          createNewInvoice := true;
          -- RAISE NOTICE 'Precisamos criar uma fatura para o cliente nº %', installation.customerPayerID;
        END IF;
      ELSE
        -- Criamos uma nova fatura a cada novo item de contrato
        createNewInvoice := true;
        -- RAISE NOTICE 'Precisamos criar uma fatura para o item de contrato nº %', installation.number;
      END IF;
      lastCustomerID := installation.customerPayerID;
      lastSubsidiaryID := installation.subsidiaryPayerID;
      
      IF (createNewInvoice) THEN
        -- Precisamos criar uma nova fatura
        INSERT INTO erp.invoices (contractorID, customerID, subsidiaryID,
          invoiceDate, referenceMonthYear, dueDate, paymentMethodID,
          definedMethodID, underAnalysis) VALUES (FcontractorID,
          installation.customerPayerID, installation.subsidiaryPayerID,
          CURRENT_DATE, to_char(FstartDate, 'MM/YYYY'),
          installation.dueDate, installation.paymentMethodID,
          installation.definedMethodID, TRUE)
        RETURNING invoiceID INTO newInvoiceID;
        -- RAISE NOTICE 'Criada a fatura nº %', newInvoiceID;
      END IF;

      -- Atualizamos a informação do número da fatura no período cobrado,
      -- se existir
      UPDATE erp.billedPeriods
         SET invoiceID = newInvoiceID
       WHERE installationID = installation.id
         AND invoiceID IS NULL;

      -- Caso o serviço tenha sido contratado com fidelização será
      -- necessário analisar a cobrança de multa
      IF ((installation.loyaltyPeriod > 0) AND (installation.notChargeLoyaltyBreak = FALSE)) THEN
        -- Temos um período de fidelidade, então verifica se o item de
        -- contrato já foi encerrado
        IF (installation.endDate IS NOT NULL) THEN
          -- O item de contrato foi encerrado, então verificamos se ele
          -- ocorreu dentro do período de apuração
          IF ((installation.endDate >= FStartDate) AND (installation.endDate <= FendDate)) THEN
            -- O encerramento ocorreu dentro do período apurado, então
            -- analiza se o tempo de duração efetiva do contrato foi
            -- inferior ao período mínimo de fidelização
            IF (installation.duration < installation.loyaltyPeriod) THEN
              -- Precisamos cobrar a multa. A cobrança é proporcional ao
              -- período que falta para completar o período da fidelização,
              -- então determinamos quantos meses ainda faltam
              monthsLeft := installation.loyaltyPeriod - installation.duration;
              -- RAISE NOTICE 'O item de contrato nº % teve duração de % meses',
              --   installation.number,
              --   monthsLeft;

              -- Determinamos a tarifa vigente no início deste período
              SELECT INTO monthPrice
                     readjustment.monthPrice
                FROM erp.readjustmentsOnInstallations AS readjustment
               WHERE readjustment.installationID = installation.id
                 AND readjustment.readjustedAt <= FstartDate
               ORDER BY readjustment.readjustedAt DESC
               FETCH FIRST ROW ONLY;
              IF NOT FOUND THEN
                -- Caso o item de contrato tenha sido criado neste mês,
                -- a consulta acima não irá retornar um valor. Neste
                -- caso, considera o mês corrente
                SELECT INTO monthPrice
                       readjustment.monthPrice
                  FROM erp.readjustmentsOnInstallations AS readjustment
                 WHERE readjustment.installationID = installation.id
                   AND readjustment.readjustedAt < (FstartDate + interval '1 month')
                 ORDER BY readjustment.readjustedAt DESC
                 FETCH FIRST ROW ONLY;
                IF NOT FOUND THEN
                  -- Disparamos uma exceção
                  RAISE EXCEPTION 'Não foi possível obter a mensalidade para o item de contrato % para o período com início em %',
                    installation.number,
                    FstartDate
                  USING HINT = 'Por favor, verifique os valores da mensalidade para este item de contrato';
                END IF;
              END IF;
              -- RAISE NOTICE 'A mensalidade é %', monthPrice;

              -- Calculamos o valor total que é a quantidade de meses que
              -- faltam vezes o valor da mensalidade vigente
              totalValue := ROUND(monthsLeft * monthPrice, 2);

              -- A multa é uma porcentagem deste valor
              fineValue := ROUND(totalValue * installation.loyaltyFine / 100, 2);

              -- Lançamos esta multa nos registros de valores cobrados
              INSERT INTO erp.billings (contractorID, contractID,
                     installationID, billingDate, name, value, invoiceID,
                     addMonthlyAutomatic, createdByUserID, updatedByUserID)
              VALUES (FcontractorID, installation.contractID, installation.id,
                      FendDate, 'Quebra do período fidelidade',
                      fineValue, newInvoiceID, true, FuserID, FuserID);
              -- RAISE NOTICE 'Inserida uma multa de % no item de contrato nº %',
              --   fineValue,
              --   installation.number;
            END IF;
          END IF;
        END IF;
      END IF;

      -- Precisamos incluir tarifas de valores cobrados mensalmente
      -- presentes no plano
      FOR contractCharge IN
        SELECT C.contractChargeID,
               B.name,
               C.chargeValue,
               B.ratePerEquipment
          FROM erp.contractCharges AS C
         INNER JOIN erp.billingTypes AS B USING (billingTypeID)
         WHERE C.contractID = installation.contractID
           AND B.billingMoments @> array[5]
           AND B.inAttendance = false
      LOOP
        IF (contractCharge.ratePerEquipment) THEN
          -- Lançamos esta tarifa nos registros de valores cobrados para
          -- cada item de contrato
          INSERT INTO erp.billings (contractorID, contractID,
                 installationID, billingDate, name, value, invoiceID,
                 addMonthlyAutomatic, isMonthlyPayment, createdByUserID,
                 updatedByUserID)
          VALUES (FcontractorID, installation.contractID, installation.id,
                  FendDate, contractCharge.name, contractCharge.chargeValue,
                  newInvoiceID, TRUE, TRUE, FuserID, FuserID);
          -- RAISE NOTICE 'Inserida a cobrança de % no valor de % no item de contrato nº %',
          --   contractCharge.name,
          --   contractCharge.chargeValue,
          --   installation.number;
        ELSE
          -- Lançamos esta tarifa nos registros de valores cobrados uma
          -- única vez para este contrato
          IF (lastContractID <> installation.contractID) THEN
            INSERT INTO erp.billings (contractorID, contractID,
                   installationID, billingDate, name, value, invoiceID,
                   addMonthlyAutomatic, isMonthlyPayment, createdByUserID,
                   updatedByUserID)
            VALUES (FcontractorID, installation.contractID, NULL, FendDate,
                    contractCharge.name, contractCharge.chargeValue,
                    newInvoiceID, TRUE, TRUE, FuserID, FuserID);
            -- RAISE NOTICE 'Inserida a cobrança de % no valor de % no contato nº %',
            --   contractCharge.name,
            --   contractCharge.chargeValue,
            --   installation.contractNumber;

            lastContractID := installation.contractID;
          END IF;
        END IF;
      END LOOP;

      -- Precisamos incluir o número da fatura em todos os lançamentos
      -- deste item de contrato que estejam em aberto (ainda não foram
      -- cobrados). Serão incluídos também os valores renegociados e
      -- abonados, mas eles não serão considerados para efeito de
      -- cálculo. Isto é feito para que seus valores sejam ocultos
      -- quando o fechamento for concluído
      UPDATE erp.billings
         SET invoiceID = newInvoiceID
       WHERE billingDate <= FendDate
         AND billings.installationID = installation.id
         AND billings.contractorID = FcontractorID
         AND invoiced = FALSE
         AND invoiceID IS NULL;
    END IF;
  END LOOP;

  IF (NOT hasInstallations) THEN
    -- RAISE NOTICE 'Não temos itens de contrato habilitados para este período.';

    RETURN false;
  END IF;

  -- Por último, determinamos os valores totais de cada nota com base
  -- nos valores calculados
  UPDATE erp.invoices
     SET invoiceValue = ROUND(billings.total, 2)
    FROM (
      SELECT invoiceID,
             SUM(value) as total
        FROM erp.billings
       WHERE granted = false
         AND renegotiated = false
         AND invoiced = false
         AND contractorID = FcontractorID
      GROUP BY invoiceID
     ) AS billings
   WHERE invoices.invoiceID = billings.invoiceID
     AND invoices.contractorID = FcontractorID;

  -- Indica que tudo deu certo
  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Gera um carnê de pagamentos
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.createCarnet(FcontractorID integer,
  FcustomerID int, FsubsidiaryID int, FstartDate date, FnumberOfParcels int,
  FfirstDueDate date, FuserID integer, Finstallations integer array)
RETURNS integer AS
$$
DECLARE
  -- Os parâmetros para cálculo de cada mensalidade e do valor total a
  -- ser cobrado
  parcelNumber  int;
  referenceDate  date;
  startDateOfPeriod  date;
  endDateOfPeriod  date;
  startDateOfBillingPeriod  date;
  monthlyValue  numeric;
  discountTotal  numeric;
  finalValue  numeric;
  daysToConsider  smallint;
  dueDate  date;

  -- O cálculo do valor de mensalidade por dia
  daysInPeriod  smallint;
  dayPrice  numeric;

  -- A análise de subsídios aplicados
  subsidyRecord  record;
  startOfSubsidy  date;
  endOfSubsidy  date;
  daysInSubsidy  smallint;
  discountValue  numeric;

  -- As informações do meio de pagamento
  FpaymentMethodID  integer;
  FdefinedMethodID  integer;

  -- Os padrâmetros de multa, juros de mora e instrução do boleto
  FfineValue  numeric(8,4);
  FarrearInterestType  integer;
  FarrearInterest  numeric(8,4);
  Fparameters  json;
  FinstructionID  integer;
  FinstructionDays  integer;

  -- O ID do último contrato
  lastContractID  integer;

  -- Os dados da instalação e de valores a serem cobrados
  installation  record;

  -- A análise de outras mensalidades presentes em contrato
  monthlyFeesRecord  record;

  -- O ID do carnê e da fatura
  newCarnetID  int;
  newInvoiceID  integer;

  -- Os dados dos boletos a serem gerados
  billets  jsonb[];
  billet  jsonb;
  billing  jsonb;
  billings  jsonb[];
  billed  jsonb;
  billeds  jsonb[];

  -- Os dados da fatura
  invoice  record;

  -- Parâmetris do boleto a ser gerado
  FbillingCounter  integer;
  ourNumber  varchar(12);
  paymentSituationID  integer;
  droppedTypeID  integer;
BEGIN
  -- Inicializamos as variáveis de processamento
  parcelNumber := 1;
  referenceDate := FstartDate;
  dueDate := FfirstDueDate;
  lastContractID := 0;
  billets := jsonb '{ }';

  IF (FnumberOfParcels > 0) THEN
    LOOP
      -- Estamos processando cada período da cobrança antecipada, então
      -- precisamos analisar os períodos sendo cobrados e construir o
      -- valor a ser cobrado baseado nos valores computados a cada mês
      -- em cada item de contrato informado, montando as respectivas
      -- parcelas do carnê
      RAISE NOTICE 'Número da parcela: %', parcelNumber;
      RAISE NOTICE 'Data de referência: %', TO_CHAR(referenceDate, 'DD/MM/YYYY');

      -- Inicializamos o registro de valores desta parcela
      billet := format(
        '{"parcel":%s,"dueDate":"%s","referenceMonth":"%s","billings":[],"billeds":[]}',
        parcelNumber,
        dueDate,
        to_char(referenceDate, 'MM/YYYY')
      )::jsonb;
      billings := jsonb '{ }';
      billeds := jsonb '{ }';

      -- Recupera as informações das instalações para as quais estamos
      -- emitindo o carnê
      FOR installation IN
        SELECT I.installationID AS id,
               I.installationNumber AS number,
               C.signatureDate,
               C.startTermAfterInstallation,
               P.prorata,
               I.startDate,
               I.monthprice,
               I.contractID,
               I.lastDayOfBillingPeriod
          FROM erp.installations AS I
         INNER JOIN erp.contracts AS C ON (I.contractID = C.contractID)
         INNER JOIN erp.plans AS P ON (P.planID = I.planID)
         WHERE I.contractorID = FcontractorID
           AND C.deleted = false
           AND C.active = true
           AND I.endDate IS NULL
           AND I.installationID = ANY(Finstallations)
         ORDER BY C.customerPayerid, C.subsidiaryPayerid, C.unifybilling, C.contractID, I.installationid
      LOOP
        -- Para cada parcela sendo calculada, analisamos se devemos ou
        -- não cobrar o período para cada um dos itens de contrato
        -- informados, de forma que conseguimos construir o valor final
        -- desta parcela corretamente
        RAISE NOTICE 'Número do item de contrato: %', installation.number;
        RAISE NOTICE 'Data da assinatura do contrato: %', TO_CHAR(installation.signatureDate, 'DD/MM/YYYY');
        RAISE NOTICE 'Data do início do item de contrato: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');
        RAISE NOTICE 'Iniciar cobrança após instalação: %', CASE WHEN installation.startTermAfterInstallation THEN 'Sim' ELSE 'Não' END;
        RAISE NOTICE 'Cobrar proporcional ao serviço prestado: %', CASE WHEN installation.prorata THEN 'Sim' ELSE 'Não' END;

        -- Determinamos o período de cobrança em um mês
        startDateOfPeriod := referenceDate;
        endDateOfPeriod := startDateOfPeriod + interval '1 month' - interval '1 day';
        RAISE NOTICE 'Período de % à %', startDateOfPeriod, endDateOfPeriod;

        -- Determinamos à partir de qual data devemos cobrar
        IF (installation.prorata) THEN
          -- Devemos cobrar proporcionalmente, então determinamos quando
          -- isto ocorre
          IF (installation.startTermAfterInstallation) THEN
            IF (installation.startDate IS NULL) THEN
              -- Como a instalação não ocorreu ainda, então consideramos
              -- o início do período mesmo
              RAISE NOTICE 'Instalação não ocorreu, considerando o período inteiro';
              startDateOfBillingPeriod := startDateOfPeriod;
            ELSE
              -- Verificamos se o início do item de contrato ocorreu
              -- durante o período que estamos analisado
              IF (installation.startDate >= startDateOfPeriod) THEN
                -- Consideramos a data de instalação
                RAISE NOTICE 'Consideramos a data de instalação';
                startDateOfBillingPeriod := installation.startDate;
              ELSE
                -- Como a instalação se deu antes do início do período que
                -- iremos cobrar, então consideramos o início do período
                -- mesmo
                RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          ELSE
            IF (installation.signatureDate IS NULL) THEN
              -- Como o contrato não foi assinado ainda, então consideramos
              -- o início do período mesmo
              RAISE NOTICE 'Contrato não foi assinado, considerando o período inteiro';
              startDateOfBillingPeriod := baseDate;
            ELSE
              -- Verificamos se a assintatura ocorreu durante o período
              -- sendo analisado
              IF (installation.signatureDate >= startDateOfPeriod) THEN
                -- Consideramos a data de assinatura
                RAISE NOTICE 'Consideramos a data de assinatura do contrato';
                startDateOfBillingPeriod := installation.signatureDate;
              ELSE
                -- Como a assinatura do contrato se deu antes do início do
                -- período que iremos cobrar, então consideramos o início
                -- do período mesmo
                RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          END IF;
        ELSE
          -- Devemos cobrar integralmente, então o início se dá sempre no
          -- início do período apurado
          RAISE NOTICE 'Consideramos o período inteiro';
          startDateOfBillingPeriod := startDateOfPeriod;
        END IF;

        -- Verificamos se já foram realizadas cobranças de períodos
        -- neste item de contrato
        IF (installation.lastDayOfBillingPeriod IS NOT NULL) THEN
          -- Precisamos levar em consideração também o último período já
          -- cobrado se ele for superior ao período que estamos cobrando
          IF ((installation.lastDayOfBillingPeriod + interval '1 day') > startDateOfBillingPeriod) THEN
            startDateOfBillingPeriod := installation.lastDayOfBillingPeriod + interval '1 day';
            RAISE NOTICE 'Consideramos o período iniciando em %', startDateOfBillingPeriod;
          END IF;
        END IF;

        -- Calculamos a quantidade de dias no período
        daysInPeriod := DATE_PART('day',
            endDateOfPeriod::timestamp - startDateOfPeriod::timestamp
          ) + 1;
        RAISE NOTICE 'Este período possui % dias', daysInPeriod;

        -- Calculamos o valor diário com base na mensalidade
        dayPrice = installation.monthPrice / daysInPeriod;
        RAISE NOTICE 'O valor diário é %', ROUND(dayPrice, 2);

        -- Verificamos se precisamos cobrar algum período nesta parcela
        -- para esta instalação
        IF (startDateOfBillingPeriod <= endDateOfPeriod) THEN
          IF (installation.prorata) THEN
            IF (startDateOfBillingPeriod = startDateOfPeriod) THEN
              -- Cobramos o valor integral da mensalidade
              monthlyValue := installation.monthPrice;
              RAISE NOTICE 'Cobrando valor integral da mensalidade';
            ELSE
              -- Cobramos o valor proporcional

              -- Calculamos a quantidade de dias a serem cobrados
              daysToConsider := DATE_PART('day',
                  endDateOfPeriod::timestamp - startDateOfBillingPeriod::timestamp
                ) + 1;
              
              -- O serviço será prestado por uma parte do mês
              monthlyValue := ROUND(daysToConsider * dayPrice, 2);
            END IF;
          ELSE
            -- Cobramos sempre o valor integral da mensalidade
            monthlyValue := installation.monthPrice;
            RAISE NOTICE 'Cobrando valor integral da mensalidade';
          END IF;

          IF (monthlyValue > 0.00) THEN
            -- Acrescentamos esta valor a ser cobrado nesta mensalidade
            RAISE NOTICE 'O valor da mensalidade calculada é %', ROUND(monthlyValue, 2);
            billings := billings || Array[
              format(
                '{"contractID":%s,"installationID":%s,"name":"%s","value":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                installation.contractid,
                installation.id,
                format(
                  'Mensalidade de %s à %s',
                  TO_CHAR(startDateOfBillingPeriod, 'DD/MM/YYYY'),
                  TO_CHAR(endDateOfPeriod, 'DD/MM/YYYY')
                ),
                monthlyValue,
                startDateOfBillingPeriod,
                endDateOfPeriod
              )::jsonb
            ];

            -- Agora analisamos quaisquer subsídios ou bonificações
            -- existentes de forma a concedermos os respectivos
            -- descontos, se pertinente
            discountTotal := 0;
            FOR subsidyRecord IN
              SELECT performedSubsidies.subsidyID AS ID,
                     performedSubsidies.bonus,
                     performedSubsidies.performedPeriod,
                     performedSubsidies.subsidyPeriod * performedSubsidies.performedPeriod AS subsidedPeriod,
                     performedSubsidies.discountType,
                     performedSubsidies.discountValue
                FROM (
                  SELECT S.subsidyID,
                         S.bonus,
                         public.intervalOfPeriod(startDateOfBillingPeriod, endDateOfPeriod) AS performedPeriod,
                         ('[' || S.periodStartedAt || ',' || COALESCE(S.periodEndedAt || ']', ')'))::public.intervalOfPeriod AS subsidyPeriod,
                         S.discountType,
                         S.discountValue
                    FROM erp.subsidies AS S
                   WHERE S.installationID = installation.id
                     AND (
                       (S.periodEndedAt IS NULL AND S.periodStartedAt <= endDateOfPeriod) OR
                       (S.periodEndedAt >= startDateOfBillingPeriod)
                      )
                   ORDER BY S.bonus DESC, S.periodStartedAt
                  ) AS performedSubsidies
            LOOP
              -- Calculamos os valores deste desconto
              startOfSubsidy := lower(subsidyRecord.subsidedPeriod);
              endOfSubsidy   := upper(subsidyRecord.subsidedPeriod);
              IF (startOfSubsidy IS NOT NULL) THEN
                daysInSubsidy  := DATE_PART('day',
                    endOfSubsidy::timestamp - startOfSubsidy::timestamp
                  ) + 1;
                RAISE NOTICE 'Período com % dias', daysInSubsidy;
                IF subsidyRecord.bonus THEN
                  -- Aplicamos 100% de desconto no período
                  IF (daysInSubsidy = daysInPeriod) THEN
                    -- O desconto foi concedido pelo mês inteiro
                    discountValue := monthPrice;
                  ELSE
                    -- O desconto foi concedido por uma parte do mês
                    discountValue := ROUND(daysInSubsidy * dayPrice, 2);
                  END IF;
                ELSE
                  -- Precisamos calcular o desconto em função do período
                  IF (subsidyRecord.discountType = 1) THEN
                    -- O desconto é um valor fixo em reais por dia
                    discountValue :=
                      ROUND(daysInSubsidy * subsidyRecord.discountValue, 2)
                    ;
                  ELSE
                    -- O desconto é uma porcentagem do valor do período
                    discountValue :=
                      ROUND(
                        (
                          (daysInSubsidy * dayPrice) *
                          subsidyRecord.discountValue / 100
                        ),
                        2
                      )
                    ;
                  END IF;
                END IF;

                RAISE NOTICE 'Adicionando desconto no item de contrato';
                billings := billings || Array[
                  format(
                    '{"contractID":%s,"installationID":%s,"monthPrice":%s,"name":"%s","value":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                    installation.contractid,
                    installation.id,
                    installation.monthprice,
                    format(
                      'Desconto de %s à %s',
                      TO_CHAR(startOfSubsidy, 'DD/MM/YYYY'),
                      TO_CHAR(endOfSubsidy, 'DD/MM/YYYY')
                    ),
                    ROUND((0 - discountValue), 2),
                    startDateOfBillingPeriod,
                    endDateOfPeriod
                  )::jsonb
                ];

                discountTotal := discountTotal + discountValue;
              END IF;
            END LOOP;

            -- Calculamos o valor final
            finalValue := monthlyValue - discountTotal;
            IF (finalValue < 0.00) THEN
              finalValue := 0.00;
            END IF;

            billeds := billeds || Array[
              format(
                '{"contractID":%s,"installationID":%s,"monthPrice":%s,"monthlyValue":%s,"discountValue":%s,"finalValue":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                installation.contractid,
                installation.id,
                installation.monthprice,
                monthlyValue,
                discountTotal,
                finalValue,
                startDateOfBillingPeriod,
                endDateOfPeriod
              )::jsonb
            ];

            -- Inicializamos o período cobrado
            billed := format(
              '{"contractID":%s,"installationID":%s,"monthPrice":%s,"monthlyValue":%s,"discountValue":0.00,"finalValue":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
              installation.contractid,
              installation.id,
              installation.monthprice,
              monthlyValue,
              monthlyValue,
              startDateOfBillingPeriod,
              endDateOfPeriod
            )::jsonb;

            -- Agora analisamos quaisquer outros valores presentes no
            -- contrato e que precisam ser computados
            FOR monthlyFeesRecord IN
              SELECT B.name,
                     C.chargeValue AS value
                FROM erp.contractCharges AS C
               INNER JOIN erp.billingTypes AS B USING (billingTypeID)
               WHERE C.contractID = installation.contractid
                 AND B.billingMoments @> array[5]
                 AND B.inAttendance = false
                 AND B.ratePerEquipment = true
            LOOP
              RAISE NOTICE 'Adicionando a cobrança de % do item de contrato para ser cobrada', monthlyFeesRecord.name;
              billings := billings || Array[
                format(
                  '{"contractID":%s,"installationID":%s,"monthPrice":%s,"name":"%s","value":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                  installation.contractid,
                  installation.id,
                  installation.monthprice,
                  monthlyFeesRecord.name,
                  monthlyFeesRecord.value,
                  startDateOfBillingPeriod,
                  endDateOfPeriod
                )::jsonb
              ];
            END LOOP;
          END IF;
        END IF;
      END LOOP;

      -- Adicionamos os valores a serem cobrados
      IF (array_length(billings, 1) > 0) THEN
        RAISE NOTICE 'Temos % itens a serem cobrados nesta parcela', array_length(billings, 1);
        RAISE NOTICE 'billings: %', billings;

        -- Adicionamos os valores a serem cobrados no boleto desta parcela
        billet := jsonb_set(
          billet, '{ billings }', to_jsonb(billings), true
        );
        billet := jsonb_set(
          billet, '{ billeds }', to_jsonb(billeds), true
        );
        RAISE NOTICE 'Adicionando o boleto ao carnê: %', billet;

        -- Adicionamos o boleto ao carnê
        billets := billets || billet;
      -- ELSE
      --   RAISE NOTICE 'Não temos itens a serem cobrados nesta parcela';
      END IF;
      
      -- Incrementamos a quantidade de parcelas que fizemos
      parcelNumber := parcelNumber + 1;

      -- Avançamos para o próximo mês
      referenceDate := referenceDate + interval '1 month';
      dueDate := dueDate + interval '1 month';

      -- Repetimos este processo até determinar parcela seja superior a
      -- quantidade de parcelas a serem emitidas
      EXIT WHEN parcelNumber > FnumberOfParcels;
    END LOOP;

    IF (array_length(billets, 1) > 0) THEN
      RAISE NOTICE 'Processando os boletos';

      -- Recuperamos a informação da cobrança a ser gerada utilizando como
      -- referência a primeira instalação informada
      SELECT INTO FpaymentMethodID, FdefinedMethodID, FfineValue, FarrearInterestType, FarrearInterest, Fparameters, FinstructionID, FinstructionDays
             C1.paymentMethodID,
             C1.definedMethodID,
             P.fineValue,
             P.arrearInterestType,
             P.arrearInterest,
             ((D1.parameters::jsonb - 'instructionID') - 'instructionDays')::json AS parameters,
             D1.parameters::jsonb->'instructionID' AS instructionID,
             D1.parameters::jsonb->'instructionDays' AS instructionDays
        FROM erp.installations AS I
       INNER JOIN erp.contracts AS C ON (I.contractID = C.contractID)
       INNER JOIN erp.plans AS P ON (I.planID = P.planID)
       INNER JOIN erp.paymentConditions AS C1 ON (C.paymentConditionID = C1.paymentConditionID)
       INNER JOIN erp.definedMethods AS D1 ON (C1.definedMethodID = D1.definedMethodID)
       WHERE I.installationID = Finstallations[1];

      -- Criamos o identificador de nosso carnê
      INSERT INTO erp.carnets (contractorID, createdAt, createdByUserID)
           VALUES (FcontractorID, CURRENT_TIMESTAMP, FuserID)
      RETURNING carnetID INTO newCarnetID;

      FOREACH billet IN ARRAY billets
      LOOP
        -- Fazemos a inserção dos boletos no banco de dados

        -- Precisamos criar uma nova fatura a cada mês
        RAISE NOTICE 'Parcela: %', billet->>'parcel';
        RAISE NOTICE 'referenceMonthYear: %', billet->>'referenceMonth';
        RAISE NOTICE 'dueDate: %', (billet->>'dueDate')::Date;
        INSERT INTO erp.invoices (contractorID, customerID, subsidiaryID,
                    invoiceDate, referenceMonthYear, dueDate, paymentMethodID,
                    definedMethodID, carnetID)
             VALUES (FcontractorID, FcustomerID, FsubsidiaryID,
                    CURRENT_DATE, (billet->>'referenceMonth')::text,
                    (billet->>'dueDate')::Date, FpaymentMethodID,
                    FdefinedMethodID, newCarnetID)
        RETURNING invoiceID INTO newInvoiceID;

        -- Adicionamos cada valor cobrado
        FOR billing IN
          SELECT * FROM jsonb_array_elements(billet->'billings')
        LOOP
          -- Inserimos os valores a serem cobrados nesta parcela
          -- Lançamos esta mensalidade nos registros de valores cobrados
          -- para esta instalação
          RAISE NOTICE 'Adicionando lançamento na fatura';
          RAISE NOTICE 'contractID: %', billing->>'contractID';
          RAISE NOTICE 'installationID: %', billing->>'installationID';
          RAISE NOTICE 'billingDate: %', (billing->>'endDateOfPeriod')::Date;
          RAISE NOTICE 'name: %', (billing->>'name')::text;
          RAISE NOTICE 'value: %', (billing->>'value')::numeric;
          INSERT INTO erp.billings (contractorID, contractID,
                 installationID, billingDate, name, value, invoiceID,
                 invoiced, addMonthlyAutomatic, isMonthlyPayment,
                 createdByUserID, updatedByUserID)
          VALUES (FcontractorID, (billing->>'contractID')::int,
                  (billing->>'installationID')::int,
                  (billing->>'endDateOfPeriod')::Date,
                  (billing->>'name')::text,
                  (billing->>'value')::numeric, newInvoiceID, TRUE,
                  TRUE, TRUE, FuserID, FuserID);
        END LOOP; -- Billing

        -- Adicionamos cada período cobrado
        FOR billed IN
          SELECT * FROM jsonb_array_elements(billet->'billeds')
        LOOP
          -- Lançamos o período cobrado
          RAISE NOTICE 'Adicionando período cobrado';
          RAISE NOTICE 'startDateOfPeriod: %', (billed->>'startDateOfPeriod')::Date;
          RAISE NOTICE 'endDateOfPeriod: %', (billed->>'endDateOfPeriod')::Date;
          RAISE NOTICE 'monthPrice: %', billed->>'monthPrice';
          RAISE NOTICE 'monthlyValue: %', billed->>'monthlyValue';
          RAISE NOTICE 'discountValue: %', billed->>'discountValue';
          RAISE NOTICE 'finalValue: %', billed->>'finalValue';
          INSERT INTO erp.billedPeriods (contractorID, installationID,
                 invoiceID, referenceMonthYear, startDate, endDate,
                 monthPrice, grossvalue, discountValue, finalValue)
          VALUES (FcontractorID, (billed->>'installationID')::int,
                 newInvoiceID, billet->>'referenceMonth',
                 (billed->>'startDateOfPeriod')::Date,
                 (billed->>'endDateOfPeriod')::Date,
                 (billed->>'monthPrice')::numeric,
                 (billed->>'monthlyValue')::numeric,
                 (billed->>'discountValue')::numeric,
                 (billed->>'finalValue')::numeric);
        END LOOP; -- Billing

        -- Por último, determinamos os valores totais desta fatura com
        -- base nos valores calculados
        UPDATE erp.invoices
           SET invoiceValue = ROUND(billings.total, 2)
          FROM (
            SELECT invoiceID,
                   SUM(value) as total
              FROM erp.billings
             WHERE invoiceID = newInvoiceID
            GROUP BY invoiceID
           ) AS billings
         WHERE invoices.invoiceID = billings.invoiceID
           AND invoices.contractorID = FcontractorID;
      END LOOP; -- Billet

      -- Lançamos os valores de cada fatura gerada para este carnê para
      -- cobrança
      FOR invoice IN
        SELECT I.contractorID,
               I.invoiceID,
               I.dueDate,
               I.invoiceValue,
               I.paymentMethodID,
               I.definedMethodID,
               A.bankID,
               A.agencyNumber,
               A.accountNumber,
               A.wallet
          FROM erp.invoices AS I
         INNER JOIN erp.definedMethods AS D USING (definedMethodID)
         INNER JOIN erp.accounts AS A USING (accountID)
         WHERE I.carnetID = newCarnetID
      LOOP
        -- Atualizamos o contador de boletos emitidos
        UPDATE erp.definedMethods
           SET billingCounter = billingCounter + 1 
         WHERE definedMethodID = 1
        RETURNING billingCounter INTO FbillingCounter;

        -- Determinamos o número de identificação do boleto no banco
        ourNumber := erp.buildBankIdentificationNumber(invoice.bankID,
          invoice.agencyNumber, invoice.accountNumber, invoice.wallet,
          FbillingCounter, invoice.invoiceID, Fparameters);

        -- Determinamos a situação do boleto
        IF (invoice.invoiceValue > 0) THEN
          paymentSituationID := 1;
          droppedTypeID := 1;
        ELSE
          paymentSituationID := 2;
          droppedTypeID := 4;
        END IF;

        -- Inserimos o boleto para cobrança
        INSERT INTO erp.bankingBilletPayments (contractorID, invoiceID,
               dueDate, valueToPay, paymentMethodID, paymentSituationID,
               definedMethodID, bankCode, agencyNumber, accountNumber,
               wallet, billingCounter, parameters, ourNumber, fineValue,
               arrearInterestType, arrearInterest, instructionID,
               instructionDays, droppedTypeID)
        VALUES (invoice.contractorID, invoice.invoiceID, invoice.dueDate,
               invoice.invoiceValue, invoice.paymentMethodID,
               paymentSituationID, invoice.definedMethodID, invoice.bankID,
               invoice.agencyNumber, invoice.accountNumber, invoice.wallet,
               FbillingCounter, Fparameters, ourNumber, FfineValue,
               FarrearInterestType, FarrearInterest, FinstructionID,
               FinstructionDays, droppedTypeID);
      END LOOP; -- Invoice
    ELSE
      -- RAISE NOTICE 'Não temos parcelas a serem cobradas';
      RETURN null;
    END IF;

    -- Indica que tudo deu certo, retornando o número do carnê
    RETURN newCarnetID;
  END IF;

  RETURN null;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Gera uma cobrança antecipada
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.createPrepayment(FcontractorID integer,
  FcustomerID int, FsubsidiaryID int, FstartDate date, FnumberOfParcels int,
  FdueDate date, FvalueToPay numeric(12,2),
  Finstallations integer array, FpaymentConditionID integer,
  FpaymentMethodID integer, FdefinedMethodID integer, FuserID integer)
RETURNS integer AS
$$
DECLARE
  -- Os parâmetros para cálculo de cada mensalidade e do valor total a
  -- ser cobrado
  parcelNumber  int;
  referenceDate  date;
  startDateOfPeriod  date;
  endDateOfPeriod  date;
  startDateOfBillingPeriod  date;
  monthlyValue  numeric;
  discountTotal  numeric;
  finalValue  numeric;
  daysToConsider  smallint;

  -- O cálculo do valor de mensalidade por dia
  daysInPeriod  smallint;
  dayPrice  numeric;

  -- A análise de subsídios aplicados
  subsidyRecord  record;
  startOfSubsidy  date;
  endOfSubsidy  date;
  daysInSubsidy  smallint;
  discountValue  numeric;

  -- O ID do último contrato
  lastContractID  integer;

  -- Os dados da instalação e de valores a serem cobrados
  installation  record;

  -- A análise de outras mensalidades presentes em contrato
  monthlyFeesRecord  record;

  -- O ID da fatura
  newInvoiceID  integer;

  -- Os dados das parcelas a serem cobradas
  parcels  jsonb[];
  parcel  jsonb;
  billing  jsonb;
  billings  jsonb[];
  billed  jsonb;
  billeds  jsonb[];

  -- Os dados da fatura
  invoice  record;
  FdefinedMethod  varchar;

  -- Parâmetros da cobrança a ser gerada
  paymentSituationID  integer;
  droppedTypeID  integer;
BEGIN
  -- Inicializamos as variáveis de processamento
  parcelNumber := 1;
  referenceDate := FstartDate;
  lastContractID := 0;
  parcels := jsonb '{ }';

  IF (FnumberOfParcels > 0) THEN
    LOOP
      -- Estamos processando cada período da cobrança antecipada, então
      -- precisamos analisar os períodos sendo cobrados e construir o
      -- valor a ser cobrado baseado nos valores computados a cada mês
      -- em cada item de contrato informado, montando as respectivas
      -- parcelas
      -- RAISE NOTICE 'Número da parcela: %', parcelNumber;
      -- RAISE NOTICE 'Data de referência: %', TO_CHAR(referenceDate, 'DD/MM/YYYY');

      -- Inicializamos o registro de valores desta parcela
      parcel := format(
        '{"parcel":%s,"referenceMonth":"%s","billings":[],"billeds":[]}',
        parcelNumber,
        to_char(referenceDate, 'MM/YYYY')
      )::jsonb;
      billings := jsonb '{ }';
      billeds := jsonb '{ }';

      -- Recupera as informações das instalações para as quais estamos
      -- emitindo a cobrança
      FOR installation IN
        SELECT I.installationID AS id,
               I.installationNumber AS number,
               C.signatureDate,
               C.startTermAfterInstallation,
               P.prorata,
               I.startDate,
               I.monthprice,
               I.contractID,
               I.lastDayOfBillingPeriod
          FROM erp.installations AS I
         INNER JOIN erp.contracts AS C ON (I.contractID = C.contractID)
         INNER JOIN erp.plans AS P ON (P.planID = I.planID)
         WHERE I.contractorID = FcontractorID
           AND C.deleted = false
           AND C.active = true
           AND I.endDate IS NULL
           AND I.installationID = ANY(Finstallations)
         ORDER BY C.customerPayerid, C.subsidiaryPayerid, C.unifybilling, C.contractID, I.installationid
      LOOP
        -- Para cada parcela sendo calculada, analisamos se devemos ou
        -- não cobrar o período para cada um dos itens de contrato
        -- informados, de forma que conseguimos construir o valor final
        -- desta parcela corretamente
        -- RAISE NOTICE 'Número do item de contrato: %', installation.number;
        -- RAISE NOTICE 'Data da assinatura do contrato: %', TO_CHAR(installation.signatureDate, 'DD/MM/YYYY');
        -- RAISE NOTICE 'Data do início do item de contrato: %', TO_CHAR(installation.startDate, 'DD/MM/YYYY');
        -- RAISE NOTICE 'Iniciar cobrança após instalação: %', CASE WHEN installation.startTermAfterInstallation THEN 'Sim' ELSE 'Não' END;
        -- RAISE NOTICE 'Cobrar proporcional ao serviço prestado: %', CASE WHEN installation.prorata THEN 'Sim' ELSE 'Não' END;

        -- Determinamos o período de cobrança em um mês
        startDateOfPeriod := referenceDate;
        endDateOfPeriod := startDateOfPeriod + interval '1 month' - interval '1 day';
        -- RAISE NOTICE 'Período de % à %', startDateOfPeriod, endDateOfPeriod;

        -- Determinamos à partir de qual data devemos cobrar
        IF (installation.prorata) THEN
          -- Devemos cobrar proporcionalmente, então determinamos quando
          -- isto ocorre
          IF (installation.startTermAfterInstallation) THEN
            IF (installation.startDate IS NULL) THEN
              -- Como a instalação não ocorreu ainda, então consideramos
              -- o início do período mesmo
              -- RAISE NOTICE 'Instalação não ocorreu, considerando o período inteiro';
              startDateOfBillingPeriod := startDateOfPeriod;
            ELSE
              -- Verificamos se o início do item de contrato ocorreu
              -- durante o período que estamos analisado
              IF (installation.startDate >= startDateOfPeriod) THEN
                -- Consideramos a data de instalação
                -- RAISE NOTICE 'Consideramos a data de instalação';
                startDateOfBillingPeriod := installation.startDate;
              ELSE
                -- Como a instalação se deu antes do início do período que
                -- iremos cobrar, então consideramos o início do período
                -- mesmo
                -- RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          ELSE
            IF (installation.signatureDate IS NULL) THEN
              -- Como o contrato não foi assinado ainda, então consideramos
              -- o início do período mesmo
              -- RAISE NOTICE 'Contrato não foi assinado, considerando o período inteiro';
              startDateOfBillingPeriod := baseDate;
            ELSE
              -- Verificamos se a assintatura ocorreu durante o período
              -- sendo analisado
              IF (installation.signatureDate >= startDateOfPeriod) THEN
                -- Consideramos a data de assinatura
                -- RAISE NOTICE 'Consideramos a data de assinatura do contrato';
                startDateOfBillingPeriod := installation.signatureDate;
              ELSE
                -- Como a assinatura do contrato se deu antes do início do
                -- período que iremos cobrar, então consideramos o início
                -- do período mesmo
                -- RAISE NOTICE 'Consideramos o período inteiro';
                startDateOfBillingPeriod := startDateOfPeriod;
              END IF;
            END IF;
          END IF;
        ELSE
          -- Devemos cobrar integralmente, então o início se dá sempre no
          -- início do período apurado
          -- RAISE NOTICE 'Consideramos o período inteiro';
          startDateOfBillingPeriod := startDateOfPeriod;
        END IF;

        -- Verificamos se já foram realizadas cobranças de períodos
        -- neste item de contrato
        IF (installation.lastDayOfBillingPeriod IS NOT NULL) THEN
          -- Precisamos levar em consideração também o último período já
          -- cobrado se ele for superior ao período que estamos cobrando
          IF ((installation.lastDayOfBillingPeriod + interval '1 day') > startDateOfBillingPeriod) THEN
            startDateOfBillingPeriod := installation.lastDayOfBillingPeriod + interval '1 day';
            -- RAISE NOTICE 'Consideramos o período iniciando em %', startDateOfBillingPeriod;
          END IF;
        END IF;

        -- Calculamos a quantidade de dias no período
        daysInPeriod := DATE_PART('day',
            endDateOfPeriod::timestamp - startDateOfPeriod::timestamp
          ) + 1;
        -- RAISE NOTICE 'Este período possui % dias', daysInPeriod;

        -- Calculamos o valor diário com base na mensalidade
        dayPrice = installation.monthPrice / daysInPeriod;
        -- RAISE NOTICE 'O valor diário é %', ROUND(dayPrice, 2);

        -- Verificamos se precisamos cobrar algum período nesta parcela
        -- para esta instalação
        IF (startDateOfBillingPeriod <= endDateOfPeriod) THEN
          IF (installation.prorata) THEN
            IF (startDateOfBillingPeriod = startDateOfPeriod) THEN
              -- Cobramos o valor integral da mensalidade
              monthlyValue := installation.monthPrice;
              -- RAISE NOTICE 'Cobrando valor integral da mensalidade';
            ELSE
              -- Cobramos o valor proporcional

              -- Calculamos a quantidade de dias a serem cobrados
              daysToConsider := DATE_PART('day',
                  endDateOfPeriod::timestamp - startDateOfBillingPeriod::timestamp
                ) + 1;
              
              -- O serviço será prestado por uma parte do mês
              monthlyValue := ROUND(daysToConsider * dayPrice, 2);
            END IF;
          ELSE
            -- Cobramos sempre o valor integral da mensalidade
            monthlyValue := installation.monthPrice;
            -- RAISE NOTICE 'Cobrando valor integral da mensalidade';
          END IF;

          IF (monthlyValue > 0.00) THEN
            -- Acrescentamos esta valor a ser cobrado nesta mensalidade
            -- RAISE NOTICE 'O valor da mensalidade calculada é %', ROUND(monthlyValue, 2);
            billings := billings || Array[
              format(
                '{"contractID":%s,"installationID":%s,"name":"%s","value":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                installation.contractid,
                installation.id,
                format(
                  'Mensalidade de %s à %s',
                  TO_CHAR(startDateOfBillingPeriod, 'DD/MM/YYYY'),
                  TO_CHAR(endDateOfPeriod, 'DD/MM/YYYY')
                ),
                monthlyValue,
                startDateOfBillingPeriod,
                endDateOfPeriod
              )::jsonb
            ];

            -- Agora analisamos quaisquer subsídios ou bonificações
            -- existentes de forma a concedermos os respectivos
            -- descontos, se pertinente
            discountTotal := 0;
            FOR subsidyRecord IN
              SELECT performedSubsidies.subsidyID AS ID,
                     performedSubsidies.bonus,
                     performedSubsidies.performedPeriod,
                     performedSubsidies.subsidyPeriod * performedSubsidies.performedPeriod AS subsidedPeriod,
                     performedSubsidies.discountType,
                     performedSubsidies.discountValue
                FROM (
                  SELECT S.subsidyID,
                         S.bonus,
                         public.intervalOfPeriod(startDateOfBillingPeriod, endDateOfPeriod) AS performedPeriod,
                         ('[' || S.periodStartedAt || ',' || COALESCE(S.periodEndedAt || ']', ')'))::public.intervalOfPeriod AS subsidyPeriod,
                         S.discountType,
                         S.discountValue
                    FROM erp.subsidies AS S
                   WHERE S.installationID = installation.id
                     AND (
                       (S.periodEndedAt IS NULL AND S.periodStartedAt <= endDateOfPeriod) OR
                       (S.periodEndedAt >= startDateOfBillingPeriod)
                      )
                   ORDER BY S.bonus DESC, S.periodStartedAt
                  ) AS performedSubsidies
            LOOP
              -- Calculamos os valores deste desconto
              startOfSubsidy := lower(subsidyRecord.subsidedPeriod);
              endOfSubsidy   := upper(subsidyRecord.subsidedPeriod);
              IF (startOfSubsidy IS NOT NULL) THEN
                daysInSubsidy  := DATE_PART('day',
                    endOfSubsidy::timestamp - startOfSubsidy::timestamp
                  ) + 1;
                -- RAISE NOTICE 'Período com % dias', daysInSubsidy;
                IF subsidyRecord.bonus THEN
                  -- Aplicamos 100% de desconto no período
                  IF (daysInSubsidy = daysInPeriod) THEN
                    -- O desconto foi concedido pelo mês inteiro
                    discountValue := monthPrice;
                  ELSE
                    -- O desconto foi concedido por uma parte do mês
                    discountValue := ROUND(daysInSubsidy * dayPrice, 2);
                  END IF;
                ELSE
                  -- Precisamos calcular o desconto em função do período
                  IF (subsidyRecord.discountType = 1) THEN
                    -- O desconto é um valor fixo em reais por dia
                    discountValue :=
                      ROUND(daysInSubsidy * subsidyRecord.discountValue, 2)
                    ;
                  ELSE
                    -- O desconto é uma porcentagem do valor do período
                    discountValue :=
                      ROUND(
                        (
                          (daysInSubsidy * dayPrice) *
                          subsidyRecord.discountValue / 100
                        ),
                        2
                      )
                    ;
                  END IF;
                END IF;

                -- RAISE NOTICE 'Adicionando desconto no item de contrato';
                billings := billings || Array[
                  format(
                    '{"contractID":%s,"installationID":%s,"monthPrice":%s,"name":"%s","value":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                    installation.contractid,
                    installation.id,
                    installation.monthprice,
                    format(
                      'Desconto de %s à %s',
                      TO_CHAR(startOfSubsidy, 'DD/MM/YYYY'),
                      TO_CHAR(endOfSubsidy, 'DD/MM/YYYY')
                    ),
                    ROUND((0 - discountValue), 2),
                    startDateOfBillingPeriod,
                    endDateOfPeriod
                  )::jsonb
                ];

                discountTotal := discountTotal + discountValue;
              END IF;
            END LOOP;

            -- Calculamos o valor final
            finalValue := monthlyValue - discountTotal;
            IF (finalValue < 0.00) THEN
              finalValue := 0.00;
            END IF;

            billeds := billeds || Array[
              format(
                '{"contractID":%s,"installationID":%s,"monthPrice":%s,"monthlyValue":%s,"discountValue":%s,"finalValue":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                installation.contractid,
                installation.id,
                installation.monthprice,
                monthlyValue,
                discountTotal,
                finalValue,
                startDateOfBillingPeriod,
                endDateOfPeriod
              )::jsonb
            ];

            -- Inicializamos o período cobrado
            billed := format(
              '{"contractID":%s,"installationID":%s,"monthPrice":%s,"monthlyValue":%s,"discountValue":0.00,"finalValue":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
              installation.contractid,
              installation.id,
              installation.monthprice,
              monthlyValue,
              monthlyValue,
              startDateOfBillingPeriod,
              endDateOfPeriod
            )::jsonb;

            -- Agora analisamos quaisquer outros valores presentes no
            -- contrato e que precisam ser computados
            FOR monthlyFeesRecord IN
              SELECT B.name,
                     C.chargeValue AS value
                FROM erp.contractCharges AS C
               INNER JOIN erp.billingTypes AS B USING (billingTypeID)
               WHERE C.contractID = installation.contractid
                 AND B.billingMoments @> array[5]
                 AND B.inAttendance = false
                 AND B.ratePerEquipment = true
            LOOP
              -- RAISE NOTICE 'Adicionando a cobrança de % do item de contrato para ser cobrada', monthlyFeesRecord.name;
              billings := billings || Array[
                format(
                  '{"contractID":%s,"installationID":%s,"monthPrice":%s,"name":"%s","value":%s,"startDateOfPeriod":"%s","endDateOfPeriod":"%s"}',
                  installation.contractid,
                  installation.id,
                  installation.monthprice,
                  monthlyFeesRecord.name,
                  monthlyFeesRecord.value,
                  startDateOfBillingPeriod,
                  endDateOfPeriod
                )::jsonb
              ];
            END LOOP;
          END IF;
        END IF;
      END LOOP;

      -- Adicionamos os valores a serem cobrados
      IF (array_length(billings, 1) > 0) THEN
        -- RAISE NOTICE 'Temos % itens a serem cobrados nesta parcela', array_length(billings, 1);
        -- RAISE NOTICE 'billings: %', billings;

        -- Adicionamos os valores a serem cobrados nesta parcela
        parcel := jsonb_set(
          parcel, '{ billings }', to_jsonb(billings), true
        );
        parcel := jsonb_set(
          parcel, '{ billeds }', to_jsonb(billeds), true
        );
        -- RAISE NOTICE 'Adicionando a parcela: %', parcel;

        -- Adicionamos a parcela à cobrança
        parcels := parcels || parcel;
      -- ELSE
      --   RAISE NOTICE 'Não temos itens a serem cobrados nesta parcela';
      END IF;
      
      -- Incrementamos a quantidade de parcelas que fizemos
      parcelNumber := parcelNumber + 1;

      -- Avançamos para o próximo mês
      referenceDate := referenceDate + interval '1 month';

      -- Repetimos este processo até determinar parcela seja superior a
      -- quantidade de parcelas a serem emitidas
      EXIT WHEN parcelNumber > FnumberOfParcels;
    END LOOP;

    IF (array_length(parcels, 1) > 0) THEN
      -- RAISE NOTICE 'Processando as parcelas';

      -- Precisamos criar uma nova fatura única que irá englobar todas
      -- as parcelas sendo cobradas
      -- RAISE NOTICE 'dueDate: %', FdueDate;
      -- RAISE NOTICE 'valueToPay: %', FvalueToPay;

      IF (FdefinedMethodID = 0) THEN
        FdefinedMethodID := NULL;
      END IF;
      INSERT INTO erp.invoices (contractorID, customerID, subsidiaryID,
                  invoiceDate, dueDate, paymentMethodID,
                  definedMethodID)
           VALUES (FcontractorID, FcustomerID, FsubsidiaryID,
                  CURRENT_DATE, FdueDate, FpaymentMethodID,
                  FdefinedMethodID)
      RETURNING invoiceID INTO newInvoiceID;

      FOREACH parcel IN ARRAY parcels
      LOOP
        -- Fazemos a inserção das parcelas no banco de dados
        -- RAISE NOTICE 'Parcela: %', parcel->>'parcel';
        -- RAISE NOTICE 'referenceMonthYear: %', parcel->>'referenceMonth';

        -- Adicionamos cada valor cobrado
        FOR billing IN
          SELECT * FROM jsonb_array_elements(parcel->'billings')
        LOOP
          -- Inserimos os valores a serem cobrados nesta parcela
          -- Lançamos esta mensalidade nos registros de valores cobrados
          -- para esta instalação
          -- RAISE NOTICE 'Adicionando lançamento na fatura';
          -- RAISE NOTICE 'contractID: %', billing->>'contractID';
          -- RAISE NOTICE 'installationID: %', billing->>'installationID';
          -- RAISE NOTICE 'billingDate: %', (billing->>'endDateOfPeriod')::Date;
          -- RAISE NOTICE 'name: %', (billing->>'name')::text;
          -- RAISE NOTICE 'value: %', (billing->>'value')::numeric;
          INSERT INTO erp.billings (contractorID, contractID,
                 installationID, billingDate, name, value, invoiceID,
                 invoiced, addMonthlyAutomatic, isMonthlyPayment,
                 createdByUserID, updatedByUserID)
          VALUES (FcontractorID, (billing->>'contractID')::int,
                  (billing->>'installationID')::int,
                  (billing->>'endDateOfPeriod')::Date,
                  (billing->>'name')::text,
                  (billing->>'value')::numeric, newInvoiceID, TRUE,
                  TRUE, TRUE, FuserID, FuserID);
        END LOOP; -- Billing

        -- Adicionamos cada período cobrado
        FOR billed IN
          SELECT * FROM jsonb_array_elements(parcel->'billeds')
        LOOP
          -- Lançamos o período cobrado
          -- RAISE NOTICE 'Adicionando período cobrado';
          -- RAISE NOTICE 'startDateOfPeriod: %', (billed->>'startDateOfPeriod')::Date;
          -- RAISE NOTICE 'endDateOfPeriod: %', (billed->>'endDateOfPeriod')::Date;
          -- RAISE NOTICE 'monthPrice: %', billed->>'monthPrice';
          -- RAISE NOTICE 'monthlyValue: %', billed->>'monthlyValue';
          -- RAISE NOTICE 'discountValue: %', billed->>'discountValue';
          -- RAISE NOTICE 'finalValue: %', billed->>'finalValue';
          INSERT INTO erp.billedPeriods (contractorID, installationID,
                 invoiceID, referenceMonthYear, startDate, endDate,
                 monthPrice, grossvalue, discountValue, finalValue)
          VALUES (FcontractorID, (billed->>'installationID')::int,
                 newInvoiceID, parcel->>'referenceMonth',
                 (billed->>'startDateOfPeriod')::Date,
                 (billed->>'endDateOfPeriod')::Date,
                 (billed->>'monthPrice')::numeric,
                 (billed->>'monthlyValue')::numeric,
                 (billed->>'discountValue')::numeric,
                 (billed->>'finalValue')::numeric);
        END LOOP; -- Billing

        -- Por último, determinamos os valores totais desta fatura com
        -- base nos valores calculados
        UPDATE erp.invoices
           SET invoiceValue = ROUND(billings.total, 2)
          FROM (
            SELECT invoiceID,
                   SUM(value) as total
              FROM erp.billings
             WHERE invoiceID = newInvoiceID
            GROUP BY invoiceID
           ) AS billings
         WHERE invoices.invoiceID = billings.invoiceID
           AND invoices.contractorID = FcontractorID;
      END LOOP; -- Parcels

      -- Lançamos os valores da fatura gerada para cobrança
      FOR invoice IN
        SELECT I.contractorID,
               I.invoiceID,
               I.dueDate,
               I.invoiceValue,
               I.paymentMethodID
          FROM erp.invoices AS I
         WHERE I.invoiceID = newInvoiceID
      LOOP
        -- Inserimos a fatura para cobrança
        INSERT INTO erp.payments (contractorID, invoiceID, dueDate,
               valueToPay, paymentMethodID, paymentSituationID)
        VALUES (invoice.contractorID, invoice.invoiceID, invoice.dueDate,
               FvalueToPay, invoice.paymentMethodID, 1);
      END LOOP; -- Invoice
    ELSE
      -- RAISE NOTICE 'Não temos valores a serem cobrados';
      RETURN null;
    END IF;

    -- Indica que tudo deu certo, retornando o número da cobrança
    RETURN newInvoiceID;
  ELSE
    RETURN null;
  END IF;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Dados de prestadores de serviços
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de prestadores
-- de serviços e de seus respectivos técnicos.
-- ---------------------------------------------------------------------
CREATE TYPE erp.serviceProviderData AS
(
  entityID                 integer,
  subsidiaryID             integer,
  technicianID             integer,
  juridicalperson          boolean,
  technicianIsTheProvider  boolean,
  level                    smallint,
  active                   boolean,
  activeTechnician         boolean,
  name                     varchar(100),
  tradingName              varchar(100),
  blocked                  boolean,
  cityID                   integer,
  cityName                 varchar(50),
  occupationArea           text,
  phones                   text,
  nationalregister         varchar(18),
  blockedLevel             smallint,
  createdAt                timestamp,
  updatedAt                timestamp,
  fullcount                integer
);

CREATE OR REPLACE FUNCTION erp.getServiceProvidersData(
  FcontractorID integer, FserviceProviderID integer,
  FsearchValue varchar(100), FsearchField varchar(20), Forder varchar,
  Fstatus integer, Skip integer, LimitOf integer)
RETURNS SETOF erp.serviceProviderData AS
$$
DECLARE
  entityData  erp.serviceProviderData%rowtype;
  row  record;
  query  varchar;
  field  varchar;
  filter  varchar;
  typeFilter  varchar;
  limits  varchar;
  blockedLevel  smallint;
  lastServiceProviderID  integer;
  rowCount  int;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID := 0;
  END IF;
  IF (FserviceProviderID IS NULL) THEN
    FserviceProviderID := 0;
  END IF;
  IF (Fstatus IS NULL) THEN
    Fstatus := 0;
  END IF;
  IF (Forder IS NULL) THEN
    Forder := 'name, technicianIsTheProvider DESC, technicianname NULLS FIRST';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;

  -- Lida com o estado. Os estados possíveis são:
  --   1: inativo
  --   2: ativo
  typeFilter := '(1 = 1)';
  IF (Fstatus > 0) THEN
    IF (Fstatus = 1) THEN
      typeFilter := ' AND (numberOfActiveTechnicians = 0)';
    ELSE
      typeFilter := ' AND (numberOfActiveTechnicians > 0)';
    END IF;
  END IF;

  -- Lida com o ID do contratante
  IF (FcontractorID > 0) THEN
    -- Realiza a filtragem pelo contratante
    filter := format(' AND entity.contractorID = %s',
                     FcontractorID);
  END IF;

  -- Lida com o ID do prestador de serviços
  IF (FserviceProviderID > 0) THEN
    -- Realiza a filtragem pelo prestador de serviços
    filter := filter || format(' AND entity.entityID = %s', FserviceProviderID);
  END IF;

  -- Lida com o campo de pesquisa
  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      -- RAISE NOTICE 'FsearchValue IS NOT NULL';

      -- Determina o campo onde será realizada a pesquisa
      CASE (FsearchField)
        WHEN 'name' THEN
          filter := filter || ' AND (' ||
            format('public.unaccented(entity.name) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ' OR ' ||
            format('public.unaccented(entity.tradingName) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ' OR ' ||
            format('public.unaccented(unity.name) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ' OR ' ||
            format('public.unaccented(technician.name) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ')'
          ;
        WHEN 'nationalregister' THEN
          filter := filter || ' AND (' ||
            format('(regexp_replace(unity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%'')',
                   regexp_replace(FsearchValue, '\D*', '', 'g')) ||
            ' OR ' ||
            format('(regexp_replace(technician.cpf, ''\D*'', '''', ''g'') ILIKE ''%%%s%%'')',
                   regexp_replace(FsearchValue, '\D*', '', 'g')) ||
            ')'
          ;
        ELSE
          -- Monta o filtro
          field := 'entity.' || FsearchField;
          filter := filter || ' AND ' ||
            format('public.unaccented(%s) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   field, FsearchValue);
      END CASE;
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  -- Monta a consulta
  query := format('WITH items AS (
                     SELECT entity.entityID,
                            entity.name,
                            entity.tradingName,
                            entity.entityTypeID,
                            type.name AS entityTypeName,
                            type.juridicalperson AS juridicalperson,
                            technician.technicianID,
                            technician.name AS technicianName,
                            technician.technicianIsTheProvider,
                            unity.subsidiaryID,
                            unity.cityID,
                            city.name AS cityName,
                            complement.occupationArea,
                            technician.cityID AS technicianCityID,
                            technicianCity.name AS technicianCityName,
                            unity.nationalRegister,
                            technician.cpf AS technicianCPF,
                            entity.blocked AS entityBlocked,
                            unity.blocked AS subsidiaryBlocked,
                            technician.blocked AS technicianBlocked,
                            entity.createdAt,
                            entity.updatedAt,
                            technician.createdAt AS technicianCreatedAt,
                            technician.updatedAt AS technicianUpdatedAt,
                            (
                              SELECT count(*)
                                FROM erp.technicians AS T
                               WHERE T.serviceproviderid = entity.entityID
                            ) AS numberOfTechnicians,
                            (
                              SELECT count(*)
                                FROM erp.technicians AS T
                               WHERE T.serviceproviderid = entity.entityID
                                 AND T.blocked = false
                            ) AS numberOfActiveTechnicians,
                            count(*) OVER(partition by entity.entityid) AS entityItems
                       FROM erp.entities AS entity
                      INNER JOIN erp.entitiesTypes AS type ON (entity.entityTypeID = type.entityTypeID)
                      INNER JOIN erp.subsidiaries AS unity ON (entity.entityID = unity.entityID AND unity.headOffice = true)
                      INNER JOIN erp.cities AS city ON (unity.cityID = city.cityID)
                      INNER JOIN erp.serviceProviders AS complement ON (entity.entityID = complement.serviceProviderID)
                       LEFT JOIN erp.technicians AS technician ON (entity.entityID = technician.serviceProviderID)
                       LEFT JOIN erp.cities AS technicianCity ON (technician.cityID = technicianCity.cityID)
                      WHERE entity.serviceProvider = true
                        AND entity.deleted = false
                        AND unity.deleted = false %s
                   )
                    SELECT *,
                           (numberOfActiveTechnicians > 0) AS active,
                           count(*) OVER() AS fullcount
                      FROM items
                     WHERE %s
                      ORDER BY %s %s',
                  filter, typeFilter,
                  Forder, limits);
  -- RAISE NOTICE 'SQL: %',query;

  lastServiceProviderID := 0;

  FOR row IN EXECUTE query
  LOOP
    -- RAISE NOTICE 'lastServiceProviderID: %', lastServiceProviderID;
    -- RAISE NOTICE 'entityID: %', row.entityID;
    -- RAISE NOTICE 'entity name: %', row.name;
    -- RAISE NOTICE 'entityItems: %', row.entityItems;
    -- RAISE NOTICE 'technicianID: %', row.technicianID;
    -- RAISE NOTICE 'technicianName: %', row.technicianName;

    IF (lastServiceProviderID <> row.entityID) THEN
      -- Iniciamos um novo grupo
      -- RAISE NOTICE 'Identificado um novo prestador de serviços com ID %', row.entityID;
      lastServiceProviderID := row.entityID;

      -- Indicamos que ainda não foi adicionada nenhuma linha
      rowCount := 0;

      -- Verifica se precisamos dividir esta entidade em mais de uma
      -- linha, criando uma linha de agrupamento
      IF ( (row.juridicalperson = TRUE) AND (row.technicianID IS NOT NULL) ) THEN
        -- RAISE NOTICE 'Adicionando uma linha para informar o prestador';
        -- Descrevemos aqui o prestador de serviços
        entityData.entityID                  := row.entityID;
        entityData.subsidiaryID              := row.subsidiaryID;
        entityData.technicianID              := 0;
        entityData.juridicalperson           := row.juridicalperson;
        entityData.level                     := 0;
        entityData.active                    := row.active;
        IF (row.juridicalperson) THEN
          entityData.technicianIsTheProvider := false;
        ELSE
          entityData.technicianIsTheProvider := row.technicianIsTheProvider;
        END IF;
        entityData.name                      := row.name;
        entityData.tradingName               := row.tradingName;
        entityData.blocked                   := row.entityBlocked;
        entityData.cityID                    := row.cityID;
        entityData.cityName                  := row.cityName;
        entityData.occupationArea            := row.occupationArea;
        entityData.nationalregister          := row.nationalRegister;
        IF (row.entityBlocked) THEN
          entityData.blockedLevel            := 1;
        ELSE
          entityData.blockedLevel            := 0;
        END IF;
        entityData.createdAt                 := row.createdAt;
        entityData.updatedAt                 := row.updatedAt;
        entityData.fullcount                 := row.fullcount;
        rowCount := 1;

        RETURN NEXT entityData;
      END IF;
    END IF;

    entityData.entityID                  := row.entityID;
    entityData.subsidiaryID              := row.subsidiaryID;
    entityData.technicianID              := row.technicianID;
    entityData.juridicalperson           := row.juridicalperson;
    IF (row.juridicalperson) THEN
      entityData.technicianIsTheProvider := false;
    ELSE
      entityData.technicianIsTheProvider := row.technicianIsTheProvider;
    END IF;
    entityData.active                    := row.active;
    IF ( rowCount > 0 ) THEN
      -- RAISE NOTICE 'Adicionando o técnico %', row.technicianName;
      entityData.level                   := 1;
      entityData.name                    := row.technicianName;
      entityData.tradingName             := '';
      entityData.blocked                 := row.technicianBlocked;
      entityData.cityID                  := row.technicianCityID;
      entityData.cityName                := row.technicianCityName;
      entityData.occupationArea          := '';
      entityData.nationalregister        := row.technicianCPF;
      IF (row.entityBlocked) THEN
        entityData.blockedLevel          := 1;
      ELSE
        IF (row.technicianBlocked) THEN
          entityData.blockedLevel        := 2;
        ELSE
          entityData.blockedLevel        := 0;
        END IF;
      END IF;
      entityData.createdAt               := row.technicianCreatedAt;
      entityData.updatedAt               := row.technicianUpdatedAt;
    ELSE
      -- RAISE NOTICE 'Adicionando o prestador %', row.name;
      entityData.level                   := 0;
      entityData.name                    := row.name;
      entityData.tradingName             := row.tradingName;
      entityData.blocked                 := row.entityBlocked;
      entityData.cityID                  := row.cityID;
      entityData.cityName                := row.cityName;
      entityData.occupationArea          := row.occupationArea;
      entityData.nationalregister        := row.nationalRegister;
      IF (row.entityBlocked) THEN
        entityData.blockedLevel          := 1;
      ELSE
        entityData.blockedLevel          := 0;
      END IF;
      entityData.createdAt               := row.createdAt;
      entityData.updatedAt               := row.updatedAt;
    END IF;
    entityData.fullcount                 := row.fullcount;
    rowCount := rowCount + 1;

    RETURN NEXT entityData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Dados de telefones do técnico
-- ---------------------------------------------------------------------
-- Função que recupera os telefones de um técnico em formato JSON
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getTechnicianPhones(FtechnicianID integer)
  RETURNS text AS
$$
DECLARE
  subsidiaryFilter  varchar;
  query  varchar;
  address  record;
  phones  varchar[];
BEGIN
  -- Selecionamos os telefones do técnico
  query := format('
    SELECT phonenumber
      FROM erp.technicianPhones
     WHERE technicianID = %s
     ORDER BY technicianPhoneid;',
     FtechnicianID
  );
  FOR address IN EXECUTE query
  LOOP
    -- Adicionamos o número de telefone a nossa relação de telefones
    -- RAISE NOTICE 'Telefone: %', address.phonenumber;
    phones := phones || Array[address.phonenumber];
  END LOOP;
  
  RETURN array_to_string(phones, ' / ');
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Dados de telefones de acordo com o perfil
-- ---------------------------------------------------------------------
-- Função que recupera os telefones de acordo com um perfil a ser usado
-- em formato JSON
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getPhones(FcontractorID integer,
  FentityID integer, FsubsidiaryID integer, FsystemActionID integer)
  RETURNS text AS
$$
DECLARE
  subsidiaryFilter  varchar;
  query  varchar;
  address  record;
  phones  varchar[];
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FentityID IS NULL) THEN
    FentityID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FsystemActionID IS NULL) THEN
    FsystemActionID = 0;
  END IF;

  -- Realiza a filtragem por unidade/filial
  IF (FsubsidiaryID > 0) THEN
    subsidiaryFilter := format(' AND S.subsidiaryID = %s', FsubsidiaryID);
  ELSE
    subsidiaryFilter := '';
  END IF;

  -- Selecionamos primeiramente os telefones principais
  query := format('
    SELECT p.phonenumber
      FROM erp.entities AS E
     INNER JOIN erp.subsidiaries AS S USING (entityID)
     INNER JOIN erp.phones AS P USING (subsidiaryID)
     WHERE E.entityID = %s %s
     ORDER BY S.subsidiaryid, P.phoneid;',
     FentityID, subsidiaryFilter
  );
  FOR address IN EXECUTE query
  LOOP
    -- Adicionamos o número de telefone a nossa relação de telefones
    -- RAISE NOTICE 'Telefone: %', address.phonenumber;
    phones := phones || Array[address.phonenumber];
  END LOOP;

  -- Agora selecionamos os telefones adicionais
  query := format('
    SELECT M.phonenumber
      FROM erp.entities AS E
     INNER JOIN erp.subsidiaries AS S USING (entityID)
     INNER JOIN erp.mailingAddresses AS M USING (subsidiaryID)
     INNER JOIN erp.actionsPerProfiles AS A USING (mailingProfileID)
     WHERE E.entityID = %s %s
       AND A.systemActionID = %s
       AND coalesce(M.phonenumber, '''') <> ''''
     ORDER BY S.subsidiaryid, M.mailingAddressID;',
     FentityID, subsidiaryFilter, FsystemActionID
  );

  FOR address IN EXECUTE query
  LOOP
    -- Adicionamos o número de telefone a nossa relação de telefones
    -- RAISE NOTICE 'Telefone: %', address.phonenumber;
    phones := phones || Array[address.phonenumber];
  END LOOP;
  
  RETURN array_to_string(phones, ' / ');
END;
$$ LANGUAGE 'plpgsql';

-- =====================================================================
-- INCLUSÃO DAS NOVAS PERMISSÕES PARA O USUÁRIO
-- =====================================================================

-- Insere a relação de permissões disponíveis para o cadastro de técnicos
-- para o sistema de ERP
INSERT INTO erp.permissions (permissionID, name, description) VALUES
  ( 415, 'ERP\Cadastre\ServiceProviders',
    'Gerenciamento de prestadores de serviços'),
  ( 416, 'ERP\Cadastre\ServiceProviders\Get',
    'Recupera as informações de prestadores de serviços'),
  ( 417, 'ERP\Cadastre\ServiceProviders\Add',
    'Adicionar prestador de serviços'),
  ( 418, 'ERP\Cadastre\ServiceProviders\Edit',
    'Editar prestador de serviços'),
  ( 419, 'ERP\Cadastre\ServiceProviders\Delete',
    'Remover prestador de serviços'),
  ( 420, 'ERP\Cadastre\ServiceProviders\ToggleBlocked',
    'Alterna o bloqueio de um prestador de serviços e/ou de uma unidade/filial do prestador de serviços'),
  ( 421, 'ERP\Cadastre\ServiceProviders\Get\PDF',
    'Gera um PDF com as informações cadastrais de um prestador de serviços'),
  ( 422, 'ERP\Cadastre\ServiceProviders\HasOneOrMore',
    'Determina se temos um ou mais prestadores de serviços válidos cadastrados'),
  ( 423, 'ERP\Cadastre\ServiceProviders\Technicians\Get',
    'Recupera as informações de técnicos'),
  ( 424, 'ERP\Cadastre\ServiceProviders\Technicians\Add',
    'Adicionar técnico'),
  ( 425, 'ERP\Cadastre\ServiceProviders\Technicians\Edit',
    'Editar técnico'),
  ( 426, 'ERP\Cadastre\ServiceProviders\Technicians\Delete',
    'Remover técnico'),
  ( 427, 'ERP\Cadastre\ServiceProviders\Technicians\ToggleBlocked',
    'Alterna o bloqueio de um técnico');

-- Insere a relação de permissões por grupo para o gerenciamento de
-- técnicos para todos os usuários (exceto clientes)
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,5) x(groupID),
          (VALUES (415, 'GET'),
                  (416, 'PATCH'),
                  (417, 'GET'), (417, 'POST'),
                  (418, 'GET'), (418, 'PUT'),
                  (419, 'DELETE'),
                  (420, 'PUT'),
                  (421, 'GET'),
                  (422, 'GET'),
                  (423, 'PATCH'),
                  (424, 'GET'), (424, 'POST'),
                  (425, 'GET'), (425, 'PUT'),
                  (426, 'DELETE'),
                  (427, 'PUT')) y(permissionID, method));

-- Atendentes não podem apagar, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (3)
   AND permissionID IN (419, 426);

-- Operadores e técnicos não podem adicionar, editar, apagar ou alterar
-- o estado do bloqueio, então revogamos
DELETE
  FROM erp.permissionsPerGroups
 WHERE groupID IN (4, 5)
   AND ( (permissionID IN (417, 419, 420, 424, 426, 427)) OR
         (permissionID = 418 AND httpMethod = 'PUT') OR
         (permissionID = 425 AND httpMethod = 'PUT') );


-- Clientes não possuem permissões, então não modifica nada


-- Precisa colocar deslocamento autorizado por técnico



-- Incluir na OS valores adicionais






-- Tabela para pagamentos
payouts
