# WooCommerce B2B Moly

WooCommerce B2B Moly è un plugin WordPress per trasformare WooCommerce in un catalogo riservato e gestire clienti B2B tramite ruoli, gruppi, campi aziendali e regole di sconto.

Gli utenti non autorizzati possono consultare il catalogo, ma non possono visualizzare i prezzi né procedere all'acquisto. Gli utenti abilitati possono invece accedere alle normali funzionalità di WooCommerce e alle condizioni commerciali assegnate.

> Il plugin è attualmente in fase di sviluppo.

## Funzionalità

- Modalità catalogo per visitatori e utenti non autorizzati.
- Prezzi nascosti con messaggio personalizzabile.
- Quantità, varianti e pulsanti di acquisto nascosti.
- Blocco dell'aggiunta al carrello e accesso a carrello e checkout.
- Accesso ai prezzi configurabile per ruolo WordPress.
- Gruppi B2B personalizzati assegnabili agli utenti.
- Campi B2B predefiniti e personalizzati.
- Compilazione automatica dei campi B2B al checkout.
- Salvataggio dei dati B2B nel profilo utente e nell'ordine.
- Regole di sconto per singolo utente o gruppo.
- Sconti su prodotto, categoria o totale dell'ordine.
- Interfaccia amministrativa tradotta in italiano.

## Requisiti

- WordPress
- WooCommerce
- PHP 8.0 o successivo consigliato

WooCommerce deve essere installato e attivo. Se WooCommerce non è disponibile, il plugin non registra le proprie funzionalità.

## Installazione

1. Copiare la cartella `woocommerce-b2b-moly` in:

   ```text
   wp-content/plugins/
   ```

2. Accedere all'amministrazione di WordPress.
3. Aprire **Plugin > Plugin installati**.
4. Attivare **WooCommerce B2B Moly**.
5. Configurare il plugin dal menu **WooB2B**.

## Configurazione

Il menu amministrativo WooB2B è suddiviso in cinque sezioni.

### Settings

Permette di configurare il testo mostrato al posto del prezzo agli utenti che non possono visualizzarlo.

Esempio:

```text
Accedi per vedere il prezzo
```

### Roles

Definisce quali ruoli WordPress possono visualizzare i prezzi e acquistare.

Se non viene selezionato alcun ruolo, tutti gli utenti autenticati possono visualizzare i prezzi.

### Groups

Permette di creare gruppi commerciali personalizzati, ad esempio:

- Rivenditori
- Distributori
- Agenti
- Clienti VIP

Ogni gruppo può essere autorizzato o meno a visualizzare i prezzi. I gruppi vengono assegnati dalla pagina di modifica del profilo utente.

La modifica dello slug di un gruppo aggiorna anche le assegnazioni degli utenti e le regole di sconto collegate.

### B2B Fields

Gestisce i dati aziendali associati agli utenti e agli ordini.

I campi predefiniti sono:

- Partita IVA
- Codice fiscale
- PEC
- SDI

Ogni campo può essere abilitato e reso obbligatorio. È inoltre possibile creare campi personalizzati scegliendo:

- etichetta;
- slug;
- tipo;
- ordine di visualizzazione;
- stato;
- obbligatorietà.

I tipi disponibili sono:

- testo;
- area di testo;
- email;
- telefono.

I valori vengono salvati nel profilo dell'utente, proposti automaticamente al checkout e copiati nell'ordine.

### Discounts

Permette di creare regole di sconto destinate a:

- un singolo utente;
- un gruppo B2B.

Gli ambiti disponibili sono:

- intero ordine;
- categoria di prodotti;
- singolo prodotto.

Gli sconti percentuali possono essere applicati a tutti gli ambiti. Gli sconti a importo fisso sono applicati al totale dell'ordine.

Ogni regola può contenere:

- nome;
- utente o gruppo destinatario;
- ambito;
- prodotto o categoria;
- tipo e valore dello sconto;
- importo minimo dell'ordine;
- stato attivo o inattivo.

Se più regole risultano applicabili, gli sconti vengono cumulati.

## Modalità catalogo

Quando un visitatore o un utente non autorizzato consulta il negozio, il plugin:

- sostituisce o nasconde il prezzo;
- nasconde quantità e opzioni di acquisto;
- disabilita le variazioni acquistabili;
- rimuove i pulsanti di aggiunta al carrello;
- impedisce l'aggiunta diretta al carrello;
- reindirizza al login gli accessi a carrello e checkout;
- mostra un messaggio con il collegamento alla pagina di accesso.

## Dati memorizzati

Il plugin utilizza opzioni WordPress per configurazioni, gruppi, campi e regole di sconto.

Le assegnazioni dei gruppi e i valori B2B vengono salvati nei metadati dell'utente. I dati B2B utilizzati durante il checkout e i riferimenti agli sconti applicati vengono salvati anche nell'ordine WooCommerce.

La disattivazione del plugin non elimina automaticamente questi dati.

## Traduzioni

Il text domain del plugin è:

```text
woocommerce-b2b-moly
```

I cataloghi di traduzione si trovano nella directory:

```text
languages/
```

Il plugin include la traduzione italiana `it_IT`.

## Struttura del plugin

```text
woocommerce-b2b-moly/
├── woocommerce-b2b-moly.php
└── languages/
    ├── woocommerce-b2b-moly.pot
    ├── woocommerce-b2b-moly-it_IT.po
    └── woocommerce-b2b-moly-it_IT.mo
```

## Stato del progetto

Versione corrente: **0.1.0**

Il plugin è in sviluppo attivo. Prima di utilizzarlo in produzione è consigliato verificarne il comportamento con il tema, il checkout e le estensioni WooCommerce installate sul sito.

## Licenza

Questo progetto è distribuito sotto licenza **GNU General Public License v2.0 (GPL-2.0)**.

Consulta il testo della [GNU GPL v2](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html) per termini e condizioni.
