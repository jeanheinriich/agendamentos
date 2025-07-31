-- =====================================================================
-- MODIFICAÇÃO DO CONTROLE DE COBRANÇAS
-- =====================================================================
-- Incluído comentários sobre um evento na cobrança por boletos, bem
-- como a replicação para eventos através de outros meios de pagamento.
-- =====================================================================

-- Renomeamos a tabela com nome incorreto existente e modificamos a sua
-- extrutura conforme as novas necessidades
ALTER TABLE erp.occurenceTypes
  RENAME TO occurrenceTypes;
ALTER TABLE erp.occurrenceTypes
  RENAME COLUMN occurenceTypeID TO occurrenceTypeID;
ALTER SEQUENCE erp.occurencetypes_occurencetypeid_seq
  RENAME TO occurrencetypes_occurrencetypeid_seq;

-- Modificamos a tabela que armazena as instruções a serem enviadas ao
-- banco
ALTER TABLE erp.billetDispatching
  ADD COLUMN reasons text DEFAULT NULL;

-- E a tabela que armazena as ocorrências em boletos
ALTER TABLE erp.bankingBilletOccurrences
  RENAME COLUMN occurenceTypeID TO occurrenceTypeID;
ALTER TABLE erp.bankingBilletOccurrences
  RENAME COLUMN occurenceCode TO occurrenceCode;
ALTER TABLE erp.bankingBilletOccurrences
  ALTER COLUMN reasons SET DEFAULT NULL;

-- Incluímos a informação de fatura principal necessária para permitir
-- a geração correta de parcelamentos
ALTER TABLE erp.invoices
  ADD COLUMN primaryInvoiceID integer DEFAULT NULL;

-- Acrescentamos uma tabela para conter as restrições que um título pode
-- conter

-- ---------------------------------------------------------------------
-- Os tipos de restrições possíveis em um pagamento
-- ---------------------------------------------------------------------
-- Armazena as informações dos tipos de restrições aplicáveis há um
-- pagamento. As restrições são acumulativas, então um mesmo título pode
-- conter mais de uma restrição.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.paymentRestrictions (
  restrictionID serial,      -- ID do tipo de restrição
  name          varchar(30)  -- Nome da restrição
                NOT NULL,
  PRIMARY KEY (restrictionID)
);

-- Insere os tipos de restrições aplicáveis
INSERT INTO erp.paymentRestrictions (restrictionID, name) VALUES
  ( 0, 'Sem restrições'),
  ( 1, 'Protestado'),
  ( 2, 'Negativado'),
  ( 3, 'Protestado e negativado'),
  ( 4, 'Em cobrança'),
  ( 5, 'Protestado e em cobrança'),
  ( 6, 'Negativado e em cobrança'),
  ( 7, 'Protest., negat. e em cobrança');

ALTER SEQUENCE erp.paymentrestrictions_restrictionid_seq RESTART WITH 8;

-- Incluímos a informação de restrição presente em um título
ALTER TABLE erp.payments
  ADD COLUMN restrictionID integer DEFAULT 0;

-- Modificamos todos os títulos protestados e negativados para a nova
-- coluna indicativa de restrições aplicadas
UPDATE erp.bankingBilletPayments
   SET restrictionID = restrictionID + 1,
       droppedTypeID = 2
 WHERE droppedTypeID = 6;
UPDATE erp.bankingBilletPayments
   SET restrictionID = restrictionID + 2,
       droppedTypeID = 2
 WHERE droppedTypeID = 7;
UPDATE erp.bankingBilletPayments
   SET restrictionID = restrictionID + 4,
       droppedTypeID = 2
 WHERE sentToDunningBureau = true;

-- Excluímos a coluna de enviado para agência de cobrança que passa a
-- fazer parte da coluna de restrições
ALTER TABLE erp.payments
  DROP COLUMN sentToDunningBureau;

-- Acrescentamos uma nova tabela para conter os tipos de ocorrências em
-- uma cobrança, bem como uma tabela para registrar as ocorrências

-- ---------------------------------------------------------------------
-- Os tipos de ocorrências possíveis em um pagamento
-- ---------------------------------------------------------------------
-- Armazena as informações dos tipos de ocorrências num pagamento,
-- válido para pagamentos por dinheiro, cheque e cartões de débito e
-- crédito.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.paymentOccurrenceTypes (
  occurrenceTypeID  serial,      -- ID do tipo de ocorrência
  name              varchar(30)  -- Nome da ocorrência
                    NOT NULL,
  PRIMARY KEY (occurrenceTypeID)
);

-- Insere os tipos de ocorrências
INSERT INTO erp.paymentOccurrenceTypes (occurrenceTypeID, name) VALUES
  ( 1, 'Registrado'),
  ( 2, 'Modificado'),
  ( 3, 'Pagamento recusado'),
  ( 4, 'Liquidado'),
  ( 5, 'Protestado'),
  ( 6, 'Exclusão de Protesto'),
  ( 7, 'Negativado'),
  ( 8, 'Exclusão Negativação'),
  ( 9, 'Outros motivos'),
  ( 10, 'Erro');

ALTER SEQUENCE erp.paymentoccurrencetypes_occurrencetypeid_seq RESTART WITH 11;

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

-- Acrescentamos um gatilho para registrar no histórico as inserções de
-- cobranças que não sejam boletos (dinheiro, cheque, cartão de débito,
-- cartão de crédito e transferência bancária)

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

-- Acrescentamos uma stored procedure que recupera as informações de
-- histórico dos movimentos de um título

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
  eventTypeName  varchar(50),
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


-- Fazemos a correção da exibição de clientes quando a cooperativa é
-- nova
CREATE OR REPLACE FUNCTION erp.getEntitiesData(FcontractorID integer,
  FentityID integer, Fgroup varchar, FsearchValue varchar(100),
  FsearchField varchar(20), Forder varchar, Fstatus integer,
  Ftype integer, Skip integer, LimitOf integer)
RETURNS SETOF erp.entityData AS
$$
DECLARE
  entityData  erp.entityData%rowtype;
  row  record;
  query  varchar;
  field  varchar;
  filter  varchar;
  typeFilter  varchar;
  limits  varchar;
  blockedLevel  smallint;
  lastEntityID  integer;
  lastSubsidiaryID  integer;
  avoidAffiliateDuplicationFilter  varchar;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID := 0;
  END IF;
  IF (FentityID IS NULL) THEN
    FentityID := 0;
  END IF;
  IF (Fgroup IS NULL) THEN
    Fgroup := 'contractor';
  END IF;
  IF (Fstatus IS NULL) THEN
    Fstatus := 0;
  END IF;
  IF (Ftype IS NULL) THEN
    Ftype := 0;
  END IF;
  IF (Forder IS NULL) THEN
    Forder := 'cooperative ASC, name, headOffice DESC, subsidiaryname, affiliatedname NULLS FIRST';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;

  -- O filtro para eliminar a duplicidade de cadastros de associados
  IF (Fgroup = 'customer') THEN
    avoidAffiliateDuplicationFilter := '( NOT((numberOfContracts = 0) AND (numberOfAffiliations > 0)) )';
  ELSE
    avoidAffiliateDuplicationFilter := '(numberOfContracts = 0)';
  END IF;

  -- Os estados possíveis são: (1) inativo e (2) ativo
  typeFilter := '';
  IF (Fstatus > 0) THEN
    IF (Fstatus = 1) THEN
      typeFilter := ' AND ' ||
        'CASE' ||
        '   WHEN affiliatedID IS NULL THEN (numberOfActiveContracts = 0)' ||
        '   ELSE (numberOfActiveAffiliations = 0)' ||
        ' END';
    ELSE
      typeFilter := ' AND ' ||
        'CASE' ||
        '   WHEN affiliatedID IS NULL THEN (numberOfActiveContracts > 0)' ||
        '   ELSE (numberOfActiveAffiliations > 0)' ||
        ' END';
    END IF;
  END IF;

  -- Os tipos possíveis são:
  --   (1) cliente, (2) associado
  IF (Ftype > 0) THEN
    IF (Ftype = 1) THEN
      typeFilter := typeFilter || ' AND ' ||
        '((numberOfContracts > 0) AND (affiliatedID IS NULL))';
    ELSE
      typeFilter := typeFilter || ' AND ' ||
        '((numberOfAffiliations > 0) AND (affiliatedID IS NOT NULL))';
    END IF;
  END IF;

  -- Realiza a filtragem por contratante
  IF (FcontractorID > 0) THEN
    filter := format(' AND entity.contractorID = %s',
                     FcontractorID);
  ELSE
    filter := format(' AND entity.contractorID >= %s',
                     FcontractorID);
  END IF;

  -- Realiza a filtragem por grupo
  filter := filter || format(' AND entity.%s = true', Fgroup);

  IF (FentityID > 0) THEN
    -- Realiza a filtragem por entidade
    filter := filter || format(' AND entity.entityID = %s', FentityID);
  END IF;

  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    -- RAISE NOTICE 'FsearchValue IS NOT NULL';
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
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
            format('public.unaccented(affiliationEntity.name) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ' OR ' ||
            format('public.unaccented(affiliationEntity.tradingName) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ' OR ' ||
            format('public.unaccented(affiliationUnity.name) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ')'
          ;
        WHEN 'nationalregister' THEN
          filter := filter || ' AND (' ||
            format('(regexp_replace(unity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%'')',
                   regexp_replace(FsearchValue, '\D*', '', 'g')) ||
            ' OR ' ||
            format('(regexp_replace(affiliationUnity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%'')',
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
                            entity.contractor,
                            entity.customer,
                            entity.supplier,
                            entity.newAffiliated,
                            entity.name,
                            entity.tradingName,
                            entity.entityTypeID,
                            type.name AS entityTypeName,
                            type.juridicalperson AS juridicalperson,
                            type.cooperative AS cooperative,
                            entity.serviceProvider,
                            entity.blocked AS entityBlocked,
                            unity.subsidiaryID,
                            unity.headOffice,
                            unity.name AS subsidiaryName,
                            joint.customerID AS affiliatedID,
                            joint.joinedat,
                            affiliationEntity.name AS affiliatedName,
                            affiliationEntity.tradingName AS affiliatedTradingName,
                            affiliationEntity.blocked AS affiliatedBlocked,
                            unity.cityID,
                            city.name AS cityName,
                            affiliationUnity.cityID AS affiliatedCityID,
                            affiliationCity.name AS affiliatedCityName,
                            unity.nationalRegister,
                            affiliationUnity.nationalRegister AS affiliatedNationalRegister,
                            affiliationUnity.blocked AS affiliatedSubsidiaryBlocked,
                            unity.blocked AS subsidiaryBlocked,
                            entity.createdAt,
                            entity.updatedAt,
                            unity.createdAt AS unityCreatedAt,
                            unity.updatedAt AS unityUpdatedAt,
                            (
                              SELECT count(*)
                                FROM erp.contracts AS entityContract
                               WHERE entityContract.customerID = entity.entityID
                            ) AS numberOfContracts,
                            (
                              SELECT count(*)
                                FROM erp.contracts AS entityContract
                               WHERE entityContract.customerID = entity.entityID
                                 AND entityContract.endDate IS NULL
                            ) AS numberOfActiveContracts,
                            (
                              SELECT count(*)
                                FROM erp.affiliations
                               WHERE affiliations.associationid = entity.entityID
                            ) AS numberOfAssociates,
                            (
                              SELECT count(*)
                                FROM erp.affiliations
                               WHERE affiliations.associationid = entity.entityID
                                 AND affiliations.unjoinedat IS NULL
                            ) AS numberOfActiveAssociates,
                            CASE
                              WHEN affiliationEntity.entityID > 0 THEN
                                (
                                  SELECT count(*)
                                    FROM erp.affiliations
                                   WHERE affiliations.associationid = entity.entityID
                                     AND affiliations.customerid = affiliationEntity.entityID
                                )
                              ELSE
                                (
                                  SELECT count(*)
                                    FROM erp.affiliations
                                   WHERE affiliations.customerid = entity.entityID
                                )
                            END AS numberOfAffiliations,
                            CASE
                              WHEN affiliationEntity.entityID > 0 THEN
                                (
                                  SELECT count(*)
                                    FROM erp.affiliations
                                   WHERE affiliations.associationid = entity.entityID
                                     AND affiliations.customerid = affiliationEntity.entityID
                                     AND affiliations.unjoinedat IS NULL
                                )
                              ELSE
                                (
                                  SELECT count(*)
                                    FROM erp.affiliations
                                   WHERE affiliations.customerid = entity.entityID
                                     AND affiliations.unjoinedat IS NULL
                                )
                            END AS numberOfActiveAffiliations,
                            count(*) OVER(partition by entity.entityid) AS entityItems,
                            (SELECT count(*) FROM erp.subsidiaries AS S WHERE S.entityID = entity.entityid) AS unityItems
                       FROM erp.entities AS entity
                      INNER JOIN erp.entitiesTypes AS type ON (entity.entityTypeID = type.entityTypeID)
                      INNER JOIN erp.subsidiaries AS unity ON (entity.entityID = unity.entityID)
                      INNER JOIN erp.cities AS city ON (unity.cityID = city.cityID)
                       LEFT JOIN erp.affiliations AS joint ON (type.cooperative = true AND entity.entityID = joint.associationID AND unity.subsidiaryID = joint.associationUnityID)
                       LEFT JOIN erp.entities AS affiliationEntity ON (joint.customerID = affiliationEntity.entityID)
                       LEFT JOIN erp.subsidiaries AS affiliationUnity ON (joint.subsidiaryID = affiliationUnity.subsidiaryID)
                       LEFT JOIN erp.cities AS affiliationCity ON (affiliationUnity.cityID = affiliationCity.cityID)
                      WHERE entity.deleted = false
                        AND unity.deleted = false %s
                   )
                    SELECT *,
                           CASE
                             WHEN affiliatedID IS NULL THEN (numberOfActiveContracts > 0)
                             ELSE (numberOfActiveAffiliations > 0)
                           END AS active,
                           CASE
                             WHEN cooperative THEN (numberOfActiveAssociates > 0)
                             ELSE FALSE
                           END AS activeAssociation,
                           CASE
                             WHEN ((numberOfContracts > 0) AND (affiliatedID IS NULL)) THEN 1
                             WHEN ((numberOfAffiliations > 0) AND (affiliatedID IS NOT NULL)) THEN 2
                             ELSE 0
                           END AS type,
                           count(*) OVER() AS fullcount
                      FROM items
                     WHERE %s %s
                      ORDER BY %s %s',
                  filter, avoidAffiliateDuplicationFilter, typeFilter,
                  Forder, limits);
  -- RAISE NOTICE 'SQL: %',query;

  lastEntityID := 0;
  lastSubsidiaryID := 0;

  FOR row IN EXECUTE query
  LOOP
    IF (lastEntityID <> row.entityID) THEN
      -- Iniciamos um novo grupo
      lastEntityID := row.entityID;

      -- Verifica se precisamos dividir esta entidade em mais de uma
      -- linha
      IF (row.unityItems > 1) THEN
        -- Descrevemos aqui a entidade principal
        entityData.entityID            := row.entityID;
        entityData.subsidiaryID        := 0;
        entityData.affiliatedID        := 0;
        entityData.juridicalperson     := row.juridicalperson;
        entityData.cooperative         := row.cooperative;
        entityData.serviceProvider     := row.serviceProvider;
        entityData.headOffice          := false;
        entityData.type                := 1;
        entityData.level               := 0;
        IF (row.cooperative) THEN
          entityData.active            := row.activeAssociation;
        ELSE
          entityData.active            := row.active;
        END IF;
        entityData.activeAssociation   := row.activeAssociation;
        entityData.name                := row.name;
        entityData.tradingName         := row.tradingName;
        entityData.blocked             := row.entityBlocked;
        entityData.cityID              := 0;
        entityData.cityName            := '';
        entityData.nationalregister    := '';
        IF (row.entityBlocked) THEN
          entityData.blockedLevel      := 1;
        ELSE
          entityData.blockedLevel      := 0;
        END IF;
        entityData.createdAt           := row.createdAt;
        entityData.updatedAt           := row.updatedAt;
        entityData.fullcount           := row.fullcount;

        RETURN NEXT entityData;
      END IF;
    END IF;

    IF (lastSubsidiaryID <> row.subsidiaryID) THEN
      -- Iniciamos um novo subgrupo
      lastSubsidiaryID := row.subsidiaryID;

      -- Verifica se precisamos subdividir esta entidade em mais de uma
      -- linha
      IF ( row.cooperative AND (row.unityItems > 1) ) THEN
        -- Temos que separar esta entidade em mais de uma linha de forma
        -- a exibir a unidade/filial desta entidade e/ou a cooperativa
        -- do associado
        
        -- Informa os dados da unidade
        entityData.entityID            := row.entityID;
        entityData.subsidiaryID        := row.subsidiaryID;
        entityData.affiliatedID        := 0;
        entityData.juridicalperson     := row.juridicalperson;
        entityData.cooperative         := row.cooperative;
        entityData.serviceProvider     := row.serviceProvider;
        entityData.headOffice          := row.headOffice;
        entityData.type                := 1;
        IF (row.unityItems > 1) THEN
          entityData.level             := 1;
        ELSE
          entityData.level             := 2;
        END IF;
        IF (row.cooperative) THEN
          entityData.active            := row.activeAssociation;
        ELSE
          entityData.active            := row.active;
        END IF;
        entityData.activeAssociation   := row.activeAssociation;
        IF (row.unityItems > 1) THEN
          entityData.blocked           := row.subsidiaryBlocked;
          entityData.name              := row.subsidiaryName;
          entityData.tradingName       := '';
        ELSE
          entityData.blocked           := (row.subsidiaryBlocked OR row.entityBlocked);
          entityData.name              := row.name;
          entityData.tradingName       := row.tradingName;
        END IF;
        entityData.cityID              := row.cityID;
        entityData.cityName            := row.cityName;
        entityData.nationalregister    := row.nationalregister;
        IF (row.entityBlocked) THEN
          entityData.blockedLevel      := 1;
        ELSE
          IF (row.subsidiaryBlocked) THEN
            entityData.blockedLevel    := 2;
          ELSE
            entityData.blockedLevel    := 0;
          END IF;
        END IF;
        IF (row.unityItems > 1) THEN
          entityData.createdAt         := row.unityCreatedAt;
          entityData.updatedAt         := row.unityUpdatedAt;
        ELSE
          entityData.createdAt         := row.createdAt;
          entityData.updatedAt         := row.updatedAt;
        END IF;
        entityData.fullcount           := row.fullcount;

        RETURN NEXT entityData;
      END IF;
    END IF;

    -- Informa os dados da entidade
    entityData.entityID              := row.entityID;
    entityData.subsidiaryID          := row.subsidiaryID;
    entityData.affiliatedID          := row.affiliatedID;
    entityData.juridicalperson       := row.juridicalperson;
    entityData.cooperative           := row.cooperative;
    entityData.serviceProvider       := row.serviceProvider;
    entityData.headOffice            := row.headOffice;
    entityData.type                  := row.type;
    entityData.active                := row.active;
    entityData.activeAssociation     := row.activeAssociation;
    IF (row.affiliatedID > 0) THEN
      entityData.level               := 3;
      entityData.name                := row.affiliatedName;
      entityData.tradingName         := row.affiliatedTradingName;
      entityData.blocked             := row.affiliatedBlocked;
      entityData.cityID              := row.affiliatedCityID;
      entityData.cityName            := row.affiliatedCityName;
      entityData.nationalregister    := row.affiliatedNationalregister;
      entityData.createdAt           := row.joinedat;
      entityData.updatedAt           := row.joinedat;

      IF (row.entityBlocked) THEN
        entityData.blockedLevel      := 1;
      ELSE
        IF (row.affiliatedBlocked) THEN
          entityData.blockedLevel    := 3;
        ELSE
          IF (row.subsidiaryBlocked) THEN
            entityData.blockedLevel  := 2;
          ELSE
            entityData.blockedLevel  := 0;
          END IF;
        END IF;
      END IF;
    ELSE
      IF (row.entityItems > 1) THEN
        entityData.level             := 1;
        entityData.name              := row.subsidiaryName;
        entityData.tradingName       := '';
        entityData.blocked           := row.subsidiaryBlocked;
        entityData.createdAt         := row.unityCreatedAt;
        entityData.updatedAt         := row.unityUpdatedAt;
      ELSE
        entityData.level             := 2;
        entityData.name              := row.name;
        entityData.tradingName       := row.tradingName;
        entityData.blocked           := (row.subsidiaryBlocked OR row.entityBlocked);
        entityData.createdAt         := row.createdAt;
        entityData.updatedAt         := row.updatedAt;
      END IF;
      entityData.cityID              := row.cityID;
      entityData.cityName            := row.cityName;
      entityData.nationalregister    := row.nationalregister;

      IF (row.entityBlocked) THEN
        entityData.blockedLevel      := 1;
      ELSE
        IF (row.subsidiaryBlocked) THEN
          entityData.blockedLevel    := 2;
        ELSE
          entityData.blockedLevel    := 0;
        END IF;
      END IF;
    END IF;
    entityData.fullcount             := row.fullcount;

    RETURN NEXT entityData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- Atualizamos a função de obtenção dos dados de pagamentos
DROP FUNCTION erp.getPaymentsData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FsearchValue varchar(100),
  FsearchField varchar(20), FpaymentMethodID integer,
  FpaymentSituationID integer, FOrder varchar, Skip integer,
  LimitOf integer);
DROP TYPE erp.paymentData;
