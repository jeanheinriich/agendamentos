-- =====================================================================
-- Pagamentos
-- =====================================================================
-- O controle das faturas emitidas para clientes e dos respectivos
-- pagamentos.
-- =====================================================================

-- ---------------------------------------------------------------------
-- As faturas
-- ---------------------------------------------------------------------
-- As faturas emitidas para cada cliente. Nela constam as informações de
-- valores cobrados (billings) de serviços executados ou outros valores
-- discriminados.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.invoices (
  invoiceID             serial,         -- ID da fatura
  contractorID          integer         -- ID do contratante
                        NOT NULL,
  customerID            integer         -- ID do cliente
                        NOT NULL,
  subsidiaryID          integer         -- ID da unidade/filial do cliente
                        NOT NULL,
  referenceMonthYear    char(7)         -- O mês/ano de referência para
                        DEFAULT NULL,   -- faturas de cobrança mensal
  invoiceDate           date            -- A data da fatura
                        NOT NULL,
  dueDate               date            -- A data do vencimento
                        NOT NULL,
  invoiceValue          numeric(12,2)   -- Valor total desta fatura
                        NOT NULL
                        DEFAULT 0.00,
  paymentMethodID       integer         -- O ID do meio de pagamento
                        NOT NULL,
  definedMethodID       integer         -- ID da configuração da forma
                        DEFAULT NULL,   -- de pagamento a ser utilizada
  underAnalysis         boolean         -- O indicativo de fatura que
                        NOT NULL        -- está em análise (em processo
                        DEFAULT false,  -- de fechamento)
  carnetID              integer         -- O número do carnet
                        DEFAULT NULL,
  primaryInvoiceID      integer         -- O número da fatura principal
                        DEFAULT NULL,   -- quando temos várias parcelas
  PRIMARY KEY (invoiceID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (customerID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (subsidiaryID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE RESTRICT,
  FOREIGN KEY (paymentMethodID)
    REFERENCES erp.paymentMethods(paymentMethodID)
    ON DELETE CASCADE,
  FOREIGN KEY (definedMethodID)
    REFERENCES erp.definedMethods(definedMethodID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Os pagamentos
-- ---------------------------------------------------------------------
-- O controle de pagamentos pendentes e realizados. Cada fatura emitida
-- possui um pagamento associado. Com isto, podemos determinar o que foi
-- devidamente pago e o que ainda está pendente. Desta tabela PAI são
-- criadas tabelas derivadas que herdam todas as colunas desta tabela e
-- acrescentam outras com informações adicionais sobre formas de
-- pagamento específicas (tais como boletos). Desta forma, temos um
-- controle centralizado e comum a todos os pagamentos, independente da
-- forma de pagamento, e um mais específico para cada forma de pagamento
-- suportada.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.payments (
  paymentID             serial,         -- ID do pagamento
  contractorID          integer         -- ID do contratante
                        NOT NULL,
  invoiceID             integer         -- ID da fatura à qual este
                        NOT NULL,       -- pagamento está atrelado
  invoiceNumber         varchar(10)     -- O número antigo da fatura
                        DEFAULT NULL,   -- para valores importados
  dueDate               date            -- A data do vencimento
                        NOT NULL,
  valueToPay            numeric(12,2)   -- Valor da fatura
                        NOT NULL
                        DEFAULT 0.00,
  paymentMethodID       integer         -- O ID do meio de pagamento
                        NOT NULL,
  paymentSituationID    integer         -- O ID da situação do pagamento
                        NOT NULL
                        DEFAULT 1,
  paidDate              date            -- A data do pagamento
                        DEFAULT NULL,
  paidValue             numeric(12,2)   -- Valor efetivamente pago
                        NOT NULL
                        DEFAULT 0.00,
  latePaymentInterest   numeric(12,2)   -- Os valores pagos referentes à
                        DEFAULT 0.00,   -- juros de mora
  abatementValue        numeric(12,2)   -- Os abatimentos concedidos
                        DEFAULT 0.00,
  fineValue             numeric(12,2)   -- Os valores pagos referentes à
                        DEFAULT 0.00,   -- multa
  tariffValue           numeric(12,2)   -- O valor cobrado à título de
                        DEFAULT 0.00,   -- tarifa
  creditDate            date            -- A data em que o valor foi
                        DEFAULT NULL,   -- devidamente creditado
  parcel                integer         -- O número de parcelas (0 se
                        DEFAULT 0,      -- não for parcelado)
  numberOfParcels       integer         -- O número total de parcelas (0
                        DEFAULT 0,      -- se não for parcelado)
  restrictionID         integer         -- ID de restrição aplicada, tal
                        DEFAULT 0,      -- como protesto ou negativação
  PRIMARY KEY (paymentID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (invoiceID)
    REFERENCES erp.invoices(invoiceID)
    ON DELETE CASCADE,
  FOREIGN KEY (paymentMethodID)
    REFERENCES erp.paymentMethods(paymentMethodID)
    ON DELETE CASCADE,
  FOREIGN KEY (paymentSituationID)
    REFERENCES erp.paymentSituations(paymentSituationID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- O registro de ocorrências em um pagamento
-- ---------------------------------------------------------------------
-- Armazena as informações de ocorrências em um meio de pagamento,
-- exceto boletos.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.paymentOccurrences (
  occurrenceID      serial,       -- ID da ocorrência
  contractorID      integer       -- ID do contratante
                    NOT NULL,
  paymentID         integer       -- ID do pagamento onde ocorreu
                    NOT NULL,
  occurrenceTypeID  integer       -- ID da ocorrência
                    NOT NULL,
  description       varchar(100)  -- Descrição da ocorrência
                    NOT NULL,
  reasons           text          -- Motivos da ocorrência
                    DEFAULT NULL,
  occurrenceDate    date          -- Data da ocorrência
                    NOT NULL
                    DEFAULT CURRENT_DATE,
  PRIMARY KEY (occurrenceID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (occurrenceTypeID)
    REFERENCES erp.paymentOccurrenceTypes(occurrenceTypeID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Gatilho para lidar com novos pagamentos
-- ---------------------------------------------------------------------
-- Função que registra uma nova cobrança no histórico (dinheiro, cheque,
-- cartão de débito, cartão de crédito e transferência bancária)
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.recordPaymentRegistration()
RETURNS trigger AS $$
DECLARE
  query varchar;
BEGIN
  -- Lida com o registro do pagamento. Faz uso da variável especial
  -- TG_OP para verificar a operação executada e de TG_WHEN para
  -- determinar o instante em que isto ocorre
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'AFTER') THEN
      -- Inserimos o evento de registro da cobrança
      INSERT INTO erp.paymentOccurrences (contractorID, paymentID,
             occurrenceTypeID, description, reasons, occurrenceDate)
      VALUES (NEW.contractorID, NEW.paymentID, 1, 'Entrada confirmada',
              'Ocorrência aceita', CURRENT_DATE);
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    -- Não faz nada

    -- Retornamos a entidade modificada
    RETURN NEW;
  ELSIF (TG_OP = 'DELETE') THEN
    -- Não faz nada

    -- Retornamos a entidade
    RETURN OLD;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER paymentTransactionTriggerAfter
  AFTER INSERT ON erp.payments
  FOR EACH ROW EXECUTE PROCEDURE erp.recordPaymentRegistration();

-- ---------------------------------------------------------------------
-- Os pagamentos por boleto
-- ---------------------------------------------------------------------
-- O controle de pagamentos pendentes e realizados por boleto. A forma
-- de pagamento por boleto exige o controle de informações específicas
-- do próprio meio de pagamento. Com isto, conseguimos controlar
-- adequadamente este tipo de emissão em paralelo com o próprio controle
-- de pagamentos.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.bankingBilletPayments (
  definedMethodID     integer         -- ID da configuração da forma
                      DEFAULT NULL,   -- de pagamento a ser utilizada
  bankCode            char(3)         -- O código do banco
                      NOT NULL,
  agencyNumber        varchar(10)     -- A agência onde o valor é
                      NOT NULL,       -- creditado
  accountNumber       varchar(15)     -- A conta onde o valor é
                      NOT NULL,       -- creditado
  wallet              varchar(10)     -- A carteira de pagamento
                      NOT NULL,
  parameters          json            -- Os parâmetros de configuração
                      NOT NULL        -- do boleto junto ao banco
                      DEFAULT '{}',
  billingCounter      integer         -- O número sequencial desta
                      NOT NULL,       -- cobrança
  ourNumber           varchar(12)     -- O número de identificação do
                      DEFAULT NULL,   -- título no banco com DAC
  fineValue           numeric(8,4)    -- Taxa de multa a ser cobrado
                      NOT NULL        -- sobre o valor devido em caso de
                      DEFAULT 0.0000, -- atraso no pagamento
  arrearInterestType  integer         -- Tipo da taxa dos juros de mora
                      NOT NULL        --   1: valor
                      DEFAULT 2,      --   2: porcentagem
  arrearInterest      numeric(8,4)    -- Taxa diária dos juros de mora
                      NOT NULL        -- que incidirão sobre o valor
                      DEFAULT 0.0333, -- devido em caso de atraso no
                                      -- pagamento
  instructionID       integer         -- A instrução que o banco deve
                      NOT NULL        -- adotar após o vencimento do
                      DEFAULT 0,      -- título. Padrão: nenhuma
  instructionDays     integer         -- A quantidade de dias após o
                      NOT NULL        -- vencimento que a instrução será
                      DEFAULT 0,      -- aplicada
  droppedTypeID       integer         -- O tipo de baixa do boleto
                      NOT NULL
                      DEFAULT 1,
  hasError            boolean         -- O indicativo que ocorreu algum
                      DEFAULT FALSE,  -- erro
  reasonForError      text            -- O(s) motivo(s) para o erro
                      DEFAULT NULL
) INHERITS (erp.payments);

-- ---------------------------------------------------------------------
-- Gatilho para lidar com a liquidação de um pagamento
-- ---------------------------------------------------------------------
-- Função que toma as providencias para liberar o acesso dos usuários e
-- encerrar mensagens de cobrança com a liquidação do título e/ou o seu
-- cancelamento ou renegociação do título
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.analyzePaymentFulfillment()
RETURNS TRIGGER AS $$
DECLARE
  FcustomerID integer;
  isCompliant boolean;
BEGIN
  -- Lida com o registro do pagamento ou cancelamento de um título. Faz
  -- uso da variável especial TG_OP para verificar a operação executada
  -- e de TG_WHEN para determinar o instante em que isto ocorre
  IF (TG_OP = 'INSERT') THEN
    -- Não faz nada com novos registros

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    -- Verificamos se estamos liquidando o título,  ou cancelando-o,
    -- situações em que será necessário verificar se o mesmo se tornou
    -- adimplente (está em dia com seus pagamentos). Se isto ocorrer,
    -- precisa eliminar mensagens de cobrança nos aplicativos e liberar
    -- o acesso novamente (caso tenha sido bloqueado)
    IF NEW.paymentSituationID > 1 THEN
      -- RAISE NOTICE 'O título foi liquidado';

      -- Obtemos quem é o cliente
      SELECT invoice.customerID
        INTO FcustomerID
        FROM erp.invoices AS invoice
      WHERE invoice.invoiceID = NEW.invoiceID;
      -- RAISE NOTICE 'O cliente é o ID %', FcustomerID;

      -- Verificamos se ele está adimplente, ou seja, se não existem
      -- outras cobranças em aberto
      SELECT NOT EXISTS (
        SELECT 1
          FROM erp.bankingBilletPayments AS P
         INNER JOIN erp.invoices AS I USING (invoiceID)
         WHERE I.customerID = FcustomerID
           AND P.paymentSituationID = 1
           AND I.invoiceID != NEW.invoiceID
           AND P.dueDate < CURRENT_DATE - interval '2 days'
           AND (P.restrictionid >> 2) & 1 = 0
      ) INTO isCompliant;
      -- RAISE NOTICE 'O cliente está adimplente? %', isCompliant;

      IF isCompliant THEN
        -- RAISE NOTICE 'O cliente está adimplente, removendo mensagens de cobranças';
        -- Removemos mensagens de cobrança deste cliente
        DELETE FROM public.messageQueue
         WHERE customerID = FcustomerID
           AND overdueNotice = TRUE
           AND recurrent = TRUE;
        
        -- Liberamos o acesso de usuários bloqueados
        -- TODO: A fazer
      END IF;
    END IF;

    -- Retornamos a entidade modificada
    RETURN NEW;
  ELSIF (TG_OP = 'DELETE') THEN
    -- Não faz nada

    -- Retornamos a entidade
    RETURN OLD;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER bankingBilletPaymentUpdateTransactionTriggerAfter
  AFTER UPDATE ON erp.bankingBilletPayments
  FOR EACH ROW EXECUTE PROCEDURE erp.analyzePaymentFulfillment();

-- ---------------------------------------------------------------------
-- Dados de pagamentos
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de cobranças
-- ---------------------------------------------------------------------
CREATE TYPE erp.paymentData AS
(
  paymentID            integer,
  invoiceID            varchar(10),
  customerID           integer,
  customerName         varchar(100),
  juridicalPerson      boolean,
  subsidiaryID         integer,
  subsidiaryName       varchar(100),
  nationalRegister     varchar(18),
  referenceMonthYear   varchar(7),
  dueDate              date,
  overdue              boolean,
  valueToPay           numeric(12,2),
  paymentMethodID      integer,
  paymentMethodName    varchar(50),
  paymentSituationID   integer,
  restrictionID        integer,
  paymentSituationName varchar(30),
  droppedTypeID        integer,
  droppedTypeName      varchar(100),
  digitableline        varchar(54),
  billingCounter       integer,
  paidDate             date,
  paidValue            numeric(12,2),
  latePaymentInterest  numeric(12,2),
  fineValue            numeric(12,2),
  abatementValue       numeric(12,2),
  tariffValue          numeric(12,2),
  creditDate           date,
  sentMailStatus       jsonb,
  hasError             boolean,
  reasonForError       text,
  fullcount            integer
);

CREATE OR REPLACE FUNCTION erp.getPaymentsData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FsearchValue varchar(100),
  FsearchField varchar(20), FpaymentMethodID integer,
  FpaymentSituationID integer, FOrder varchar, Skip integer,
  LimitOf integer)
RETURNS SETOF erp.paymentData AS
$$
DECLARE
  paymentData  erp.paymentData%rowtype;
  row          record;
  vehicleData  record;
  query        varchar;
  field        varchar;
  filter       varchar;
  limits       varchar;
  blockedLevel integer;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FcustomerID IS NULL) THEN
    FcustomerID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FpaymentMethodID IS NULL) THEN
    FpaymentMethodID = 0;
  END IF;
  IF (FpaymentSituationID IS NULL) THEN
    FpaymentSituationID = 0;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'payments.dueDate ASC, customers.name ASC';
  END IF;
  IF (FOrder ILIKE 'invoices.referencemonthyear%') THEN
    FOrder = 'substring(invoices.referencemonthyear, 4) || substring(invoices.referencemonthyear, 1, 2)' ||
      substring(FOrder, 28);
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  filter := '';
  
  -- Realiza a filtragem por cliente
  IF (FcustomerID > 0) THEN
    filter := format(' AND customers.entityID = %s',
                    FcustomerID);
    IF (FsubsidiaryID > 0) THEN
      filter := filter || format(' AND subsidiaries.subsidiaryID = %s',
                                 FsubsidiaryID);
    END IF;
  END IF;

  IF (FpaymentMethodID > 0) THEN
    -- Visualizamos apenas os valores que estão no processo de análise
    -- para o faturamento
    filter := filter || format(' AND payments.paymentMethodID = %s',
                               FpaymentMethodID);
  END IF;

  IF (FpaymentSituationID > 0) THEN
    CASE (FpaymentSituationID)
      WHEN 1 then
        -- Visualizamos todos os valores à vencer, exceto os enviados à
        -- agência de cobrança
        filter := filter || ' AND (payments.paymentSituationID = 1 AND ((payments.restrictionid >> 2) & 1 = 0))';
      WHEN 10 then
        -- Visualizamos apenas os valores que estão há vencer
        filter := filter || ' AND (payments.paymentSituationID = 1 AND ((payments.restrictionid >> 2) & 1 = 0) AND payments.duedate >= CURRENT_DATE)';
      WHEN 11 then
        -- Visualizamos apenas os valores que estão vencidos
        filter := filter || ' AND (payments.paymentSituationID = 1 AND ((payments.restrictionid >> 2) & 1 = 0) AND payments.duedate < CURRENT_DATE)';
      WHEN 12 then
        -- Visualizamos apenas os valores que estão negativados
        filter := filter || ' AND (payments.paymentSituationID = 1 AND ((payments.restrictionid >> 2) & 1 = 0) AND ((payments.restrictionid >> 1) & 1 = 1))';
      WHEN 13 then
        -- Visualizamos apenas os valores que estão protestados
        filter := filter || ' AND (payments.paymentSituationID = 1 AND ((payments.restrictionid >> 2) & 1 = 0) AND ((payments.restrictionid >> 0) & 1 = 1))';
      WHEN 14 then
        -- Visualizamos apenas os valores que estão com uma empresa de
        -- cobrança
        filter := filter || ' AND (payments.paymentSituationID = 1 AND ((payments.restrictionid >> 2) & 1 = 1))';
      ELSE
        -- Visualizamos apenas os valores que estão de acordo com a
        -- situação informada
        filter := filter || format(' AND payments.paymentSituationID = %s',
                                   FpaymentSituationID);
    END CASE;
  END IF;

  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    -- RAISE NOTICE 'FsearchValue IS NOT NULL';
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      -- Monta o filtro conforme o campo
      CASE (FsearchField)
        WHEN 'invoiceid' THEN
          filter := filter ||
            format(' AND (CASE WHEN payments.invoiceNumber IS NULL THEN CAST(payments.invoiceID AS text) LIKE ''%%%s%%'' ELSE payments.invoiceNumber ILIKE ''%%%s%%'' END)', CAST(CAST(regexp_replace('0'||FsearchValue, '\D', '', 'g') AS integer) AS text), FsearchValue);
        WHEN 'name' THEN
          filter := filter ||
            format(' AND customers.%s ILIKE ''%%%s%%''', FsearchField, FsearchValue);
        ELSE
          -- Monta o filtro
          filter := filter ||
            format(' AND payments.%s ILIKE ''%%%s%%''', FsearchField, FsearchValue);
      END CASE;
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  -- Monta a consulta
  query := format('
    SELECT payment.paymentID,
           CASE
             WHEN payment.invoiceNumber IS NULL THEN CAST(payment.invoiceID AS text)
             ELSE payment.invoiceNumber
           END AS invoiceID,
           payment.customerID,
           payment.customerName,
           payment.juridicalperson,
           payment.subsidiaryID,
           payment.subsidiaryName,
           payment.nationalregister,
           payment.referenceMonthYear,
           payment.dueDate,
           payment.overdue,
           payment.valueToPay,
           payment.paymentMethodID,
           payment.paymentMethodName,
           payment.paymentSituationID,
           payment.restrictionID,
           payment.paymentSituationName,
           payment.droppedTypeID,
           payment.droppedTypeName,
           CASE
             WHEN payment.paymentSituationID IN (1, 4, 5) AND payment.paymentMethodID = 5 AND payment.bankCode IN (''237'', ''341'') THEN
               erp.getDigitableLine(
                 payment.bankCode,
                 payment.agencyNumber,
                 payment.accountNumber,
                 payment.wallet,
                 payment.billingCounter,
                 payment.invoiceID,
                 payment.dueDate,
                 payment.valueToPay,
                 payment.parameters
               )
             ELSE ''''
           END AS digitableLine,
           payment.billingCounter,
           payment.paidDate,
           payment.paidValue,
           payment.latePaymentInterest,
           payment.fineValue,
           payment.abatementValue,
           payment.tariffValue,
           payment.creditDate,
           erp.getMailStatus(payment.paymentID) AS sentMailStatus,
           payment.hasError,
           payment.reasonForError,
           payment.fullcount
      FROM (
        SELECT payments.paymentID,
               payments.invoiceID,
               payments.invoiceNumber,
               invoices.customerID,
               customers.name AS customerName,
               customerType.juridicalperson AS juridicalperson,
               invoices.subsidiaryID,
               subsidiaries.name AS subsidiaryName,
               subsidiaries.nationalregister,
               invoices.referenceMonthYear,
               payments.dueDate,
               CASE
                 WHEN payments.paymentSituationID = 1 THEN (payments.dueDate < CURRENT_DATE)
                 ELSE false
               END AS overdue,
               payments.valueToPay,
               payments.paymentMethodID,
               paymentMethods.name AS paymentMethodName,
               payments.paymentSituationID,
               payments.restrictionID,
               CASE
                 WHEN bankingbilletpayments.droppedTypeID = 1 THEN paymentSituations.name
                 WHEN payments.paymentSituationID IN (4, 5) AND payments.restrictionID > 0 THEN paymentSituations.name || '', porém '' || paymentRestrictions.name
                 WHEN payments.restrictionID > 0 THEN paymentRestrictions.name
                 WHEN payments.paymentSituationID = 1 AND payments.dueDate < CURRENT_DATE THEN ''*** Vencido à '' || (CURRENT_DATE - payments.dueDate) || '' dias***''
                 WHEN payments.paymentSituationID = 2 AND payments.abatementValue > 0 THEN paymentSituations.name || '' com desconto''
                 ELSE paymentSituations.name
               END AS paymentSituationName,
               CASE
                 WHEN bankingbilletpayments.droppedTypeID IS NULL THEN 0
                 ELSE bankingbilletpayments.droppedTypeID
               END AS droppedTypeID,
               CASE
                 WHEN bankingbilletpayments.droppedTypeID IS NULL THEN ''''
                 ELSE droppedTypes.name
               END AS droppedTypeName,
               bankingbilletpayments.bankCode,
               bankingbilletpayments.agencyNumber,
               bankingbilletpayments.accountNumber,
               bankingbilletpayments.wallet,
               bankingbilletpayments.billingCounter,
               bankingbilletpayments.parameters,
               payments.paidDate,
               CASE 
                 WHEN payments.paymentSituationID = 1 THEN NULL
                 ELSE payments.paidValue
               END AS paidValue,
               CASE 
                 WHEN payments.paymentSituationID = 1 THEN NULL
                 ELSE payments.latePaymentInterest
               END AS latePaymentInterest,
               CASE 
                 WHEN payments.paymentSituationID = 1 THEN NULL
                 ELSE payments.fineValue
               END AS fineValue,
               payments.abatementValue,
               payments.tariffValue,
               payments.creditDate,
               CASE
                 WHEN bankingbilletpayments.hasError IS NULL THEN false
                 ELSE bankingbilletpayments.hasError
               END AS hasError,
               CASE
                 WHEN bankingbilletpayments.hasError IS NULL THEN ''''
                 WHEN bankingbilletpayments.hasError = FALSE THEN ''''
                 ELSE bankingbilletpayments.reasonForError
               END AS reasonForError,
               count(*) OVER() AS fullcount
          FROM erp.payments
         INNER JOIN erp.invoices USING (invoiceID)
         INNER JOIN erp.entities AS customers ON (invoices.customerID = customers.entityID)
         INNER JOIN erp.entitiesTypes AS customerType ON (customers.entityTypeID = customerType.entityTypeID)
         INNER JOIN erp.subsidiaries ON (invoices.subsidiaryID = subsidiaries.subsidiaryID)
         INNER JOIN erp.paymentMethods ON (payments.paymentMethodID = paymentMethods.paymentMethodID)
         INNER JOIN erp.paymentSituations ON (payments.paymentSituationID = paymentSituations.paymentSituationID)
          LEFT JOIN erp.paymentRestrictions USING (restrictionID)
          LEFT JOIN erp.bankingbilletpayments USING (paymentID)
          LEFT JOIN erp.droppedTypes USING (droppedTypeID)
         WHERE payments.contractorID = %s %s
           AND payments.valueToPay > 0.00
         ORDER BY %s %s
      ) AS payment;',
    fContractorID, filter, FOrder, limits);
  -- RAISE NOTICE 'Query IS %', query;
  FOR row IN EXECUTE query
  LOOP
    paymentData.paymentID            := row.paymentID;
    paymentData.invoiceID            := row.invoiceID;
    paymentData.customerID           := row.customerID;
    paymentData.customerName         := row.customerName;
    paymentData.juridicalPerson      := row.juridicalPerson;
    paymentData.subsidiaryID         := row.subsidiaryID;
    paymentData.subsidiaryName       := row.subsidiaryName;
    paymentData.nationalRegister     := row.nationalRegister;
    paymentData.referenceMonthYear   := row.referenceMonthYear;
    paymentData.dueDate              := row.dueDate;
    paymentData.overdue              := row.overdue;
    paymentData.valueToPay           := row.valueToPay;
    paymentData.paymentMethodID      := row.paymentMethodID;
    paymentData.paymentMethodName    := row.paymentMethodName;
    paymentData.paymentSituationID   := row.paymentSituationID;
    paymentData.restrictionID        := row.restrictionID;
    paymentData.paymentSituationName := row.paymentSituationName;
    paymentData.droppedTypeID        := row.droppedTypeID;
    paymentData.droppedTypeName      := row.droppedTypeName;
    paymentData.digitableLine        := row.digitableLine;
    paymentData.billingCounter       := row.billingCounter;
    paymentData.paidDate             := row.paidDate;
    paymentData.paidValue            := row.paidValue;
    paymentData.latePaymentInterest  := row.latePaymentInterest;
    paymentData.fineValue            := row.fineValue;
    paymentData.abatementValue       := row.abatementValue;
    paymentData.tariffValue          := row.tariffValue;
    paymentData.creditDate           := row.creditDate;
    paymentData.sentMailStatus       := row.sentMailStatus;
    paymentData.hasError             := row.hasError;
    paymentData.reasonForError       := row.reasonForError;
    paymentData.fullcount            := row.fullcount;

    RETURN NEXT paymentData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- SELECT * FROM erp.getPaymentsData(1, 0, 0, '', 'invoiceID', NULL, NULL, 'payments.dueDate ASC', 0, 10);

-- ---------------------------------------------------------------------
-- O controle de arquivos de remessa e retorno
-- ---------------------------------------------------------------------
-- O controle dos arquivos de remessa e retorno gerados pelo sistema que
-- são enviados/recebidos para registro e baixa de boletos.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.bankingTransmissionFiles (
  transmissionFileID    serial,       -- Número de identificação do arquivo
  contractorID          integer       -- ID do contratante
                        NOT NULL,
  createdAt             timestamp     -- A data de criação do arquivo
                        NOT NULL
                        DEFAULT CURRENT_TIMESTAMP,
  filename              varchar(30)   -- Nome do arquivo gerado/recebido
                        NOT NULL,
  isShippingFile        boolean       -- O indicativo de que é um arquivo
                        NOT NULL,     -- de remessa (se não, é retorno)
  PRIMARY KEY (transmissionFileID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- A fila de despacho de boletos bancários
-- ---------------------------------------------------------------------
-- Armazena as informações a serem enviadas ao banco nos arquivos de
-- remessa.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.billetDispatching (
  dispatchingID     serial,       -- ID do evento de despacho
  contractorID      integer       -- ID do contratante
                    NOT NULL,
  paymentID         integer       -- ID do pagamento atrelado ao evento
                    NOT NULL,
  instructionID     integer       -- ID da instrução
                    NOT NULL,
  requestDate       date          -- Data da requisição
                    NOT NULL
                    DEFAULT CURRENT_DATE,
  reasons           text          -- Motivos da ocorrência
                    DEFAULT NULL,
  dispatchDate      date          -- Data do despacho
                    DEFAULT NULL,
  shippingFileID    integer       -- O ID do arquivo de remessa
                    DEFAULT NULL,
  PRIMARY KEY (dispatchingID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (instructionID)
    REFERENCES erp.billetInstructions(instructionID)
    ON DELETE RESTRICT,
  FOREIGN KEY (shippingFileID)
    REFERENCES erp.bankingTransmissionFiles(transmissionFileID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Gatilho para lidar com novos boletos
-- ---------------------------------------------------------------------
-- Função que insere o boleto para registro na fila de despacho de
-- boletos bancários
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.sendBilletToRegistration()
RETURNS trigger AS $$
DECLARE
  query varchar;
BEGIN
  -- Lida com o registro do boleto. Faz uso da variável especial TG_OP
  -- para verificar a operação executada e de TG_WHEN para determinar o
  -- instante em que isto ocorre
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'AFTER') THEN
      -- Inserimos o boleto na fila de despacho de boletos bancários
      INSERT INTO erp.billetDispatching (contractorID, paymentID,
        instructionID) VALUES (NEW.contractorID, NEW.paymentID, 1);
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    -- Não faz nada

    -- Retornamos a entidade modificada
    RETURN NEW;
  ELSIF (TG_OP = 'DELETE') THEN
    -- Não faz nada

    -- Retornamos a entidade
    RETURN OLD;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER bankingBilletPaymentTransactionTriggerAfter
  AFTER INSERT ON erp.bankingBilletPayments
  FOR EACH ROW EXECUTE PROCEDURE erp.sendBilletToRegistration();

-- ---------------------------------------------------------------------
-- O registro de ocorrências retornadas em um boleto
-- ---------------------------------------------------------------------
-- Armazena as informações recebidas do banco nos arquivos de retorno,
-- informando a ocorrência em cada boleto.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.bankingBilletOccurrences (
  occurrenceID      serial,       -- ID da ocorrência
  contractorID      integer       -- ID do contratante
                    NOT NULL,
  paymentID         integer       -- ID do pagamento onde ocorreu
                    NOT NULL,
  occurrenceTypeID  integer       -- ID da ocorrência
                    NOT NULL,
  occurrenceCode    integer       -- ID da ocorrência no banco
                    NOT NULL,
  description       varchar(100)  -- Descrição da ocorrência
                    NOT NULL,
  reasons           text          -- Motivos da ocorrência
                    DEFAULT NULL,
  occurrenceDate    date          -- Data da ocorrência
                    NOT NULL
                    DEFAULT CURRENT_DATE,
  tariffValue       numeric(12,2) -- Valor da tarifa associado à
                    NOT NULL,     -- ocorrência
  returnFileID      integer       -- O ID do arquivo de retorno
                    DEFAULT NULL,
  PRIMARY KEY (occurrenceID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (returnFileID)
    REFERENCES erp.bankingTransmissionFiles(transmissionFileID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Gatilho para lidar com inclusão de tarifas
-- ---------------------------------------------------------------------
-- Função que altera os valores cobrados à titulo de tarifa no boleto
-- a cada inserção de nova tarifa do arquivo de retorno.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.computeTariffsOnBillet()
RETURNS trigger AS $$
DECLARE
  query varchar;
BEGIN
  -- Lida com o registro de um retorno. Faz uso da variável especial
  -- TG_OP para verificar a operação executada e de TG_WHEN para
  -- determinar o instante em que isto ocorre
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'AFTER') THEN
      -- Verificamos se temos um valor de tarifa cobrado
      IF (NEW.tariffValue > 0.00) THEN
        -- Atualizamos o total de cobrança de tarifa no boleto
        UPDATE erp.bankingbilletpayments
           SET tariffvalue = tariffvalue + NEW.tariffvalue
         WHERE paymentID = NEW.paymentID;
      END IF;
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    -- Não faz nada

    -- Retornamos a entidade modificada
    RETURN NEW;
  ELSIF (TG_OP = 'DELETE') THEN
    -- Não faz nada

    -- Retornamos a entidade
    RETURN OLD;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER bankingBilletOccurrenceTransactionTriggerAfter
  AFTER INSERT ON erp.bankingBilletOccurrences
  FOR EACH ROW EXECUTE PROCEDURE erp.computeTariffsOnBillet();

-- ---------------------------------------------------------------------
-- Dados de histórico de um título
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de histórico
-- dos movimentos de um título
-- ---------------------------------------------------------------------
CREATE TYPE erp.paymentHistoryData AS
(
  occurrenceID   integer,
  eventDate      date,
  eventTypeID    integer,
  eventTypeName  varchar(30),
  description    varchar(100),
  reasons        text,
  performed      boolean
);

CREATE OR REPLACE FUNCTION erp.getPaymentHistory(FcontractorID integer,
  FpaymentID integer)
RETURNS SETOF erp.paymentHistoryData AS
$$
DECLARE
  historyData  erp.paymentHistoryData%rowtype;
  row          record;
  query        varchar;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FpaymentID IS NULL) THEN
    FpaymentID = 0;
  END IF;

  -- Monta a consulta
  query := format('
    SELECT occurrenceID,
           eventDate,
           eventTypeID,
           eventTypeName,
           description,
           reasons,
           performed
      FROM (
            SELECT payment.occurrenceID,
                   payment.occurrenceDate AS eventDate,
                   1 AS itemOrder,
                   payment.occurrenceTypeID AS eventTypeID,
                   occurrence.name AS eventTypeName,
                   payment.description,
                   payment.reasons,
                   true AS performed
              FROM erp.paymentOccurrences AS payment
             INNER JOIN erp.paymentOccurrenceTypes AS occurrence USING (occurrenceTypeID)
             WHERE payment.contractorID = %s
               AND payment.paymentID = %s
             UNION
            SELECT dispatching.dispatchingID AS occurrenceID,
                   CASE
                     WHEN dispatching.dispatchDate IS NULL THEN requestDate
                     ELSE dispatching.dispatchDate
                   END AS eventDate,
                   2 AS itemOrder,
                   dispatching.instructionID AS eventTypeID,
                   instruction.name AS eventTypeName,
                   CASE
                     WHEN dispatching.dispatchDate IS NULL THEN ''Solicitação não enviada''
                     ELSE ''''
                   END AS description,
                   dispatching.reasons,
                   CASE
                     WHEN dispatching.dispatchDate IS NULL THEN FALSE
                     ELSE true
                   END AS performed
              FROM erp.billetDispatching AS dispatching
             INNER JOIN erp.billetInstructions AS instruction USING (instructionID)
             WHERE dispatching.contractorID = %s
               AND dispatching.paymentID = %s
             UNION
            SELECT billet.occurrenceID,
                   billet.occurrenceDate AS eventDate,
                   3 AS itemOrder,
                   billet.occurrenceTypeID AS eventTypeID,
                   occurrence.name AS eventTypeName,
                   billet.description,
                   billet.reasons,
                   true AS performed
              FROM erp.bankingBilletOccurrences AS billet
             INNER JOIN erp.occurrenceTypes AS occurrence USING (occurrenceTypeID)
             WHERE billet.contractorID = %s
               AND billet.paymentID = %s
           ) AS events
     ORDER BY eventDate, itemOrder;',
    FcontractorID, FpaymentID, FcontractorID, FpaymentID, FcontractorID,
    FpaymentID);
  -- RAISE NOTICE 'Query IS %', query;
  FOR row IN EXECUTE query
  LOOP
    historyData.occurrenceID   := row.occurrenceID;
    historyData.eventDate      := row.eventDate;
    historyData.eventTypeID    := row.eventTypeID;
    historyData.eventTypeName  := row.eventTypeName;
    historyData.description    := row.description;
    historyData.reasons        := row.reasons;
    historyData.performed      := row.performed;

    RETURN NEXT historyData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- O controle de carnês
-- ---------------------------------------------------------------------
-- O controle de faturas que compõe um mesmo carnê emitido.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.carnets (
  carnetID        serial,       -- ID do carnê
  contractorID    integer       -- ID do contratante
                  NOT NULL,
  createdAt       timestamp     -- Data/hora da emissão
                  NOT NULL,
  createdByUserID integer       -- O ID do usuário responsável
                  NOT NULL,     -- pelo cadastro deste registro
  PRIMARY KEY (carnetID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Gera um carnê de pagamentos
-- ---------------------------------------------------------------------
-- Stored Procedure que determina os valores a serem cobrados em cada
-- parcela de um carnê, gerando os respectivos boletos para cada parcela
-- e fazendo os devidos registros de períodos cobrados.
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
                         CASE WHEN S.discountType = 1 THEN
                           S.discountValue / EXTRACT(DAY FROM (DATE_TRUNC('MONTH', startDateOfBillingPeriod + INTERVAL '1 MONTH') - INTERVAL '1 DAY'))
                         ELSE
                           S.discountValue
                         END AS discountValue
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

-- Exemplo:
-- SELECT erp.createCarnet(1,42, 45, '2022-03-01'::Date, 3, '2022-04-15'::Date, 2, '{35}') AS carnetID;

-- ---------------------------------------------------------------------
-- Gera uma cobrança antecipada
-- ---------------------------------------------------------------------
-- Stored Procedure que determina os valores a serem cobrados em cada
-- parcela de uma cobrança antecipada, gerando uma cobrança única que
-- contém todas as parcelas e fazendo os devidos registros de períodos
-- cobrados.
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
                         CASE WHEN S.discountType = 1 THEN
                           S.discountValue / EXTRACT(DAY FROM (DATE_TRUNC('MONTH', startDateOfBillingPeriod + INTERVAL '1 MONTH') - INTERVAL '1 DAY'))
                         ELSE
                           S.discountValue
                         END AS discountValue
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

-- Exemplo de uso
-- SELECT erp.createPrepayment(1, 42, 45, '2022-04-01'::Date, 3, '2022-03-21'::Date, 285.00, '{31}', 1, 1, 0, 2) AS prepaymentID;

-- ---------------------------------------------------------------------
-- Gera as parcelas de um carnê de acordo
-- ---------------------------------------------------------------------
-- Stored Procedure que insere os valores a serem cobrados em cada
-- parcela de um carnê para um acordo. O valor das parcelas é informado
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.createArrangementCarnet(FcontractorID integer,
  FcustomerID int, FsubsidiaryID int, FcontractID int, FstartDate date,
  Fvalue numeric(12,2), FnumberOfParcels int, FuserID integer)
RETURNS integer AS
$$
DECLARE
  -- As informações do meio de pagamento
  FpaymentMethodID  integer;
  FdefinedMethodID  integer;

  -- O ID do carnê gerado
  newCarnetID  int;

  -- O ID da fatura gerada
  newInvoiceID  integer;

  -- O contador de mêses para as faturas emitidas
  interaction  int;

  -- A data do vencimento
  dueDate  date;

  -- O valor do carnê
  carnetValue  numeric;

  -- Os dados da fatura
  invoice  record;

  -- O número sequencial do boleto
  FbillingCounter  integer;

  -- Nosso número
  ourNumber  varchar(12);

  -- Os padrâmetros de multa, juros de mora e instrução do boleto
  FfineValue  numeric(8,4);
  FarrearInterestType  integer;
  FarrearInterest  numeric(8,4);
  Fparameters  json;
  FinstructionID  integer;
  FinstructionDays  integer;
BEGIN
  -- Recuperamos a informação da cobrança a ser gerada
  SELECT INTO FpaymentMethodID, FdefinedMethodID, FfineValue, FarrearInterestType, FarrearInterest, Fparameters, FinstructionID, FinstructionDays
         C1.paymentMethodID,
         C1.definedMethodID,
         P.fineValue,
         P.arrearInterestType,
         P.arrearInterest,
         ((D1.parameters::jsonb - 'instructionID') - 'instructionDays')::json AS parameters,
         D1.parameters::jsonb->'instructionID' AS instructionID,
         D1.parameters::jsonb->'instructionDays' AS instructionDays
    FROM erp.contracts AS C
   INNER JOIN erp.plans AS P ON (C.planID = P.planID)
   INNER JOIN erp.paymentConditions AS C1 ON (C.paymentConditionID = C1.paymentConditionID)
   INNER JOIN erp.definedMethods AS D1 ON (C1.definedMethodID = D1.definedMethodID)
   WHERE C.contractID = FcontractID;

  -- Criamos o identificador de nosso carnê
  INSERT INTO erp.carnets (contractorID, createdAt, createdByUserID)
  VALUES (FcontractorID, CURRENT_TIMESTAMP, FuserID)
  RETURNING carnetID INTO newCarnetID;

  -- Inicializamos as variáveis de processamento
  interaction := 1;
  dueDate := FstartDate;

  LOOP
    IF (interaction > 1) THEN
      -- Avançamos mais um mês
      dueDate := (dueDate
        + interval '1 month')::DATE
      ;
    END IF;

    -- Precisamos criar uma nova fatura
    INSERT INTO erp.invoices (contractorID, customerID, subsidiaryID,
      invoiceDate, referenceMonthYear, dueDate, paymentMethodID,
      definedMethodID, carnetID, invoiceValue) VALUES (FcontractorID,
      FcustomerID, FsubsidiaryID, CURRENT_DATE,
      to_char(dueDate, 'MM/YYYY'), dueDate, FpaymentMethodID,
      FdefinedMethodID, newCarnetID, Fvalue)
    RETURNING invoiceID INTO newInvoiceID;

    -- Lançamos esta mensalidade nos registros de valores cobrados
    INSERT INTO erp.billings (contractorID, contractID, billingDate,
           name, value, invoiceID, invoiced, addMonthlyAutomatic,
           isMonthlyPayment, createdByUserID, updatedByUserID)
    VALUES (FcontractorID, FcontractID, dueDate, 'Acordo negociação (Parcela ' || interaction::text || ' de ' || FnumberOfParcels || ')',
            Fvalue, newInvoiceID, TRUE, FALSE, FALSE, FuserID, FuserID);
    RAISE NOTICE 'Inserida a cobrança da parcela % no valor de %',
      (interaction + 1),
      Fvalue;

    -- Incrementamos a quantidade de interações que fizemos
    interaction := interaction + 1;

    -- Repetimos este processo até determinar interação seja superior a
    -- quantidade de parcelas a serem emitidas
    EXIT WHEN interaction > FnumberOfParcels;
  END LOOP;

  -- Verifica se as faturas geraram algum valor a ser cobrado
  SELECT INTO carnetValue
         SUM(I.invoiceValue)
    FROM erp.invoices AS I
   WHERE I.carnetID = newCarnetID;
  IF (carnetValue = 0.00) THEN
    -- Disparamos uma exceção
    RAISE EXCEPTION 'Não foi possível obter mensalidades para a geração do carnê nº % com % parcelas e início em %',
      newCarnetID,
      FnumberOfParcels,
      FstartDate
      USING HINT = 'Por favor, verifique os períodos já cobrados';

    RETURN NULL;
  END IF;

  -- Lançamos os valores de cada fatura para cobrança
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

    -- Inserimos o boleto para cobrança
    INSERT INTO erp.bankingBilletPayments (contractorID, invoiceID,
           dueDate, valueToPay, paymentMethodID, paymentSituationID,
           definedMethodID, bankCode, agencyNumber, accountNumber,
           wallet, billingCounter, parameters, ourNumber, fineValue,
           arrearInterestType, arrearInterest, instructionID,
           instructionDays, droppedTypeID)
    VALUES (invoice.contractorID, invoice.invoiceID, invoice.dueDate,
           invoice.invoiceValue, invoice.paymentMethodID,
           1, invoice.definedMethodID, invoice.bankID,
           invoice.agencyNumber, invoice.accountNumber, invoice.wallet,
           FbillingCounter, Fparameters, ourNumber, FfineValue,
           FarrearInterestType, FarrearInterest, FinstructionID,
           FinstructionDays, 1);

    -- Indicamos que todas os lançamentos foram cobrados
    UPDATE erp.billings
       SET invoiced = TRUE
     WHERE invoiceID = invoice.invoiceID;
  END LOOP;

  -- Indica que tudo deu certo, retornando o número do carnê
  RETURN newCarnetID;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Obtém o escopo de uma cobrança por boleto.
-- ---------------------------------------------------------------------
-- Através do ID do pagamento, obtém se existem outros pagamentos
-- associados, como é o caso do carnê, onde um boleto está vinculado aos
-- demais.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getPaymentScope(FpaymentID integer)
RETURNS int[] AS
$$
DECLARE
  carnetID  integer;
  filter  varchar;
  query  varchar;
  scope  int[];
BEGIN
  SELECT INTO carnetID
         I.carnetID
    FROM erp.bankingbilletpayments AS P
   INNER JOIN erp.invoices AS I USING (invoiceID)
   WHERE P.paymentID = FpaymentID;

  IF (carnetID IS NULL) THEN
    filter := format('P.paymentID = %s', FpaymentID);
  ELSE
    filter := format('I.carnetID = %s', carnetID);
  END IF;

  query := format('
    SELECT array(
      SELECT P.paymentID
        FROM erp.payments AS P
       INNER JOIN erp.invoices AS I USING (invoiceID)
       WHERE %s
       ORDER BY P.paymentID);',
    filter);
  EXECUTE query INTO scope;

  RETURN scope;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Obtém a relação de dados de contato de cobranças em aberto
-- ---------------------------------------------------------------------
-- Stored Procedure que obtém os dados de clientes com valores em aberto
-- e seus respectivos telefones de contato.
-- ---------------------------------------------------------------------
CREATE TYPE erp.billingContactData AS
(
  name         varchar(100),
  sequence     integer,
  phoneType    varchar(20),
  phoneNumber  varchar(16),
  comment      varchar(100),
  complement   jsonb
);

CREATE OR REPLACE FUNCTION erp.getBillingPhoneList(FcontractorID integer,
  FphoneType integer, overdue boolean, sentToDunningBureau boolean,
  FamountOfVehicles integer, Ftype integer)
RETURNS SETOF erp.billingContactData AS
$$
DECLARE
  customer record;
  phone record;
  phoneData erp.billingContactData%rowtype;
  restrictionFilter integer := CASE WHEN sentToDunningBureau THEN 1 ELSE 0 END;
  lastSequence integer := 0;
  query  varchar;
BEGIN
  IF (FphoneType IS NULL) THEN
    FphoneType := 0;
  END IF;
  IF (Ftype IS NULL) THEN
    Ftype := 1;
  END IF;

  -- Monta a consulta baseada no tipo de filtro
  query := CASE
    WHEN Ftype = 1 AND overdue = TRUE THEN
      -- Obtemos a relação de clientes que possuem valores em aberto à
      -- pelo menos 3 dias
      format('
        SELECT C.name,
               I.customerID AS id,
               I.subsidiaryID,
               ''''::varchar(100) AS comment,
               ''{}''::json AS complement
          FROM erp.payments AS P
         INNER JOIN erp.invoices AS I USING (invoiceID)
         INNER JOIN erp.entities AS C ON (I.customerID = C.entityID)
         WHERE P.paymentSituationID = 1
           AND P.dueDate < (CURRENT_DATE - interval ''3 days'')
           AND (P.restrictionid >> 2) & 1 = %s
           AND P.contractorID = %s
         GROUP BY name, customerID, subsidiaryID
         ORDER BY name, customerID, subsidiaryID',
        restrictionFilter, FcontractorID
      )
    WHEN Ftype = 1 AND overdue = FALSE THEN
      -- Obtemos a relação de clientes que possuem veículos ativos
      format('
        WITH customers AS (
          SELECT E.name,
                 CT.customerID,
                 CT.subsidiaryID
            FROM erp.contracts AS CT
           INNER JOIN erp.entities AS E ON (CT.customerID = E.entityID)
           WHERE CT.contractorID = %s
             AND CT.active
             AND CT.endDate IS NULL
             AND E.entitytypeid IN (1, 2)
           GROUP BY E.name, CT.customerID, CT.subsidiaryID
        )
        SELECT C.name,
               C.customerID AS id,
               C.subsidiaryID,
               COUNT(DISTINCT V.vehicleID) AS amountOfActiveVehicles,
               ''''::varchar(100) AS comment,
               ''{}''::json AS complement
          FROM customers AS C
         INNER JOIN erp.vehicles AS V ON (C.customerID = V.customerID)
         INNER JOIN erp.equipments AS E ON (V.vehicleID = E.vehicleID AND E.storageLocation = ''Installed'' AND E.customerPayerID = C.customerID)
         GROUP BY C.name, C.customerID, C.subsidiaryID
        HAVING COUNT(*) >= %s
         ORDER BY C.name, C.customerID;',
        FcontractorID, FamountOfVehicles
      )
    WHEN Ftype = 2 THEN
      -- Obtemos a relação de associados ativos    
      format('
        SELECT affiliated.name,
               affiliation.customerID AS id,
               affiliation.subsidiaryID,
               association.name::varchar(100) AS comment,
               ''{}''::json AS complement
          FROM erp.entities AS association
         INNER JOIN erp.subsidiaries AS associationUnity ON (association.entityID = associationUnity.entityID)
         INNER JOIN (
          SELECT DISTINCT associationID,
                 associationUnityID,
                 customerID,
                 subsidiaryID
            FROM erp.affiliations
           WHERE unjoinedAt IS NULL) AS affiliation
            ON (association.entityID = affiliation.associationID AND associationUnity.subsidiaryID = associationUnityID)
         INNER JOIN erp.entities AS affiliated
            ON (affiliation.customerID = affiliated.entityID)
         INNER JOIN erp.subsidiaries AS affiliatedUnity
            ON (affiliated.entityID = affiliatedUnity.entityID AND affiliation.subsidiaryID = affiliatedUnity.subsidiaryID)
         WHERE association.customer = true AND association.contractorID = %s;',
        FcontractorID
      )
    WHEN Ftype = 4 THEN
      -- Obtemos a relação de boletos à vencer até o final do mês corrente
      format('
        SELECT C.name,
               I.customerID AS id,
               I.subsidiaryID,
               P.paymentID::varchar AS comment,
               ''{}''::json AS complement
          FROM erp.payments AS P
         INNER JOIN erp.invoices AS I USING (invoiceID)
         INNER JOIN erp.entities AS C ON (I.customerID = C.entityID)
         WHERE P.paymentSituationID = 1
           AND P.dueDate BETWEEN CURRENT_DATE AND (date_trunc(''MONTH'', CURRENT_DATE) + INTERVAL ''1 month'')::DATE
           AND P.contractorID = %s
        ORDER BY name, customerID, subsidiaryID',
        FcontractorID
      )
    WHEN Ftype = 5 THEN
      -- Obtemos a relação de associados bloqueados da principal
      format('
        SELECT C.name,
               A.customerID AS id,
               A.subsidiaryID,
               ''''::varchar(100) AS comment,
               ''{}''::json AS complement
          FROM erp.affiliations AS A
         INNER JOIN erp.entities AS C ON (A.customerID = C.entityID)
         WHERE A.unjoinedAt IS NULL
           AND C.contractorID = %s
           AND A.associationID = 1486
           AND EXISTS (
                 SELECT 1
                   FROM erp.affiliateBlocking AS B
                  WHERE B.customerID = A.customerID
                    AND B.unblockedAt IS NULL
                    AND B.associationID = 1486)
           AND NOT EXISTS (
                 SELECT 1
                   FROM erp.users AS U
                 WHERE U.entityID = A.customerID
                   AND U.blocked = TRUE)
         ORDER BY name, customerID, subsidiaryID',
        FcontractorID
      )
    WHEN Ftype = 6 THEN
      -- Obtemos a relação de rastreadore com falha de comunicação à
      -- pelo menos 24h
      format('
        WITH equipmentsWithoutCommunication AS (
          SELECT equipment.serialNumber,
                 model.name AS model,
                 brand.name AS brand,
                 simcard.iccid,
                 simcard.phoneNumber,
                 operator.name AS operator,
                 vehicle.plate,
                 equipment.lastCommunication
            FROM erp.equipments AS equipment
           INNER JOIN erp.equipmentModels AS model USING (equipmentModelID)
           INNER JOIN erp.equipmentBrands AS brand USING (equipmentBrandID)
           INNER JOIN erp.vehicles AS vehicle USING (vehicleID)
            LEFT JOIN erp.simcards AS simcard ON (equipment.equipmentID = simcard.equipmentID)
            LEFT JOIN erp.mobileOperators AS operator ON (simcard.mobileOperatorID = operator.mobileOperatorID)
           WHERE equipment.contractorID = %s
             AND equipment.storageLocation = ''Installed''
             AND (equipment.lastCommunication < CURRENT_TIMESTAMP - interval ''6h'')
        )
        SELECT serialNumber AS name,
               (brand || '' / '' || model)::varchar(100) AS comment,
               phoneNumber,
               json_agg(json_build_object(''iccid'', iccid, ''plate'', plate, ''lastCommunication'', lastCommunication)) AS complement
          FROM equipmentsWithoutCommunication
         GROUP BY serialNumber, brand, model, phoneNumber;',
        FcontractorID
      )
    ELSE
      -- Obtemos a relação de clientes com veículos que possuam
      -- rastreador com falha de comunicação a pelo menos 48h;
      format('
        WITH equipmentsWithoutCommunication AS (
          SELECT vehicle.customerID,
                 vehicle.subsidiaryID,
                 customer.name AS customerName,
                 equipment.customerPayerID,
                 equipment.subsidiaryPayerID,
                 CASE
                   WHEN vehicle.customerID <> equipment.customerPayerID THEN payer.name
                   ELSE ''''
                 END AS payerName,
                 vehicle.plate,
                 equipment.lastCommunication
            FROM erp.equipments AS equipment
           INNER JOIN erp.vehicles AS vehicle USING (vehicleID)
           INNER JOIN erp.installations AS item USING (installationID)
           INNER JOIN erp.contracts AS contract USING (contractID)
           INNER JOIN erp.entities AS payer ON (equipment.customerPayerID = payer.entityID)
           INNER JOIN erp.entities AS customer ON (vehicle.customerID = customer.entityID)
           WHERE equipment.contractorID = %s
             AND equipment.storageLocation = ''Installed''
             AND (equipment.lastCommunication < CURRENT_TIMESTAMP - interval ''48h'')
             AND item.enddate IS NULL
             AND contract.active = TRUE
             AND contract.enddate IS NULL
             AND payer.blocked = FALSE
             AND customer.blocked = FALSE
             AND vehicle.blocked = FALSE
        )
        SELECT customerName AS name,
               customerID AS id,
               subsidiaryID,
               payerName::varchar(100) AS comment,
               json_agg(json_build_object(''plate'', plate, ''lastCommunication'', lastCommunication)) AS complement
          FROM equipmentsWithoutCommunication
         GROUP BY customerID, subsidiaryID, customerName, customerPayerID, subsidiaryPayerID, payerName;',
        FcontractorID
      )
  END;
  -- RAISE NOTICE 'SQL: %', query;

  -- Recupera os a relação de clientes
  FOR customer IN
    EXECUTE query
  LOOP
    phoneData.name := customer.name::varchar(100);
    phoneData.comment := customer.comment::varchar(100);
    phoneData.complement := customer.complement;
    lastSequence   := 0;

    IF (Ftype = 6) THEN
      phoneData.sequence    := 1;
      phoneData.phoneType   := 2;
      phoneData.phoneNumber := customer.phoneNumber;

      RETURN NEXT phoneData;
    ELSE
      -- Para cada cliente, recupera os telefones principais
      FOR phone IN
        SELECT P.phoneNumber AS number,
              T.name AS type,
              ROW_NUMBER () OVER (ORDER BY P.entityID) AS sequence
          FROM erp.phones AS P
        INNER JOIN erp.phoneTypes AS T USING (phoneTypeID)
        WHERE P.entityID = customer.id
          AND P.subsidiaryID = customer.subsidiaryID
          AND P.phoneTypeID = CASE
                                WHEN FphoneType > 0 THEN FphoneType
                                ELSE P.phoneTypeID
                              END
        ORDER BY P.phoneid
      LOOP
        lastSequence          := phone.sequence;
        phoneData.sequence    := phone.sequence;
        phoneData.phoneType   := phone.type;
        phoneData.phoneNumber := phone.number;

        RETURN NEXT phoneData;
      END LOOP;

      -- Agora recupera os telefones de contatos adicionais
      FOR phone IN
        SELECT DISTINCT ON (M.mailingAddressID) mailingAddressID,
              M.phoneNumber AS number,
              T.name AS type,
              ROW_NUMBER () OVER (ORDER BY M.entityID) AS sequence
          FROM erp.mailingAddresses AS M
        INNER JOIN erp.phoneTypes AS T USING (phoneTypeID)
        INNER JOIN erp.actionsPerProfiles AS A USING (mailingProfileID)
        WHERE M.entityID = customer.id
          AND M.subsidiaryID = customer.subsidiaryID
          AND CASE
                WHEN Ftype = 4 THEN A.systemActionID IN (3, 4)
                WHEN overdue THEN A.systemActionID IN (3, 4)
                ELSE A.systemActionID IN (1, 2, 3, 4, 5)
              END
          AND coalesce(M.phoneNumber, '') <> ''
          AND M.phoneTypeID = CASE
                                WHEN FphoneType > 0 THEN FphoneType
                                ELSE M.phoneTypeID
                              END
        ORDER BY M.mailingAddressID
      LOOP
        phoneData.sequence    := lastSequence + phone.sequence;
        phoneData.phoneType   := phone.type;
        phoneData.phoneNumber := phone.number;

        RETURN NEXT phoneData;
      END LOOP;

    END IF;
  END LOOP;
END
$$
LANGUAGE 'plpgsql';

-- Exemplos
-- 1. Selecionar todos os telefones de clientes ativos (independente da
--    quantidade de veículos ativos)
-- SELECT * FROM erp.getBillingPhoneList(1, 0, false, false, 0, 1);
-- 2. Selecionar todos os telefones de clientes ativos com ao menos 5
--    veículos ativos
-- SELECT * FROM erp.getBillingPhoneList(1, 0, false, false, 5, 1);
-- 3. Selecionar todos os telefones de clientes com valores abertos que
-- ainda não tenham sido enviados à empresa de cobranças
-- SELECT * FROM erp.getBillingPhoneList(1, 0, true, false, 0, 1);
-- 4. Selecionar todos os telefones de clientes com valores abertos que
-- tenham sido enviados à empresa de cobranças
-- SELECT * FROM erp.getBillingPhoneList(1, 0, true, true, 0, 1);
-- 5. Selecionar apenas o primeiro número de celular de cada cliente
--    ativo com ao menos 1 veículo igualmente ativo
-- SELECT * FROM erp.getBillingPhoneList(1, 2, false, false, 1, 1);
-- 6. Selecionar os celulares de associados ativos
-- SELECT * FROM erp.getBillingPhoneList(1, 2, false, false, 0, 2);
-- 7. Selecionar os telefones de clientes com boletos à vencer
-- SELECT * FROM erp.getBillingPhoneList(1, 2, false, false, 0, 4);
-- 8. Selecionar os celulares de clientes com veículos sem comunicação
--    à mais de 48h
-- SELECT * FROM erp.getBillingPhoneList(1, 0, false, false, 0, 3);
-- 9. Selecionar os celulares de rastreadores sem comunicação à mais de
--    6h
-- SELECT * FROM erp.getBillingPhoneList(1, 0, false, false, 0, 6);

-- ---------------------------------------------------------------------
-- Registra o pagamento de uma cobrança
-- ---------------------------------------------------------------------
-- Stored Procedure que registra o pagamento de uma fatura manualmente.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.pay(FcontractorID integer, FpaymentID int,
  FpaymentMethodID int, FpaidDate date, FcreditDate date,
  FpaidValue numeric(12,2), Freasons text)
RETURNS boolean AS
$$
DECLARE
  -- A situação de baixa do boleto sendo confirmado
  FdroppedTypeID  integer;
  -- A situação de restrição do título a ser aplicada
  FrestrictionID  integer;
  -- Os dados das restrições existentes no título
  restriction  record;
  -- Os dados da instrução de registro do boleto
  dispatchInstruction  record;
  -- A instrução a ser gerada ao banco em caso de boletos
  newInstruction  integer;
BEGIN
  -- Precisamos obter as informações de restrição aplicadas ao título
  SELECT INTO restriction
         restrictionID AS id,
         ((payments.restrictionid >> 0) & 1 = 1) AS protested,
         ((payments.restrictionid >> 1) & 1 = 1) AS creditBlocked
    FROM erp.payments
   WHERE contractorid = FcontractorID
     AND paymentID = FpaymentID;

  -- Inicialmente, a restrição se mantém inalterada
  FrestrictionID := restriction.id;

  -- Executamos a instrução de confirmar o pagamento em função do meio
  -- de pagamento utilizado
  CASE FpaymentMethodID
    WHEN 5 THEN
      -- Boleto bancário

      -- Precisamos obter qual a situação do boleto atualmente
      SELECT INTO FdroppedTypeID
             droppedTypeID
        FROM erp.bankingBilletPayments
       WHERE contractorid = FcontractorID
         AND paymentID = FpaymentID;

      IF NOT FOUND THEN
        -- Disparamos uma exceção, pois não foi possível encontrar a
        -- informação do pagamento
        RAISE EXCEPTION 'Não foi possível obter os dados do pagamento % para realizar a baixa',
          FpaymentID
        USING HINT = 'Por favor, verifique se o pagamento foi registrado como boleto';
      END IF;

      -- Em função da situação do boleto, toma as medidas necessárias
      -- para liquidar o título
      CASE FdroppedTypeID
        WHEN 1 THEN
          -- O boleto ainda não foi registrado, então verificamos se a
          -- instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de pagamento existe, verificamos se ela foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para baixar o boleto
              newInstruction := 2;

              -- Precisamos lidar com as restrições
              IF (restriction.protested) THEN
                -- Precisamos enviar uma instrução para sustar o
                -- protesto e baixar o título
                newInstruction := 8;

                -- Retiramos a informação de protesto do registro
                FrestrictionID := (FrestrictionID & ~1);
              END IF;

              IF (restriction.creditBlocked) THEN
                -- Precisamos enviar uma instrução para sustar a
                -- negativação e baixar o título
                newInstruction := 11;

                -- Retiramos a informação de negativação do registro
                FrestrictionID := (FrestrictionID & ~2);
              END IF;
            ELSE
              -- Precisamos excluir a instrução para que o boleto não
              -- seja mais registrado
              newInstruction := 0;
              DELETE FROM erp.billetDispatching
               WHERE dispatchingID = dispatchInstruction.id;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
        WHEN 4 THEN
          -- Baixado por decurso de prazo

          -- O título não existe mais na instituição, então não precisa
          -- lidar com o banco
          newInstruction := 0;
        ELSE
          -- Demais situações, simplesmente baixa o título

          -- Verificamos se a instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de registro existe, verificamos se foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para baixar o título
              newInstruction := 2;
            ELSE
              -- Precisamos excluir a instrução para que o boleto não
              -- seja mais registrado
              newInstruction := 0;
              DELETE FROM erp.billetDispatching
               WHERE dispatchingID = dispatchInstruction.id;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
      END CASE;

      -- Realizamos a baixa do título
      -- RAISE NOTICE 'Atualizando boleto';
      UPDATE erp.bankingBilletPayments
         SET paymentSituationID = 2,
             paidDate = FpaidDate,
             paidValue = FpaidValue,
             latePaymentInterest = 0.00,
             fineValue = 0.00,
             creditDate = FcreditDate,
             droppedTypeID = 3,
             restrictionID = FrestrictionID,
             hasError = false,
             reasonForError = null
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      IF (newInstruction > 0) THEN
        -- Inserimos a instrução para o banco
        -- RAISE NOTICE 'Inserindo evento para despachar ao banco';
        INSERT INTO erp.billetDispatching (contractorID, paymentID,
               instructionID, reasons)
        VALUES (FcontractorID, FpaymentID, newInstruction, Freasons);
      ELSE
        -- Inserimos a ocorrência de liquidação do título
        -- RAISE NOTICE 'Inserindo ocorrência no boleto';
        INSERT INTO erp.bankingBilletOccurrences (contractorID, paymentID,
               occurrenceTypeID, occurrenceCode, description, reasons,
               occurrenceDate, tariffValue)
        VALUES (FcontractorID, FpaymentID, 1, 0, 'Liquidação informada',
                Freasons, FpaidDate, 0.00);
      END IF;
    ELSE
      -- Demais pagamentos

      -- Retiramos a informação de protesto do registro
      FrestrictionID := (FrestrictionID & ~1);
      -- Retiramos a informação de negativação do registro
      FrestrictionID := (FrestrictionID & ~2);

      -- Apenas realizamos a baixa do título
      -- RAISE NOTICE 'Atualizando pagamento';
      UPDATE erp.payments
         SET paymentSituationID = 2,
             paidDate = FpaidDate,
             paidValue = FpaidValue,
             latePaymentInterest = 0.00,
             fineValue = 0.00,
             creditDate = FcreditDate,
             restrictionID = FrestrictionID
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      -- Inserimos a ocorrência de liquidação do título
      -- RAISE NOTICE 'Inserindo ocorrência no pagamento';
      INSERT INTO erp.paymentOccurrences (contractorID, paymentID,
             occurrenceTypeID, description, reasons, occurrenceDate)
      VALUES (FcontractorID, FpaymentID, 5, 'Liquidação informada',
              Freasons, FpaidDate);
  END CASE;

  -- RAISE NOTICE 'Inserindo e-mail do recibo';
  INSERT INTO erp.emailsQueue
         (contractorID, mailEventID, originRecordID, recordsOnScope)
  VALUES (FcontractorID, 4, FpaymentID, Array[FpaymentID]);

  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Registra a concessão de abatimento em uma cobrança
-- ---------------------------------------------------------------------
-- Stored Procedure que registra a concessão de abatimento de uma
-- fatura.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.abatement(FcontractorID integer,
  FpaymentID int, FpaymentMethodID int, FabatementValue numeric(12,2),
  Freasons text)
RETURNS boolean AS
$$
DECLARE
  -- Os dados do pagamento ao qual estamos concedendo abatimento
  FdroppedTypeID  integer;
  -- Os dados da instrução de registro do boleto
  dispatchInstruction  record;
  -- A instrução a ser gerada ao banco em caso de boletos
  newInstruction  integer;
BEGIN
  -- Executamos a instrução de conceder um abatimento em função do meio
  -- de pagamento utilizado
  CASE FpaymentMethodID
    WHEN 5 THEN
      -- Boleto bancário
      
      -- Precisamos obter qual a situação do boleto atualmente
      SELECT INTO FdroppedTypeID
             droppedTypeID
        FROM erp.bankingBilletPayments
       WHERE contractorid = FcontractorID
         AND paymentID = FpaymentID;

      IF NOT FOUND THEN
        -- Disparamos uma exceção, pois não foi possível encontrar a
        -- informação do pagamento
        RAISE EXCEPTION 'Não foi possível obter os dados do pagamento % para conceder o abatimento',
          FpaymentID
        USING HINT = 'Por favor, verifique se o pagamento foi registrado como boleto';
      END IF;

      -- Em função da situação do boleto, toma as medidas necessárias
      -- para conceder o abatimento
      CASE FdroppedTypeID
        WHEN 1 THEN
          -- O boleto ainda não foi registrado, então verificamos se a
          -- instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de registro existe, verificamos se foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para conceder o
              -- abatimento
              newInstruction := 3;
            ELSE
              -- Não precisamos fazer nada pois o boleto ainda não foi
              -- registrado
              newInstruction := 0;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
        WHEN 4 THEN
          -- Baixado por decurso de prazo

          -- O título não existe mais na instituição, então não precisa
          -- lidar com o banco
          newInstruction := 0;
        ELSE
          -- Demais situações, simplesmente concede o abatimento

          -- Verificamos se a instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de registro existe, verificamos se foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para conceder o
              -- abatimento
              newInstruction := 3;
            ELSE
              -- Não precisamos fazer nada pois o boleto ainda não foi
              -- registrado
              newInstruction := 0;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
      END CASE;

      -- Realizamos a concessão do abatimento
      -- RAISE NOTICE 'Atualizando boleto';
      UPDATE erp.bankingBilletPayments
         SET abatementValue = FabatementValue
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      IF (newInstruction > 0) THEN
        -- Inserimos a instrução para o banco
        -- RAISE NOTICE 'Inserindo evento para despachar ao banco';
        INSERT INTO erp.billetDispatching (contractorID, paymentID,
               instructionID, reasons)
        VALUES (FcontractorID, FpaymentID, newInstruction, Freasons);
      ELSE
        -- Inserimos a ocorrência de concessão de abatimento
        -- RAISE NOTICE 'Inserindo concessão de abatimento no boleto';
        INSERT INTO erp.bankingBilletOccurrences (contractorID, paymentID,
               occurrenceTypeID, occurrenceCode, description, reasons,
               occurrenceDate, tariffValue)
        VALUES (FcontractorID, FpaymentID, 11, 0, 'Abatimento concedido',
                Freasons, CURRENT_DATE, 0.00);
      END IF;
    ELSE
      -- Demais pagamentos

      -- Apenas realizamos a concessão do abatimento
      -- RAISE NOTICE 'Incluindo abatimento';
      UPDATE erp.payments
         SET abatementValue = FabatementValue
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      -- Inserimos a ocorrência de concessão de abatimento
      -- RAISE NOTICE 'Inserindo concessão de abatimento no pagamento';
      INSERT INTO erp.paymentOccurrences (contractorID, paymentID,
             occurrenceTypeID, description, reasons, occurrenceDate)
      VALUES (FcontractorID, FpaymentID, 2, 'Abatimento concedido',
              Freasons, CURRENT_DATE);
  END CASE;

  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Registra o cancelamento da concessão de abatimento em uma cobrança
-- ---------------------------------------------------------------------
-- Stored Procedure que cancela a concessão de abatimento de uma fatura.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.ungrantAbatement(FcontractorID integer,
  FpaymentID int, FpaymentMethodID int, Freasons text)
RETURNS boolean AS
$$
DECLARE
  -- Os dados do pagamento do qual estamos retirando o abatimento
  FdroppedTypeID  integer;
  -- Os dados da instrução de registro do boleto
  dispatchInstruction  record;
  -- A instrução a ser gerada ao banco em caso de boletos
  newInstruction  integer;
BEGIN
  -- Executamos a instrução de cancelar o abatimento em função do meio
  -- de pagamento utilizado
  CASE FpaymentMethodID
    WHEN 5 THEN
      -- Boleto bancário
      
      -- Precisamos obter qual a situação do boleto atualmente
      SELECT INTO FdroppedTypeID
             droppedTypeID
        FROM erp.bankingBilletPayments
       WHERE contractorid = FcontractorID
         AND paymentID = FpaymentID;

      IF NOT FOUND THEN
        -- Disparamos uma exceção, pois não foi possível encontrar a
        -- informação do pagamento
        RAISE EXCEPTION 'Não foi possível obter os dados do pagamento % para cancelar o abatimento',
          FpaymentID
        USING HINT = 'Por favor, verifique se o pagamento foi registrado como boleto';
      END IF;

      -- Em função da situação do boleto, toma as medidas necessárias
      -- para cancelar o abatimento
      CASE FdroppedTypeID
        WHEN 1 THEN
          -- O boleto ainda não foi registrado, então verificamos se a
          -- instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de registro existe, verificamos se foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para cancelar o
              -- abatimento
              newInstruction := 4;
            ELSE
              -- Não precisamos fazer nada pois o boleto ainda não foi
              -- registrado
              newInstruction := 0;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
        WHEN 4 THEN
          -- Baixado por decurso de prazo

          -- O título não existe mais na instituição, então não precisa
          -- lidar com o banco
          newInstruction := 0;
        ELSE
          -- Demais situações, simplesmente cancela o abatimento

          -- Verificamos se a instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de registro existe, verificamos se foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para cancelar o
              -- abatimento
              newInstruction := 4;
            ELSE
              -- Não precisamos fazer nada pois o boleto ainda não foi
              -- registrado
              newInstruction := 0;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
      END CASE;

      -- Realizamos o cancelamento do abatimento
      -- RAISE NOTICE 'Atualizando boleto';
      UPDATE erp.bankingBilletPayments
         SET abatementValue = 0.00
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      IF (newInstruction > 0) THEN
        -- Inserimos a instrução para o banco
        -- RAISE NOTICE 'Inserindo evento para despachar ao banco';
        INSERT INTO erp.billetDispatching (contractorID, paymentID,
               instructionID, reasons)
        VALUES (FcontractorID, FpaymentID, newInstruction, Freasons);
      ELSE
        -- Inserimos a ocorrência de cancelamento de abatimento
        -- RAISE NOTICE 'Inserindo cancelamento de abatimento no boleto';
        INSERT INTO erp.bankingBilletOccurrences (contractorID, paymentID,
               occurrenceTypeID, occurrenceCode, description, reasons,
               occurrenceDate, tariffValue)
        VALUES (FcontractorID, FpaymentID, 12, 0, 'Abatimento cancelado',
                Freasons, CURRENT_DATE, 0.00);
      END IF;
    ELSE
      -- Demais pagamentos

      -- Apenas realizamos o cancelamento do abatimento
      -- RAISE NOTICE 'Cancelando abatimento';
      UPDATE erp.payments
         SET abatementValue = 0.00
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      -- Inserimos a ocorrência de cancelamento do abatimento
      -- RAISE NOTICE 'Inserindo cancelamento de abatimento';
      INSERT INTO erp.paymentOccurrences (contractorID, paymentID,
             occurrenceTypeID, description, reasons, occurrenceDate)
      VALUES (FcontractorID, FpaymentID, 2, 'Abatimento cancelado',
              Freasons, CURRENT_DATE);
  END CASE;

  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Registra a modificação da data de vencimento em uma cobrança
-- ---------------------------------------------------------------------
-- Stored Procedure que registra a modificação da data de vencimento de
-- uma fatura.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.changeDuedate(FcontractorID integer,
  FpaymentID int, FpaymentMethodID int, FnewDuedate date,
  Freasons text)
RETURNS boolean AS
$$
DECLARE
  -- Os dados do pagamento do qual iremos modificar a data de vencimento
  FdroppedTypeID  integer;
  -- Os dados da instrução de registro do boleto
  dispatchInstruction  record;
  -- A instrução a ser gerada ao banco em caso de boletos
  newInstruction  integer;
BEGIN
  -- Executamos a instrução de alterar a data de vencimento em função do
  -- meio de pagamento utilizado
  CASE FpaymentMethodID
    WHEN 5 THEN
      -- Boleto bancário
      
      -- Precisamos obter qual a situação do boleto atualmente
      SELECT INTO FdroppedTypeID
             droppedTypeID
        FROM erp.bankingBilletPayments
       WHERE contractorid = FcontractorID
         AND paymentID = FpaymentID;

      IF NOT FOUND THEN
        -- Disparamos uma exceção, pois não foi possível encontrar a
        -- informação do pagamento
        RAISE EXCEPTION 'Não foi possível obter os dados do pagamento % para alterar o vencimento',
          FpaymentID
        USING HINT = 'Por favor, verifique se o pagamento foi registrado como boleto';
      END IF;

      -- Em função da situação do boleto, toma as medidas necessárias
      -- para modificar o vencimento do título
      CASE FdroppedTypeID
        WHEN 1 THEN
          -- O boleto ainda não foi registrado, então verificamos se a
          -- instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de registro existe, verificamos se foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para modifiar a data de
              -- vencimento
              newInstruction := 5;
            ELSE
              -- Não precisamos fazer nada pois o boleto ainda não foi
              -- registrado
              newInstruction := 0;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
        WHEN 4 THEN
          -- Baixado por decurso de prazo

          -- O título não existe mais na instituição, então não precisa
          -- lidar com o banco
          newInstruction := 0;
        ELSE
          -- Demais situações, simplesmente modifica a data de
          -- vencimento

          -- Verificamos se a instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de registro existe, verificamos se foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para alterar a data de
              -- vencimento
              newInstruction := 5;
            ELSE
              -- Não precisamos fazer nada pois o boleto ainda não foi
              -- registrado
              newInstruction := 0;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
      END CASE;

      -- Realizamos a alteração do vencimento do título
      -- RAISE NOTICE 'Atualizando boleto';
      UPDATE erp.bankingBilletPayments
         SET duedate = FnewDuedate
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      IF (newInstruction > 0) THEN
        -- Inserimos a instrução para o banco
        -- RAISE NOTICE 'Inserindo evento para despachar ao banco';
        INSERT INTO erp.billetDispatching (contractorID, paymentID,
               instructionID, reasons)
        VALUES (FcontractorID, FpaymentID, newInstruction, Freasons);
      ELSE
        -- Inserimos a ocorrência de modificação da data de vencimento
        -- RAISE NOTICE 'Inserindo modificação da data de vencimento';
        INSERT INTO erp.bankingBilletOccurrences (contractorID, paymentID,
               occurrenceTypeID, occurrenceCode, description, reasons,
               occurrenceDate, tariffValue)
        VALUES (FcontractorID, FpaymentID, 4, 0, 'Inclusão de negativação',
                Freasons, CURRENT_DATE, 0.00);
      END IF;
    ELSE
      -- Demais pagamentos

      -- Apenas realizamos a alteração da data de vencimento
      -- RAISE NOTICE 'Alterando vencimento do título';
      UPDATE erp.payments
         SET duedate = FnewDuedate
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      -- Inserimos a ocorrência de alteração do vencimento
      -- RAISE NOTICE 'Inserindo ocorrência de alteração do vencimento no pagamento';
      INSERT INTO erp.paymentOccurrences (contractorID, paymentID,
             occurrenceTypeID, description, reasons, occurrenceDate)
      VALUES (FcontractorID, FpaymentID, 2, 'Inclusão de negativação',
              Freasons, CURRENT_DATE);
  END CASE;

  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Registra o cancelamento do título em uma cobrança
-- ---------------------------------------------------------------------
-- Stored Procedure que cancela a cobrança de uma fatura.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.cancelPayment(FcontractorID integer,
  FpaymentID int, FpaymentMethodID int, Freasons text)
RETURNS boolean AS
$$
DECLARE
  -- Os dados do pagamento a ser cancelado
  FdroppedTypeID  integer;
  -- A situação de restrição do título a ser aplicada
  FrestrictionID  integer;
  -- Os dados das restrições existentes no título
  restriction  record;
  -- Os dados da instrução de registro do boleto
  dispatchInstruction  record;
  -- A instrução a ser gerada ao banco em caso de boletos
  newInstruction  integer;
BEGIN
  -- Precisamos obter as informações de restrição aplicadas ao título
  SELECT INTO restriction
         restrictionID AS id,
         ((payments.restrictionid >> 0) & 1 = 1) AS protested,
         ((payments.restrictionid >> 1) & 1 = 1) AS creditBlocked
    FROM erp.payments
   WHERE contractorid = FcontractorID
     AND paymentID = FpaymentID;

  -- Inicialmente, a restrição se mantém inalterada
  FrestrictionID := restriction.id;

  -- Executamos a instrução de cancelar o título em função do meio de
  -- pagamento utilizado
  CASE FpaymentMethodID
    WHEN 5 THEN
      -- Boleto bancário
      
      -- Precisamos obter qual a situação do boleto atualmente
      SELECT INTO FdroppedTypeID
             droppedTypeID
        FROM erp.bankingBilletPayments
       WHERE contractorid = FcontractorID
         AND paymentID = FpaymentID;

      IF NOT FOUND THEN
        -- Disparamos uma exceção, pois não foi possível encontrar a
        -- informação do pagamento
        RAISE EXCEPTION 'Não foi possível obter os dados do pagamento % a ser cancelado.',
          FpaymentID
        USING HINT = 'Por favor, verifique se o pagamento foi registrado como boleto';
      END IF;

      -- Em função da situação do boleto, toma as medidas necessárias
      -- para cancelar o abatimento
      CASE FdroppedTypeID
        WHEN 1 THEN
          -- O boleto ainda não foi registrado, então verificamos se a
          -- instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de registro existe, verificamos se foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para cancelar o título
              newInstruction := 2;

              -- Precisamos lidar com as restrições
              IF (restriction.protested) THEN
                -- Precisamos enviar uma instrução para sustar o
                -- protesto e baixar o título
                newInstruction := 8;

                -- Retiramos a informação de protesto do registro
                FrestrictionID := (FrestrictionID & ~1);
              END IF;

              IF (restriction.creditBlocked) THEN
                -- Precisamos enviar uma instrução para sustar a
                -- negativação e baixar o título
                newInstruction := 11;

                -- Retiramos a informação de negativação do registro
                FrestrictionID := (FrestrictionID & ~2);
              END IF;
            ELSE
              -- Precisamos excluir a instrução para que o boleto não
              -- seja mais registrado
              newInstruction := 0;
              DELETE FROM erp.billetDispatching
               WHERE dispatchingID = dispatchInstruction.id;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
        WHEN 4 THEN
          -- Baixado por decurso de prazo

          -- O título não existe mais na instituição, então não precisa
          -- lidar com o banco
          newInstruction := 0;
        ELSE
          -- Demais situações, simplesmente cancela o título

          -- Verificamos se a instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de registro existe, verificamos se foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para cancelar o título
              newInstruction := 2;
            ELSE
              -- Precisamos excluir a instrução para que o boleto não
              -- seja mais registrado
              newInstruction := 0;
              DELETE FROM erp.billetDispatching
               WHERE dispatchingID = dispatchInstruction.id;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
      END CASE;

      -- Realizamos o cancelamento do título
      -- RAISE NOTICE 'Cancelando o boleto';
      UPDATE erp.bankingBilletPayments
         SET paymentSituationID = 3,
             droppedTypeID = 5,
             restrictionID = FrestrictionID
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      IF (newInstruction > 0) THEN
        -- Inserimos a instrução para o banco
        -- RAISE NOTICE 'Inserindo evento para despachar ao banco';
        INSERT INTO erp.billetDispatching (contractorID, paymentID,
               instructionID, reasons)
        VALUES (FcontractorID, FpaymentID, newInstruction, Freasons);
      ELSE
        -- Inserimos a ocorrência de cancelamento do título
        -- RAISE NOTICE 'Inserindo cancelamento do boleto';
        INSERT INTO erp.bankingBilletOccurrences (contractorID, paymentID,
               occurrenceTypeID, occurrenceCode, description, reasons,
               occurrenceDate, tariffValue)
        VALUES (FcontractorID, FpaymentID, 2, 0, 'Cancelamento da cobrança',
                Freasons, CURRENT_DATE, 0.00);
      END IF;
    ELSE
      -- Demais pagamentos

      -- Retiramos a informação de protesto do registro
      FrestrictionID := (FrestrictionID & ~1);
      -- Retiramos a informação de negativação do registro
      FrestrictionID := (FrestrictionID & ~2);

      -- Apenas realizamos o cancelamento do título
      -- RAISE NOTICE 'Cancelando cobrança';
      UPDATE erp.payments
         SET paymentSituationID = 3,
             restrictionID = FrestrictionID
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      -- Inserimos a ocorrência de cancelamento do título
      -- RAISE NOTICE 'Inserindo cancelamento da cobrança';
      INSERT INTO erp.paymentOccurrences (contractorID, paymentID,
             occurrenceTypeID, description, reasons, occurrenceDate)
      VALUES (FcontractorID, FpaymentID, 4, 'Cancelamento da cobrança',
              Freasons, CURRENT_DATE);
  END CASE;

  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Registra a inclusão de negativação no título
-- ---------------------------------------------------------------------
-- Stored Procedure que informa a inclusão do título no serviço de
-- proteção ao crédito (negativação)
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.creditBlocked(FcontractorID integer,
  FpaymentID int, FpaymentMethodID int, Freasons text)
RETURNS boolean AS
$$
DECLARE
  -- Os dados do pagamento a ser negativado
  FdroppedTypeID  integer;
  -- A situação de restrição do título a ser aplicada
  FrestrictionID  integer;
  -- Os dados das restrições existentes no título
  restriction  record;
  -- Os dados da instrução de registro do boleto
  dispatchInstruction  record;
  -- A instrução a ser gerada ao banco em caso de boletos
  newInstruction  integer;
BEGIN
  -- Precisamos obter as informações de restrição aplicadas ao título
  SELECT INTO restriction
         restrictionID AS id,
         ((payments.restrictionid >> 0) & 1 = 1) AS protested,
         ((payments.restrictionid >> 1) & 1 = 1) AS creditBlocked
    FROM erp.payments
   WHERE contractorid = FcontractorID
     AND paymentID = FpaymentID;

  -- Acrescentamos a informação de negativação na restrição
  FrestrictionID := (restriction.id | 2);

  -- Executamos a instrução de negativar o título em função do meio de
  -- pagamento utilizado
  CASE FpaymentMethodID
    WHEN 5 THEN
      -- Boleto bancário
      
      -- Precisamos obter qual a situação do boleto atualmente
      SELECT INTO FdroppedTypeID
             droppedTypeID
        FROM erp.bankingBilletPayments
       WHERE contractorid = FcontractorID
         AND paymentID = FpaymentID;

      IF NOT FOUND THEN
        -- Disparamos uma exceção, pois não foi possível encontrar a
        -- informação do pagamento
        RAISE EXCEPTION 'Não foi possível obter os dados do pagamento % a ser negativado.',
          FpaymentID
        USING HINT = 'Por favor, verifique se o pagamento foi registrado como boleto';
      END IF;

      -- Em função da situação do boleto, toma as medidas necessárias
      -- para negativar
      CASE FdroppedTypeID
        WHEN 1 THEN
          -- O boleto ainda não foi registrado, então verificamos se a
          -- instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- Precisamos enviar uma instrução para negativar o título
            newInstruction := 10;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
        WHEN 4 THEN
          -- Baixado por decurso de prazo

          -- O título não existe mais na instituição, então não precisa
          -- lidar com o banco
          newInstruction := 0;
        ELSE
          -- Demais situações, simplesmente negativa o título

          -- Verificamos se a instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de registro existe, verificamos se foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para negativar o título
              newInstruction := 10;
            ELSE
              -- Não precisamos fazer nada pois o boleto ainda não foi
              -- registrado
              newInstruction := 0;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
      END CASE;

      -- Realizamos a negativação do título
      -- RAISE NOTICE 'Acrescentando restrição de negativação';
      UPDATE erp.bankingBilletPayments
         SET restrictionID = FrestrictionID
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      IF (newInstruction > 0) THEN
        -- Inserimos a instrução para o banco
        -- RAISE NOTICE 'Inserindo instrução de negativação do boleto';
        INSERT INTO erp.billetDispatching (contractorID, paymentID,
               instructionID, reasons)
        VALUES (FcontractorID, FpaymentID, newInstruction, Freasons);
      ELSE
        -- Inserimos a ocorrência de negativação do título
        -- RAISE NOTICE 'Inserindo negativação do boleto';
        INSERT INTO erp.bankingBilletOccurrences (contractorID, paymentID,
               occurrenceTypeID, occurrenceCode, description, reasons,
               occurrenceDate, tariffValue)
        VALUES (FcontractorID, FpaymentID, 7, 0, 'Inclusão de negativação',
                Freasons, CURRENT_DATE, 0.00);
      END IF;
    ELSE
      -- Demais pagamentos

      -- Realizamos a negativação do título
      -- RAISE NOTICE 'Acrescentando restrição de negativação';
      UPDATE erp.payments
         SET restrictionID = FrestrictionID
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      -- Inserimos a ocorrência de negativação do título
      -- RAISE NOTICE 'Inserindo negativação da cobrança';
      INSERT INTO erp.paymentOccurrences (contractorID, paymentID,
             occurrenceTypeID, description, reasons, occurrenceDate)
      VALUES (FcontractorID, FpaymentID, 8, 'Inclusão de negativação',
              Freasons, CURRENT_DATE);
  END CASE;

  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Registra a retirada do título do serviço de proteção ao crédito
-- ---------------------------------------------------------------------
-- Stored Procedure que exclui a negativação da cobrança de uma fatura.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.creditUnblocked(FcontractorID integer,
  FpaymentID int, FpaymentMethodID int, Freasons text)
RETURNS boolean AS
$$
DECLARE
  -- Os dados do pagamento que precisa ser retirada a negativação
  FdroppedTypeID  integer;
  -- A situação de restrição do título a ser aplicada
  FrestrictionID  integer;
  -- Os dados das restrições existentes no título
  restriction  record;
  -- Os dados da instrução de registro do boleto
  dispatchInstruction  record;
  -- A instrução a ser gerada ao banco em caso de boletos
  newInstruction  integer;
BEGIN
  -- Precisamos obter as informações de restrição aplicadas ao título
  SELECT INTO restriction
         restrictionID AS id,
         ((payments.restrictionid >> 0) & 1 = 1) AS protested,
         ((payments.restrictionid >> 1) & 1 = 1) AS creditBlocked
    FROM erp.payments
   WHERE contractorid = FcontractorID
     AND paymentID = FpaymentID;

  -- Retiramos a informação de negativação na restrição
  FrestrictionID := (restriction.id & ~2);

  -- Executamos a instrução de sustar a negativação do título em função
  -- do meio de pagamento utilizado
  CASE FpaymentMethodID
    WHEN 5 THEN
      -- Boleto bancário
      
      -- Precisamos obter qual a situação do boleto atualmente
      SELECT INTO FdroppedTypeID
             droppedTypeID
        FROM erp.bankingBilletPayments
       WHERE contractorid = FcontractorID
         AND paymentID = FpaymentID;

      IF NOT FOUND THEN
        -- Disparamos uma exceção, pois não foi possível encontrar a
        -- informação do pagamento
        RAISE EXCEPTION 'Não foi possível obter os dados do pagamento % a ser sustada a negativação.',
          FpaymentID
        USING HINT = 'Por favor, verifique se o pagamento foi registrado como boleto';
      END IF;

      -- Em função da situação do boleto, toma as medidas necessárias
      -- para sustar negativação
      CASE FdroppedTypeID
        WHEN 1 THEN
          -- O boleto ainda não foi registrado, então verificamos se a
          -- instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- Precisamos enviar uma instrução para sustar a negativação
            -- do título
            newInstruction := 12;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
        WHEN 4 THEN
          -- Baixado por decurso de prazo

          -- O título não existe mais na instituição, então não precisa
          -- lidar com o banco
          newInstruction := 0;
        ELSE
          -- Demais situações, simplesmente suspende a negativação do
          -- título

          -- Verificamos se a instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de registro existe, verificamos se foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para negativar o título
              newInstruction := 12;
            ELSE
              -- Não precisamos fazer nada pois o boleto ainda não foi
              -- registrado
              newInstruction := 0;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
      END CASE;

      -- Realizamos a sustação da negativação do título
      -- RAISE NOTICE 'Retirando restrição de negativação';
      UPDATE erp.bankingBilletPayments
         SET restrictionID = FrestrictionID
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      IF (newInstruction > 0) THEN
        -- Inserimos a instrução para o banco
        -- RAISE NOTICE 'Inserindo instrução de retirada da negativação do boleto';
        INSERT INTO erp.billetDispatching (contractorID, paymentID,
               instructionID, reasons)
        VALUES (FcontractorID, FpaymentID, newInstruction, Freasons);
      ELSE
        -- Inserimos a ocorrência de sustação da negativação do título
        -- RAISE NOTICE 'Inserindo sustação da negativação do boleto';
        INSERT INTO erp.bankingBilletOccurrences (contractorID, paymentID,
               occurrenceTypeID, occurrenceCode, description, reasons,
               occurrenceDate, tariffValue)
        VALUES (FcontractorID, FpaymentID, 8, 0, 'Exclusão de negativação',
                Freasons, CURRENT_DATE, 0.00);
      END IF;
    ELSE
      -- Demais pagamentos

      -- Realizamos a sustação da negativação do título
      -- RAISE NOTICE 'Retirando restrição de negativação';
      UPDATE erp.payments
         SET restrictionID = FrestrictionID
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      -- Inserimos a ocorrência de sustação da negativação do título
      -- RAISE NOTICE 'Inserindo sustação da negativação da cobrança';
      INSERT INTO erp.paymentOccurrences (contractorID, paymentID,
             occurrenceTypeID, description, reasons, occurrenceDate)
      VALUES (FcontractorID, FpaymentID, 9, 'Exclusão de negativação',
              Freasons, CURRENT_DATE);
  END CASE;

  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Registra a inclusão de protesto no título
-- ---------------------------------------------------------------------
-- Stored Procedure que informa a inclusão do protesto do título
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.protest(FcontractorID integer,
  FpaymentID int, FpaymentMethodID int, Freasons text)
RETURNS boolean AS
$$
DECLARE
  -- Os dados do pagamento a ser protestado
  FdroppedTypeID  integer;
  -- A situação de restrição do título a ser aplicada
  FrestrictionID  integer;
  -- Os dados das restrições existentes no título
  restriction  record;
  -- Os dados da instrução de registro do boleto
  dispatchInstruction  record;
  -- A instrução a ser gerada ao banco em caso de boletos
  newInstruction  integer;
BEGIN
  -- Precisamos obter as informações de restrição aplicadas ao título
  SELECT INTO restriction
         restrictionID AS id,
         ((payments.restrictionid >> 0) & 1 = 1) AS protested,
         ((payments.restrictionid >> 1) & 1 = 1) AS creditBlocked
    FROM erp.payments
   WHERE contractorid = FcontractorID
     AND paymentID = FpaymentID;

  -- Acrescentamos a informação de protesto na restrição
  FrestrictionID := (restriction.id | 1);

  -- Executamos a instrução de protestar o título em função do meio de
  -- pagamento utilizado
  CASE FpaymentMethodID
    WHEN 5 THEN
      -- Boleto bancário
      
      -- Precisamos obter qual a situação do boleto atualmente
      SELECT INTO FdroppedTypeID
             droppedTypeID
        FROM erp.bankingBilletPayments
       WHERE contractorid = FcontractorID
         AND paymentID = FpaymentID;

      IF NOT FOUND THEN
        -- Disparamos uma exceção, pois não foi possível encontrar a
        -- informação do pagamento
        RAISE EXCEPTION 'Não foi possível obter os dados do pagamento % a ser protestado.',
          FpaymentID
        USING HINT = 'Por favor, verifique se o pagamento foi registrado como boleto';
      END IF;

      -- Em função da situação do boleto, toma as medidas necessárias
      -- para protestar
      CASE FdroppedTypeID
        WHEN 1 THEN
          -- O boleto ainda não foi registrado, então verificamos se a
          -- instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- Precisamos enviar uma instrução para protestar o título
            newInstruction := 7;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
        WHEN 4 THEN
          -- Baixado por decurso de prazo

          -- O título não existe mais na instituição, então não precisa
          -- lidar com o banco
          newInstruction := 0;
        ELSE
          -- Demais situações, simplesmente protesta o título

          -- Verificamos se a instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de registro existe, verificamos se foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para negativar o título
              newInstruction := 7;
            ELSE
              -- Não precisamos fazer nada pois o boleto ainda não foi
              -- registrado
              newInstruction := 0;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
      END CASE;

      -- Realizamos o protesto do título
      -- RAISE NOTICE 'Acrescentando restrição de protesto';
      UPDATE erp.bankingBilletPayments
         SET restrictionID = FrestrictionID
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      IF (newInstruction > 0) THEN
        -- Inserimos a instrução para o banco
        -- RAISE NOTICE 'Inserindo instrução de protesto do boleto';
        INSERT INTO erp.billetDispatching (contractorID, paymentID,
               instructionID, reasons)
        VALUES (FcontractorID, FpaymentID, newInstruction, Freasons);
      ELSE
        -- Inserimos a ocorrência de protesto do título
        -- RAISE NOTICE 'Inserindo protesto do boleto';
        INSERT INTO erp.bankingBilletOccurrences (contractorID, paymentID,
               occurrenceTypeID, occurrenceCode, description, reasons,
               occurrenceDate, tariffValue)
        VALUES (FcontractorID, FpaymentID, 5, 0, 'Protesto da cobrança',
                Freasons, CURRENT_DATE, 0.00);
      END IF;
    ELSE
      -- Demais pagamentos

      -- Realizamos o protesto do título
      -- RAISE NOTICE 'Acrescentando restrição de protesto';
      UPDATE erp.payments
         SET restrictionID = FrestrictionID
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      -- Inserimos a ocorrência de protesto do título
      -- RAISE NOTICE 'Inserindo protesto da cobrança';
      INSERT INTO erp.paymentOccurrences (contractorID, paymentID,
             occurrenceTypeID, description, reasons, occurrenceDate)
      VALUES (FcontractorID, FpaymentID, 6, 'Inclusão em cartório',
              Freasons, CURRENT_DATE);
  END CASE;

  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Registra a retirada do protesto do título
-- ---------------------------------------------------------------------
-- Stored Procedure que exclui o protesto da cobrança de uma fatura.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.unprotest(FcontractorID integer,
  FpaymentID int, FpaymentMethodID int, Freasons text)
RETURNS boolean AS
$$
DECLARE
  -- Os dados do pagamento que precisa ser retirado o protesto
  FdroppedTypeID  integer;
  -- A situação de restrição do título a ser aplicada
  FrestrictionID  integer;
  -- Os dados das restrições existentes no título
  restriction  record;
  -- Os dados da instrução de registro do boleto
  dispatchInstruction  record;
  -- A instrução a ser gerada ao banco em caso de boletos
  newInstruction  integer;
BEGIN
  -- Precisamos obter as informações de restrição aplicadas ao título
  SELECT INTO restriction
         restrictionID AS id,
         ((payments.restrictionid >> 0) & 1 = 1) AS protested,
         ((payments.restrictionid >> 1) & 1 = 1) AS creditBlocked
    FROM erp.payments
   WHERE contractorid = FcontractorID
     AND paymentID = FpaymentID;

  -- Retiramos a informação de protesto na restrição
  FrestrictionID := (restriction.id & ~1);

  -- Executamos a instrução de sustar o protesto do título em função do
  -- meio de pagamento utilizado
  CASE FpaymentMethodID
    WHEN 5 THEN
      -- Boleto bancário
      
      -- Precisamos obter qual a situação do boleto atualmente
      SELECT INTO FdroppedTypeID
             droppedTypeID
        FROM erp.bankingBilletPayments
       WHERE contractorid = FcontractorID
         AND paymentID = FpaymentID;

      IF NOT FOUND THEN
        -- Disparamos uma exceção, pois não foi possível encontrar a
        -- informação do pagamento
        RAISE EXCEPTION 'Não foi possível obter os dados do pagamento % a ser sustado o protesto.',
          FpaymentID
        USING HINT = 'Por favor, verifique se o pagamento foi registrado como boleto';
      END IF;

      -- Em função da situação do boleto, toma as medidas necessárias
      -- para sustar protesto
      CASE FdroppedTypeID
        WHEN 1 THEN
          -- O boleto ainda não foi registrado, então verificamos se a
          -- instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- Precisamos enviar uma instrução para sustar o protesto
            -- do título
            newInstruction := 9;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
        WHEN 4 THEN
          -- Baixado por decurso de prazo

          -- O título não existe mais na instituição, então não precisa
          -- lidar com o banco
          newInstruction := 0;
        ELSE
          -- Demais situações, simplesmente suspende o protesto do
          -- título

          -- Verificamos se a instrução de registro foi enviada ao banco
          SELECT INTO dispatchInstruction
                 dispatchingID AS id,
                 (shippingFileID IS NOT NULL) AS dispatched
            FROM erp.billetDispatching
           WHERE contractorid = FcontractorID
             AND paymentID = FpaymentID
             AND instructionID = 1;
          IF FOUND THEN
            -- A instrução de registro existe, verificamos se foi
            -- enviado ao banco
            IF (dispatchInstruction.dispatched) THEN
              -- Precisamos enviar uma instrução para negativar o título
              newInstruction := 9;
            ELSE
              -- Não precisamos fazer nada pois o boleto ainda não foi
              -- registrado
              newInstruction := 0;
            END IF;
          ELSE
            -- A instrução de registro não existe, então não precisa
            -- lidar com o banco
            newInstruction := 0;
          END IF;
      END CASE;

      -- Realizamos a sustação do protesto do título
      -- RAISE NOTICE 'Retirando restrição de protesto';
      UPDATE erp.bankingBilletPayments
         SET restrictionID = FrestrictionID
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      IF (newInstruction > 0) THEN
        -- Inserimos a instrução para o banco
        -- RAISE NOTICE 'Inserindo instrução de retirada do protesto do boleto';
        INSERT INTO erp.billetDispatching (contractorID, paymentID,
               instructionID, reasons)
        VALUES (FcontractorID, FpaymentID, newInstruction, Freasons);
      ELSE
        -- Inserimos a ocorrência de sustação do protesto do título
        -- RAISE NOTICE 'Inserindo sustação do protesto do boleto';
        INSERT INTO erp.bankingBilletOccurrences (contractorID, paymentID,
               occurrenceTypeID, occurrenceCode, description, reasons,
               occurrenceDate, tariffValue)
        VALUES (FcontractorID, FpaymentID, 6, 0, 'Retirado de cartório e manutenção carteira',
                Freasons, CURRENT_DATE, 0.00);
      END IF;
    ELSE
      -- Demais pagamentos

      -- Realizamos a sustação do protesto do título
      -- RAISE NOTICE 'Retirando restrição de protesto';
      UPDATE erp.payments
         SET restrictionID = FrestrictionID
       WHERE paymentID = FpaymentID
         AND contractorid = FcontractorID;

      -- Inserimos a ocorrência de sustação do protesto do título
      -- RAISE NOTICE 'Inserindo sustação do protesto da cobrança';
      INSERT INTO erp.paymentOccurrences (contractorID, paymentID,
             occurrenceTypeID, description, reasons, occurrenceDate)
      VALUES (FcontractorID, FpaymentID, 7, 'Retirado de cartório e manutenção carteira',
              Freasons, CURRENT_DATE);
  END CASE;

  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

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
