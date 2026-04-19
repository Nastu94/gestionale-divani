# Gestionale Divani

![Laravel 12](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white)
![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![Livewire 3](https://img.shields.io/badge/Livewire-3.x-FB70A9)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.x-06B6D4?logo=tailwindcss&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-Frontend-646CFF?logo=vite&logoColor=white)

Applicazione web sviluppata con **Laravel 12** per la gestione operativa di una realtà produttiva nel settore dei divani.

Il progetto centralizza in un'unica piattaforma i flussi di:

- anagrafiche clienti, fornitori e utenti;
- catalogo prodotti, componenti e categorie;
- variabili tessuto / colore e relativi override;
- ordini cliente e ordini fornitore;
- disponibilità, movimenti, lotti e prenotazioni di magazzino;
- approvvigionamenti e monitoraggio supply;
- etichette PDF, DDT e work order;
- reportistica, ricerca globale e controllo permessi.

---

## Indice

- [Panoramica](#panoramica)
- [Moduli principali](#moduli-principali)
- [Stack tecnologico](#stack-tecnologico)
- [Requisiti](#requisiti)
- [Avvio rapido](#avvio-rapido)
- [Configurazione ambiente](#configurazione-ambiente)
- [Comandi utili](#comandi-utili)
- [Flussi di dominio](#flussi-di-dominio)
- [Struttura del progetto](#struttura-del-progetto)
- [Autorizzazioni e sicurezza](#autorizzazioni-e-sicurezza)
- [Linee guida per lo sviluppo](#linee-guida-per-lo-sviluppo)

---

## Panoramica

**Gestionale Divani** è pensato per coprire la parte commerciale, produttiva e logistica del ciclo operativo aziendale.

L'applicazione consente di:

- gestire prodotti finiti e componenti di produzione;
- associare varianti tessuto / colore ai modelli;
- inserire ordini cliente standard e occasionali;
- generare o aggiornare la copertura degli ordini tramite stock e ordini fornitore;
- monitorare entrate e uscite di magazzino;
- produrre documenti operativi per spedizione e produzione;
- controllare accessi e permessi con granularità per area funzionale.

---

## Moduli principali

### Anagrafiche

- clienti;
- fornitori;
- clienti occasionali;
- utenti;
- ruoli e permessi.

### Catalogo e configurazione prodotto

- categorie componenti;
- componenti / articoli;
- prodotti / modelli;
- variabili tessuto × colore;
- override di prezzo per combinazioni variabili;
- prezzi personalizzati cliente-prodotto;
- listini fornitore / componente.

### Ordini

- ordini cliente;
- ordini fornitore;
- generazione e riserva numero ordine;
- conferma pubblica ordini standard tramite token;
- conferma manuale interna;
- ricalcolo approvvigionamenti;
- verifica componenti e copertura fabbisogno.

### Magazzino e logistica

- magazzini;
- livelli stock;
- entrate di magazzino;
- uscite di magazzino;
- lotti;
- prenotazioni stock;
- resi cliente;
- etichette ordine PDF;
- DDT;
- work order PDF.

### Monitoraggio e strumenti operativi

- dashboard approvvigionamenti;
- alert;
- report ordini cliente;
- report ordini fornitore;
- report stock levels;
- report stock movements;
- ricerca globale.

---

## Stack tecnologico

### Backend

- **PHP 8.2+**
- **Laravel 12**
- **Laravel Jetstream**
- **Laravel Sanctum**
- **Livewire 3**
- **Spatie Laravel Permission**
- **Spatie Laravel Activitylog**
- **Laravel Scout + TNTSearch**
- **Barryvdh DomPDF**
- **PhpSpreadsheet**
- **PowerGrid**
- **Kalnoy Nestedset**

### Frontend

- **Vite**
- **Tailwind CSS**
- **Alpine.js**
- **Axios**

---

## Requisiti

Per eseguire il progetto in locale servono almeno:

- PHP **8.2** o superiore;
- Composer;
- Node.js + npm;
- un database compatibile con Laravel;
- estensioni PHP richieste dal framework e dai package installati.

> Il file `.env.example` è predisposto di default per **SQLite**, con queue, cache e sessioni su database.

---

## Avvio rapido

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

Esempio **SQLite**:

```env
DB_CONNECTION=sqlite
```

Esempio **MySQL / MariaDB**:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestionale_divani
DB_USERNAME=root
DB_PASSWORD=
```

### 7. Esegui migration

```bash
php artisan migrate
```

### 8. Avvia gli asset frontend

Sviluppo:

```bash
npm run dev
```

Build produzione:

```bash
npm run build
```

### 9. Avvia l'applicazione

Avvio completo in sviluppo:

```bash
composer dev
```

Il comando avvia:

- server Laravel;
- listener queue;
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

## Configurazione ambiente

### Database

Il progetto supporta la configurazione classica Laravel tramite `.env`.

Verifica almeno:

```env
APP_NAME="Gestionale Divani"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
```

### Mail

Per il flusso di conferma ordini cliente è consigliato configurare correttamente il mailer:

```env
MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### Queue / Cache / Sessioni

Configurazione base prevista dall'ambiente di esempio:

```env
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

### Conferma ordini cliente

Configurazione utile:

```env
ORDERS_CONFIRMATION_TTL_DAYS=14
```

Questa variabile controlla la validità del link pubblico di conferma inviato al cliente.

### Riconciliazione approvvigionamenti

Configurazioni utili:

```env
SUPPLY_RECONCILE_ENABLED=true
SUPPLY_RECONCILE_WINDOW_DAYS=30
SUPPLY_RECONCILE_SCHEDULE_TIME=06:00
SUPPLY_RECONCILE_TZ=Europe/Rome
SUPPLY_RECONCILE_DRY_RUN=false
SUPPLY_RECONCILE_LOG_CHANNEL=supply
```

> La frequenza e l'orario effettivi dipendono dalla configurazione dello scheduler Laravel presente nel progetto.

---

## Comandi utili

### Setup e manutenzione

```bash
php artisan migrate
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Test e qualità

```bash
php artisan test
./vendor/bin/pint
```

### Supply reconciliation

Esecuzione manuale:

```bash
php artisan reservations:weekly-reconcile
```

Esecuzione di test senza scritture:

```bash
php artisan reservations:weekly-reconcile --dry
```

Con override finestra:

```bash
php artisan reservations:weekly-reconcile --start=2026-04-01 --days=30
```

---

## Flussi di dominio

### Ordini cliente standard

Per gli ordini standard il sistema può:

- inviare una richiesta di conferma al cliente;
- esporre una pagina pubblica con token;
- registrare conferma o rifiuto;
- notificare i destinatari interni;
- creare approvvigionamenti aggiuntivi quando la consegna è nella finestra configurata.

### Ordini cliente occasionali

Gli ordini occasionali seguono un flusso operativo più diretto e sono pensati per una gestione interna senza passaggio pubblico di conferma.

### Etichette, DDT e work order

Il progetto espone flussi dedicati alla generazione di documenti PDF operativi, tra cui:

- etichette ordine cliente;
- documenti di trasporto;
- work order.

### Magazzino

Il sistema traccia:

- giacenze correnti;
- movimenti di entrata;
- movimenti di uscita;
- lotti;
- disponibilità prenotata;
- copertura ordini e shortfall.

### Supply dashboard

È presente una sezione dedicata al monitoraggio degli approvvigionamenti, con possibilità di eseguire una riconciliazione della copertura ordini.

---

## Struttura del progetto

Alcune aree rilevanti della codebase:

```text
app/
├── Console/Commands/        # Comandi Artisan personalizzati
├── Http/Controllers/        # Controller applicativi, API interne e stampa documenti
├── Livewire/                # Componenti Livewire
├── Models/                  # Modelli Eloquent
├── Services/                # Servizi di dominio e logica applicativa

database/
├── migrations/              # Struttura del database
├── seeders/                 # Seed iniziali e supporto dati

resources/
├── views/                   # Blade views e template PDF

routes/
├── web.php                  # Rotte web, moduli protetti e rotte pubbliche
├── console.php              # Scheduler e task pianificati
```

---

## Autorizzazioni e sicurezza

L'applicazione utilizza un modello di autorizzazione granulare basato su **ruoli** e **permessi**.

Questo approccio consente di separare chiaramente le aree operative, ad esempio:

- visualizzazione e modifica anagrafiche;
- gestione ordini cliente / fornitore;
- accesso a entrate e uscite di magazzino;
- utilizzo della reportistica;
- gestione configurazioni di prodotto e variabili;
- accesso ai documenti operativi.

Le modifiche rilevanti possono inoltre essere tracciate tramite **activity log**.

---

## Linee guida per lo sviluppo

Quando introduci nuove funzionalità è consigliato:

- mantenere allineati migration, model, controller e permessi;
- documentare nel README i nuovi flussi di dominio;
- verificare l'impatto su queue, scheduler e generazione documenti;
- aggiornare le configurazioni `.env.example` quando vengono aggiunte nuove variabili;
- esplicitare eventuali dipendenze tra ordini, magazzino e approvvigionamenti.

---

## Possibili estensioni del README

Se la repository verrà usata anche come vetrina pubblica, si possono aggiungere in seguito:

- screenshot del gestionale;
- diagramma dei flussi ordine → stock → approvvigionamento;
- sezione dedicata ai permessi principali;
- guida rapida per deploy e ambienti staging / produzione;
- changelog o roadmap.
