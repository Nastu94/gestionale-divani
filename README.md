# Gestionale Divani

Applicazione web sviluppata con **Laravel 12** per la gestione operativa di una realtà produttiva nel settore dei divani.

Il progetto copre i principali flussi aziendali legati a:

- anagrafiche clienti e fornitori;
- catalogo prodotti e componenti;
- varianti tessuto / colore;
- ordini cliente e ordini fornitore;
- magazzino, lotti, movimenti e prenotazioni stock;
- approvvigionamenti e riconciliazione fabbisogni;
- etichette PDF, DDT e reportistica;
- ruoli, permessi, audit log e ricerca globale.

---

## Panoramica

**Gestionale Divani** nasce per centralizzare in un'unica piattaforma la gestione commerciale, produttiva e logistica.

L'applicazione consente di:

- gestire prodotti finiti e componenti di produzione;
- monitorare disponibilità, movimenti e riserve di magazzino;
- inserire ordini cliente standard e occasionali;
- generare approvvigionamenti in base alla copertura reale di stock;
- tracciare flussi operativi con permessi e log attività;
- produrre documenti operativi come etichette ordine e documenti di trasporto.

---

## Funzionalità principali

### Anagrafiche e catalogo

- gestione **clienti**;
- gestione **fornitori**;
- gestione **categorie componenti**;
- gestione **componenti** con attributi tecnici;
- gestione **prodotti** con varianti configurabili;
- amministrazione centralizzata di **tessuti** e **colori**.

### Ordini

- **ordini cliente**;
- **ordini fornitore**;
- supporto a clienti **standard** e **occasionali**;
- conferma pubblica degli ordini standard tramite **link con token**;
- flusso di accettazione / rifiuto ordine con invio notifiche;
- ricalcolo approvvigionamenti sugli ordini.

### Magazzino e logistica

- gestione **magazzini**;
- livelli stock e disponibilità per componente;
- movimenti di carico / scarico;
- gestione **lotti**;
- prenotazioni di stock su ordini;
- resi prodotto e gestione giacenze dedicate;
- generazione **etichette PDF** ordine;
- supporto a **DDT** e documenti operativi di magazzino.

### Controllo e amministrazione

- sistema di **ruoli e permessi**;
- **activity log** delle modifiche;
- ricerca globale;
- report su ordini e magazzino;
- job schedulati per la riconciliazione settimanale del fabbisogno.

---

## Stack tecnologico

### Backend

- PHP **8.2+**
- Laravel **12**
- Laravel Jetstream
- Laravel Sanctum
- Livewire **3**
- Spatie Laravel Permission
- Spatie Laravel Activitylog
- Laravel Scout + TNTSearch
- Barryvdh DomPDF
- PhpSpreadsheet

### Frontend

- Vite
- Tailwind CSS
- Alpine.js

---

## Requisiti

Per eseguire il progetto in locale servono almeno:

- PHP 8.2 o superiore;
- Composer;
- Node.js e npm;
- un database compatibile con Laravel.

> Il file `.env.example` è predisposto di default per **SQLite**, con sessioni, cache e queue su database.

---

## Installazione

### 1. Clona la repository

```bash
git clone https://github.com/Nastu94/gestionale-divani.git
cd gestionale-divani
```

### 2. Installa le dipendenze backend

```bash
composer install
```

### 3. Installa le dipendenze frontend

```bash
npm install
```

### 4. Crea il file di ambiente

```bash
cp .env.example .env
```

### 5. Genera la chiave applicativa

```bash
php artisan key:generate
```

### 6. Configura il database

Nel file `.env` imposta i parametri del database che vuoi utilizzare.

Esempio con SQLite:

```env
DB_CONNECTION=sqlite
```

Esempio con MySQL / MariaDB:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestionale_divani
DB_USERNAME=root
DB_PASSWORD=
```

### 7. Esegui le migration

```bash
php artisan migrate
```

### 8. Compila gli asset frontend

Per sviluppo:

```bash
npm run dev
```

Per build produzione:

```bash
npm run build
```

### 9. Avvia l'applicazione

Avvio completo in ambiente di sviluppo:

```bash
composer dev
```

Il comando avvia:

- server Laravel;
- listener delle code;
- log viewer via Pail;
- Vite in modalità sviluppo.

In alternativa puoi avviare i processi separatamente:

```bash
php artisan serve
php artisan queue:work
php artisan schedule:work
npm run dev
```

---

## Configurazioni utili

### Conferma ordini cliente

Il progetto include un flusso pubblico di conferma / rifiuto ordine per i clienti standard.

Variabili utili:

```env
ORDERS_CONFIRMATION_TTL_DAYS=14
```

Questa configurazione controlla la durata del link pubblico inviato al cliente.

### Riconciliazione approvvigionamenti

È disponibile una riconciliazione schedulata per verificare la copertura degli ordini confermati in una finestra temporale configurabile.

Variabili utili:

```env
SUPPLY_RECONCILE_ENABLED=true
SUPPLY_RECONCILE_WINDOW_DAYS=30
SUPPLY_RECONCILE_SCHEDULE_TIME=06:00
SUPPLY_RECONCILE_TZ=Europe/Rome
SUPPLY_RECONCILE_DRY_RUN=false
SUPPLY_RECONCILE_LOG_CHANNEL=supply
```

Esecuzione manuale:

```bash
php artisan reservations:weekly-reconcile
```

Esecuzione di test senza scritture:

```bash
php artisan reservations:weekly-reconcile --dry
```

---

## Flussi principali del dominio

### Ordini standard

Per gli ordini cliente standard il sistema può:

- inviare una richiesta di conferma al cliente;
- esporre una pagina pubblica con token;
- registrare conferma o rifiuto;
- notificare i destinatari interni;
- generare approvvigionamenti aggiuntivi quando necessario.

### Ordini occasionali

Gli ordini per clienti occasionali seguono un flusso separato, orientato a una gestione più diretta e operativa.

### Etichette e documenti

Il progetto genera etichette ordine in PDF con dati di spedizione, riferimenti, prodotto, varianti e note colore.

### Magazzino

Il sistema traccia disponibilità, riserve, movimenti e lotti per supportare produzione, logistica e approvvigionamento.

---

## Struttura del progetto

Alcune aree rilevanti della codebase:

```text
app/
├── Http/Controllers/        # Controller applicativi e API interne
├── Livewire/                # Componenti Livewire
├── Models/                  # Modelli Eloquent
├── Services/                # Logiche applicative e servizi di dominio
├── Console/Commands/        # Comandi Artisan personalizzati

database/
├── migrations/              # Struttura del database

resources/
├── views/                   # Blade views e template PDF

routes/
├── web.php                  # Rotte web e aree protette
├── console.php              # Scheduler e comandi console
```

---

## Sicurezza e autorizzazioni

L'applicazione utilizza un modello a permessi granulari per limitare l'accesso alle diverse sezioni operative.

Le autorizzazioni sono gestite tramite ruoli e permessi, mentre le modifiche significative possono essere tracciate tramite activity log.

---

## Note per lo sviluppo

- mantenere allineate migration, model e permessi quando si introduce una nuova funzionalità;
- documentare i nuovi flussi di dominio nel README;
- verificare l'impatto delle modifiche sui job schedulati e sulle code;
- aggiornare la documentazione dei flussi ordine quando cambiano conferme, etichette o approvvigionamenti.

---

## Stato del README

Questo README descrive la repository in base alla struttura attuale del progetto e sostituisce il README standard generato da Laravel.

Con l'evoluzione del gestionale è consigliato aggiornarlo ogni volta che vengono introdotti:

- nuovi moduli;
- nuovi comandi Artisan;
- nuove integrazioni;
- cambiamenti nei flussi ordini / magazzino / approvvigionamenti.
